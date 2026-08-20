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
2. Register `Symfony\AI\McpBundle\McpBundle` in `config/bundles.php` and configure it
   (`config/packages/mcp.php` — server name, version, instructions, `discovery.scan_dirs` for the project's own tools,
   `client_transports: { http: true }`, `http.path`, `session.store: cache`). `client_transports.http` is mandatory —
   without it McpBundle registers neither the PSR HTTP factories nor the `mcp` routing loader. The bundle prepends its
   own tool directory to `discovery.scan_dirs` and provides the `cache.mcp.sessions` service, so only project-specific
   values belong here. Two discovery caveats: once any `scan_dirs` value exists (including the prepended one), the
   McpBundle default `['src']` no longer applies — always list the project tool directories explicitly; and the
   prepended vendor path assumes the default composer `vendor` directory relative to the project root.
3. Enable the section (requires the `logs` section to be enabled; `allowed_hosts` must be non-empty — an empty list
   would reject every request with 403):
   ```yaml
   anzu_systems_common:
       mcp:
           enabled: true
           allowed_hosts: '%env(csv:ANZU_MCP_ALLOWED_HOSTS)%'
           tool_error_exceptions:
               App\Exception\SomeBackendException: 'Backend is temporarily unavailable, retry the call.'
           rate_limiter:
               limit: 120
               interval: '1 minute'
               cache_pool: 'some_redis.cache'
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
   The `session.cache_pool` option takes effect only while the McpBundle `http.session` config keeps its default
   `cache_pool: cache.mcp.sessions` — setting a custom pool there makes this option a silent no-op.
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

* `McpController` (alias `mcp.server.controller`) — streamable HTTP transport endpoint with DNS-rebinding protection
  (`allowed_hosts`) and a sliding-window rate limit (per user, or per caller when the security token carries the
  rate-limit attributes — see below).
* `McpToolExecutor` — wraps tool callbacks: converts `McpToolInputException`, `AccessDeniedException` and configured
  `tool_error_exceptions` into tool error results, logs every call to the monolog `mcp` channel and to the `mcpLogs`
  capped collection.
* `StrictToolArgumentsRequestHandler` — rejects tool calls with unknown arguments.
* `McpToolAccessChecker` + `FilterToolsListRequestHandler` — per-tool permissions mapped to host permissions (see
  below); the call-side check lives in `McpToolExecutor`.
* `SearchAppLogsTool`, `SearchAuditLogsTool`, `GetLogsByContextTool` — diagnostic tools over the shared log
  collections, correlated by `contextId`.

## Rate limit override per caller

`McpRateLimiter` keys the sliding window by the current user id and applies the configured `rate_limiter.limit`.
An authenticator can override both by setting attributes on the security token:

```php
$token->setAttribute(McpRateLimiter::TOKEN_ATTRIBUTE_KEY, 'pat_' . $personalAccessToken->getId());
$token->setAttribute(McpRateLimiter::TOKEN_ATTRIBUTE_LIMIT, $personalAccessToken->getRateLimit());
```

`TOKEN_ATTRIBUTE_KEY` (string) selects the bucket (e.g. one bucket per personal access token), `TOKEN_ATTRIBUTE_LIMIT`
(positive `int`) replaces the configured limit for that bucket — `null`, a missing attribute or a non-positive value
keeps the configured default. Tokens without the attributes fall back to the per-user bucket. The configured
`rate_limiter.interval` applies to every bucket. The personal access token authenticator from
[anzusystems/auth-bundle](https://github.com/anzusystems/auth-bundle) sets both attributes.

## Tool permissions

Every tool is mapped to an existing permission of the host application in `mcp.tool_permissions` (tool name →
permission name); `McpToolAccessChecker` resolves it through the host's standard permission model
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

* `tools/call` of a tool the current user may not use returns a tool error result (`{"error": "Access denied — …"}`) from
  `McpToolExecutor::execute()` before the tool callback runs, and the denied call is written to the `mcpLogs` collection
  with the error (so every tool must route through the executor, as the bundle tools do).
* `tools/list` returns only the tools the current user may call — the filter runs after registry pagination, so a
  page may come back with fewer tools (even none) while `nextCursor` is still set; clients follow the cursor as usual.

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
