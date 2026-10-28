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
   (`config/packages/mcp.php` — `name`, `version`, `instructions`, `transports: { http: true }`, `http.path`, and
   `registry.tools` listing the project's own tool namespace). `transports.http` is mandatory — without it McpBundle
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
           allowed_hosts: '%env(csv:ANZU_MCP_ALLOWED_HOSTS)%'
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
* `McpToolExecutor` — wraps tool callbacks: checks the tool permission and the write grant, validates the request
  object with the Symfony validator, serializes an object result with the anzu serializer, converts
  `McpToolInputException`, `ValidationException`, `AccessDeniedException` and configured `tool_error_exceptions` into
  `CallToolResult`s with `isError: true`, refuses write tools in read-only mode, includes a successful write tool call
  in the audit log, logs every call (with the serialized request as `params`) to the monolog `mcp` channel and to the
  `mcpLogs` capped collection (see below).
* `StrictToolArgumentsRequestHandler` — rejects tool calls with unknown arguments.
* `McpToolAccessChecker` + `FilterToolsListRequestHandler` — per-tool permissions mapped to host permissions (see
  below); the call-side check lives in `McpToolExecutor`.
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

Every tool routes through
`McpToolExecutor::execute(string $toolName, ?object $request, Closure $callback): CallToolResult`:

1. the tool permission and, for a write tool, the write grant are checked (see below);
2. when `$request` is an object it is validated with the Symfony validator — any violation answers the call with a
   tool error `Invalid arguments: <propertyPath>: <message> …` before the callback runs, so constraint messages should
   be plain English sentences ending with a period (e.g. `limit must be at least 1.`); a write tool is refused with a
   tool error asking to retry later while `AnzuApp::isReadOnlyMode()` is on, and with
   `A write tool already ran in this request — send the call of "<tool>" in a separate request.` when the callback of
   a write tool already returned without an exception in the same HTTP request (one JSON-RPC batch). The executor
   marks the main request itself after such a callback, so the guard does not depend on audit resources: a write tool
   that registers no resource blocks the next write call of the request just like one that does. Without a main
   request (no HTTP request on the stack) there is no guard;
3. the callback runs; an object result is turned into the tool result array with the anzu serializer
   (`#[Serialize]` properties/getters), an array result is used as is. A `ValidationException` thrown by the callback
   answers the call with the same `Invalid arguments: <field>: <message> …` error listing every field error. Before
   the callback of a write tool runs, the main request is included in the audit log again (`McpController` excludes
   every MCP request) and it is excluded again when the call fails, unless a written resource is already recorded on
   it (`AuditLogResourceHelper::hasResourceId()`), e.g. by the failed call itself before it failed; the host tool
   registers the entity it wrote through `McpToolExecutor::addAuditLogResource()`, which appends its id to the ids
   already recorded on the main request (`AuditLogResourceHelper::addResourceByEntity()`); a tool whose call must not
   be audited (e.g. one returning a secret) calls `AuditLogResourceHelper::excludeFromAuditLogs()` in its callback, and
   its successful call stays excluded, because the guard refuses every later write call of the request before it
   could include the request again;
4. the payload is returned as a `CallToolResult` carrying it as `structuredContent` — a tool error additionally sets
   `isError: true`, so the protocol client sees a failed call instead of a successful one with an `error` key;
5. the call is logged with `params` = the serialized request (`[]` for a `null` request).

The bundle tools follow this shape: `Mcp/Model/Request/*Request` (readonly, `#[Serialize]` + `#[Assert]`) built from
the `#[Schema]` parameters, `McpLogFinder` returning a `McpLogSearchResult` / `McpLogsByContextResult` (facts only),
and `Mcp/Model/Response/*Response` owning the JSON shape, the hint and the warning texts. Log rows that are already
serialized arrays are exposed through `#[Serialize(handler: RawArrayHandler::class)]`, which passes them through the
serializer untouched (the default array handling re-indexes nested maps).

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
  `{"error": "Access denied — …"}`) from `McpToolExecutor::execute()` before the tool callback runs, and the denied
  call is written to the `mcpLogs` collection with the error (so every tool must route through the executor, as the
  bundle tools do).
* `tools/list` returns only the tools the current user may call — the filter runs after registry pagination, so a
  page may come back with fewer tools (even none) while `nextCursor` is still set; clients follow the cursor as usual.

`McpCompilerPass` resolves the name of every `mcp.tool` service method carrying `#[McpTool]` (see "Write tools" below)
and fails the container compilation with a `LogicException` when
* one tool name is registered by more than one tool (two classes or methods resolving to the same name) — the SDK
  registry keeps only the last tool registered under a name, and the read/write classification would be ambiguous;
* a `tool_permissions` key matches no registered tool (e.g. a typo) — such a mapping would grant nothing.

A tool that is not declared by `#[McpTool]` on a tagged `mcp.tool` service (e.g. one registered by an McpBundle app)
is unknown to these checks and cannot be mapped in `tool_permissions`.

### Write tools

A tool is a read tool only when its `#[McpTool]` declares `annotations: new ToolAnnotations(readOnlyHint: true)`;
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
#[McpTool(
    name: ListSitesTool::NAME,
    description: '…',
    annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false),
)]
```

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

Tools must surface both. The single convention is `McpToolExecutor::WARNINGS_KEY` (`warnings`): a `list<string>` of
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
