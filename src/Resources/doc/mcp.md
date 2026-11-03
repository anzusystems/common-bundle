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
  `params`) to the monolog `mcp` channel and to the `mcpLogs` capped collection (see below). The tool itself stays a
  plain invokable service with only its own dependencies.
* `McpWriteToolGuard` — the write grant, read-only mode, one write per request and the audit log of write tools.
* `McpToolCallLogger` and `McpToolErrorResolver` — the call log and the `tool_error_exceptions` map of the handler.
* `StrictToolArgumentsRequestHandler` — rejects tool calls with unknown arguments.
* `McpToolAccessChecker` + `FilterToolsListRequestHandler` — per-tool permissions mapped to host permissions (see
  below); the call-side check lives in `McpToolReferenceHandler`.
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

## Tool requests and responses

A tool is declared with `#[AsMcpTool]`, which extends the SDK `#[McpTool]`, and looks like a Symfony controller:
its `__invoke()` takes the request DTO and returns the response.

```php
#[AsMcpTool(
    name: ListKeywordsTool::NAME,
    description: <<<'TXT'
        Searches active keywords (editorial topic tags) by name.
        Filter {{ list_published_articles }} by the returned ids.
        TXT,
    values: ['list_published_articles' => ListPublishedArticlesTool::NAME],
    readOnly: true,
)]
final readonly class ListKeywordsTool
{
    public const string NAME = 'list_keywords';

    public function __construct(
        private KeywordRepositoryMcpDecorator $keywordRepoMcpDecorator,
    ) {
    }

    public function __invoke(ListKeywordsRequest $request): McpKeywordListResponse
    {
        return $this->keywordRepoMcpDecorator->getList($request);
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
* A write tool that registers the entity it wrote takes `McpWriteToolGuard` in its constructor and calls
  `addAuditLogResource($entity)`.

* `readOnly`, `destructive` and `idempotent` become the `ToolAnnotations` (`openWorldHint` is always `false`): a tool
  is a write tool unless it says `readOnly: true`, so a forgotten flag hides it from tokens without the write grant;
  `destructive` (default `true`, as in the MCP specification) and `idempotent` (default `false`) describe a write tool
  only.
* `values` replace the `{{ name }}` placeholders of the description, like the parameters of a validation message;
  point to another tool through its `NAME` constant, so a renamed or removed tool breaks the build instead of the
  text. A placeholder without a value, or a value without a placeholder, fails the container compilation.
* The input class is the request DTO: `McpToolSchemaCompilerPass` makes the tool's input schema from its
  `#[Serialize]` properties (`McpSchemaFactory`) — type, default, `required` and the limits of its `Assert`
  constraints (`Regex` is left out: PCRE is not the ECMA-262 dialect of `pattern`). A property is required when it has
  no default value or carries `NotBlank`, `NotNull` or `Count(min ≥ 1)`. A `Uuid` property (also nullable or in a list)
  is a string of format `uuid`, so the SDK rejects an invalid UUID before the call reaches the handler.
* The return class is the response: the pass appends a `Returns:` list of its `#[Serialize]` properties to the
  description, because clients hand the model the description and the input schema, not necessarily an output schema.
* `#[McpField(description: …, values: […])]` describes a DTO property, a response property or a case of a backed
  enum; the described cases of an enum field are listed after the field's own description, the default one marked.
  Keep validation messages generic: the schema rejects most invalid calls before the validator runs, and the
  handler prefixes every violation with its property path.

Requests and responses use public properties and property hooks, no getters and setters. The serializer reads and
writes a public or hooked property directly, so a property with a hook must be `public` (a `private` one fails) and a
request property cannot be `private(set)` (it is not deserialized). A `set` hook normalizes the input, and a virtual
`get` property exposes the result in the response; it has no default, so it is `required` in the output schema like
the getter it replaces. The fields are listed in declaration order.

