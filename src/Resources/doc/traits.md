Traits
============

---

Common bundle provides some useful traits

### Autowiring helper traits

These traits are useful for common service injection to prevent creating an abstract depending on these services (composition over inheritance). [Read more about Setter Injection](https://symfony.com/doc/current/service_container/injection_types.html#setter-injection) and use it wisely.

* [EntityManagerAwareTrait](https://github.com/anzusystems/common-bundle/blob/main/src/Traits/EntityManagerAwareTrait.php)
* [ResourceLockerAwareTrait](https://github.com/anzusystems/common-bundle/blob/main/src/Traits/ResourceLockerAwareTrait.php)
* [SerializerAwareTrait](https://github.com/anzusystems/common-bundle/blob/main/src/Traits/SerializerAwareTrait.php)

### Application logic helper traits

Traits which helps handle common application logic inside different type of services.

* [LoggerAwareRequest](https://github.com/anzusystems/common-bundle/blob/main/src/Traits/LoggerAwareRequest.php)
  * Use it if you have enabled anzu [logs](logs.md) functionality and want to log http requests. As a result of `loggedRequest` method call, you will retrieve [HttpClientResponse](https://github.com/anzusystems/common-bundle/blob/main/src/Model/HttpClient/HttpClientResponse.php). 
