## [Unreleased]

### Requirements
* Requires `symfony/mcp-bundle` `^0.13` (with `mcp/sdk` `^0.8.1`); the `conflict` moved from `>=0.12` to `<0.13`. The
  host must migrate `config/packages/mcp.php` to the `servers:` shape — `app`/`version` become
  `servers.<name>.{name,version}`, `client_transports` becomes `transports`, and `discovery.scan_dirs` is replaced by
  `registry.tools` patterns (file-based discovery is gone: a tool needs the `mcp.tool` tag **and** a matching pattern).
  The new `mcp.server_name` option must name the same server key, because the bundle attaches its controller, its own
  tools and the session store to it. Session keys are now prefixed `mcp-<server_name>-`, so sessions open across the
  deploy are dropped and clients re-initialize.

### Features
* MCP pagination reports its own clamping: new `McpPageWindowResolver` + `McpPageWindow` (page capped at `PAGE_MAX` 200, limit at the caller's maximum) give host tools one place to clamp page and limit, exposing `isClamped()`, `getOffset()` and `getReachableRows()`. New `McpToolExecutor::WARNINGS_KEY` (`warnings`, a `list<string>`) is the one convention for reporting a narrowed result, for bundle and host tools alike.
* MCP date windows report their own truncation: `McpDateWindow` carries `truncated`, set by `McpDateWindowResolver` when the requested range was cut down to the 31-day cap (article window: `until` pulled back to `from + 31 days` or, with no `publishedUntil`, back from now; log window: `from` pushed forward to `until - 31 days`). `search_app_logs` and `search_audit_logs` now echo the effective `from`/`until` and a `warnings` list (always present, `[]` when nothing was shortened) reporting a shortened window or a truncated result, so a partial result can no longer be read as a complete one.
* Per-tool MCP permissions: `mcp.tool_permissions` maps every tool name to an existing host permission, resolved by `McpToolAccessChecker` through the host's standard permission model (unmapped tool = denied, super admin bypass). `McpToolExecutor::execute()` answers unauthorized `tools/call` with a tool error result logged into `mcpLogs` before running the tool callback (BC: the executor now requires `McpToolAccessChecker`), `FilterToolsListRequestHandler` hides unauthorized tools from `tools/list`.
* Log search queries (`JournalLogRepository::findLatest()`, `AuditLogRepository::findLatest()`, `findLatestByContextId()`, `McpLogRepository::findLatestByContextId()`) are bounded and sorted by the built-in `_id` index instead of the unindexed `datetime` sort; new `MongoHelper` with `minObjectIdFor()` / `maxObjectIdFor()` / `sortNewestFirst()` and `FIELD_ID` / `SORT_DESC` constants. See the "Log query bounds" section of `src/Resources/doc/mcp.md` for the slack assumptions.
* `McpRateLimiter` applies an elevated limit by role: new `mcp.rate_limiter.elevated_role` + `mcp.rate_limiter.elevated_limit` options replace the configured `limit` for users granted the role (`Security::isGranted()`, so role hierarchy applies); the limiter keys the sliding window by user id and takes the configured `limit` + `interval` and the storage directly (the `anzu_systems_common.mcp.rate_limiter_factory` service is gone).
* `McpToolExecutor::execute(string $toolName, ?object $request, Closure $callback)` validates the request object with the Symfony validator (violations answer the call with `Invalid arguments: <propertyPath>: <message> …` before the callback runs), serializes an object callback result with the anzu serializer and logs the serialized request as `params`. The bundle log tools now follow that shape: `SearchAppLogsRequest`, `SearchAuditLogsRequest`, `GetLogsByContextRequest` (`#[Serialize]` + `#[Assert]`, `limit` must be at least 1), `McpLogSearchResult` / `McpLogsByContextResult` as pure results and `McpAppLogSearchResponse`, `McpAuditLogSearchResponse`, `McpLogsByContextResponse` owning the JSON shape, hints and warning texts. New `RawArrayHandler` serializer handler passes already-serialized arrays through untouched (`#[Serialize(handler: RawArrayHandler::class)]`).

### Changes
* MCP integration follows the McpBundle 0.13 per-server container layout: `McpController` is aliased to
  `mcp.server.<server_name>.controller`, the tool-list and strict-arguments handlers take
  `mcp.server.<server_name>.registry`, and the pagination limit of the tool-list filter is read from that server's
  `pagination_limit` instead of the removed `mcp.pagination_limit` parameter. The controller builds its middleware
  through the McpBundle `MiddlewareFactory` rather than its own list — SDK 0.8 applies `ProtocolVersionMiddleware`
  to handshake-era traffic itself, and a custom list carrying it rejects every modern-era (2026-07-28) request.
* The `cache.mcp.sessions` definition is gone: `mcp.session.cache_pool` is wrapped in a `Psr16Cache` and prepended as
  the McpBundle session store of the configured server, so a custom McpBundle session pool no longer silently wins.
* MCP tool errors are reported as protocol errors: `McpToolExecutor::execute()` and `StrictToolArgumentsRequestHandler` now answer a denied, invalid or failed call with a `CallToolResult` carrying `isError: true` (the `{"error": …}` payload stays available as `structuredContent`), so a client no longer records a failed tool call as a successful one.
* The MCP rate limit is charged per JSON-RPC message instead of per HTTP request: `McpController` counts the messages of a batch body and `McpRateLimiter::checkRateLimit(int $messages = 1)` consumes that many tokens (clamped at the bucket size). A single request could otherwise carry up to `MessageFactory::DEFAULT_MAX_BATCH_SIZE` (100) tool calls for one token.
* The `warnings` entry about a shortened result now reports real truncation instead of a clamped `limit` argument: `McpLogFinder` fetches one row beyond the effective limit and `McpLogSearchResult` carries `hasMore` instead of `requestedLimit`. A `limit` above the maximum with few matches no longer warns, and a limit within the maximum with more matches now does.
* A non-empty `mcp.tool_permissions` map is required — an empty map used to hide every tool from `tools/list` and deny every `tools/call` without any error at boot; it now fails at compile time like an empty `allowed_hosts`.
* BC change: `AbstractLogRepository::FIELD_ID` (protected) moved to `MongoHelper::FIELD_ID`; `McpToolExecutor` requires `McpToolAccessChecker`, `ValidatorInterface` and `Serializer`, `execute()` takes a `?object $request` instead of a params array and returns a `CallToolResult` instead of an array (tools declaring `: array` must be retyped); `McpLogFinder` requires `McpContextIdResolver`, `findAppLogs()` / `findAuditLogs()` take the request objects and return a `McpLogSearchResult` (rows plus the resolved `McpDateWindow`, the effective limit and `hasMore`), `findLogsByContext()` replaces the three `find*ByContextId()` methods; `McpAuditLogFilter` and `McpLogSearchResult::toToolResponse()` are gone; the log tools no longer take `McpContextIdResolver`.

## [12.0.0](https://github.com/anzusystems/common-bundle/compare/11.3.0...12.0.0) (2026-07-22)

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
