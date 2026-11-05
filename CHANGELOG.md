## Unreleased

### Features
* MCP write tools, fail-closed: a tool is a read tool only when its `#[McpTool]` declares `annotations: new ToolAnnotations(readOnlyHint: true)`, every other tool (`readOnlyHint` false or missing) is a write tool. `McpToolDefinitionsCompilerPass` collects the read tool names at compile time from the `#[McpTool]` attribute of every `mcp.tool` service (class level or on the tagged method, as McpBundle reads it; a tool without an explicit `name` gets the name the SDK derives through `ElementMetadataResolver::resolveName()`, the class short name for `__invoke` and the method name otherwise) and passes them to `McpToolAccessChecker`, so the classification works without a built MCP server and a tool name the checker does not know is a write tool. `McpToolAccessChecker::isToolGranted()` grants a write tool only when its mapped tool permission is granted and the tool scope of the request lists it, so `tools/list` hides it and `tools/call` denies it otherwise; the new `isToolPermissionGranted()`, `isWriteGranted()` and `isWriteTool()` expose both checks and the classification, `getWriteToolNames()` lists the tools with a permission that are write tools, in map order, and `getPermittedWriteToolNames()` those of them whose tool permission the current user holds. The bundle only asks for the attribute, the host grants it per tool through a voter (`anzusystems/auth-bundle` provides one granting the write tools listed on the personal access token); without a voter the default affirmative strategy denies every write tool.
* `McpToolReferenceHandler` answers a `tools/call` of a write tool whose tool permission is granted but that is not in the write tools of the scope with `Access denied — the write tool "<tool>" is not granted to this token. Create a new personal access token with this write tool.` instead of the generic tool access error. It answers a `ValidationException` thrown by a tool callback (e.g. by the anzu `Validator` in a host facade) with an `Invalid arguments: <field>: <message> …` tool error listing every field error, so an AI client can fix its arguments; it is logged to the `mcp` channel and `mcpLogs` with the error text and takes precedence over `tool_error_exceptions`. In read-only mode (`AnzuApp::isReadOnlyMode()`) the handler refuses every write tool with a tool error asking to retry later before its callback runs, so a host write tool needs no `throwOnReadOnlyMode()` of its own.
* Successful write tool calls reach the audit log: `McpController` still excludes every MCP request, `McpToolReferenceHandler` includes the main request through the new `AuditLogResourceHelper::includeInAuditLogs()` (counterpart of `excludeFromAuditLogs()`) right before a write tool callback runs and excludes it again when the call fails, so a tool that excludes the request in its callback (e.g. one returning a secret) stays excluded. The host tool registers the entity it wrote through the new `McpAuditLogAwareTrait`, which records it on the main request with `AuditLogResourceHelper` like a controller; a failed write call excludes the request again only when no written resource is recorded on it yet (new `AuditLogResourceHelper::hasResourceId()`), e.g. by the failed call itself before it failed. Read tool calls and requests without a successful write stay excluded.
* An HTTP request (one JSON-RPC batch) carries at most one successful write: once the callback of a write tool returned without an exception, `McpToolReferenceHandler` marks the main request with its own attribute and refuses every further write tool call of that request with `A write tool already ran in this request — send the call of "<tool>" in a separate request.` before the callback runs. The guard does not depend on audit resources — a write tool that registers none blocks the next write call as well — and a refused call leaves the audit state of the request untouched, so the audit log of the first write and an exclusion a tool set in its callback both stay. Without a main request there is no guard.
* `#[AsMcpTool]` (extends `#[McpTool]`) declares a tool whose request DTO is the input class of its `__invoke()` and whose response is its return class: the new `McpToolSchemaCompilerPass` makes the input schema from the `#[Serialize]` properties of the DTO with their types, defaults and the limits of their `Assert` constraints (`McpSchemaFactory`; `Regex` is left out, PCRE is not the dialect of `pattern`), and appends a `Returns:` list of the response fields to the description (`McpOutputDescriptionFactory`). The new `#[McpField]` describes a property, a getter or a case of a backed enum; the `descriptionValues` of both attributes replace `{{ name }}` placeholders of the description, e.g. with the `NAME` of a tool the text points to, so a renamed tool breaks the build instead of the text. A placeholder without a value or a value without a placeholder throws a `LogicException` when the container is compiled. Its `readOnly`, `destructive` (default `true`) and `idempotent` flags become the `ToolAnnotations` with `openWorldHint: false`; a tool without `readOnly: true` is a write tool.
* `McpToolDefinitionsCompilerPass` fails the container compilation with a `LogicException` when a tool name does not pass the SDK `NameValidator` (1 to 64 letters, digits, dots, underscores, hyphens or slashes); the SDK itself registers such a tool and only logs a warning.
* `mcp.instructions_file`: a Markdown file that `McpCompilerPass` reads into the instructions of the server, registered as a container resource.
* MCP tool scope: `McpToolAccessChecker` asks an optional `McpToolScopeProviderInterface` service for a `McpToolScope` with the read and write tools of the request. A read tool outside it is hidden from `tools/list` and denied on `tools/call` with the tool access error; without a scope read tools are not narrowed and write tools are denied.
* `McpSchemaFactory` describes a `Symfony\Component\Uid\Uuid` property of a request DTO (also nullable or in a list) as `{"type": "string", "format": "uuid"}` instead of an object schema of the class, so the SDK rejects an invalid UUID with `-32602` before the tool runs and the serializer builds the `Uuid` objects.
* `McpSchemaFactory` describes a `Collection<int, X>` property or getter (from its `@var` or `@return`, also nullable) as `{"type": "array", "items": <X>}`, the schema of `list<X>`, and a `Collection<string, X>` as `{"type": "object", "additionalProperties": <X>}`, which a response serializes as an object only with `strategy: Serialize::KEYS_VALUES`. A `Collection` without its key and item types fails the container compilation with a `LogicException` naming the member.
* `UuidArrayHandler` serializer handler for a `list<Uuid>` property (`#[Serialize(handler: UuidArrayHandler::class)]`): the serializer-bundle `ObjectHandler` builds the items of a typed array only as objects from arrays, so it cannot turn a list of UUID strings into `Uuid` objects; an invalid item fails the deserialization.
* `#[McpExistingId(entity: Site::class, resolverTool: ListSitesTool::NAME)]` checks that an `int` or a `list<int>` property of a tool request holds ids of existing rows of the entity, with one query through the entity manager of the entity; `null` and `[]` pass without a query. An unknown id fails with `Unknown id "5" — no such record exists. Resolve it via list_sites instead of guessing the id.`, unknown ids of a list with one violation `Unknown ids "5", "7" — no such records exist. Resolve them via list_sites instead of guessing the ids.` An entity with a composite identifier throws a `LogicException`.
* MCP host guard: with the `mcp` section enabled, `McpHostRouteListener` answers 404 to a main request on a host of `mcp.allowed_hosts` or on a pod with the new `mcp.pod: true` (default `false`) unless its route has the `McpHostRouteListener::ALLOWED_ON_MCP_HOST` default. `McpHostResolver` is the routing condition service `mcp_host` with `isMcpHost(request)` and `isNotMcpHost(request)`.
* ECS fixer `PromotedPropertyAttributeSpacingFixer` (`AnzuSystems/promoted_property_attribute_spacing`): in a multi-line constructor where a promoted parameter has an attribute, a parameter with an attribute or a comment above it is set off by one blank line, other parameters follow each other without one, and no blank line follows `(` or precedes `)`; register it in the host `ecs.php`.
* PHPStan rule `McpObjectArrayRule` (`anzu.mcp.objectArray`): in the MCP classes of a host it reports method parameters, return types and properties, `#[Serialize]` ones included, that hold an array of the host's objects and asks for a `Collection` instead; enums and objects outside the host namespace (`Uuid`, `DateTimeImmutable`) are allowed. Include `vendor/anzusystems/common-bundle/src/PHPStan/rules.neon` in the host `phpstan.neon` and set `anzuMcpObjectArray.classNamespaces` (e.g. `App\Domain\*\Mcp`, `App\Mcp`; `*` is one namespace segment) and `anzuMcpObjectArray.objectNamespace` (e.g. `App`); without them the rule checks nothing.
* `CollectionHelper::traversableToUniqueIds()` takes the same arguments as `traversableToIds()` and returns each id once, in the order of its first occurrence, as a list.
* `#[AsMcpTool]` takes a required `category`: the camelCase name (`^[a-z][a-zA-Z0-9]*$`) of the group a tool belongs to, e.g. in a token form; categories come in the order of their first tool in `McpToolAccessChecker::getToolNames()`. `McpToolDefinitionsCompilerPass` collects it from every `#[AsMcpTool]` tool and the new `McpToolAccessChecker::getToolCategory()` returns it (a `LogicException` for a tool without a category). The bundle log tools are in the new `AsMcpTool::CATEGORY_DIAGNOSTICS` (`diagnostics`).
* `#[AsMcpTool(previewOf: CreateQuizTool::NAME)]` declares a read tool that previews a write tool: `McpToolAccessChecker` grants it exactly when it grants the write tool (tool scope and permission of the write tool) in `tools/list` and on `tools/call`, otherwise the call gets the tool access error. A preview has no permission of its own, so `getToolNames()`, `getWriteToolNames()`, `getPermittedToolNames()` and `getPermittedWriteToolNames()` never list it and a token never names it; as a read tool it skips the write grant, read-only mode, the one write per request and the audit log.
* `McpToolDefinitionsCompilerPass` fails the container compilation with a `LogicException` when a category is not camelCase, `previewOf` names an unknown tool or one that is not a write tool declared by `#[AsMcpTool]`, a preview is not `readOnly`, a write tool has more than one preview, a preview has its own permission, or a preview is in another category than its write tool.
* `McpDeleteGuard` decides whether an entity may be deleted through MCP: it was created at most `McpDeleteGuard::MAX_AGE_DAYS` (7) days before `AnzuApp::date()`, by the current user (an entity without an author is refused), and nothing uses it. `resolveRefusals()` returns every failed condition as one sentence (`TOO_OLD_MESSAGE`, `NOT_CREATOR_MESSAGE`, `IN_USE_MESSAGE`) for a preview, and `assertDeletable()` throws `McpDeleteRejectedException` listing them all. The guard does not check the delete permission; the host tool does.
* `McpDeleteDependencyProviderInterface`: a host reports what uses an entity of the class its static `getSupportedEntity()` returns, as short English descriptions from `provideDependencies()` (empty when nothing does). With the `mcp` section the bundle tags every autoconfigured implementation with `AnzuSystemsCommonBundle::TAG_MCP_DELETE_DEPENDENCY_PROVIDER` and gives them to `McpDeleteGuard` keyed by the entity class; an entity without a provider throws a `LogicException`, never "no dependencies".
* an MCP write tool asks the user to confirm through stateless elicitation when the client declares form elicitation on revision 2026-07-28: `McpToolReferenceHandler` runs its preview and returns `input_required` with the key `confirm_<previewHash>` only when the `previewHash` matches; `accept` with `confirm: true` writes, any other answer is a tool error and nothing is asked again. Other clients stay on the preview.
* `McpWritePreviewInterface`, `McpConfirmationMessage` and `McpPreviewHashAwareInterface` (moved from the host).
* the call log (`mcpLogs`, `mcp` channel) has the `client` name, and the round that only asks is logged to the channel alone.
* `mcp.confirmation_fallback_locale` (default `null`): the language of the write confirmation for a user without `AnzuUser::getLocale()`; `null` uses the default locale of the translator.
* `McpToolAccessChecker::getPreviewToolName()` returns the preview of a write tool, `null` when it has none.
* `McpPreviewHashFactory` and `McpPreviewHashTrait` (moved from the host): the hash covers the serialized decorator of the current state without `id`, `adminUrl`, `createdAt` and `modifiedAt`, so a new entity needs no hash code of its own.
* `#[AsMcpTool(inputPermission: …)]`: a permission checked on the input of every call, with the input as the voter subject, before the tool runs; `McpInputEnricherInterface` then narrows the input. A tool with it fails the compilation without an input class or without that service.
* `McpPageResponseTrait`: `page`, `limit` and the warning about a reduced page of a list response.
* `McpSchemaFactory` describes a property with a `Compound` constraint by the constraints inside it.
* `McpToolSchemaCompilerPass` adds the write limit of a tool to its description, with the tools that share it.
* Log tools return null instead of `''` and `0` for a value the record does not have, `get_logs_by_context` returns the `client` of a tool call, and the output schema lists the fields of a record through the new `McpAuditLogDecorator`, `McpAppLogDecorator` and `McpToolCallLogDecorator`.

