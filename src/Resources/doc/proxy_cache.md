Proxy cache
============

---

Common bundle can add all proxy cache headers with specified TTLs and xKeys. It also adds system specific xKey automatically to enable purging the cache for the whole system.

--- 

### Usage

Create a class that extends `AnzuSystems\Contracts\Response\Cache\AbstractCacheSettings` and set TTL:

```php
final class ExampleCacheSettings extends AbstractCacheSettings
{
    public function __construct() {
        parent::__construct(60);
    }
}  
```

Then simply pass it to the `okResponse()` function available in controller:

```php
public function getSomeInfo(): JsonResponse
{
    return $this->okResponse($someInfo, new ExampleCacheSettings());
}
```

### Custom xKey

If you want to add a custom xKey, just override the `getXkeys()` function and add all desired xKeys:

```php
protected function getXKeys(): array
{
    return [
        'custom_xKey',
    ];
}
```

### Dynamic xKey

If you are planning to generate xKeys based on some object/entity, override the `buildXKeyFromObject()` function.
This will also provide you with a static helper function later on (see cache purging below).

```php
final class ExamplePostCacheSettings extends AbstractCacheSettings
{
    public const XKEY_PREFIX = 'post';

    public function __construct(
        private Post $post,
    ) {
        parent::__construct(60);
    }

    public static function buildXKeyFromObject(object $data): string
    {
        if ($data instanceof Post) {
            return self::XKEY_PREFIX . '-' . ((string) $data->getId());
        }

        return self::XKEY_PREFIX;
    }

    protected function getXKeys(): array
    {
        return [
            self::buildXKeyFromObject($this->post),
        ];
    }
}
```

Then use it in controller like this:

```php
public function getSomeInfoAboutPost(Post $post): JsonResponse
{
    return $this->okResponse($someInfoAboutPost, new ExamplePostCacheSettings($post));
}
```

#### Purging cache by xKey:

You can always retrieve the correct xKey (i.e. if you are planning to purge cache by xKey):

```php
// xkey for whole system: 
ExampleCacheSettings::getSystemXkey();

// xkey for a particular post:
ExamplePostCacheSettings::buildXKeyFromObject($post);
```
