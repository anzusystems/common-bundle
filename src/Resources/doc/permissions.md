Traits
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

### Provide user permission update API.
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

#### Create controller action:
```php
use AnzuSystems\CommonBundle\Model\Permission\PermissionUserUpdateDto;
use AnzuSystems\SerializerBundle\Request\ParamConverter\SerializerParamConverter;
use App\Entity\User;

#[Route('/permissions/{user}', 'update_permissions', ['user' => '\d+'], methods: [Request::METHOD_PATCH])]
#[ParamConverter('permissionUserUpdateDto', converter: SerializerParamConverter::class)]
public function updatePermissions(User $user, PermissionUserUpdateDto $permissionUserUpdateDto): JsonResponse
{
    return $this->okResponse(
        $this->userManager->updatePermissions($user, $permissionUserUpdateDto)
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
    #[ParamConverter('apiParams', converter: ApiFilterParamConverter::class)]
    public function getList(ApiParams $apiParams): JsonResponse
    {
        return $this->okResponse(
            $this->permissionGroupRepo->findByApiParams($apiParams),
        );
    }
    
    #[Route('/permission-group', 'create', methods: [Request::METHOD_POST])]
    #[ParamConverter('permissionGroup', converter: SerializerParamConverter::class)]
    public function create(PermissionGroup $permissionGroup): JsonResponse
    {
        return $this->createdResponse(
            $this->permissionGroupFacade->create($permissionGroup)
        );
    }
    
    #[Route('/permission-group/{permissionGroup}', 'update', ['permissionGroup' => '\d+'], methods: [Request::METHOD_PUT])]
    #[ParamConverter('newPermissionGroup', converter: SerializerParamConverter::class)]
    public function update(PermissionGroup $permissionGroup, PermissionGroup $newPermissionGroup): JsonResponse
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
