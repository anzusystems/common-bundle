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
  (`allowed_hosts`) and a per-user sliding-window rate limit.
* `McpToolExecutor` — wraps tool callbacks: converts `McpToolInputException`, `AccessDeniedException` and configured
  `tool_error_exceptions` into tool error results, logs every call to the monolog `mcp` channel and to the `mcpLogs`
  capped collection.
* `StrictToolArgumentsRequestHandler` — rejects tool calls with unknown arguments.
* `SearchAppLogsTool`, `SearchAuditLogsTool`, `GetLogsByContextTool` — diagnostic tools over the shared log
  collections, correlated by `contextId`.