### Changes
* BC change: `#[AsMcpTool]` takes `permission`, the permission of the host the tool needs, instead of the removed `mcp.tool_permissions` map. The log tools of the bundle take theirs from the new required `mcp.log_tools_permission`. A tool declared by `#[AsMcpTool]` without a permission or a preview now fails the container compilation instead of staying hidden. Tools and their categories come in the order of the tool services, no longer in the order of the map.
* BC change: the `values` argument of `#[AsMcpTool]` and `#[McpField]` is now `descriptionValues`.
* `StrictToolArgumentsRequestHandler` lists the allowed arguments only for a tool the user may call, so a caller without the tool no longer learns them, and its rejection reaches `mcpLogs`.
* BC change: `McpDateWindowResolver::resolveArticleWindow()` is now `resolveDateRange()` with the names of its arguments, `ensureNotInverted()` takes them too, and `McpDateWindowInputException` has one `createInverted()`.
* BC change: `McpLogSearchResult` and `McpLogsByContextResult` hold collections of decorators; `McpLogFinder::FIELD_TRUNCATE_LENGTH` and `TRUNCATED_SUFFIX` move to `AbstractMcpLogDecorator`.
* BC change: `McpToolDefinitionsCompilerPass` takes over the tools from `McpCompilerPass`, and the compile errors of both come from the new `McpConfigurationException` instead of the `*_MESSAGE` constants.
* BC change: `McpWritePreviewInterface` declares `$writable` and `$currentState`, and `$previewHash` is set by `McpToolReferenceHandler`: a preview no longer calculates its hash.
* BC change: an `OptimisticLockException` of a write tool answers `McpWriteRejectedException::createPreviewStale()`, so a host drops its catch.
* BC change: `McpToolAccessChecker` takes the new `McpToolDefinitions` instead of the maps of permissions, read tools, categories and previews; `McpWriteRateLimiter` and `McpToolReferenceHandler` read it too, and `McpToolSignature` moved into `McpToolDefinition`.
* BC change: `McpToolReferenceHandler::ACCESS_DENIED_MESSAGE` and `INVALID_ARGUMENTS_PREFIX` move to `McpToolErrorResolver`, and `McpToolErrorResolver::resolveMessage()` is now `resolveBackendMessage()`. The confirmation and the write limit moved from the handler to `McpWriteToolGuard::guardPreview()`.
* BC change: `McpToolReferenceHandler` compares the `previewHash` of every write tool with its preview for all clients, not only with elicitation, so a host drops its own check. `#[AsMcpTool(rateLimiter: …)]` names a limiter of `framework.rate_limiter` taking one token per write that passed its preview, with the new `McpWriteRateLimiter` and `McpWriteRejectedException::createRateLimitExceeded()`.
* BC change: `McpWriteToolGuard` no longer records the audit log: `addAuditLogResource()` is replaced by `McpAuditLogAwareTrait`, which a write tool uses, and `AuditLogResourceHelper::addResourceByEntity()` is removed. A failed write stays out of the audit log unless the tool recorded a resource before it failed.
* BC change: `McpToolInputException` and `McpToolCallRejectedException` are abstract with a protected constructor. A host adds a subclass with static `create*()` methods, the handler keeps turning them into tool errors. The message constants of `McpWriteToolGuard`, `McpWriteConfirmation`, `McpToolReferenceHandler::TOOL_ACCESS_DENIED_MESSAGE` and `McpDeleteGuard::REFUSED_MESSAGE` moved into the new `McpWriteRejectedException`, `McpToolAccessRejectedException` and `McpDeleteRejectedException`.
* BC change: the bundle requires `anzusystems/serializer-bundle` `^7.0`, which refuses input that does not fit the type. See `UPGRADE-7.0.md` of that bundle.
* `BsonConverter` writes through the `PropertyAccessor` of serializer-bundle, so a Mongo document can use public, `private(set)` and other properties without a setter method.
* `McpDateWindowResolver::parseDateTime()` converts the parsed date-time to UTC, and a value without an offset is read as UTC. A host that binds the window to a Doctrine datetime column dropped the offset and shifted the window by it.
* BC change: the bundle requires PHP 8.5.
* BC change (security): a host MCP tool without `readOnlyHint: true` is now a write tool, hidden from `tools/list`, denied on `tools/call` unless the tool scope of the request lists it, and refused in read-only mode. Annotate every read tool with `annotations: new ToolAnnotations(readOnlyHint: true)` on its `#[McpTool]` before upgrading; the bundle log tools (`search_app_logs`, `search_audit_logs`, `get_logs_by_context`) declare `ToolAnnotations(readOnlyHint: true, openWorldHint: false)`.
* BC change: `McpToolDefinitionsCompilerPass` fails the container compilation with a `LogicException` when one MCP tool name is registered by more than one tool (two `#[McpTool]` classes or methods resolving to the same name — the SDK registry keeps only the last one). Rename one of the tools before upgrading.
* `ForceIndexWalker` matches the hinted join table only as a whole, escaped word (`JOIN <table> <alias>`), so a hint for `article_kind` no longer lands on a join of `article_kind_has_keyword` and produces invalid SQL.
* The tool error for an `AccessDeniedException` thrown by a tool callback reads `Access denied — the current MCP user is not allowed to access the requested resource.` instead of `… not allowed to use the requested filters.`, so it fits write tools too.
* BC change: `SearchAppLogsRequest`, `SearchAuditLogsRequest` and `GetLogsByContextRequest` are plain classes with public properties and defaults instead of readonly constructor promotion, because the serializer requires every promoted constructor parameter; `McpLogFinder::LIMIT_MIN_MESSAGE` is removed in favour of the generic validator message.
* New dependency `symfony/filesystem` (`^7.1|^8.0`).
* BC change: `McpToolExecutor` is removed and an `#[AsMcpTool]` tool is a plain invokable service without a base class, like a Symfony controller: `__invoke(ListKeywordsRequest $request): McpKeywordListResponse`. The new `McpToolReferenceHandler` (an SDK `ReferenceHandlerInterface`) deserializes the call arguments into the input class of `__invoke()`, runs the checks, invokes the tool through the SDK handler, serializes the returned object and logs the call; the new `McpToolReferenceHandlerCompilerPass` puts it into the server around the SDK handler, or between the MCP App reference handler and the SDK one. `__invoke()` takes the input class and then an optional `RequestContext`, only the `RequestContext`, or nothing, and returns a response class or a `CallToolResult`, which is passed through unchanged (one with `isError: true` is logged as an error and does not count as a write); `McpToolSchemaCompilerPass` fails the container compilation for any other signature, and `McpToolReferenceHandlerCompilerPass` for an `#[AsMcpTool]` tool that also has an MCP App template. Error texts, the order of the checks, the audit log handling, the one write per request and the call logs stay as they were.
* BC change: `McpToolExecutor::ERROR_KEY` moves to `McpToolReferenceHandler`, `WARNINGS_KEY` is removed, `McpToolExecutor::addAuditLogResource()` is replaced by `McpAuditLogAwareTrait`, which a write tool uses, and the `tool_error_exceptions` map moves to the new `McpToolErrorResolver`. The write grant, read-only mode and the one write per request move to the new `McpWriteToolGuard`, the call log to the new `McpToolCallLogger`. A failed check before the tool runs throws the new `McpToolCallRejectedException`, whose message is the tool error. `McpToolSchemaCompilerPass` no longer fills a `$toolInputClasses` argument.
* BC change: the MCP log responses (`McpAppLogSearchResponse`, `McpAuditLogSearchResponse`, `McpLogsByContextResponse`) expose their fields as virtual properties instead of getters; the requests already had public properties.
* Static analysis runs on PHPStan level 5 (`phpstan.neon`, `bin/phpstan`, the CI step) instead of Psalm; `psalm.xml` and `bin/psalm` are removed.
* `BaseUserDto` and `AbstractCsvRowAccessor` declare `@phpstan-consistent-constructor`, so PHPStan in a host checks that their subclasses keep a constructor `new static()` can call.
* BC change: McpToolDefinitionsCompilerPass fails the container compilation for a write tool without a preview, a preview response without McpWritePreviewInterface and a write input that does not extend the preview input and implement McpPreviewHashAwareInterface.
* McpCompilerPass sets explicit server capabilities without listChanged and subscribe, a subscription lifetime of 1 ms and no input required shim.
* New dependency `symfony/translation-contracts` (`^3`).
* BC change: `McpPageWindow` exposes `$clamped`, `$raisedToMinimum`, `$offset` and `$reachableRows` as virtual properties instead of `isClamped()`, `isRaisedToMinimum()`, `getOffset()` and `getReachableRows()`; the class is no longer `readonly`, its constructor properties still are.
* BC change: `#[AsMcpTool]` requires `category`. Add `category:` to every tool before upgrading.
* BC change: `McpObjectArrayRule` reports `#[Serialize]` arrays of host objects too; declare them as `Collection<int, X>`.
* BC change: `McpSchemaFactory` fails the container compilation for a `Collection` property without `Collection<…>` in its `@var` (a getter without it in its `@return`).

