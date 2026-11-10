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

* `equalities`: `note.label = :keyset0` for every field with a value (the parameters are numbered, so a field of an
  embeddable such as `dates.publishedAt` works too); `null` adds no condition, so `pinned: null`
  lists pinned and other notes. A key is a field or a to-one association of the root entity. The value is bound
  without a type, so Doctrine infers it (an entity by its id, an enum by its value).
* `ORDER BY note.createdAt DESC, note.id DESC`. The order of the query builder is replaced. All fields go in one
  direction, and the last one is unique (usually `id`), so rows with the same `createdAt` are neither repeated nor
  skipped.
* With a cursor: `(note.createdAt < :cursor0 OR (note.createdAt = :cursor0 AND note.id < :cursor1))`,
  `>` for `Order::Ascending`. The parameters get the Doctrine type of the field mapping.
* `LIMIT limit + 1`. The extra row only tells whether there is a next page; `nextCursor` holds the fields of the last
  row of the page.
* The optimizer hint `/*+ INDEX(<table alias> <index>) */` right after `SELECT`, through `IndexHintWalker` (the query
  builder must not set another output walker).

Every order field must be a mapped field, not nullable, of a type the cursor can hold (`datetime_immutable`, `integer`,
`smallint`, `string`; `KeysetCursorCodec::FIELD_TYPES`). Otherwise `paginate()` throws a `LogicException` before the
query runs: a descending order puts the rows with `null` last, where `x < :cursor0` never reaches them.

The last order field must also be the single-column identifier or a field with `#[ORM\Column(unique: true)]`, or
`paginate()` throws a `LogicException`: the cursor continues after its value, so rows with the same value on the boundary
of a page would be left out. A field unique only through `#[ORM\UniqueConstraint]` does not count.

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

With class table inheritance (`InheritanceType('JOINED')`) the resolver reads the indexes of every table of the
hierarchy, and takes one only from the table that holds every field. Doctrine reads a field from the table of the class
that declares it, and the primary key from the table of the root entity, where `PRIMARY` is too. A child entity
ordered by `name, id` of its parent therefore uses the index of the parent table, and the hint goes to the alias of
that table. Fields on more than one table, such as a field of the child ordered by `id`, throw a `LogicException`
before the query runs, because no index spans two tables and MySQL would sort the rows in a filesort. An index name
mapped on two tables of the hierarchy throws a `LogicException` as well when the resolver or a hint picks it, because
the hint could not tell which table it means; the other indexes of the hierarchy stay usable. The list of mapped
indexes and their tables is `IndexHintWalker::resolveMappedIndexes()` (a `MappedIndex` per table that maps the name),
which the walker checks the hint against too.

Without such an index `paginate()` throws a `LogicException` before the query runs. It names the entity whose table
holds the fields (the parent entity for fields of the parent table), the equality and order fields and the index to add:

```
No named index of App\Entity\Note starts with the equality fields [label, author] in any order followed by the order fields [createdAt, id], so the keyset query would sort its rows in a filesort. Add #[ORM\Index(name: 'IDX_label_author_createdAt', fields: ['label', 'author', 'createdAt'])].
```

A new combination of equalities therefore fails in the first test that runs it, not in production with a filesort.

### What the rule does not check

* Conditions the caller adds to the query builder itself (`andWhere()` with a range, `IN`, `LIKE`, a join on a to-one
  association) are allowed, but the index check knows nothing about them. The forced index stays the one for the equalities and the order, so
  such a condition filters the rows the index reads. When it removes most of them, a page can read many rows. Check
  such a query with `EXPLAIN` on production-sized data, for the first page and for a later one.
* A join on a to-many association. `paginate()` reads `limit + 1` SQL rows, and Doctrine merges the rows of one
  entity, so a page comes back short and `nextCursor` is `null` while rows remain: with `limit: 2`, a note with two
  matching tags takes two of the three rows, and the list ends after the second note. Filter by a collection with a
  subquery instead: `note.id IN (SELECT n2.id FROM App\Entity\Note n2 JOIN n2.tags t2 WHERE t2.name IN (:names))`.
* That the index exists in the database. The resolver reads the mapping; a migration must create the index. MySQL
  only warns about a hint naming a missing index and runs the query without it, with a filesort.

## Cursor format

`KeysetCursorCodec` writes base64url JSON without padding: `{"query": "<fingerprint>", "values": {…}}`. The
`query` is a hash of the DQL and the parameters of the query before the cursor condition, so a cursor only continues
the list it came from: a cursor of another filter, another order or another direction throws `InvalidCursorException`
instead of skipping rows. The limit is not part of it and may change between pages. The `values` are the order field
values of the last row:

* a date as ISO 8601 in UTC with microseconds (`KeysetCursorCodec::DATE_FORMAT`, e.g. `2026-08-21T08:00:00.123456Z`);
  it is read back into the default time zone, because DBAL writes a date parameter without converting it;
* an integer or a string as it is.

**Every value bound to the query builder is part of the fingerprint, so it must be the same on every page of the same
arguments.** A value that changes by itself, such as the current time as the default of `until` or a list in an
unstable order, gives the second page another fingerprint, and every `nextCursor` fails with `InvalidCursorException`.
Leave such a default out of the query (no `until` condition when the caller sent none), or bind a value the caller
sends again with the cursor.

Reading it back checks base64, the JSON object, the fingerprint of the query, exactly the keys of the order fields and
the type each field has in the Doctrine mapping. Anything else throws `InvalidCursorException`.

The cursor is not signed. It only says where to continue; the query keeps every filter and access check of the call.
