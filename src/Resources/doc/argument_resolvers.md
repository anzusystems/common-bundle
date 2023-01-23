Argument Resolvers
============

---

### [ValueObjectValueResolver](https://github.com/anzusystems/common-bundle/blob/main/src/Request/ValueResolver/ValueObjectValueResolver.php)

Converts value object value into value object. Example in controller action:
```php
#[Route('/user/{user}/{newState}', methods: ['PATCH'])]
public function state(User $user, UserState $newState): JsonResponse;
```

---

### [ArrayStringValueResolver](https://github.com/anzusystems/common-bundle/blob/main/src/Request/ValueResolver/ArrayStringValueResolver.php)

Converts string separated by string value into value object. Example in controller action:
```php
#[Route('/user/{user}/{ids}', methods: ['GET'])]
public function getByUserAndIds(User $user, #[ArrayStringParam] array $ids): JsonResponse;
```

---

### [ApiFilterValueResolver](https://github.com/anzusystems/common-bundle/blob/main/src/Request/ValueResolver/ApiFilterValueResolver.php)

Converts common get parameters used for listing into [ApiParams](https://github.com/anzusystems/common-bundle/blob/main/src/ApiFilter/ApiParams.php) object.

```php
#[Route('', methods: ['GET'])]
public function getList(ApiParams $apiParams): JsonResponse;
```


### Available params:

| Name | Info | Default | Example GET param |
| ---- | ---- | --- | ---- |
| limit | limit the record list | 20 | limit=50 |
| offset | offset of the record list | 0 | offset=10 |
| filter | filters | [] | listed bellow in table [Available filters](#available-filters) |
| order | order the record list | [] | order[id]=desc |
| bigTable | use if table is big and don't want use total count which is slow | 1 (true) | bigTable=1 |

### Available filters:

| Name | Info | Example GET param | 
| ---- | ---- | --- |
|  lt    |   lower than | filter_lt[createdAt]=2021-11-17T23:00:00 |
|  lte    |   lower or equal than | filter_lte[createdAt]=2021-11-17T23:00:00 |
|  gt    |   greater than | filter_gt[createdAt]=2021-11-17T23:00:00 |
|  gte    |   greater or equal than | filter_gte[createdAt]=2021-11-17T23:00:00 |
|  eq    |   equal than | filter_eq[id]=1 |
|  neq    |   not equal than | filter_neq[id]=1 |
|  in    |   is in | filter_in[state]=active,gdpr_deleted |
|  notIn    |   is not in | filter_lt[createdAt]=2021-11-17T23:00:00 |
|  endsWith    | ends with needle | filter_endWith[email]=petitpress.sk |
|  startsWith    |   starts with needle | filter_lt[name]=Jozo |
|  contains    |   contains needle | filter_lt[contains]=anzu |
|  memberOf    |   entity member of entities | filter_memberOf[memberOf]=2,5 |

### Usage in application logic:

[AbstractAnzuRepository](https://github.com/anzusystems/common-bundle/blob/main/src/Repository/AbstractAnzuRepository.php) provides method for finding records by ApiParams object and returning [ApiResponseList](https://github.com/anzusystems/common-bundle/blob/main/src/ApiFilter/ApiResponseList.php):
```php
public function findByApiParams(ApiParams $apiParams): ApiResponseList;
```