## [11.8.0](https://github.com/anzusystems/common-bundle/compare/11.7.0...11.8.0) (2026-10-13)

### Features
* `ResourceLocker::lock()` takes an optional `ttl` in seconds (default `ResourceLocker::DEFAULT_TTL`, the 60 seconds used so far), so a lock can cover work that runs longer than a minute.

## [11.7.0](https://github.com/anzusystems/common-bundle/compare/11.6.0...11.7.0) (2026-10-01)

### Features
* `CsvHelper::getCsv()` takes `separator`, `enclosure` and `escape` (defaults `,`, `"` and `\`, the parsing so far) and passes them to `SplFileObject::setCsvControl()`, so a `;`-separated file can be read through the helper. Pass `escape: ''` for files that only double their quotes (RFC 4180, Excel, Python `csv`).
* `AbstractCsvRowAccessor::getMissingHeaders()` lists the `HEADERS` the file does not have, so a caller can reject a file instead of failing on the first `get()`, and `isEmpty()` tells a blank line (read by `SplFileObject` as `[null]`, including the one after the last line break) from an invalid row.

### Changes
* `CsvHelper::getCsv()` (and `writeCsv()` / `appendCsv()` through it) no longer triggers the PHP 8.4 deprecation `SplFileObject::setCsvControl(): the $escape parameter must be provided` on every call.
* `AbstractCsvRowAccessor::setHeader()` skips a UTF-8 BOM before it parses the header, so the first column of a file exported with a BOM is matched, quoted or not; it used to stay unmapped and `get()` failed on an undefined index.

## [11.6.0](https://github.com/anzusystems/common-bundle/compare/11.5.0...11.6.0) (2026-09-16)

### Changes
* BC change (security): reading journal and audit logs through the log API (`getJournalLogs`, `getOneJournalLog`, `getAuditLogs`, `getOneAuditLog`) requires the new `logs.read_role`, `ROLE_SUPER_ADMIN` by default. Access was until now decided by host routing and firewall alone, so set `read_role` to a role or permission your current log consumers already hold to keep them working. Creating custom logs stays unrestricted. With MCP enabled, map `search_audit_logs`, `search_app_logs` and `get_logs_by_context` in `mcp.tool_permissions` to the same role.

## [11.5.0](https://github.com/anzusystems/common-bundle/compare/11.4.2...11.5.0) (2026-09-09)

### Requirements
* Requires `symfony/mcp-bundle` `^0.13` (with `mcp/sdk` `^0.8.1`); the `conflict` moved from `>=0.12` to `<0.13`. The host must migrate `config/packages/mcp.php` to the `servers:` shape — `app`/`version` become `servers.<name>.{name,version}`, `client_transports` becomes `transports`, and `discovery.scan_dirs` is replaced by `registry.tools` patterns (file-based discovery is gone: a tool needs the `mcp.tool` tag **and** a matching pattern). The new `mcp.server_name` option must name the same server key, because the bundle attaches its controller, its own tools and the session store to it. Session keys are now prefixed `mcp-<server_name>-`, so sessions open across the deploy are dropped and clients re-initialize.
* An enabled `mcp` section requires a non-empty `mcp.tool_permissions` map (tool name → host permission name), otherwise the container fails at compile time like an empty `allowed_hosts` — a tool that is not mapped is hidden from `tools/list` and denied on `tools/call`, there is no implicit access.

### Features
* Per-tool MCP permissions: `mcp.tool_permissions` maps every tool name to an existing host permission, resolved by `McpToolAccessChecker` through the host's standard permission model (`Security::isGranted()` → the host voters, super admin bypass). `McpToolExecutor::execute()` answers an unauthorized `tools/call` with a tool error result logged into `mcpLogs` before the tool callback runs, `FilterToolsListRequestHandler` hides unauthorized tools from `tools/list` (the filter runs after registry pagination, so a page may come back with fewer tools while `nextCursor` is still set).
* `McpRateLimiter` applies an elevated limit by role: new `mcp.rate_limiter.elevated_role` + `mcp.rate_limiter.elevated_limit` options replace the configured `limit` for users granted the role (`Security::isGranted()`, so role hierarchy applies); a `null` or non-positive `elevated_limit`, or an unset role, keeps the default, and the configured `interval` applies to every bucket.
* MCP date windows report their own truncation: `McpDateWindow` carries `truncated`, set by `McpDateWindowResolver` when the requested range was cut down to the 31-day cap (article window: `until` pulled back to `from + 31 days` or, with no `publishedUntil`, back from now; log window: `from` pushed forward to `until - 31 days`). `search_app_logs` and `search_audit_logs` now echo the effective `from`/`until` and a `warnings` list (always present, `[]` when nothing was shortened) reporting a shortened window or a result that was actually cut off — `McpLogFinder` fetches one row beyond the effective limit to tell real truncation from a clamped `limit` argument — so a partial result can no longer be read as a complete one.
* MCP pagination reports its own clamping: new `McpPageWindowResolver` + `McpPageWindow` (page capped at `PAGE_MAX` 200, limit at the caller's maximum) give host tools one place to clamp page and limit, exposing `isClamped()`, `getOffset()`, `getReachableRows()` and the ready-made `getClampWarning()` text. New `McpToolExecutor::WARNINGS_KEY` (`warnings`, a `list<string>`) is the one convention for reporting a narrowed result, for bundle and host tools alike.
* `McpToolExecutor::execute(string $toolName, ?object $request, Closure $callback)` validates the request object with the Symfony validator (violations answer the call with `Invalid arguments: <propertyPath>: <message> …` before the callback runs), serializes an object callback result with the anzu serializer and logs the serialized request as `params`. The bundle log tools follow that shape: `SearchAppLogsRequest`, `SearchAuditLogsRequest`, `GetLogsByContextRequest` (`#[Serialize]` + `#[Assert]`, `limit` must be at least 1), `McpLogSearchResult` / `McpLogsByContextResult` as pure results and `McpAppLogSearchResponse`, `McpAuditLogSearchResponse`, `McpLogsByContextResponse` owning the JSON shape, hints and warning texts. New `RawArrayHandler` serializer handler passes already-serialized arrays through untouched (`#[Serialize(handler: RawArrayHandler::class)]`); it is a generic sibling of `ArrayStringHandler` and is expected to move to `anzusystems/serializer-bundle` later.

### Changes
* MCP integration follows the McpBundle 0.13 per-server container layout: `McpController` is aliased to `mcp.server.<server_name>.controller`, the tool-list and strict-arguments handlers take `mcp.server.<server_name>.registry`, and the pagination limit of the tool-list filter is read from that server's `pagination_limit` instead of the removed `mcp.pagination_limit` parameter. The controller builds its middleware through the McpBundle `MiddlewareFactory` rather than its own list — SDK 0.8 applies `ProtocolVersionMiddleware` to handshake-era traffic itself, and a custom list carrying it rejects every modern-era (2026-07-28) request.
* The `cache.mcp.sessions` definition is gone: `mcp.session.cache_pool` is wrapped in a `Psr16Cache` and prepended as the McpBundle session store of the configured server, so a custom McpBundle session pool no longer silently wins.
* MCP tool errors are reported as protocol errors: `McpToolExecutor::execute()` and `StrictToolArgumentsRequestHandler` now answer a denied, invalid or failed call with a `CallToolResult` carrying `isError: true` (the `{"error": …}` payload stays available as `structuredContent`), so a client no longer records a failed tool call as a successful one.
* The MCP rate limit is charged per JSON-RPC message instead of per HTTP request: `McpController` counts the messages of a batch body and `McpRateLimiter::checkRateLimit(int $messages = 1)` consumes that many tokens (clamped at the bucket size). A single request could otherwise carry up to `MessageFactory::DEFAULT_MAX_BATCH_SIZE` (100) tool calls for one token.
* The MCP sliding window is consumed under a lock: `McpRateLimiter` takes a `LockFactory` (a Redis one over `settings.app_redis` by default, or the service named by the new `mcp.rate_limiter.lock_factory` option), so parallel requests of one user — across pods, since the limiter storage is shared — can no longer both pass on the same pre-consume state.
* Log search queries (`JournalLogRepository::findLatest()`, `AuditLogRepository::findLatest()`, `findLatestByContextId()`, `McpLogRepository::findLatestByContextId()`) are bounded and sorted by the built-in `_id` index instead of the unindexed `datetime` sort and re-sorted by `datetime` afterwards; new `MongoHelper` with `minObjectIdFor()` / `maxObjectIdFor()` / `sortNewestFirst()` and `FIELD_ID` / `SORT_DESC` constants derives the bounds. See the "Log query bounds" section of `src/Resources/doc/mcp.md` for the slack assumptions.
* BC change: `McpToolExecutor` requires `McpToolAccessChecker`, `ValidatorInterface` and `Serializer`, `execute()` takes a `?object $request` instead of a params array and returns a `CallToolResult` instead of an array (tools declaring `: array` must be retyped); `McpRateLimiter` takes the configured `limit`, `interval` and the rate limiter `StorageInterface` directly instead of a `RateLimiterFactory` (the `anzu_systems_common.mcp.rate_limiter_factory` service is gone); `McpLogFinder` requires `McpContextIdResolver`, `findAppLogs()` / `findAuditLogs()` take the request objects and return a `McpLogSearchResult` (rows plus the resolved `McpDateWindow`, the effective limit and `hasMore`), `findLogsByContext()` replaces `findAppLogsByContextId()` / `findAuditLogsByContextId()` / `findMcpLogsByContextId()` and `McpAuditLogFilter` is gone; the log tools no longer take `McpContextIdResolver`.

## [11.4.2](https://github.com/anzusystems/common-bundle/compare/11.4.1...11.4.2) (2026-09-02)

### Changes
* `CurrentAnzuUserProvider` implements `ResetInterface` and its service carries the `kernel.reset` tag, so the memoized current user is dropped whenever the kernel resets its services (between requests of one process, between tests that keep one kernel per class). Subclasses inherit it; an application service definition with autoconfiguration picks the tag up automatically.

## [11.4.1](https://github.com/anzusystems/common-bundle/compare/11.4.0...11.4.1) (2026-08-17)

### Changes
* `symfony/mcp-bundle` requirement raised from `^0.10` to `^0.11` (the `suggest` entry accepts `^0.10|^0.11`); the `conflict` moves to `>=0.12`.

## [11.4.0](https://github.com/anzusystems/common-bundle/compare/11.3.0...11.4.0) (2026-08-05)

### Features
* New opt-in `mcp` config section (disabled by default — upgrading without enabling it requires no new packages, env variables or infrastructure) built on `symfony/mcp-bundle`: `McpController` with streamable HTTP transport, DNS-rebinding protection (`allowed_hosts`, required non-empty) and a per-user sliding-window rate limit; `McpToolExecutor` translating `McpToolInputException`, `AccessDeniedException` and a configurable `tool_error_exceptions` FQCN-to-message map into tool error results while logging every call to the `mcp` monolog channel and a capped `mcpLogs` mongo collection; `StrictToolArgumentsRequestHandler` rejecting tool calls with unknown arguments.
* Diagnostic MCP tools `search_app_logs`, `search_audit_logs` and `get_logs_by_context` over the bundle-owned `appLogs`/`auditLogs` collections and the new `mcpLogs` collection, correlated by `contextId`, windows capped at 31 days and results at 50 rows with long fields truncated.
* `anzu:mcp:create-log-collection` command provisioning the capped `mcpLogs` collection idempotently; the mcp mongo connection defaults to the `logs.journal.mongo` connection, so no extra env variables are needed in the default setup.
* `JournalLogRepository::findLatest()`, `AuditLogRepository::findLatest()` and `findLatestByContextId()` — filterable newest-first raw log searches on a new shared `AbstractLogRepository` base.
* `McpCompilerPass` overriding the McpBundle `mcp.server.controller` and `cache.mcp.sessions` definitions after extension merge, so the bundle controller (rate limit, audit-log exclusion, allowed hosts) and the configured session cache pool always win regardless of bundle order.
* See `src/Resources/doc/mcp.md` for the enable checklist (requires `symfony/mcp-bundle` + `symfony/rate-limiter`, the `logs` section enabled, host-owned `config/packages/mcp.php` with `client_transports.http: true` and a route import; the MCP endpoint ships without authentication — pair it with the personal access tokens from `anzusystems/auth-bundle`).

### Changes
* `JournalLogRepository` and `AuditLogRepository` now extend the new `AbstractLogRepository` (public API unchanged).
* New `conflict` with `symfony/mcp-bundle >=0.11` — the MCP integration compiles against the 0.10 SDK internals.

## [11.3.0](https://github.com/anzusystems/common-bundle/compare/11.2.0...11.3.0) (2026-07-13)

### Features
* New `LogContextContentProcessor` — expands the JSON-encoded `LogContext::content` string back into an array for handlers that report structured context. Without it, structured log context (e.g. `['taskId' => 1, 'detail' => 'reason']`) reaches Sentry as one opaque JSON string inside `monolog.context.content` — not searchable and easy to miss. Registered as a plain service (no `monolog.processor` tag) on purpose, so the journal/audit mongo pipeline keeps persisting `content` as a string; apps push it onto their outbound handler:
```php
$services
    ->set('sentry.monolog.handler', SentryHandler::class)
    ->arg('$hub', service(HubInterface::class))
    ->arg('$level', Level::Warning)
    ->arg('$bubble', true)
    ->arg('$fillExtraContext', true)
    ->call('pushProcessor', [service(LogContextContentProcessor::class)])
;
```

## [11.2.0](https://github.com/anzusystems/common-bundle/compare/11.1.1...11.2.0) (2026-07-03)

### Features
* New `HttpExceptionHandler` — standardized JSON error response (`error`, `detail`, `contextId`) for any unhandled `HttpException`, preserving the exception's original status code (429, 503, ...) instead of falling through to the default handler as 500. Registered after the more specific handlers (`AccessDeniedExceptionHandler`, `NotFoundExceptionHandler`, ...).
* `ExceptionHandlerCompilerPass` now sorts exception handlers by the `priority` attribute of the `anzu_systems_common.logs.exception_handler` tag (high → low). Handlers without an explicit priority keep the default `0`; the built-in `HttpExceptionHandler` registers with `-100`, so more specific handlers always take precedence:
```php
$definition->addTag(AnzuSystemsCommonBundle::TAG_EXCEPTION_HANDLER, ['priority' => -100]);
```

## [10.0.0](https://github.com/anzusystems/common-bundle/compare/9.4.0...10.0.0) (2024-10-30)

### Features
 * App logger is the default logger expected to be logged to Sentry or Syslog and log errors, not mongodb. To keep logging some other data in Mongodb, new journal logger was created. 

### Changes
* BC change: `anzu_mongo_app_log_collection` was renamed to `anzu_mongo_journal_log_collection`
* BC change: routing changes - `app` was renamed to `journal`
Before:
```php
    $routes
        ->add('anzu_common.logs.app_list', '/api/adm/v1/log/app')
            ->methods([Request::METHOD_GET])
            ->controller([LogController::class, 'getAppLogs'])
    ;

    $routes
        ->add('anzu_common.logs.app_get_one', '/api/adm/v1/log/app/{id}')
            ->methods([Request::METHOD_GET])
            ->controller([LogController::class, 'getOneAppLog'])
    ;
```

Now:
```php
    $routes
        ->add('anzu_common.logs.journal_list', '/api/adm/v1/log/journal')
            ->methods([Request::METHOD_GET])
            ->controller([LogController::class, 'getJournalLogs'])
    ;

    $routes
        ->add('anzu_common.logs.journal_get_one', '/api/adm/v1/log/journal/{id}')
            ->methods([Request::METHOD_GET])
            ->controller([LogController::class, 'getOneJournalLog'])
    ;
```

* BC change: configuration change

Before:
```php
    $logsConfig
        ->app()
            ->ignoredExceptions([
                NotFoundHttpException::class,
                AccessDeniedException::class,
                UnauthorizedHttpException::class,
                ValidationException::class,
            ])
            ->mongo()
                ->uri(env('ANZU_MONGODB_APP_LOG_URI'))
                ->username(env('ANZU_MONGODB_APP_LOG_USERNAME'))
                ->password(env('ANZU_MONGODB_APP_LOG_PASSWORD'))
                ->database(env('ANZU_MONGODB_APP_LOG_DB'))
                ->ssl(env('ANZU_MONGODB_APP_LOG_SSL')->bool())
                ->collection('appLogs')
    ;
```

Now:
```php
    $logsConfig
        ->app()
            ->ignoredExceptions([
                NotFoundHttpException::class,
                AccessDeniedException::class,
                UnauthorizedHttpException::class,
                ValidationException::class,
            ])
    ;

    $logsConfig
        ->journal()
            ->mongo()
                ->uri(env('ANZU_MONGODB_APP_LOG_URI'))
                ->username(env('ANZU_MONGODB_APP_LOG_USERNAME'))
                ->password(env('ANZU_MONGODB_APP_LOG_PASSWORD'))
                ->database(env('ANZU_MONGODB_APP_LOG_DB'))
                ->ssl(env('ANZU_MONGODB_APP_LOG_SSL')->bool())
                ->collection('appLogs')
    ;
```

## [8.0.0](https://github.com/anzusystems/common-bundle/compare/7.0.0...8.0.0) (2024-05-29)
### Features
* Added command `anzusystems:user:sync-base` for loading basic user set (depends on `user_sync_data` configuration)
* Added `BaseUserDto` to `UserDto`, added `UserTracking` and `TimeTracking` fields 
* Added `mapDataFn` to `findByApiParams` and `findByApiParamsWithInfiniteListing` functions

### Changes
* BC change -> Abstract voter expects `ROLE_SUPER_ADMIN` instead of `ROLE_ADMIN` to grant full access

## [7.0.0](https://github.com/anzusystems/common-bundle/compare/6.0.4...7.0.0) (2024-05-13)
### Changes
* Fix sending job with old status to event dispatcher and not updating modifiedBy by @pulzarraider in #56
* Update to anzusystems/serializer-bundle 4.0 by @pulzarraider in #57
Read the UPGRADE.md if you want to update to this version.