```php
final class ListKeywordsRequest
{
    private const int QUERY_LENGTH_MAX = 100;

    #[Assert\NotBlank]
    #[Assert\Length(max: self::QUERY_LENGTH_MAX)]
    #[McpField(description: 'Keyword name to search for; ignores diacritics and lists the best matches first.')]
    #[Serialize]
    public string $query = App::EMPTY_STRING {
        set => trim($value);
    }
}

final class McpKeywordListResponse
{
    /**
     * @var list<McpKeywordDecorator>
     */
    #[McpField(description: 'Best matches; narrow the query when the expected keyword is missing.')]
    #[Serialize(type: McpKeywordDecorator::class)]
    public array $keywords {
        get => array_map(McpKeywordDecorator::getInstance(...), $this->result->keywords);
    }

    public function __construct(
        private readonly KeywordListResult $result,
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
   before it failed; the tool registers the entity it wrote through `McpWriteToolGuard::addAuditLogResource()`, which
   appends its id to the ids already recorded on the main request (`AuditLogResourceHelper::addResourceByEntity()`); a
   tool whose call must not be audited (e.g. one returning a secret) calls
   `AuditLogResourceHelper::excludeFromAuditLogs()`, and its successful call stays excluded, because the
   guard refuses every later write call of the request before it could include the request again;
4. the payload is returned as a `CallToolResult` carrying it as `structuredContent` — a tool error additionally sets
   `isError: true`, so the protocol client sees a failed call instead of a successful one with an `error` key;
5. the call is logged with `params` = the serialized input (`[]` without one).

`McpToolReferenceHandlerCompilerPass` puts the handler into the server after McpBundle's `McpPass`: around an SDK
`ReferenceHandler` on the handler locator of the server, or, when MCP App tool templates registered
`mcp.server.<server>.app.reference_handler`, between that handler and its SDK handler. It also reads the `__invoke()` signature of every `#[AsMcpTool]` tool once
and passes the map to the handler, so a call needs no reflection. It fails the container
compilation when an `#[AsMcpTool]` tool also has an MCP App template (the template needs an array, the handler returns
a `CallToolResult`) and when the server already has another reference handler.

The serializer builds an input object without a constructor: a DTO with optional fields has public properties with
defaults (it treats every promoted constructor parameter as required).
The bundle tools follow this shape: `Mcp/Model/Request/*Request`, `McpLogFinder` returning a `McpLogSearchResult` /
`McpLogsByContextResult` (facts only), and `Mcp/Model/Response/*Response` owning the JSON shape, the hint and the
warning texts. Log rows that are already serialized arrays are exposed through
`#[Serialize(handler: RawArrayHandler::class)]`, which passes them through the serializer untouched (the default
array handling re-indexes nested maps).

## Tool permissions

Every tool is mapped to an existing permission of the host application in `mcp.tool_permissions` (tool name →
permission name; the map must not be empty — the bundle fails at compile time otherwise, because an empty map hides
every tool and denies every call); `McpToolAccessChecker` resolves it through the host's standard permission model
(`Security::isGranted()` → the host voters, super admin bypass). A tool that is not mapped is denied — there is no
implicit access.

```yaml
anzu_systems_common:
    mcp:
        tool_permissions:
            search_app_logs: cms_log_read
            search_audit_logs: cms_log_read
            get_logs_by_context: cms_log_read
            list_sites: cms_site_read
```

* `tools/call` of a tool the current user may not use returns a tool error result (`isError: true` with
  `{"error": "Access denied — …"}`) from `McpToolReferenceHandler` before the tool runs, and the denied call is
  written to the `mcpLogs` collection with the error (so every tool is declared by `#[AsMcpTool]`, as the bundle tools
  are).
* `tools/list` returns only the tools the current user may call — the filter runs after registry pagination, so a
  page may come back with fewer tools (even none) while `nextCursor` is still set; clients follow the cursor as usual.

