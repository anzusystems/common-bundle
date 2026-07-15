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
