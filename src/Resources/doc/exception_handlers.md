Exception Handlers
============

---

If your application provides mainly api endpoints, you need to handle exceptions and transform them into json response. For this purpose, you should use common exception handlers provided by Common Bundle.

Each exception handler must implement [ExceptionHandlerInterface](https://github.com/anzusystems/common-bundle/blob/main/src/Exception/Handler/ExceptionHandlerInterface.php).

Exceptions are then handled by [ExceptionListener](https://github.com/anzusystems/common-bundle/blob/main/src/Event/Listener/ExceptionListener.php) which listens on `kernel.exception` event.

---

### Configuration

#### Define error handling only for defined regexes

By default, error handling is enabled on all uri paths. You can change it by defining your own regexes for which should be the feature enabled:
```yaml
anzu_systems_common:
    errors:
        only_uri_match:
            - ^/api/
```

#### Default exception handler

Default exception handler is used as fallback exception handler, by default is used [DefaultExceptionHandler](https://github.com/anzusystems/common-bundle/blob/main/src/Exception/Handler/DefaultExceptionHandler.php). You can change it in config:

```yaml
anzu_systems_common:
    errors:
        default_exception_handler: App\Exception\Handler\YourDefaultExceptionHandler
```

#### Set of exception handlers

By default, bellow listed exception handlers are used. If you want to use only some of them, specify `exception_handlers` option and list handlers which you want to use: 

```yaml
anzu_systems_common:
    errors:
        exception_handlers:
            - AnzuSystems\CommonBundle\Exception\Handler\NotFoundExceptionHandler
            - AnzuSystems\CommonBundle\Exception\Handler\ValidationExceptionHandler
            - AnzuSystems\CommonBundle\Exception\Handler\AppReadOnlyModeExceptionHandler
            - AnzuSystems\CommonBundle\Exception\Handler\AccessDeniedExceptionHandler
            - AnzuSystems\CommonBundle\Serializer\Exception\SerializerExceptionHandler
```

#### Register your own exception handler

To register your own handler, just implement [ExceptionHandlerInterface](https://github.com/anzusystems/common-bundle/blob/main/src/Exception/Handler/ExceptionHandlerInterface.php). If your application is using autoconfiguration, it will autoconfigure your service with tag `anzu_systems_common.logs.exception_handler` and `ExceptionListener` will use your own handler. In case you are not using autoconfiguration, tag your service on your own.