`McpCompilerPass` resolves the name of every `mcp.tool` service method carrying `#[McpTool]` (see "Write tools" below)
and fails the container compilation with a `LogicException` when
* one tool name is registered by more than one tool (two classes or methods resolving to the same name) — the SDK
  registry keeps only the last tool registered under a name, and the read/write classification would be ambiguous;
* a tool name is not 1 to 64 letters, digits, dots, underscores, hyphens or slashes (the SDK `NameValidator`) — the
  SDK would register it and only log a warning;
* a `tool_permissions` key matches no registered tool (e.g. a typo) — such a mapping would grant nothing.

A tool that is not declared by `#[McpTool]` on a tagged `mcp.tool` service (e.g. one registered by an McpBundle app)
is unknown to these checks and cannot be mapped in `tool_permissions`.

### Write tools

A tool is a read tool only when its `#[McpTool]` declares `annotations: new ToolAnnotations(readOnlyHint: true)`
(`#[AsMcpTool(readOnly: true)]`);
every other tool (`readOnlyHint` false or missing) is a write tool. `McpCompilerPass` reads the attribute of every
`mcp.tool` service at compile time (on the tagged method, or on the class for `__invoke`, as McpBundle does) and passes
the read tool names to `McpToolAccessChecker`, so the classification needs no built MCP server and a tool name it does
not know is a write tool. A tool without an explicit `name` is matched by the name the SDK derives
(`ElementMetadataResolver::resolveName()`: the class short name for `__invoke`, the method name otherwise).

A write tool additionally requires `McpToolAccessChecker::WRITE_PERMISSION` (`mcp_tool_write`) through
`Security::isGranted()` with the tool name as the subject, so it is hidden from `tools/list` and denied on
`tools/call` unless a host voter grants that attribute for that tool — `anzusystems/auth-bundle` provides one granting
the write tools listed on the personal access token. `McpToolAccessChecker::getWriteToolNames()` lists the tools
mapped in `tool_permissions` that are write tools, in map order, and `getPermittedWriteToolNames()` narrows them to the
tools whose permission the current user holds. A call denied only for the missing write grant answers
`Access denied — the write tool "<tool>" is not granted to this request.` Host voters built on the bundle's
`AbstractVoter` let super admins through on the mapped tool permissions, but not on `mcp_tool_write`, which only a
voter supporting that attribute grants — a super admin whose token does not grant a write tool is denied it too.
"Without a voter the attribute is denied" holds only with Symfony's default access decision strategy (`affirmative`)
and `allow_if_all_abstain: false`; a host that changes either needs a voter that denies `mcp_tool_write`. Annotate
every read tool, or it disappears for clients without the write grant:

```php
#[AsMcpTool(
    name: ListSitesTool::NAME,
    description: '…',
    readOnly: true,
)]
```

### Token scope

A host can narrow the tools of a request, e.g. to the tools listed on a personal access token, by registering a
service under the id `McpToolScopeProviderInterface` (`anzusystems/auth-bundle` provides one).
`provideAllowedToolNames()` returns the tool names the request may use, or `null` for no narrowing; without such a
service every permitted tool stays available. The scope only narrows: a tool in the scope still needs its mapped
permission and, for a write tool, the write grant. A tool outside the scope is hidden from `tools/list` and denied on
`tools/call` with the usual access error. `McpToolAccessChecker::getPermittedToolNames()` lists every tool mapped in
`tool_permissions` whose permission the current user holds, in map order, without the scope, so a host can offer them
for a new token.

## Date windows

`McpDateWindowResolver` caps both the article window (`resolveArticleWindow()`) and the log window
(`resolveLogWindow()`) at `DATE_RANGE_MAX_DAYS` (31). The cap is applied silently to the effective window, so
`McpDateWindow::$truncated` reports whether the caller asked for more than it got:

