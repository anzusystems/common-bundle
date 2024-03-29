AnzuSystems Common Bundle by Petit Press a.s. (www.sme.sk)
=====

Provides common functionality among Anzusystems' projects.

---

## Installation

From within container execute the following command to download the latest version of the bundle:
```console
$ composer require anzusystems/common-bundle --no-scripts
```

### Step 3: Use the Bundle

Change your `src\Kernel.php` to extend `AnzuSystems\CommonBundle\Kernel\AnzuKernel`.

`AnzuKernel` requires for constructor some extra parameters:
```php
public function __construct(
    private string $appSystem, // Specific application/system name, eg. "core".
    private string $appVersion, // Specific application/system version, eg. "1.0.0".
    private bool $appReadOnlyMode, // Boolean if application/system should run in read only mode.
    string $environment,
    bool $debug,
) {
    parent::__construct(
        environment: $environment,
        debug: $debug
    );
}
```
It means, you have to initialize `AnzuKernel` on all entry points.
* `public/index.php` should look like this:

```php
<?php

declare(strict_types=1);

use App\Kernel;
use AnzuSystems\CommonBundle\Kernel\AnzuKernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context): Kernel {
    return new Kernel(
        appNamespace: $context['APP_NAMESPACE'],
        appSystem: $context['APP_SYSTEM'],
        appVersion: $context['APP_VERSION'],
        appReadOnlyMode: (bool) $context['APP_READ_ONLY_MODE'],
        environment: $context['APP_ENV'],
        debug: (bool) $context['APP_DEBUG'],
    );
};
```
* `bin/console` should look like this:
```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

if (!is_file(dirname(__DIR__).'/vendor/autoload_runtime.php')) {
    throw new LogicException('Symfony Runtime is missing. Try running "composer require symfony/runtime".');
}

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context): Application {
    $kernel = new Kernel(
        appNamespace: $context['APP_NAMESPACE'],
        appSystem: $context['APP_SYSTEN'],
        appVersion: $context['APP_VERSION'],
        appReadOnlyMode: (bool) $context['APP_READ_ONLY_MODE'],
        environment: $context['APP_ENV'],
        debug: (bool) $context['APP_DEBUG'],
    );

    return new Application($kernel);
};
```

# Configuration

You must define config in `config/packages/anzu_common.yaml`. Here is a fully listed config:

