Logs
============

---

Anzu projects are using MongoDB to store logs.

---

### Application logs

Common Bundle registers app channel for Monolog logger. Each time you use monolog as `LoggerInterface $appLogger` or just `LoggerInterface $logger`, logs are stored to default app logger.

Each exception is automatically logged by [ExceptionListener](https://github.com/anzusystems/common-bundle/blob/main/src/Event/Listener/ExceptionListener.php).
It's possible to ignore logging of defined exception classes by configuration. For example, if you don't want to log 404 errors, use this config:
```yaml
anzu_systems_common:
    logs:
        app:
            ignored_exceptions:
                - Symfony\Component\HttpKernel\Exception\NotFoundHttpException
```

You can use [LoggerAwareRequest](https://github.com/anzusystems/common-bundle/blob/main/src/Traits/LoggerAwareRequest.php) which logs each external http request send through `loggedRequest` method.

### Journal logs

Common Bundle registers journal channel for Monolog logger. Each time you use monolog as `LoggerInterface $journalLogger`, logs are stored in Mongo collection `appLogs`.

You must your connection to Mongo for journal logs:
```yaml
anzu_systems_common:
    logs:
        journal:
            mongo:
                uri: '%env(ANZU_MONGODB_APP_LOG_URI)%'
                username: '%env(ANZU_MONGODB_APP_LOG_USERNAME)%'
                password: '%env(ANZU_MONGODB_APP_LOG_PASSWORD)%'
                database: '%env(ANZU_MONGODB_APP_LOG_DB)%'
                ssl: '%env(bool:ANZU_MONGODB_APP_LOG_SSL)%'
                collection: appLogs
```

You must also register [ContextIdentityMiddleware](https://github.com/anzusystems/common-bundle/blob/main/src/Messenger/Middleware/ContextIdentityMiddleware.php) in your messenger configuration (`config/packages/messenger.yaml`):
```yaml
framework:
    messenger:
        buses:
            messenger.bus.default:
                default_middleware: true
                middleware:
                    - AnzuSystems\CommonBundle\Messenger\Middleware\ContextIdentityMiddleware
```

---

### Audit logs

Common Bundle also registers audit channel for Monolog. Can be injected as `LoggerInterface $auditLogger`.

You must configure your connection to Mongo for audit logs:

```yaml
anzu_systems_common:
    logs:
        audit:
            mongo:
                uri: '%env(ANZU_MONGODB_AUDIT_LOG_URI)%'
                username: '%env(ANZU_MONGODB_AUDIT_LOG_USERNAME)%'
                password: '%env(ANZU_MONGODB_AUDIT_LOG_PASSWORD)%'
                database: '%env(ANZU_MONGODB_AUDIT_LOG_DB)%'
                ssl: '%env(bool:ANZU_MONGODB_AUDIT_LOG_SSL)%'
                collection: auditLogs
```

Each **POST**, **PUT**, **PATCH** and **DELETE** requests are automatically logged by [AuditLogSubscriber](https://github.com/anzusystems/common-bundle/blob/main/src/Event/Subscriber/AuditLogSubscriber.php). If you want to change logged methods, use configuration:
```yaml
anzu_systems_common:
    logs:
        audit:
            logged_methods: ['POST', 'PUT', 'PATCH', 'DELETE']
```

Don't forget to create supervisor config for consumer in `docker/{project}/local/etc/supervisor/conf.d/` and `docker/{project}/prod/etc/supervisor/conf.d/`.

---

### Configure Messenger transport

App and audit logs are logged asynchronously through Symfony Messenger. You should configure the transport by this example:

```yaml
anzu_systems_common:
    logs:
        messenger_transport:
            name: 'core_log'
            dsn: '%env(MESSENGER_TRANSPORT_DSN)%?topic[name]=core_log'
```

---

### Register routing for Application and Audit logs

You should register routes for [LogController](https://github.com/anzusystems/common-bundle/blob/main/src/Controller/LogController.php), it's used by Anzu Admin to list both types of logs.

Create route configuration in `config/routes/logs.yaml`
```yaml
anzu_systems_common.logs.app_list:
    path: /api/adm/v1/log/journal
    methods: GET
    controller: AnzuSystems\CommonBundle\Controller\LogController::getJournalLogs

anzu_systems_common.logs.journal_get_one:
    path: /api/adm/v1/log/journal/{id}
    methods: GET
    controller: AnzuSystems\CommonBundle\Controller\LogController::getOneJournalLog

anzu_systems_common.logs.audit_list:
    path: /api/adm/v1/log/audit
    methods: GET
    controller: AnzuSystems\CommonBundle\Controller\LogController::getAuditLogs

anzu_systems_common.logs.audit_get_one:
    path: /api/adm/v1/log/audit/{id}
    methods: GET
    controller: AnzuSystems\CommonBundle\Controller\LogController::getOneAuditLog

# optional, if you need to log also custom logs via API endpoint, i.e. admin frontend errors.
anzu_systems_common.logs.create:
  path: /api/adm/v1/log
  methods: POST
  controller: AnzuSystems\CommonBundle\Controller\LogController::create

```
