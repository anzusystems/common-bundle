Health Check
============

---

[Health check service](https://github.com/anzusystems/common-bundle/blob/main/src/HealthCheck/HealthChecker.php) uses set of modules against each is performed health check. If one of the modules is unhealthy, the summarized result will be unhealthy. 

---

### Configuration

You can specify common modules by configuration, by default all listed modules bellow are used. If you want to use only some of them, specify modules option and list modules which you want to use:
```yaml
anzu_systems_common:
    health_check:
        modules:
            - AnzuSystems\CommonBundle\HealthCheck\Module\OpCacheModule
            - AnzuSystems\CommonBundle\HealthCheck\Module\ForwardIpModule
            - AnzuSystems\CommonBundle\HealthCheck\Module\MysqlModule
            - AnzuSystems\CommonBundle\HealthCheck\Module\MongoModule
            - AnzuSystems\CommonBundle\HealthCheck\Module\RedisModule
            - AnzuSystems\CommonBundle\HealthCheck\Module\DataMountModule
```

#### Configure MysqlModule

MySQL module is making simple select as `SELECT 1 FROM table LIMIT 1`. By default, the table `_doctrine_migration_versions` is used, if you need to change it, configure it:
```yaml
anzu_systems_common:
    health_check:
        mysql_table_name: _DoctrineMigrationVersions
```
#### Configure MongoModule

Mongo module is making ping to defined collection, by default are used collections `anzu_mongo_journal_log_collection` and `anzu_mongo_audit_log_collection`, if you want to change it, you can configure it:

```yaml
anzu_systems_common:
    health_check:
        mongo_collections: [anzu_mongo_journal_log_collection, anzu_mongo_audit_log_collection, anzu_mongo_app_something_specific_collection]  
```

#### Register your own health check module

Just implement [ModuleInterface](https://github.com/anzusystems/common-bundle/blob/main/src/HealthCheck/Module/ModuleInterface.php). If your application is using autoconfiguration, it will autoconfigure your service with tag `anzu_systems_common.health_check.module` and HealthChecker will use your own module. In case you are not using autoconfiguration, tag your service on your own.

### Register routing

Common bundle provides [HealthCheckController](https://github.com/anzusystems/common-bundle/blob/main/src/Controller/HealthCheckController.php). **It's on you to register routes as you wish.**

Create route configuration in `config/routes/health_check.yaml`, for example:
```yaml
anzu_systems_common.health_check:
    path: /api/sys/v1/health
    methods: GET
    controller: AnzuSystems\CommonBundle\Controller\HealthCheckController::healthCheck
```

---
