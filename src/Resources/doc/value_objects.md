Value Objects
============

---
In some cases, where [Enums](https://github.com/anzusystems/contracts/blob/main/src/Resources/doc/enums.md) are not suitable, ValueObjects are used. 
All value objects should extend [AbstractValueObject](https://github.com/anzusystems/contracts/blob/main/src/Model/ValueObject/AbstractValueObject.php) and override two constants:
```php
public const DEFAULT_VALUE = '';
public const AVAILABLE_VALUES = [];
```

Here is the example of implementation for user state:

### 1. Create a Value Object

`App\Model\ValueObject\UserState`:
```php
<?php

declare(strict_types=1);

namespace App\Model\ValueObject;

use AnzuSystems\Contracts\Model\ValueObject\AbstractValueObject;

final class UserState extends AbstractValueObject
{
    public const ACTIVE = 'active';
    public const GDPR_DELETED = 'gdpr_deleted';
    public const BANNED = 'banned';

    public const AVAILABLE_VALUES = [
        self::ACTIVE,
        self::GDPR_DELETED,
        self::BANNED,
    ];
    public const DEFAULT_VALUE = self::ACTIVE;
}
```

### 2. Create a Doctrine type for the Value Object

`App\Model\ValueObject\UserState`:
```php
<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use App\Model\ValueObject\UserState;
use Doctrine\DBAL\Platforms\AbstractPlatform;

final class UserStateType extends AbstractValueObjectType
{
    public function convertToPHPValue($value, AbstractPlatform $platform): UserState
    {
        return new UserState((string) $value);
    }
}
```

### 3. Register the Doctrine type in Doctrine config

`config/packages/doctrine.yaml`:
```yaml
doctrine:
    dbal:
        types:
            UserStateType: App\Doctrine\Type\UserStateType
```

### 4. Use the Value Object in your entity

`App\Entity\User`:

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use AnzuSystems\CommonBundle\Entity\AnzuUser;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User extends AnzuUser
{
    #[ORM\Column(type: 'UserStateType')]
    private UserState $state;
}
```