* `resolveArticleWindow()` — `true` when the resolved `until` is earlier than the requested one, or (when `publishedUntil`
  was omitted) earlier than now, i.e. whenever the range was cut short at `publishedFrom + 31 days`.
* `resolveLogWindow()` — `true` when `from` was moved forward to `until - 31 days`.

`McpPageWindowResolver` does the same for pagination: `resolve($page, $limit, $limitMax)` clamps the page at
`PAGE_MAX` (200) and the limit at the caller's maximum, and the returned `McpPageWindow` reports `isClamped()` plus
the offset and the number of rows still reachable through pagination.

Tools must surface both. The single convention is `McpToolReferenceHandler::WARNINGS_KEY` (`warnings`): a `list<string>` of
plain-language sentences saying what was narrowed and what to do about it, always present (`[]` when nothing was).
`McpAppLogSearchResponse` / `McpAuditLogSearchResponse` build the log tool response envelope that way from the
`McpLogSearchResult` — rows, the effective `from`/`until`, the effective `limit`, the tool hint, and the `warnings`
for a shortened window or for results that were actually cut off — so a client cannot mistake a partial result for a
complete one. `McpLogFinder` fetches one row beyond the effective limit to tell the two apart, so the warning reports
real truncation rather than a clamped `limit` argument. Host tools are expected to use the same key rather than
inventing their own.

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
#[McpExistingId(entity: Site::class, resolverTool: ListSitesTool::NAME)]
public ?int $siteId = null;
```

`McpExistingIdValidator` reads the ids with one query through the entity manager of the entity
(`SELECT <identifier> FROM <entity> WHERE <identifier> IN (:ids)`); `null` and `[]` pass without a query. An unknown id
fails with `Unknown id "5" — no such record exists. Resolve it via list_sites instead of guessing the id.`, unknown ids
of a list with one violation naming each of them once. An entity with a composite identifier is a `LogicException`.
The validator is registered also without the `mcp` section.

## MCP host and pod

With the section enabled, `McpHostRouteListener` answers 404 to every main request that comes to a host of
`allowed_hosts` or to a pod with `pod: true`, unless its route has the `McpHostRouteListener::ALLOWED_ON_MCP_HOST`
default. It runs on `kernel.request` with priority 24, after the router. core-cms runs the MCP endpoint on its own pods
and hosts:

```php
'mcp' => [
    'allowed_hosts' => env('CORE_CMS_MCP_ALLOWED_HOSTS')->csv(),
    'pod' => env('CORE_CMS_MCP_POD')->bool(),
],
```

```php
$routes
    ->import(resource: '.', type: 'mcp')
    ->defaults([McpHostRouteListener::ALLOWED_ON_MCP_HOST => true])
;
```

`McpHostResolver` is a routing condition service (alias `mcp_host`) for routes that must differ by host:
`condition: "service('mcp_host').isMcpHost(request)"` or `isNotMcpHost(request)`.

## Query time limit

`McpQueryBuilder` is a Doctrine `QueryBuilder` whose query gets the MySQL optimizer hint
`/*+ MAX_EXECUTION_TIME(<ms>) */` through `McpMaxExecutionTimeWalker`, so a tool query that runs too long fails instead
of holding a connection. The host builds it in its MCP repositories:

```php
public function createQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
{
    return new McpQueryBuilder(entityManager: $this->getEntityManager(), maxExecutionTimeMs: $this->queryMaxExecutionTimeMs)
        ->select($alias)
        ->from($this->getEntityName(), $alias, $indexBy)
    ;
}
```

## Debugging

McpBundle's own tools work with this setup:

* `bin/console debug:mcp` lists the tools of the server with their handler and description;
* in a debug environment the Symfony profiler has an MCP panel, and every `/api/mcp` response carries an
  `X-Debug-Token`.

The server info of `initialize` comes from the McpBundle server config: set `version` (e.g. `env('APP_VERSION')`),
`description` and `website_url` there.
