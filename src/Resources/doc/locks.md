Locks
============

---

### Command locks

By default, all symfony commands using locks to prevent concurrency.
Locks are handled by [CommandLockSubscriber](https://github.com/anzusystems/common-bundle/blob/main/src/Event/Subscriber/CommandLockSubscriber.php).

You can specify your unlocked commands by configuration, by default `AssetsInstallCommand`, `CacheWarmupCommand` and `ConsumeMessagesCommand` are unlocked:
```yaml
anzu_systems_common:
    settings:
        unlocked_commands: 
            - Symfony\Bundle\FrameworkBundle\Command\AssetsInstallCommand
            - Symfony\Bundle\FrameworkBundle\Command\CacheWarmupCommand
            - Symfony\Component\Messenger\Command\ConsumeMessagesCommand
            - App\Command\YourAdditionalUnlockedCommand
```

A lock key is created from command name and command arguments.

A locked command is released on `console.error` or `console.terminate` event.

---

### Resource locker

If you need to acquire atomic lock for some entity or api call, you can use [ResourceLocker](https://github.com/anzusystems/common-bundle/blob/main/src/Util/ResourceLocker.php) utitlity.

It composes three usable public methods:
```php
public function lock(BaseIdentifiableInterface | string $resource, bool $blocking = true): bool;
public function unLock(BaseIdentifiableInterface | string $resource): void;
public function unlockAll(): void;
```

For example, you need to lock the user entity to prevent concurrency:
```php
$this->resourceLocker->lock($user);
$user->doSomeAction();
$this->resourceLocker->unlock($user);
```
In this case, if something calls this process, it will perform the action immediately only if there is no other project doing the same action at the same time. 
If there is concurrency, it will wait until the first performer unlock the resource, and then it will do the action. 

In case you don't want to wait and rather cancel the action, you can use set `blocking` parameter to `false` which will end up by throwing `LogicException`.

[AbstractAnzuApiController](https://github.com/anzusystems/common-bundle/blob/main/src/Controller/AbstractAnzuApiController.php) provides protected method `lockApi(bool $blocking = false): void;` for ability to lock whole api endpoint. It uses route name as a lock key.
Use it for example as this:
```php
public function someControllerAction(): Response
{
    $this->lockApi();
    
    return $this->noContentResponse();
}
```

A lock key is created from the primary identifier `$object->getId()` or by the string you can provide instead of referencing `BaseIdentifiableInterface` object.

All locked resource are released on `kernel.terminate` by [LockReleaseListener](https://github.com/anzusystems/common-bundle/blob/main/src/Event/Listener/LockReleaseListener.php).
