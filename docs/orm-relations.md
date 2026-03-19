# ORM Relations

Relations are usually the first ORM topic that feels confusing.

This guide focuses on the practical questions that matter most: where the foreign key lives, which side owns the write, and when related data appears on an entity.

The mental model is intentionally close to TypeORM:

- owner sides store the actual foreign key or join table metadata
- inverse sides describe how to navigate the graph
- relations are loaded explicitly
- collection relations are not magic arrays from nowhere; they appear because the query asked for them

The current ORM test coverage verifies:

- loading `OneToOne`, `ManyToOne`, `OneToMany`, and `ManyToMany` relations
- owner-side join-column writes for relation objects

That is the behavior this guide leans on.

## The most important rule: know the owner side

Use this as the quick reference:

| Relation type | Owner side | Stored key |
| --- | --- | --- |
| `OneToOne` | the side with `#[JoinColumn(...)]` | a foreign key column on the owner table |
| `ManyToOne` | the `ManyToOne` property | a foreign key column on the many-side table |
| `OneToMany` | inverse side only | no foreign key lives on this property |
| `ManyToMany` | the side with `#[JoinTable(...)]` | rows in the join table |

When writes feel surprising, ownership is usually the first thing to check.

## One-to-one

Think of `User` and `Profile`: one user has one profile, and one profile belongs to one user.

```php
<?php

namespace Assegaiphp\BlogApi\Users\Entities;

use Assegai\Orm\Attributes\Columns\Column;
use Assegai\Orm\Attributes\Columns\PrimaryGeneratedColumn;
use Assegai\Orm\Attributes\Entity;
use Assegai\Orm\Attributes\Relations\JoinColumn;
use Assegai\Orm\Attributes\Relations\OneToOne;
use Assegai\Orm\Queries\Sql\ColumnType;

#[Entity(table: 'profiles', database: 'blog')]
class ProfileEntity
{
  #[PrimaryGeneratedColumn]
  public ?int $id = null;

  #[Column(type: ColumnType::TEXT, nullable: false)]
  public string $bio = '';

  #[OneToOne(type: UserEntity::class)]
  public ?UserEntity $user = null;
}

#[Entity(table: 'users', database: 'blog')]
class UserEntity
{
  #[PrimaryGeneratedColumn]
  public ?int $id = null;

  #[Column(type: ColumnType::VARCHAR, nullable: false)]
  public string $name = '';

  #[OneToOne(type: ProfileEntity::class)]
  #[JoinColumn(name: 'profileId')]
  public ?ProfileEntity $profile = null;
}
```

In that model:

- `UserEntity::$profile` is the owner side
- `users.profileId` stores the key
- `ProfileEntity::$user` is the inverse navigation back to the user

Load it explicitly:

```php
<?php

$user = $usersRepository->findOne([
  'where' => ['id' => 1],
  'relations' => ['profile'],
])->getData();

$profile = $profilesRepository->findOne([
  'where' => ['id' => 1],
  'relations' => ['user'],
])->getData();
```

## Many-to-one and one-to-many

This is the classic `Author` and `Post` relationship:

- many posts belong to one author
- one author has many posts

```php
<?php

namespace Assegaiphp\BlogApi\Posts\Entities;

use Assegai\Orm\Attributes\Columns\Column;
use Assegai\Orm\Attributes\Columns\PrimaryGeneratedColumn;
use Assegai\Orm\Attributes\Entity;
use Assegai\Orm\Attributes\Relations\ManyToOne;
use Assegai\Orm\Attributes\Relations\OneToMany;
use Assegai\Orm\Queries\Sql\ColumnType;

#[Entity(table: 'authors', database: 'blog')]
class AuthorEntity
{
  #[PrimaryGeneratedColumn]
  public ?int $id = null;

  #[Column(type: ColumnType::VARCHAR, nullable: false)]
  public string $name = '';

  #[OneToMany(type: PostEntity::class, referencedProperty: 'id', inverseSide: 'author')]
  public array $posts = [];
}

#[Entity(table: 'posts', database: 'blog')]
class PostEntity
{
  #[PrimaryGeneratedColumn]
  public ?int $id = null;

  #[Column(type: ColumnType::VARCHAR, nullable: false)]
  public string $title = '';

  #[ManyToOne(type: AuthorEntity::class)]
  public ?AuthorEntity $author = null;
}
```

In practice:

- the foreign key lives on the `posts` table
- `PostEntity::$author` is the write-oriented owner side
- `AuthorEntity::$posts` is the read-oriented inverse collection

Load either direction explicitly:

