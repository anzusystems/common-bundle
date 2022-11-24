Debug
============

---

For better debugging your application state on deployment environments, you can register routes for [DebugController](https://github.com/anzusystems/common-bundle/blob/main/src/Controller/DebugController.php).

It provides some basic actions:

#### Lead Time
```php
public function getLeadTime(): JsonResponse;
```

#### OpCache status

* requires ROLE_ADMIN

```php
public function opcacheStatus(): JsonResponse;
```

#### IP check

* requires ROLE_ADMIN

```php
public function ipCheck(Request $request): JsonResponse;
```

#### Error

* requires ROLE_ADMIN

```php
public function error(Request $request): JsonResponse;
```

---

### Register routing

Create route configuration in `config/routes/debug.yaml`, for example:
```yaml
anzu_systems_common.debug.opcache_prv:
    path: /api/prv/v1/debug/opcache
    methods: GET
    controller: AnzuSystems\CommonBundle\Controller\DebugController::opcacheStatus

anzu_systems_common.debug.ip_prv:
    path: /api/prv/v1/debug/ip
    methods: GET
    controller: AnzuSystems\CommonBundle\Controller\DebugController::ipCheck

anzu_systems_common.debug.error_prv:
    path: /api/prv/v1/debug/error
    methods: GET
    controller: AnzuSystems\CommonBundle\Controller\DebugController::error

anzu_systems_common.debug.lead_time_prv:
    path: /api/prv/v1/debug/lead-time
    methods: GET
    controller: AnzuSystems\CommonBundle\Controller\DebugController::getLeadTime

anzu_systems_common.debug.lead_time_pub:
    path: /api/pub/v1/debug/lead-time
    methods: GET
    controller: AnzuSystems\CommonBundle\Controller\DebugController::getLeadTime
```