```yaml
anzu_common:
    settings:
        # Service id of your application Redis
        app_redis: TestRedis
        # Boolean flag for enabling/disabling proxy cache headers.
        app_cache_proxy_enabled: true
        # Set FCQN to your User entity class
        user_entity_class: App\Entity\User
        # Namespace of your application entity classes
        app_entity_namespace: App\Entity
        # Namespace of your application value objects.
        app_value_object_namespace: App\Model\ValueObject
        # FCQN of your commands which won't be locked against concurrency. Defaults to command listed bellow.
        unlocked_commands:
            - Symfony\Bundle\FrameworkBundle\Command\AssetsInstallCommand
            - Symfony\Bundle\FrameworkBundle\Command\CacheWarmupCommand
            - Symfony\Component\Messenger\Command\ConsumeMessagesCommand
    health_check:
        enabled: true
        # Table name against which is performed health check.
        mysql_table_name: _doctrine_migration_versions
        mongo_collections: [anzu_mongo_app_log_collection, anzu_mongo_audit_log_collection]
        # Modules used for health check. Defaults to modules listed bellow.
        # Here you can only define some of these modules.
        # For none use modules: []
        modules:
            - AnzuSystems\CommonBundle\HealthCheck\Module\OpCacheModule
            - AnzuSystems\CommonBundle\HealthCheck\Module\ForwardIpModule
            - AnzuSystems\CommonBundle\HealthCheck\Module\MysqlModule
            - AnzuSystems\CommonBundle\HealthCheck\Module\MongoModule
            - AnzuSystems\CommonBundle\HealthCheck\Module\RedisModule
            - AnzuSystems\CommonBundle\HealthCheck\Module\DataMountModule
    errors:
        enabled: true
        # Default empty. You can define regexes on which is error handling enabled.
        only_uri_match:
          - ^/api/
        # Default exception handler service id. You can change to your own service id, it's required.
        default_exception_handler: AnzuSystems\CommonBundle\Exception\Handler\DefaultExceptionHandler
        # Exception Handlers used in `AnzuSystems\CommonBundle\Event\Listener\ExceptionListener`.
        # Here you can only define some of these handlers.
        # For none use exception_handlers: []
        exception_handlers:
            - AnzuSystems\CommonBundle\Exception\Handler\NotFoundExceptionHandler
            - AnzuSystems\CommonBundle\Exception\Handler\ValidationExceptionHandler
            - AnzuSystems\CommonBundle\Exception\Handler\AppReadOnlyModeExceptionHandler
            - AnzuSystems\CommonBundle\Exception\Handler\AccessDeniedExceptionHandler
    logs:
        enabled: true
        # Logs are sent through Symfony Messenger.
        messenger_transport:
            # Name of your messenger transport.
            name: 'core_log'
            # Messenger transport DSN
            dsn: '%env(MESSENGER_TRANSPORT_DSN)%?topic[name]=core_log'
        # Application log section
        app:
            # Mongo connection definition
            mongo:
                uri: '%env(ANZU_MONGODB_APP_LOG_URI)%'
                username: '%env(ANZU_MONGODB_APP_LOG_USERNAME)%'
                password: '%env(ANZU_MONGODB_APP_LOG_PASSWORD)%'
                database: '%env(ANZU_MONGODB_APP_LOG_DB)%'
                ssl: '%env(bool:ANZU_MONGODB_APP_LOG_SSL)%'
                collection: appLogs
        # Audit log section
        audit:
            # Mongo connection definition
            mongo:
                uri: '%env(ANZU_MONGODB_AUDIT_LOG_URI)%'
                username: '%env(ANZU_MONGODB_AUDIT_LOG_USERNAME)%'
                password: '%env(ANZU_MONGODB_AUDIT_LOG_PASSWORD)%'
                database: '%env(ANZU_MONGODB_AUDIT_LOG_DB)%'
                ssl: '%env(bool:ANZU_MONGODB_AUDIT_LOG_SSL)%'
                collection: auditLogs
            logged_methods: ['POST', 'PUT', 'PATCH', 'DELETE']
    permissions:
        # List of roles that can be assigned to user.
        roles: [ROLE_USER, ROLE_ADMIN]
        # Default list of grants that can be assigned to all permissions.
        default_grants: [2, 0]
        config:
            # List of arbitrary subjects of permission.
            app_article:
                # List of subject's actions with default grants:
                create:
                update:
                # Action with non-default grants for permission app_article_delete:
                delete:
                    grants: [0, 1, 2]
            app_post:
                create:
        translation:
            subjects:
                # Translation of all unique subjects defined in config above.
                app_article:
                    # Feel free to add more languages.
                    en: Article
                    sk: Článok
                app_post:
                  en: Post
                  sk: Príspevok
            actions:
                # Translation of all unique actions in all subjects defined in config above.
                create:
                  en: Create
                  sk: Vytvor
                update:
                  en: Update
                  sk: Uprav
                delete:
                  en: Delete
                  sk: Zmaž
            roles:
                # Translation of all roles provided in configuration above.
                ROLE_USER:
                    en: User
                    sk: Užívateľ
                ROLE_ADMIN:
                    en: Admin
                    sk: Administrátor
```
# Setup permissions and their management
See [Permission management](src/Resources/doc/permissions.md)
# Documentation

Besides AnzuSystems' own
[serializer-bundle](https://github.com/anzusystems/serializer-bundle) and
[contracts](https://github.com/anzusystems/contracts),
common-bundle provides many functionalities, you can read about them in following categories:

* [Argument Resolvers](Resources/doc/argument_resolvers.md)
* [Debug](src/Resources/doc/debug.md)
* [Exception Handlers](src/Resources/doc/exception_handlers.md)
* [Fixtures](src/Resources/doc/fixtures.md)
* [Health Check](src/Resources/doc/health_check.md)
* [Helpers](src/Resources/doc/helpers.md)
* [Locks](src/Resources/doc/locks.md)
* [Logs](src/Resources/doc/logs.md)
* [Param Converters (deprecated)](src/Resources/doc/param_converters.md)
* [Proxy Cache](src/Resources/doc/proxy_cache.md)
* [Tests](src/Resources/doc/tests.md)
* [Traits](src/Resources/doc/traits.md)
* [Value Objects](src/Resources/doc/value_objects.md)
* [Permissions](src/Resources/doc/permissions.md)

# Troubleshooting

Some packages as Google SDK requires environment variables to be defined globally.
Allow usage of putenv in your `composer.json`
```json
"extra": {
    "runtime": {
        "use_putenv": true
    }
}
```
