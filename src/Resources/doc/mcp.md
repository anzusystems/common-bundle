# MCP Server

Opt-in MCP (Model Context Protocol) server infrastructure built on [symfony/mcp-bundle](https://github.com/symfony/mcp-bundle),
including three diagnostic log tools (`search_app_logs`, `search_audit_logs`, `get_logs_by_context`) that read the
bundle's own `appLogs`/`auditLogs` mongo collections plus a dedicated capped `mcpLogs` collection of MCP tool calls.

The section is disabled by default. A project that does not enable it needs no new packages, env variables or
infrastructure after a bundle upgrade.

## Enabling

1. Install the suggested packages:
   ```console
   $ composer require symfony/mcp-bundle symfony/rate-limiter
   ```
2. Register `Symfony\AI\McpBundle\McpBundle` in `config/bundles.php` and configure one server under `servers`
   (`config/packages/mcp.php` — `name`, `version`, `transports: { http: true }`, `http.path`, and `registry.tools`
   listing the project's own tool namespace; the instructions come from `instructions_file` below).
   `transports.http` is mandatory — without it McpBundle
   registers neither the controller nor the route for that server. McpBundle 0.12 replaced file-based discovery:
   a tool is exposed when its service carries the `mcp.tool` tag (autoconfigured from `#[McpTool]`) **and** matches a
   `registry` pattern, so a namespace prefix must end with a trailing backslash. The bundle appends its own tools and
   the session store to that server, so only project-specific values belong here.
3. Enable the section (requires the `logs` section to be enabled; `allowed_hosts` must be non-empty — an empty list
   would reject every request with 403). `server_name` must match the key used under `mcp.servers`, because the bundle
   attaches its controller, tools and session store to that server:
   ```yaml
   anzu_systems_common:
       mcp:
           enabled: true
           server_name: 'default'
           instructions_file: '%kernel.project_dir%/config/mcp/instructions.md'
           allowed_hosts: '%env(csv:ANZU_MCP_ALLOWED_HOSTS)%'
           pod: '%env(bool:ANZU_MCP_POD)%'
           confirmation_fallback_locale: 'sk'
           log_tools_permission: app_log_read
           tool_error_exceptions:
               App\Exception\SomeBackendException: 'Backend is temporarily unavailable, retry the call.'
           rate_limiter:
               limit: 120
               interval: '1 minute'
               elevated_role: ROLE_SYS_MCP
               elevated_limit: 600
               cache_pool: 'some_redis.cache'
               lock_factory: 'some.lock.factory'
           session:
               cache_pool: 'some_redis.cache'
           logs:
               mongo:
                   collection: 'mcpLogs'
                   size_mb: 200
               add_to_health_check: false
   ```
   `instructions_file` is a Markdown file that `McpCompilerPass` reads into the server instructions; it is a container
   resource, so an edit rebuilds the container in dev.
   `confirmation_fallback_locale` is the language of the write confirmation for a user without `AnzuUser::getLocale()`;
   the default `null` uses the default locale of the translator.
   The `logs.mongo` connection options (`uri`, `username`, `password`, `database`, `ssl`) default to the
   `logs.journal.mongo` connection, so they only need to be set when the mcp log collection lives elsewhere.
   `session.cache_pool` is a PSR-6 pool; the bundle wraps it in a `Psr16Cache` and prepends it as the McpBundle
   `session` store of the configured server, so the McpBundle `session` config needs no host-side value.
   `allowed_hosts` stays on this side rather than in the McpBundle `http.allowed_hosts`, because that option is
   validated as an array at compile time and an env-driven list is still an unresolved placeholder by then.
4. Import the MCP route (`config/routes/mcp.php`):
   ```php
   $routes->import('.', 'mcp');
   ```
5. Secure the MCP endpoint with a firewall. The endpoint has no authentication on its own — pair it with the
   personal access token authentication from [anzusystems/auth-bundle](https://github.com/anzusystems/auth-bundle)
   or any other authenticator.
6. Create the capped mongo collection during deploy:
   ```console
   $ bin/console anzu:mcp:create-log-collection
   ```
7. Optionally register a monolog handler for the `mcp` channel (the bundle prepends the channel itself).

## Provided services

* `McpController` (alias `mcp.server.<server_name>.controller`) — streamable HTTP transport endpoint with
  DNS-rebinding protection
  (`allowed_hosts`, applied through the McpBundle `MiddlewareFactory` so the SDK's other default middleware stays in
  place) and a sliding-window rate limit per user, charged one token per JSON-RPC message of the request (members of
  `rate_limiter.elevated_role` get `rate_limiter.elevated_limit` — see below).
* `McpToolReferenceHandler` — the SDK reference handler that runs every call of an `#[AsMcpTool]` tool, the way the
  Symfony kernel runs a controller: deserializes the arguments into the input class of the tool's `__invoke()`, checks
  the tool permission and the write grant, validates the input with the Symfony validator, serializes the returned
  object with the anzu serializer, converts `McpToolInputException`, `ValidationException`, `AccessDeniedException`
  and configured `tool_error_exceptions` into `CallToolResult`s with `isError: true`, refuses write tools in read-only
  mode, includes a successful write tool call in the audit log, logs every call (with the serialized input as
  `params`) to the monolog `mcp` channel and to the `mcpLogs` capped collection (see below). It asks the user to confirm
  a write when the client can answer, see "Write confirmation" below. The tool itself stays a plain invokable service
  with only its own dependencies.
* `McpWriteToolGuard` — the write grant, read-only mode and one write per request.
* `McpWriteConfirmation` — when a write asks the user to confirm, how it reads the answer and what the question looks
  like, see "Write confirmation" below.
* `McpDeleteGuard` — the rule of deleting an entity through MCP, see "Delete tools" below.
* `McpToolCallLogger` and `McpToolErrorResolver` — the call log and the `tool_error_exceptions` map of the handler.
* `StrictToolArgumentsRequestHandler` — answers a call of a granted tool with unknown arguments by a tool error that lists
  the allowed ones and logs it. It runs before the SDK validates the arguments against the schema; a tool the user may not
  call is left to that validation, whose error does not list them.
* `McpToolAccessChecker` + `FilterToolsListRequestHandler` — the permission of each tool (see below); the call-side
  check lives in `McpToolReferenceHandler`.
* `McpPromptAccessChecker`, `FilterPromptsListRequestHandler` and `RejectUngrantedPromptRequestHandler` — a prompt follows the
  tools it names, see "Prompts" below.
* `SearchAppLogsTool`, `SearchAuditLogsTool`, `GetLogsByContextTool` — diagnostic tools over the shared log
  collections, correlated by `contextId`.

## Rate limit by role

`McpRateLimiter` keys the sliding window by the current user id and applies the configured `rate_limiter.limit`.
When `rate_limiter.elevated_role` is set and `Security::isGranted()` grants it to the current user (so role hierarchy
applies), `rate_limiter.elevated_limit` replaces the configured limit for that user — `null` or a non-positive
`elevated_limit`, or an unset role, keeps the default. The configured `rate_limiter.interval` applies to every bucket.

The sliding window is read and written under a lock, so parallel requests of one user cannot both pass on the same
pre-consume state. The lock store is Redis (`settings.app_redis`), shared by every pod, unless
`rate_limiter.lock_factory` names a host `LockFactory` service to use instead.

The endpoint accepts JSON-RPC batches, so `McpController` counts the messages of the request body and charges that
many tokens (clamped at the bucket size), otherwise one batch of up to `MessageFactory::DEFAULT_MAX_BATCH_SIZE` (100)
tool calls would cost a single token. A request that is not a JSON array of messages costs one token.

A write the user confirms takes two requests, so it costs two tokens.

## Tool requests and responses

A tool is declared with `#[AsMcpTool]`, which extends the SDK `#[McpTool]`, and looks like a Symfony controller:
its `__invoke()` takes the request DTO and returns the response.

```php
#[AsMcpTool(
    name: ListLabelsTool::NAME,
    description: <<<'TXT'
        Searches labels by name.
        Filter {{ list_notes }} by the returned ids.
        TXT,
    category: McpToolCategory::LABELS,
    permission: Permission::LABEL_READ,
    descriptionValues: ['list_notes' => ListNotesTool::NAME],
    readOnly: true,
)]
final readonly class ListLabelsTool
{
    public const string NAME = 'list_labels';

    public function __construct(
        private LabelRepositoryMcpDecorator $labelRepoMcpDecorator,
    ) {
    }

    public function __invoke(ListLabelsRequest $request): McpLabelListResponse
    {
        return $this->labelRepoMcpDecorator->getList($request);
    }
}
```

* The signature is the single source of the input and the output. `__invoke()` takes the input class and then the
  optional `RequestContext` (`__invoke(XRequest $request)`, `__invoke(XRequest $request, RequestContext $context)`),
  only the `RequestContext`, or nothing for a tool without input; it returns a response class, or a `CallToolResult`
  that is passed through unchanged (the checks, the error mapping, the audit log and the call log still apply, only
  the serialization is skipped). `McpToolSchemaCompilerPass` fails the container compilation with a `LogicException`
  for any other signature.
* A tool without input logs `params` `[]`.
* A write tool that registers the entity it wrote uses `McpAuditLogAwareTrait` and calls
  `setAuditResourceByEntity($entity)`, or `setAuditResource($name, $id)` after a delete, because Doctrine clears the id.

* `readOnly`, `destructive` and `idempotent` become the `ToolAnnotations` (`openWorldHint` is always `false`): a tool
  is a write tool unless it says `readOnly: true`, so a forgotten flag hides it from tokens without the write grant;
  `destructive` (default `true`, as in the MCP specification) and `idempotent` (default `false`) describe a write tool
  only.
* `category` (required) names the group the tool belongs to, e.g. in the tool list of a token form. It is camelCase
  (`^[a-z][a-zA-Z0-9]*$`), so a client can build a translation key from it; categories come in the order of their first
  tool in `McpToolDefinitions::getToolNames()`. The bundle log tools are in `AsMcpTool::CATEGORY_DIAGNOSTICS` (`diagnostics`).
  `McpToolDefinitions::getToolCategory()` returns the category of a tool and throws a `LogicException` for a tool
  without one.
* `previewOf` makes the tool the preview of a write tool, see "Preview tools" below.
* `descriptionValues` replace the `{{ name }}` placeholders of the description, like the parameters of a validation message;
  point to another tool through its `NAME` constant, so a renamed or removed tool breaks the build instead of the
  text. A placeholder without a value, or a value without a placeholder, fails the container compilation.
* The input class is the request DTO: `McpToolSchemaCompilerPass` makes the tool's input schema from its
  `#[Serialize]` properties (`McpSchemaFactory`) — type, default, `required` and the limits of its `Assert`
  constraints (`Regex` is left out: PCRE is not the ECMA-262 dialect of `pattern`). A property is required when it has
  no default value or carries `NotBlank`, `NotNull` or `Count(min ≥ 1)`. A `Uuid` property (also nullable or in a list)
  is a string of format `uuid`, so the SDK rejects an invalid UUID before the call reaches the handler. A
  `Collection<int, X>` (from its `@var`, or the `@return` of a getter, also nullable) is an array of `X`, the same schema
  as `list<X>`. A `Collection<string, X>` is an object with `additionalProperties`; a response writes it as an object
  only with `strategy: Serialize::KEYS_VALUES`. A `Collection` without its key and item types fails the container
  compilation with a `LogicException` naming the member. An array follows the same rule: only `array<string, X>` is an
  object, `array<X>` is a list, and a bare `array` fails the compilation the same way. A class without a `#[Serialize]`
  member, e.g. `DateTimeImmutable`, fails it too, because its schema would refuse every value. A `DateTimeImmutable`
  with `#[Serialize(handler: McpDateTimeHandler::class)]` (also nullable) is a string, and the schema appends
  `McpDateTimeHandler::FORMAT_DESCRIPTION` to its description: the handler reads a calendar date `YYYY-MM-DD` with
  an optional time (`THH:MM`, seconds and a fraction optional) and an optional offset (`Z`, `+HH`, `+HHMM` or `+HH:MM`),
  reads a value without an offset as UTC and returns it in UTC. The description asks the agent for the UTC offset,
  valid on that day, of the time zone the date is meant in, because the server cannot know it, and says that a date
  alone is the start of that day. Which time zone that is (the user's, a site's) the instructions of the host say. The
  schema has no `format: date-time`, which requires an offset. An unreadable value is answered with
  `Invalid arguments: <field>: "<value>" is not an ISO 8601 date-time, …` before the validator runs.
* A field that ends a period and is compared inclusively, such as `until`, declares
  `#[Serialize(handler: McpDateTimeHandler::class, type: McpDateTimeHandler::END_OF_DAY)]`. There a date alone is the
  end of that day (`23:59:59.999999` UTC), so `"until": "2026-10-31"` keeps the records of 31 October; a value with a
  time is read as it is. The schema appends `McpDateTimeHandler::END_OF_DAY_DESCRIPTION` to the description of such a
  field. `McpLogSearchRequestTrait::$until` declares it.
* The return class is the response: the pass appends a `Returns:` list of its `#[Serialize]` properties to the
  description, because clients hand the model the description and the input schema, not necessarily an output schema.
* `#[McpField(description: …, descriptionValues: […])]` describes a DTO property, a response property, a prompt argument (see
  "Prompts") or a case of a backed enum; the described cases of an enum field are listed after the field's own description, the default one marked.
  A description longer than 120 characters or with a `{{ placeholder }}` is a nowdoc `<<<'TXT'` with one sentence per
  line. The `Returns:` list joins the lines of a field description with spaces; the description of a tool keeps them.
  Keep validation messages generic: the schema rejects most invalid calls before the validator runs, and the
  handler prefixes every violation with its property path.

Requests and responses use public properties and property hooks, no getters and setters. A `set` hook normalizes the input, and a virtual
`get` property exposes the result in the response; it has no default, so it is `required` in the output schema like
the getter it replaces. The fields are listed in declaration order.

A list of objects is a `Collection<int, X>` with `#[Serialize(type: X::class)]`:

* On input `type:` is required, otherwise `ObjectHandler` builds the items as the collection class.
* A parameterless constructor sets an input `Collection` to `new ArrayCollection()`. A promoted parameter would be
  required.
* `McpObjectArrayRule` reports an array of the host's objects in the MCP classes, also with `#[Serialize]`, and `McpTraitObjectArrayRule` in the MCP traits.
* `McpToolInputPermissionRule` reports an `#[AsMcpTool]` whose request implements an interface of `anzuMcpToolInputPermission.inputPermissions` and whose `__invoke()` does not call `denyAccessUnlessGranted()` with the permission of that interface on the request before any other statement.
* `NamedArgumentsRule` reports a call with more than two arguments that are not all named in the namespaces of `anzuNamedArguments.classNamespaces`. A call with a value for a variadic parameter, a spread or no known signature is left alone.

```php
final class ListLabelsRequest
{
    private const int QUERY_LENGTH_MAX = 100;

    #[Assert\NotBlank]
    #[Assert\Length(max: self::QUERY_LENGTH_MAX)]
    #[McpField(description: 'Label name to search for; ignores diacritics and lists the best matches first.')]
    #[Serialize]
    public string $query = App::EMPTY_STRING {
        set => trim($value);
    }
}

final class McpLabelListResponse
{
    /**
     * @param Collection<int, McpLabelDecorator> $labels
     */
    public function __construct(
        #[McpField(description: 'Best matches; narrow the query when the expected label is missing.')]
        #[Serialize(type: McpLabelDecorator::class)]
        public private(set) Collection $labels,
    ) {
    }
}
```

`McpToolReferenceHandler` runs a call of an `#[AsMcpTool]` tool (its `ToolReference` handler is the class and
`__invoke`) with the tool name of the reference and the read-only flag of its `#[AsMcpTool]`; every other element goes
to the inner handler unchanged:

1. the call arguments, without the SDK's own `_session` and `_request`, are deserialized into the input class;
2. the tool permission and, for a write tool, the write grant are checked (see below); the input
   is validated with the Symfony validator — any violation answers the call with a tool error
   `Invalid arguments: <propertyPath>: <message> …` before the tool runs; a write tool is refused with a tool
   error asking to retry later while `AnzuApp::isReadOnlyMode()` is on, and with
   `A write tool already ran in this request — send the call of "<tool>" in a separate request.` when a write tool
   already returned without an exception in the same HTTP request (one JSON-RPC batch).
   `McpWriteToolGuard` marks the main request itself after such a call, so the guard does not depend on audit
   resources: a write tool that registers no resource blocks the next write call of the request just like one that
   does. Without a main request (no HTTP request on the stack) there is no guard. A check that fails throws
   `McpToolCallRejectedException`, whose message is the tool error;
3. the inner handler invokes the tool with the input under the name of its parameter, so the SDK resolves the tool
   service from the handler locator and the `RequestContext` as usual; a returned object is turned into the tool
   result array with the anzu serializer (`#[Serialize]` properties/getters), a `CallToolResult` is passed through (with `isError: true` it is logged as an error and does not count as a write). A
   `ValidationException` thrown by the tool answers the call with the same `Invalid arguments: <field>: <message> …`
   error listing every field error. Before a write tool runs, the main request is included in the audit log again
   (`McpController` excludes every MCP request) and it is excluded again when the call fails, unless a written
   resource is already recorded on it (`AuditLogResourceHelper::hasResourceId()`), e.g. by the failed call itself
   before it failed; the tool registers the entity it wrote through `McpAuditLogAwareTrait`, which records it on the
   main request with `AuditLogResourceHelper` like a controller; a
   tool whose call must not be audited (e.g. one returning a secret) calls
   `AuditLogResourceHelper::excludeFromAuditLogs()`, and its successful call stays excluded, because the
   guard refuses every later write call of the request before it could include the request again;
4. the payload is returned as a `CallToolResult` carrying it as `structuredContent` — a tool error additionally sets
   `isError: true`, so the protocol client sees a failed call instead of a successful one with an `error` key;
5. the call is logged with `params` = the serialized input (`[]` without one).

`McpToolReferenceHandlerCompilerPass` puts the handler into the server after McpBundle's `McpPass`, around an SDK
`ReferenceHandler` on the handler locator of the server. `McpToolDefinitionsCompilerPass` reads the `__invoke()` signature of every `#[AsMcpTool]` tool once
and stores it in `McpToolDefinition::$signature`, so a call needs no reflection. `McpToolReferenceHandlerCompilerPass` fails the container
compilation when a tool has an MCP App template and when the server already has another reference handler. A template
needs a tool that returns an array: an `#[AsMcpTool]` returns a `CallToolResult`, and a plain `#[McpTool]` has no
permission, so its every call would be denied.

The serializer builds an input object without a constructor: a DTO with optional fields has public properties with
defaults (it treats every promoted constructor parameter as required).
The bundle tools follow this shape: `Mcp/Model/Request/*Request`, `McpLogFinder` returning a `McpLogSearchResult` /
`McpLogsByContextResult` (facts only), and `Mcp/Model/Response/*Response` owning the JSON shape and the warning
texts. Log rows that are already serialized arrays are exposed through
`#[Serialize(handler: RawArrayHandler::class)]`, which passes them through the serializer untouched (the default
array handling re-indexes nested maps).

## Tool permissions

Every tool names an existing permission of the host application; `McpToolAccessChecker` resolves it through the host's
standard permission model (`Security::isGranted()` → the host voters, super admin bypass). There is no implicit access:
a tool declared by `#[AsMcpTool]` without a permission fails the container compilation.

A host tool declares it in the attribute:

```php
#[AsMcpTool(
    name: ListLabelsTool::NAME,
    description: 'Lists the labels.',
    category: McpToolCategory::LABELS,
    permission: Permission::LABEL_READ,
    readOnly: true,
)]
```

`permission` holds for the tool whatever its input, so `tools/list` can check it. A tool whose input names what it reads
checks the input itself on the first line of `__invoke()`, like a controller. A tool is a plain service, so the check
comes from a trait of the host:

```php
public function __invoke(ListNoteHistoryRequest $request): McpNoteHistoryResponse
{
    $this->denyAccessUnlessGranted(Permission::NOTE_MCP_INNER_ACCESS, $request);

    return $this->noteRepoMcpDecorator->getHistoryList($request);
}
```

`McpToolReferenceHandler` answers the `AccessDeniedException` with the usual access error. Before the tool runs it calls the
host service `McpInputEnricherInterface` for every input, which narrows it to what the user may read, e.g. an empty label
filter to the allowed labels. It leaves values sent by the client alone, so the voter still refuses a foreign label. The host
sets the alias of the interface to its service. Without one an empty filter stays empty.

Nothing in the bundle enforces that check, so the host names its scoped requests for the PHPStan rule:

```neon
parameters:
    anzuMcpToolInputPermission:
        inputPermissions:
            'App\Mcp\Model\McpLabelIdsAwareInterface': 'App\Security\Permission::NOTE_MCP_INNER_ACCESS'
```

A log tool of this bundle (`search_app_logs`, `search_audit_logs`, `get_logs_by_context`) cannot know the permissions of
its host, so the host names the one permission they share:

```yaml
anzu_systems_common:
    mcp:
        log_tools_permission: app_log_read
```

`McpToolDefinitionsCompilerPass` builds `McpToolDefinitions` from the attributes, in the order of the tool services. An
enabled `mcp` section without `log_tools_permission` fails at compile time.

* `tools/call` of a tool the current user may not use returns a tool error result (`isError: true` with
  `{"error": "Access denied — …"}`) from `McpToolReferenceHandler` before the tool runs, and the denied call is
  written to the `mcpLogs` collection with the error (so every tool is declared by `#[AsMcpTool]`, as the bundle tools
  are).
* `tools/list` returns only the tools the current user may call — the filter runs after registry pagination, so a
  page may come back with fewer tools (even none) while `nextCursor` is still set; clients follow the cursor as usual.

`McpToolDefinitionsCompilerPass` resolves the name of every `mcp.tool` service method carrying `#[McpTool]` (see "Write tools" below)
and fails the container compilation with a `LogicException` when
* one tool name is registered by more than one tool (two classes or methods resolving to the same name) — the SDK
  registry keeps only the last tool registered under a name, and the read/write classification would be ambiguous;
* a tool name is not 1 to 64 letters, digits, dots, underscores, hyphens or slashes (the SDK `NameValidator`) — the
  SDK would register it and only log a warning;
* a `category` is not camelCase (`^[a-z][a-zA-Z0-9]*$`);
* `previewOf` names an unknown tool, or one that is not a write tool declared by `#[AsMcpTool]` (e.g. another
  preview);
* a preview is not `readOnly`;
* a write tool has more than one preview;
* a preview has its own permission;
* a tool declared by `#[AsMcpTool]` that is not a preview has no permission (`McpConfigurationException::createToolWithoutPermission()`);
* a preview is in another category than its write tool;
* a write tool declared by `#[AsMcpTool]` has no preview (`McpConfigurationException::createWriteToolWithoutPreview()`);
* the response class of a preview does not implement `McpWritePreviewInterface`
  (`McpConfigurationException::createPreviewResponseWithoutConfirmation()`);
* the input class of a write tool does not implement `McpPreviewHashAwareInterface` or does not extend the input class
  of its preview (`McpConfigurationException::createWriteInputNotPreviewInput()`).

A tool that is not declared by `#[McpTool]` on a tagged `mcp.tool` service (e.g. one registered by an McpBundle app)
is unknown to these checks. It has no permission and neither has a plain `#[McpTool]`, so `tools/list` hides both and
`McpToolReferenceHandler` answers their `tools/call` with the tool access error.

### Write tools

A write tool declared by `#[AsMcpTool]` must have a preview, see "Preview tools" below.

A tool is a read tool only when its `#[McpTool]` declares `annotations: new ToolAnnotations(readOnlyHint: true)`
(`#[AsMcpTool(readOnly: true)]`);
every other tool (`readOnlyHint` false or missing) is a write tool. `McpToolDefinitionsCompilerPass` reads the attribute of every
`mcp.tool` service at compile time (on the tagged method, or on the class for `__invoke`, as McpBundle does) and passes
the result to `McpToolDefinitions`, one `McpToolDefinition` per tool, which `McpToolAccessChecker`, `McpWriteRateLimiter`
and `McpToolReferenceHandler` read. So the classification needs no built MCP server and a tool name it does not know is a
write tool. A tool without an explicit `name` is matched by the name the SDK derives
(`ElementMetadataResolver::resolveName()`: the class short name for `__invoke`, the method name otherwise).

A write tool additionally has to be in the write tools of the request's scope (*Token scope* below), so it is hidden
from `tools/list` and denied on `tools/call` without it. Without a scope every write tool is denied, for super admins
too. `McpToolDefinitions::getWriteToolNames()` lists the tools with a permission that are write tools, in
the order of the tools. A call
denied only for the scope answers `Access denied — the write tool "<tool>" is not granted to this token. Create a new personal access token with this write tool.` Annotate
every read tool, or it disappears for clients without the write grant:

```php
#[AsMcpTool(
    name: ListLabelsTool::NAME,
    description: '…',
    category: McpToolCategory::LABELS,
    readOnly: true,
)]
```

### Preview tools

A preview is a read tool that shows what a write tool would save and changes nothing. It declares its write tool with
`previewOf` and is in the same category:

```php
#[AsMcpTool(
    name: PreviewNoteTool::NAME,
    description: '…',
    category: McpToolCategory::NOTES,
    readOnly: true,
    previewOf: CreateNoteTool::NAME,
)]
```

A preview with `$writable` false has no hash, and the guard then lets the write tool run without the hash check, the
confirmation and the write limit. So a write tool has to check again the rule that made its preview not writable.

`McpToolReferenceHandler` signs every preview: when `McpWritePreviewInterface::$writable` is true, it sets `previewHash`
from the write tool name, the arguments and `$currentState`, the decorator of what the write would change (`null` when
the write creates something new). `McpPreviewHashFactory` serializes the decorator and leaves out
`McpPreviewHashFactory::VOLATILE_FIELDS` (`id`, `adminUrl`, `createdAt`, `modifiedAt`) at any depth, so saving a record
unchanged invalidates no preview, and everything else the decorator shows does. A host needs no hash code of its own for
a new entity: its preview says whether a write is possible and returns the decorator. A facade that needs to compare two
versions of a record calls `McpPreviewHashFactory::createStateHash()` on both.

Before every write tool with a preview, the handler runs the preview with the same input and compares its `previewHash`
with the one the call sent. A different hash answers `McpWriteRejectedException::createPreviewStale()`, a preview without a
hash has nothing to write, so the tool runs and reports its own result. An `OptimisticLockException` of a write tool, a
record changed in between, answers the same, so a host has neither a check nor a catch of its own.

The hash of a preview without a `$currentState` covers only the tool and the arguments, so a write does not invalidate it.
`McpPreviewHashReservation` therefore lets one user use such a hash once in `McpPreviewHashReservation::LIFETIME_SECONDS`
(300), in the pool of `rate_limiter.cache_pool`. It reserves the hash after the confirmation and before the write limit
takes its token, and releases it when the write limit refuses the call or the tool fails, so the same hash works again
after an error. A response that fails after the write keeps the hash, because the record exists. A repeated call answers `McpWriteRejectedException::createPreviewHashUsed()` and takes no token of the
write limit. So the same user cannot create two identical records in that time, and two calls at the same moment can
still both pass, because the pool has no atomic reservation.

`McpPreviewHashTrait` adds the `previewHash` field to the input of a write tool; the class declares the constant
`PREVIEW_TOOL` with the name of its preview, which the error and the field description use.
The schema does not list the field as `required` (`#[McpField(requiredBySchema: false)]`), because the SDK would refuse a
missing value with a generic error before the validator could name the preview to call.

`#[AsMcpTool(rateLimiter: 'note_write')]` names a limiter of `framework.rate_limiter`, the budget of writes per user on top
of `McpRateLimiter`. A write that passed its preview takes one token before the tool runs, tools naming one limiter share
it and the error names them. A call without a change or refused by its preview takes none. `McpToolSchemaCompilerPass`
adds a sentence about the limit to the description of the tool, naming the tools that share it, so a description does not
repeat it.

A preview has no permission of its own: `McpToolAccessChecker` grants it exactly when it grants its write tool —
the write tools of the scope and the permission of the write tool — in `tools/list` and on
`tools/call`; otherwise the call gets the usual tool access error. `McpToolDefinitions::getToolNames()` and
`getWriteToolNames()`, and `McpToolAccessChecker::getPermittedToolNames()` read the
tools with a permission, so they never list a preview. As a read tool it skips the write grant check of its own, read-only mode, the one write per request and the
audit log.

The response class of a preview implements `McpWritePreviewInterface`. The input class of the write tool implements
`McpPreviewHashAwareInterface` and extends the input class of the preview (the same class is allowed), because the
validated input of the write goes straight into the preview.

### Write confirmation

A write tool asks the user to confirm when the client declares form elicitation on revision 2026-07-28
(`RequestContext::getClientCapabilities()?->elicitationForm`). `McpToolReferenceHandler` does it after the access checks
and the validation of the input, so a call without the right to write asks nothing:

1. Without an answer it runs the preview, without a call log of its own and with the input of the write:
   * an exception of the preview is the result of the call, the same tool error the write would give, and nothing is
     written;
   * `previewHash: null` runs the write without a question, because such a write saves nothing;
   * another `previewHash` than in the arguments is a tool error (`McpWriteRejectedException::createPreviewStale()`);
   * the same `previewHash` ends the call with `input_required`, under the key `confirm_<previewHash>`.
2. The client repeats the call with `inputResponses`. With `accept` and `confirm: true` the preview runs again and, when
   its `previewHash` still matches, the write runs. Any other answer under that key is a tool error (`McpWriteRejectedException::createConfirmationRefused()`), and nothing is
   asked again: `decline`, `cancel`, an unticked box and an answer that cannot be read. An answer under another key asks
   again.

A client on an older revision, or without form elicitation, is never asked. It stays on the preview and `previewHash`.

The text comes from `McpWritePreviewInterface::$confirmationMessage`, a translation key and its parameters, as a virtual
property without `#[Serialize]`, like `$writable` and `$currentState`, so the output of the preview does not change. The translation domain is
`McpWriteConfirmation::TRANSLATION_DOMAIN`, the title of the field is the key `McpWriteConfirmation::FIELD_TITLE_KEY`, and
the host needs the Symfony translator enabled and the catalogues in place. Take the text from stored data; the model may
influence only a name in quotes. The language is `AnzuUser::getLocale()`, then `confirmation_fallback_locale`.

The field has no `default`: a preset `true` would turn an unticked box into consent.

This protects against the model, not against a hostile client. The model sees only the `arguments`, and
`inputResponses` is their sibling in `params`. The owner of a token can write the answer alone, or leave out the
capability and take the preview path. The answer is not one-time: a client that sends an accepted call again writes
again.

The round that only asks has no row in `mcpLogs`, only `Mcp tool confirmation asked.` in the `mcp` channel, with `tool`,
`userId`, `params` and `durationMs`. `Mcp tool call.` and `mcpLogs` have the `client` field: the client name from the
request on revision 2026-07-28, from the session on a handshake revision, otherwise `null`.

`McpCompilerPass` sets three things on the server builder, and it does nothing when the server has no builder:

* explicit capabilities without `listChanged` and `subscribe`. McpBundle always sets an event dispatcher, so the SDK
  would announce `listChanged`, which the server never sends, and Claude Code would open a `subscriptions/listen` stream
  that holds an FPM worker. `prompts` and `resources` follow the `mcp.prompt`, `mcp.resource` and
  `mcp.resource_template` tags;
* `McpCompilerPass::SUBSCRIPTION_LIFETIME_SECONDS`: a client that opens the stream anyway gets its ack and complete
  within one 250 ms tick. `0` means forever in the SDK;
* `withoutInputRequiredShim()`: with the shim a returned `InputRequiredResult` would hold the worker on a handshake
  revision.

### Delete tools

Each entity gets its own delete tool, with its own permission and preview. `McpDeleteGuard` holds the rule they share.
An entity may be deleted through MCP only when all of these hold:

* it was created at most `McpDeleteGuard::MAX_AGE_DAYS` days ago (`createdAt` against `AnzuApp::date()`);
* its `createdBy` is the current user, so an entity without an author is refused;
* nothing uses it.

`resolveRefusals()` returns every failed condition as one sentence, for a preview that shows them as data.
`assertDeletable()` throws `McpToolCallRejectedException` with all of them, for the write tool, in its transaction after
the row is locked.

A host reports what uses an entity with one `McpDeleteDependencyProviderInterface` per entity class:

```php
final readonly class NoteDeleteDependencyProvider implements McpDeleteDependencyProviderInterface
{
    public static function getSupportedEntity(): string
    {
        return Note::class;
    }

    public function provideDependencies(object $entity): array
    {
        if (false === $entity instanceof Note) {
            throw new UnexpectedValueException(sprintf('Unsupported entity "%s".', get_debug_type($entity)));
        }
        $linkCount = $entity->getIncomingLinks()->count();

        return $linkCount > 0 ? [sprintf('linked %d times from other notes', $linkCount)] : [];
    }
}
```

With the `mcp` section the bundle tags an autoconfigured implementation itself. The class of the entity is the key of
the provider; for a Doctrine proxy (`getReference()` without native lazy objects) it is the entity class the proxy
extends. An entity without a provider throws a `LogicException`, never "no dependencies".

The guard does not check the delete permission: the host tool does, on the stored entity. The tool records the audit
resource with `setAuditResource()` and the id from its response, because Doctrine clears the id after the flush.

### Token scope

A host says what a request may use by registering a service under the id `McpToolScopeProviderInterface`
(`anzusystems/auth-bundle` provides one for personal access tokens). `provideToolScope()` returns a `McpToolScope`:

- `readToolNames`: the read tools the request may use, `null` keeps every read tool.
- `writeToolNames`: the write tools the request may use.

Without such a service, or when it returns an empty `new McpToolScope()`, read tools are not narrowed and write tools are denied.

A read tool declared with `#[AsMcpTool(explicitGrant: true)]` is not in "every read tool": `readToolNames: null` and a
request without a scope leave it out, so it is granted only when `readToolNames` names it. Use it for a tool whose data
someone should decide to send to the model provider when they create the token, e.g. private notes.
`McpToolDefinitions::find($toolName)->explicitGrant` tells a host which tools to leave out of its "every read tool"
choice. A write tool is granted only by name anyway, so the flag changes nothing there. The scope only
narrows: a tool in the scope still needs its permission. A tool outside the scope is hidden from `tools/list` and
denied on `tools/call`. `McpToolAccessChecker::getPermittedToolNames()` lists every tool whose
permission the current user holds, in the order of the tools, without the scope, so a host can offer them for a new token. A preview
follows the scope of its write tool, so a token never names it.

## Prompts

A prompt is a template the user starts as a command in the MCP client. Declare it with `#[AsMcpPrompt]`, which extends
the SDK `#[McpPrompt]` with the tools the prompt leads the agent to call, and render its text with
`McpPromptMessageTrait`:

```php
#[AsMcpPrompt(
    name: LabelDigestPrompt::NAME,
    description: 'Summarizes the notes with a label.',
    tools: [ListLabelsTool::NAME, ListNotesTool::NAME, GetNotesTool::NAME],
)]
final readonly class LabelDigestPrompt
{
    use McpPromptMessageTrait;

    public const string NAME = 'label_digest';

    private const string TEXT = <<<'TXT'
        Summarize the notes with the label "{{ label }}".
        1. Resolve the label with {{ list_labels }}.
        …
        TXT;

    public function __invoke(
        #[McpField(description: 'Name or id of the label.')]
        string $label,
    ): PromptMessage {
        return $this->createUserMessage(self::TEXT, ['label' => $label]);
    }
}
```

- **Who sees a prompt.** `prompts/list` shows a prompt and `prompts/get` returns it only when
  `McpToolAccessChecker::isToolGranted()` holds for every tool in its `tools`, the same check as `tools/list` and
  `tools/call`. Otherwise `prompts/get`, and `completion/complete` of an argument of that prompt, answer the JSON-RPC
  error `-32602` with `Access denied — the current MCP user is
  not allowed to use every tool of the prompt "<prompt>".` A prompt has no permission of its own. A prompt without
  tools (`#[AsMcpPrompt(tools: [])]`) is seen by everyone. `tools` has no default, so a prompt cannot leave it out
  by accident.
- **Tools in the text.** The text names a tool only as `{{ <tool name> }}`, e.g. `{{ list_labels }}`.
  `createUserMessage()` replaces every placeholder of a tool in `tools` by its name and every `{{ <argument> }}` by the
  value of that argument, in one pass, so an argument value is never read as a placeholder. A placeholder that is
  neither in `tools` nor an argument throws `McpConfigurationException` naming the prompt, so a tool the text names
  cannot be missing from the tools that decide who sees the prompt. A tool in `tools` or an argument that the text does
  not use throws a `LogicException` (`McpField::fillPlaceholders()`). The check runs when the prompt renders, so a test
  that renders every prompt keeps the texts right.
- **Arguments.** Each parameter of `__invoke()` is an argument of the prompt, described by its `#[McpField]`
  (`descriptionValues` fill the description as for a tool field). It is `required` unless it is optional or has a
  default. A parameter of a type the SDK injects (`RequestContext`, `ClientGateway`) is no argument; a backed enum is
  one, the SDK reads it from the client through `tryFrom()`. The SDK reads an
  argument description only from the `@param` tag of the handler, which drops it silently when the parameter is
  renamed, so `McpPromptLoaderCompilerPass` replaces the `addPrompt` call of every `#[AsMcpPrompt]` with
  `McpPromptLoader`, which registers the prompt with these arguments and keeps its title, description, icons, meta and
  completion providers. A parameter without `#[McpField]` fails the container compilation with
  `McpConfigurationException`.
- **No plain `#[McpPrompt]`.** A prompt of the server declared by the plain SDK `#[McpPrompt]` names no tools, so the
  filter above would show it to every token. `McpPromptLoaderCompilerPass` fails the container compilation with
  `McpConfigurationException` for it; declare it with `#[AsMcpPrompt(tools: […])]`, or `tools: []` for a prompt that
  calls no tool.
- **Compile-time check.** `McpPromptDefinitionsCompilerPass` collects the tools of every `#[AsMcpPrompt]` into
  `McpPromptDefinitions`; a tool that is not an MCP tool of the server and a prompt name that two prompts use fail the
  container compilation with `McpConfigurationException`.

## Tool errors

`McpToolInputException` (an argument the client can correct) and `McpToolCallRejectedException` (a call a guard refused)
are abstract, and the handler turns any subclass into a tool error with its message. A host adds one subclass per domain
and builds each message in a static `create*()` method. The constructor is protected, so a message cannot be thrown from
anywhere else:

```php
final class McpNoteToolInputException extends McpToolInputException
{
    public static function createNotDescribable(int $noteId): self
    {
        return new self(sprintf('Note "%d" has attachments, which MCP cannot keep: tell the user to edit it in the admin.', $noteId));
    }
}
```

A message tells the model what to do next, so it is prose, not an error code.

## Date windows

`McpDateWindowResolver` takes the dates the request read with `McpDateTimeHandler` and caps both a date range
(`resolveDateRange()`) and the log window (`resolveLogWindow()`) at `DATE_RANGE_MAX_DAYS` (31). The cap is applied silently to the effective window, so
`McpDateWindow::$truncated` reports whether the caller asked for more than it got:

* `resolveDateRange()` — `true` when the resolved `until` is earlier than the requested one, or (when it was omitted)
  earlier than now, i.e. whenever the range was cut short at `from + 31 days`.
* `resolveLogWindow()` — `true` when `from` was moved forward to 31 days before `until`.

`resolveLogWindow()` counts the default `from` (1 day) and the cap from `until`, or from now when `until` is in the
future, so a future `until` still searches the last day instead of an empty window.

The resolver does not check the order of the two dates. The request refuses an `until` before `from` itself with
`#[Assert\GreaterThanOrEqual(propertyPath: 'from', message: McpDateWindowResolver::NOT_BEFORE_FROM_MESSAGE)]`, so the
agent gets `Invalid arguments: until: This value must not be before from.` like any other invalid argument. The log
requests (`McpLogSearchRequestTrait`) refuse a `from` in the future the same way
(`McpDateWindowResolver::NOT_IN_FUTURE_MESSAGE`), so a local time read as UTC does not end in an empty result.

Pagination is checked by the input schema instead: a request declares
`#[Assert\Range(min: McpPageWindow::PAGE_MIN, max: McpPageWindow::PAGE_MAX)]` on `page` and
`#[Assert\Range(min: McpPageWindow::LIMIT_MIN, max: <its maximum>)]` on `limit`, so the SDK refuses a page or limit
outside them before the tool runs. `McpPageRequestTrait` declares `page` this way, with `PAGE_MIN` as its default.
`McpPageWindow` gives the tool the `offset`.

Tools must surface a shortened window. `warnings` report only what the server finds out while it answers, such as a
shortened window, more records than the limit or a search that did not finish; an argument the tool cannot use is
refused by the schema or the validator instead. A response gives advice in two `list<string>` fields of plain-language sentences:
`warnings` says how the result is incomplete or adjusted, `nextSteps` says what the agent has to do next. A response has
only the fields it can fill, and a field it has is always present (`[]` when there is nothing to say). Data stays in its
own fields, and advice that is always true belongs in the description of the tool. `McpWarningsTrait` and
`McpNextStepsTrait` declare one field each and ask the response for its texts through their own method, `getWarnings()`
or `getNextSteps()`, so a next step cannot end up in a response that has only `warnings`. Both return `McpNotes`, whose
texts come from named static methods, as the errors of a tool come from exception factories:

```php
final class McpExampleListResponse
{
    use McpNextStepsTrait;
    use McpWarningsTrait;

    protected function getWarnings(): McpNotes
    {
        return new McpNotes()
            ->addIf($this->window->truncated, McpExampleNote::windowTruncated());
    }

    protected function getNextSteps(): McpNotes
    {
        return new McpNotes()
            ->addIf($this->items->isEmpty(), McpExampleNote::changeFilters());
    }
}
```

`McpPageResponseTrait` returns `page` and `limit` of a list response. `McpAppLogSearchResponse` / `McpAuditLogSearchResponse`
build the log tool response from the `McpLogSearchResult` — rows, the effective `from`/`until`, the effective `limit`,
and the `warnings` for a shortened window or for results that were actually cut off — so a client cannot mistake a
partial result for a complete one. `McpLogFinder` fetches one row beyond the effective limit to tell the two apart, so
the warning reports real truncation.

## Pagination

A list tool pages in one of two ways:

* **Pages** (`page` and `limit`) for a bounded set: a small table, or an Elasticsearch search, which returns at most
  10 000 hits (the default `index.max_result_window`), as many as `McpPageWindow::PAGE_MAX` (200) pages of 50. The request uses
  `McpPageRequestTrait`, the response `McpPageResponseTrait` and `hasNextPage`, and the repository gets the
  `offset` from `McpPageWindow`.
* **A cursor** for a database table that keeps growing, such as a history or a log. An offset makes the database
  read and throw away every row of the earlier pages, so a deep page of a long history gets slower with each page.
  A cursor continues after the last row of the previous page through an index instead.

A cursor tool uses `McpCursorRequestTrait` (`cursor`) in the request and `McpCursorResponseTrait` (`nextCursor`) in
the response, and its repository pages the query with `KeysetPaginator` (see [Keyset pagination](pagination.md) for
the query, the index rule and the cursor format):

```php
final class ListNotesRequest
{
    use McpCursorRequestTrait;

    private const int LIMIT_DEFAULT = 20;
    private const int LIMIT_MAX = 50;

    #[McpExistingId(entity: Label::class, resolverTool: ListLabelsTool::NAME)]
    #[McpField(
        description: <<<'TXT'
            Id of the label, as returned by {{ list_labels }}.
            TXT,
        descriptionValues: ['list_labels' => ListLabelsTool::NAME],
    )]
    #[Serialize]
    public int $labelId;

    #[Assert\Range(min: McpPageWindow::LIMIT_MIN, max: self::LIMIT_MAX)]
    #[McpField(description: 'Number of notes per page.')]
    #[Serialize]
    public int $limit = self::LIMIT_DEFAULT;
}

final class McpNoteListResponse
{
    use McpCursorResponseTrait;

    /**
     * @var Collection<int, McpNoteDecorator>
     */
    #[McpField(description: 'Newest first by createdAt.')]
    #[Serialize(type: McpNoteDecorator::class)]
    public Collection $notes {
        get => $this->cursorPage->items;
    }

    /**
     * @param KeysetPage<McpNoteDecorator> $cursorPage
     */
    public function __construct(
        private readonly KeysetPage $cursorPage,
    ) {
    }
}

// in the decorator of the tool, with NoteRepository::findAllByLabel() of pagination.md
return new McpNoteListResponse(
    $this->noteRepository->findAllByLabel(label: $label, pinned: null, cursor: $request->cursor, limit: $request->limit)
        ->map(static fn (Note $note): McpNoteDecorator => new McpNoteDecorator($note)),
);
```

A cursor the tool did not return for the same arguments throws `InvalidCursorException`, and `McpToolErrorResolver`
answers it as an invalid `cursor` argument: `Invalid arguments: cursor: The cursor is not a nextCursor of this list …`
(`McpToolErrorResolver::CURSOR_ARGUMENT`, `InvalidCursorException::MESSAGE`).

## Log query bounds

The log repositories behind the diagnostic tools (`JournalLogRepository::findLatest()`, `AuditLogRepository::findLatest()`,
`findLatestByContextId()` and `McpLogRepository::findLatestByContextId()`) have no index on `datetime`; they bound and sort
every query by the built-in `_id` index instead, deriving the ObjectId range from the `datetime` window via
`MongoHelper::minObjectIdFor()` / `maxObjectIdFor()`. The `datetime` filter stays in place, so the `_id` range only
narrows the scan. Assumptions behind the bounds:

* Journal and audit records are written asynchronously (Messenger consumer), so the `_id` timestamp is the insertion time,
  not the record `datetime`. The range is widened by `-1 minute` below and `+1 day` above the window; a record inserted more
  than one day after its `datetime` (consumer outage, failed-message replay) falls outside a historical window.
* Records are selected in insertion (`_id`) order and the returned page is re-sorted by `datetime`; with a `limit`, the
  selected newest rows are those inserted last, which can differ from a strict `datetime` ordering when consumers run in
  parallel.
* For selective filters (`contextId`, `onlyErrors`) over large windows the scan still fetches every document in the `_id`
  range until `limit` is filled, guarded only by `mongo_query_max_time_ms`; a compound `{ 'context.contextId': 1, _id: -1 }`
  index on `appLogs` / `auditLogs` is the real fix for by-context lookups on busy collections.

## Existing ids

`#[McpExistingId]` checks that an `int` or a `list<int>` property of a tool request holds ids of existing rows of an
entity and tells the client which tool lists the valid ids, so an agent does not guess them:

```php
#[McpExistingId(entity: Label::class, resolverTool: ListLabelsTool::NAME)]
public ?int $labelId = null;
```

`McpExistingIdValidator` reads the ids with one query through `AnzuRepositoryInterface::findExistingIds()` of the
entity's repository; `null` and `[]` pass without a query. An unknown id
fails with `Unknown id "5" — no such record exists. Resolve it via list_labels instead of guessing the id.`, unknown ids
of a list with one violation naming each of them once. An entity whose repository does not implement
`AnzuRepositoryInterface` is a `LogicException`.
The validator is registered also without the `mcp` section.

A tool can then take the entity with `EntityManager::getReference()` and needs no `find()` and `null` check. A row
deleted between the check and the first read throws `EntityNotFoundException`, so a host adds it to `tool_error_exceptions`.

## MCP host and pod

With the section enabled, `McpHostRouteListener` answers 404 to every main request that comes to a host of
`allowed_hosts` or to a pod with `pod: true`, unless its route has the `McpHostRouteListener::ALLOWED_ON_MCP_HOST`
default. It runs on `kernel.request` with priority 24, after the router. `McpHostChecker` trims the hosts and leaves out
empty ones, and `McpController` reads them from it, so both see one list. A host can run the MCP endpoint on its own pods
and hosts:

```php
'mcp' => [
    'allowed_hosts' => env('ANZU_MCP_ALLOWED_HOSTS')->csv(),
    'pod' => env('ANZU_MCP_POD')->bool(),
],
```

```php
$routes
    ->import(resource: '.', type: 'mcp')
    ->defaults([McpHostRouteListener::ALLOWED_ON_MCP_HOST => true])
;
```

## Debugging and looking at the tools

Tools are no routes, so NelmioApiDocBundle cannot describe them. Use what reads the same definitions a client gets:

* `bin/console debug:mcp` lists the tools of the server with their handler and description, `bin/console debug:mcp <tool>`
  shows the description and the input schema of one tool.
* In a debug environment the Symfony profiler has an MCP panel, and every `/api/mcp` response carries an
  `X-Debug-Token`.
* [MCP Inspector](https://github.com/modelcontextprotocol/inspector) (`npx @modelcontextprotocol/inspector`) connects to the
  endpoint with a token and calls the tools.

The server info of `initialize` comes from the McpBundle server config: set `version` (e.g. `env('APP_VERSION')`),
`description` and `website_url` there.
