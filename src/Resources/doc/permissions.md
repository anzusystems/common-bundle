Permissions
============

---

Permission management

### Provide permission configuration/translation for admin client. 
```yaml
# config/routes/permissions.yaml
anzu_systems_common.permissions.config:
  path: /api/adm/v1/permissions/config
  methods: GET
  controller: AnzuSystems\CommonBundle\Controller\PermissionController::getConfig

```
See [README](../../../README.md) for permission configuration example.

### Provide user permission create/update API.
#### Override permissionGroup property in your User entity to get relations to PermissionGroup:
```php
    #[ORM\ManyToMany(targetEntity: PermissionGroup::class, inversedBy: 'users', fetch: 'EXTRA_LAZY', indexBy: 'id')]
    #[ORM\JoinTable]
    #[Serialize(handler: EntityIdHandler::class, type: PermissionGroup::class)]
    protected Collection $permissionGroups;

```
#### Use provided `AbstractUserManager`:
```php
use AnzuSystems\CommonBundle\Domain\User\AbstractUserManager;

class UserManager extends AbstractUserManager
```

#### Create controller actions:
```php
use AnzuSystems\Contracts\Model\User\UserDto;
use AnzuSystems\SerializerBundle\Attributes\SerializeParam;
use App\Entity\User;

    #[Route('', 'create', methods: [Request::METHOD_POST])]
    public function create(#[SerializeParam] UserDto $userDto): JsonResponse
    {
        return $this->createdResponse(
            $this->userManager->createAnzuUser($userDto)
        );
    }

    #[Route('/{user}', 'update', ['user' => '\d+'], methods: [Request::METHOD_PUT])]
    public function update(User $user, #[SerializeParam] UserDto $userDto): JsonResponse
    {
        return $this->okResponse(
            $this->userManager->updateAnzuUser($user, $userDto)
        );
    }
```

### Provide PermissionGroup management APIs.
#### Create PermissionGroup entity:
```php
use AnzuSystems\Contracts\Entity\AnzuPermissionGroup;

#[ORM\Entity(repositoryClass: PermissionGroupRepository::class)]
class PermissionGroup extends AnzuPermissionGroup
{
    use UserTrackingTrait;
}
```
#### Create PermissionGroup repository:
```php
use use AnzuSystems\CommonBundle\Repository\AbstractAnzuRepository;

final class PermissionGroupRepository extends AbstractAnzuRepository
{
    protected function getEntityClass(): string
    {
        return PermissionGroup::class;
    }
}
```

#### Create controller:
```php
use AnzuSystems\CommonBundle\Domain\PermissionGroup\PermissionGroupFacade;
use AnzuSystems\CommonBundle\Controller\AbstractAnzuApiController;

final class PermissionGroupController extends AbstractAnzuApiController 
{
    #[Route('/permission-group/{permissionGroup}', 'get_one', ['permissionGroup' => '\d+'], methods: [Request::METHOD_GET])]
    public function getOne(PermissionGroup $permissionGroup): JsonResponse
    {
        return $this->okResponse($permissionGroup);
    }
    
    #[Route('/permission-group', 'get_list', methods: [Request::METHOD_GET])]
    public function getList(ApiParams $apiParams): JsonResponse
    {
        return $this->okResponse(
            $this->permissionGroupRepo->findByApiParams($apiParams),
        );
    }
    
    #[Route('/permission-group', 'create', methods: [Request::METHOD_POST])]
    public function create(#[SerializeParam] PermissionGroup $permissionGroup): JsonResponse
    {
        return $this->createdResponse(
            $this->permissionGroupFacade->create($permissionGroup)
        );
    }
    
    #[Route('/permission-group/{permissionGroup}', 'update', ['permissionGroup' => '\d+'], methods: [Request::METHOD_PUT])]
    public function update(PermissionGroup $permissionGroup, #[SerializeParam] PermissionGroup $newPermissionGroup): JsonResponse
    {
        return $this->okResponse(
            $this->permissionGroupFacade->update($permissionGroup, $newPermissionGroup)
        );
    }
    
    #[Route('/permission-group/{permissionGroup}', 'delete', ['permissionGroup' => '\d+'], methods: [Request::METHOD_DELETE])]
    public function delete(PermissionGroup $permissionGroup): JsonResponse
    {
        $this->permissionGroupFacade->delete($permissionGroup);
    
        return $this->noContentResponse();
    }
}
```