```php
<?php

$post = $postsRepository->findOne([
  'where' => ['id' => 1],
  'relations' => ['author'],
])->getData();

$author = $authorsRepository->findOne([
  'where' => ['id' => 1],
  'relations' => ['posts'],
])->getData();
```

## Many-to-many

Use this when both sides can have multiple related records, like `Post` and `Tag`.

```php
<?php

namespace Assegaiphp\BlogApi\Posts\Entities;

use Assegai\Orm\Attributes\Columns\Column;
use Assegai\Orm\Attributes\Columns\PrimaryGeneratedColumn;
use Assegai\Orm\Attributes\Entity;
use Assegai\Orm\Attributes\Relations\JoinTable;
use Assegai\Orm\Attributes\Relations\ManyToMany;
use Assegai\Orm\Queries\Sql\ColumnType;

#[Entity(table: 'tags', database: 'blog')]
class TagEntity
{
  #[PrimaryGeneratedColumn]
  public ?int $id = null;

  #[Column(type: ColumnType::VARCHAR, nullable: false)]
  public string $label = '';

  #[ManyToMany(type: PostEntity::class, inverseSide: 'tags')]
  public array $posts = [];
}

#[Entity(table: 'posts', database: 'blog')]
class PostEntity
{
  #[PrimaryGeneratedColumn]
  public ?int $id = null;

  #[Column(type: ColumnType::VARCHAR, nullable: false)]
  public string $title = '';

  #[ManyToMany(type: TagEntity::class, inverseSide: 'posts')]
  #[JoinTable(name: 'posts_tags', joinColumn: 'post_id', inverseJoinColumn: 'tag_id')]
  public array $tags = [];
}
```

In that model:

- `PostEntity::$tags` is the owner side because it has `#[JoinTable(...)]`
- the join table stores the relationship rows
- `TagEntity::$posts` is the inverse side

Load either side with the `relations` option:

```php
<?php

$post = $postsRepository->findOne([
  'where' => ['id' => 2],
  'relations' => ['tags'],
])->getData();

$tag = $tagsRepository->findOne([
  'where' => ['id' => 2],
  'relations' => ['posts'],
])->getData();
```

## How relation loading works

Relations are not loaded unless you ask for them.

The most common forms are:

```php
<?php

['relations' => ['author', 'tags']]
```

or:

```php
<?php

['relations' => ['author' => true, 'tags' => true]]
```

Use that on `find()`, `findOne()`, and the entity-manager level equivalents when you need related data.

## Writing relation objects through the owner side

When you want to persist an owner-side relation object, use `save()` with relation options enabled.

Example: create a post and point it at an existing author.

```php
<?php

use Assegai\Orm\Management\Options\InsertOptions;

$author = $authorsRepository->findOne([
  'where' => ['id' => 1],
])->getData();

$post = $postsRepository->create([
  'title' => 'Inserted with author',
]);

$post->author = $author;

$result = $postsRepository->save(
  $post,
  new InsertOptions(relations: ['author'])
);
```

The key idea is that the owner-side property is what the ORM can translate into the stored key.

For updates, the same pattern applies with `UpdateOptions`:

```php
<?php

use Assegai\Orm\Management\Options\UpdateOptions;

$post = $postsRepository->findOne([
  'where' => ['id' => 1],
])->getData();

$post->author = $anotherAuthor;

$postsRepository->save(
  $post,
  new UpdateOptions(relations: ['author'])
);
```

## Relation options

Relation attributes accept a `RelationOptions` object for advanced behavior such as:

- `cascade`
- `isNullable`
- `onDelete`
- `onUpdate`
- `isEager`
- `isPersistent`
- `orphanedRowAction`

Those options are there for cases where your schema and lifecycle rules need more control, but the most important first step is still correct ownership and explicit loading.

## Common pitfalls

- Putting `#[JoinColumn]` on both sides of a one-to-one. Pick one owner side.
- Expecting `OneToMany` to store a key. The actual key lives on the `ManyToOne` side.
- Forgetting `#[JoinTable]` on the owner side of a many-to-many.
- Reading a relation property without loading it in the query.
- Trying to write a relation from the inverse side and expecting the foreign key to move automatically.

## Relation techniques that work well

- Use singular names for related object properties like `$post->author` and plural names for collection properties like `$author->posts`.
- Keep the owning side obvious in the entity definition, even when the inverse side feels more convenient to read from.
- Load only the relations the request actually needs.
- Keep relation writes in the service layer so the controller stays about HTTP, not graph persistence.

## Next step

Once the model is in place, move on to [ORM Migrations and Database Workflows](./orm-migrations-and-database-workflows.md).
