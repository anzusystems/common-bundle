# Keyset pagination

An offset makes the database read and throw away every row of the earlier pages, so a deep page of a long table gets
slower with each page. A keyset (cursor) page continues after the last row of the previous page through an index
instead, so every page costs the same. Use it for a table that keeps growing, such as a history or a log. A small table
or an Elasticsearch search can keep pages with an offset.

The classes are in `AnzuSystems\CommonBundle\Pagination\Keyset` and do not depend on the `mcp` section:

* `KeysetPaginator::paginate()` pages a query builder and returns a `KeysetPage`.
* `KeysetOrder` is the order of the keyset: the fields of the root entity and one direction.
* `KeysetPage<T>` holds the `items` of the page and the `nextCursor`; `map()` turns the items into other objects (for
  example decorators) and keeps the cursor.
* `KeysetCursorCodec` writes and reads the cursor.
* `KeysetIndexResolver` finds the index the query must use.
* `Exception\InvalidCursorException` (an `InvalidArgumentException`) means that the client sent a cursor the list did
  not return. Turn it into a client error of your API; the MCP layer already does (see *Pagination* in `mcp.md`).

## Example

Notes have a label and a creation date, and a list shows the notes of one label, newest first, optionally only the
pinned ones:

```php
#[ORM\Entity(repositoryClass: NoteRepository::class)]
#[ORM\Index(name: 'IDX_label_createdAt', fields: ['label', 'createdAt'])]
#[ORM\Index(name: 'IDX_label_pinned_createdAt', fields: ['label', 'pinned', 'createdAt'])]
class Note
{
    // id, label (ManyToOne), pinned, createdAt …
}

final class NoteRepository extends AbstractAnzuRepository
{
    private const array ORDER_FIELDS = ['createdAt', 'id'];

    public function __construct(
        ManagerRegistry $registry,
        private readonly KeysetPaginator $keysetPaginator,
    ) {
        parent::__construct($registry);
    }

    /**
     * @return KeysetPage<Note>
     *
     * @throws InvalidCursorException
     * @throws JsonException
     */
    public function findAllByLabel(Label $label, ?bool $pinned, ?string $cursor, int $limit): KeysetPage
    {
        return $this->keysetPaginator->paginate(
            queryBuilder: $this->createQueryBuilder('note'),
            entityClass: Note::class,
            order: new KeysetOrder(fields: self::ORDER_FIELDS, direction: Order::Descending),
            equalities: ['label' => $label, 'pinned' => $pinned],
            cursor: $cursor,
            limit: $limit,
        );
    }
}
```

The caller passes `nextCursor` of the response back as `cursor` for the next page; `null` means the last page.

## What `paginate()` adds to the query

* `equalities`: `note.label = :keysetLabel` for every field with a value; `null` adds no condition, so `pinned: null`
  lists pinned and other notes. A key is a field or a to-one association of the root entity. The value is bound
  without a type, so Doctrine infers it (an entity by its id, an enum by its value).
* `ORDER BY note.createdAt DESC, note.id DESC`. The order of the query builder is replaced. All fields go in one
  direction, and the last one is unique (usually `id`), so rows with the same `createdAt` are neither repeated nor
  skipped.
* With a cursor: `(note.createdAt < :cursorCreatedAt OR (note.createdAt = :cursorCreatedAt AND note.id < :cursorId))`,
  `>` for `Order::Ascending`. The parameters get the Doctrine type of the field mapping.
* `LIMIT limit + 1`. The extra row only tells whether there is a next page; `nextCursor` holds the fields of the last
  row of the page.
* The optimizer hint `/*+ INDEX(<table alias> <index>) */` right after `SELECT`, through `ForceIndexWalker` (the query
  builder must not set another output walker).

## The index rule

`KeysetIndexResolver` reads the indexes of the entity mapping (`#[ORM\Index]`, `#[ORM\UniqueConstraint]` and the
primary key, as `PRIMARY`) and takes the first one that:

1. starts with the columns of the equalities that have a value, in any order, and
2. continues with the columns of the order fields, in their order.

The last order field may be missing from the index when it is the single-column primary key, because InnoDB appends
the primary key to every secondary index. The index must then end right before it: `IDX_label_createdAt` on
`(label_id, created_at)` serves `label = ? ORDER BY created_at, id`, but an index on `(label_id, created_at, title)`
does not, because `title` comes before the appended `id`.

In the example, `pinned: null` uses `IDX_label_createdAt` and `pinned: true` uses `IDX_label_pinned_createdAt`.
Only indexes with a name in the mapping count, because the optimizer hint needs the name.

Without such an index `paginate()` throws a `LogicException` before the query runs. It names the entity, the
equality and order fields and the index to add:

```
No named index of App\Entity\Note starts with the equality fields [label, author] in any order followed by the order fields [createdAt, id], so the keyset query would sort its rows in a filesort. Add #[ORM\Index(name: 'IDX_label_author_createdAt', fields: ['label', 'author', 'createdAt'])].
```

A new combination of equalities therefore fails in the first test that runs it, not in production with a filesort.

### What the rule does not check

* Conditions the caller adds to the query builder itself (`andWhere()` with a range, `IN`, `LIKE`, a join) are allowed,
  but the index check knows nothing about them. The forced index stays the one for the equalities and the order, so
  such a condition filters the rows the index reads. When it removes most of them, a page can read many rows. Check
  such a query with `EXPLAIN` on production-sized data, for the first page and for a later one.
* That the index exists in the database. The resolver reads the mapping; a migration must create the index. A
  missing one fails the query with `Key '…' doesn't exist`.

## Cursor format

`KeysetCursorCodec` writes the order field values of the last row as base64url JSON without padding:

* a date as ISO 8601 in UTC with microseconds (`KeysetCursorCodec::DATE_FORMAT`, e.g. `2026-08-21T08:00:00.123456Z`);
  it is read back into the default time zone, because DBAL writes a date parameter without converting it;
* an integer or a string as it is.

Reading it back checks base64, the JSON object, exactly the keys of the order fields and the type each field has in the
Doctrine mapping (`datetime_immutable`, `integer`, `smallint`, `string`). Anything else throws
`InvalidCursorException`. A field of another type throws a `LogicException` when the cursor is read.

The cursor is not signed. It only says where to continue; the query keeps every filter and access check of the call.
