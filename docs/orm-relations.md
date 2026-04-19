# Relations

Relations describe how entities connect to each other.

That sounds simple, but relation bugs usually come from one of three misunderstandings:

- not knowing which side owns the write
- expecting related data to load automatically
- treating collection properties as if they create foreign keys by themselves

This guide focuses on the practical mental model that keeps those mistakes rare.

## The most important rule: know the owner side

Use this as the quick reference:

| Relation type | Owner side | Where the stored key lives |
| --- | --- | --- |
| `OneToOne` | the side with `#[JoinColumn(...)]` | a foreign key on the owner table |
| `ManyToOne` | the `ManyToOne` property | a foreign key on the many-side table |
| `OneToMany` | inverse side only | nowhere on this property directly |
| `ManyToMany` | the side with `#[JoinTable(...)]` | the join table |

If a relation write behaves strangely, ownership is the first thing to check.

## One-to-one

Use one-to-one when each record on one side matches at most one record on the other side.

Example: a cinema has one profile, and a profile belongs to one cinema.

```php
<?php

namespace Assegaiphp\CinemaHub\Cinemas\Entities;

use Assegai\Orm\Attributes\Columns\Column;
use Assegai\Orm\Attributes\Columns\PrimaryGeneratedColumn;
use Assegai\Orm\Attributes\Entity;
use Assegai\Orm\Attributes\Relations\JoinColumn;
use Assegai\Orm\Attributes\Relations\OneToOne;
use Assegai\Orm\Queries\Sql\ColumnType;

#[Entity(table: 'cinema_profiles', database: 'cinema')]
class CinemaProfileEntity
{
  #[PrimaryGeneratedColumn]
  public ?int $id = null;

  #[Column(type: ColumnType::TEXT, nullable: false)]
  public string $description = '';

  #[OneToOne(type: CinemaEntity::class)]
  public ?CinemaEntity $cinema = null;
}

#[Entity(table: 'cinemas', database: 'cinema')]
class CinemaEntity
{
  #[PrimaryGeneratedColumn]
  public ?int $id = null;

  #[Column(type: ColumnType::VARCHAR, nullable: false)]
  public string $name = '';

  #[OneToOne(type: CinemaProfileEntity::class)]
  #[JoinColumn(name: 'profile_id')]
  public ?CinemaProfileEntity $profile = null;
}
```

How to read that:

- `CinemaEntity::$profile` is the owner side
- the `cinemas` table stores the foreign key
- `CinemaProfileEntity::$cinema` is the inverse navigation back


### SQL shape for the one-to-one example

A one-to-one relation still becomes ordinary SQL tables. The special part is that the owner table stores a foreign key that should point to only one row.

```sql
CREATE TABLE cinema_profiles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  description TEXT NOT NULL
);

CREATE TABLE cinemas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(255) NOT NULL,
  profile_id INTEGER NULL UNIQUE,
  FOREIGN KEY (profile_id) REFERENCES cinema_profiles(id)
);
```

| Table | Important columns | What to notice |
| --- | --- | --- |
| `cinema_profiles` | `id`, `description` | no foreign key lives here in this example |
| `cinemas` | `id`, `name`, `profile_id` | `profile_id` is the owner-side join column |

Because the join column is declared explicitly as `#[JoinColumn(name: 'profile_id')]`, the SQL column uses that exact name.

## Many-to-one and one-to-many

This is the most common relation pair.

Example: many showtimes belong to one cinema, and one cinema has many showtimes.

```php
<?php

namespace Assegaiphp\CinemaHub\Showtimes\Entities;

use Assegai\Orm\Attributes\Columns\Column;
use Assegai\Orm\Attributes\Columns\PrimaryGeneratedColumn;
use Assegai\Orm\Attributes\Entity;
use Assegai\Orm\Attributes\Relations\ManyToOne;
use Assegai\Orm\Attributes\Relations\OneToMany;
use Assegai\Orm\Queries\Sql\ColumnType;

#[Entity(table: 'cinemas', database: 'cinema')]
class CinemaEntity
{
  #[PrimaryGeneratedColumn]
  public ?int $id = null;

  #[Column(type: ColumnType::VARCHAR, nullable: false)]
  public string $name = '';

  #[OneToMany(type: ShowtimeEntity::class, referencedProperty: 'id', inverseSide: 'cinema')]
  public array $showtimes = [];
}

#[Entity(table: 'showtimes', database: 'cinema')]
class ShowtimeEntity
{
  #[PrimaryGeneratedColumn]
  public ?int $id = null;

  #[Column(type: ColumnType::VARCHAR, nullable: false)]
  public string $startsAt = '';

  #[ManyToOne(type: CinemaEntity::class)]
  public ?CinemaEntity $cinema = null;
}
```

What matters here:

- the foreign key lives on the `showtimes` table
- `ShowtimeEntity::$cinema` is the owner side
- `CinemaEntity::$showtimes` is the inverse collection

If you are saving relation data, write through the owner side.


### SQL shape for the many-to-one example

This pair is easier to understand if you read it from the table side first: one cinema row can be referenced by many showtime rows.

```sql
CREATE TABLE cinemas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(255) NOT NULL
);

CREATE TABLE showtimes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  starts_at VARCHAR(255) NOT NULL,
  cinema_id INTEGER NULL,
  FOREIGN KEY (cinema_id) REFERENCES cinemas(id)
);
```

| Table | Important columns | What to notice |
| --- | --- | --- |
| `cinemas` | `id`, `name` | the parent row lives here |
| `showtimes` | `id`, `starts_at`, `cinema_id` | `cinema_id` is the stored foreign key |

The important part is that `#[OneToMany(...)]` does not create its own column. The actual stored key is the implicit join column on the `ManyToOne` side, which the ORM now derives here as `cinema_id`.

## Many-to-many

Use many-to-many when both sides can have multiple related records.

Example: one movie can belong to many genres, and one genre can describe many movies.

```php
<?php

namespace Assegaiphp\CinemaHub\Movies\Entities;

use Assegai\Orm\Attributes\Columns\Column;
use Assegai\Orm\Attributes\Columns\PrimaryGeneratedColumn;
use Assegai\Orm\Attributes\Entity;
use Assegai\Orm\Attributes\Relations\JoinTable;
use Assegai\Orm\Attributes\Relations\ManyToMany;
use Assegai\Orm\Queries\Sql\ColumnType;

#[Entity(table: 'genres', database: 'cinema')]
class GenreEntity
{
  #[PrimaryGeneratedColumn]
  public ?int $id = null;

  #[Column(type: ColumnType::VARCHAR, nullable: false)]
  public string $name = '';

  #[ManyToMany(type: MovieEntity::class, inverseSide: 'genres')]
  public array $movies = [];
}

#[Entity(table: 'movies', database: 'cinema')]
class MovieEntity
{
  #[PrimaryGeneratedColumn]
  public ?int $id = null;

  #[Column(type: ColumnType::VARCHAR, nullable: false)]
  public string $title = '';

  #[ManyToMany(type: GenreEntity::class, inverseSide: 'movies')]
  #[JoinTable(name: 'movies_genres', joinColumn: 'movie_id', inverseJoinColumn: 'genre_id')]
  public array $genres = [];
}
```

In this case:

- `MovieEntity::$genres` is the owner side because it has `#[JoinTable(...)]`
- the join table stores the relationship rows
- `GenreEntity::$movies` is the inverse side


### SQL shape for the many-to-many example

A many-to-many relation becomes three tables: the two entity tables you already expected, plus a join table whose only job is to connect their keys.

```sql
CREATE TABLE movies (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title VARCHAR(255) NOT NULL
);

CREATE TABLE genres (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(255) NOT NULL
);

CREATE TABLE movies_genres (
  movie_id INTEGER NOT NULL,
  genre_id INTEGER NOT NULL,
  PRIMARY KEY (movie_id, genre_id),
  FOREIGN KEY (movie_id) REFERENCES movies(id),
  FOREIGN KEY (genre_id) REFERENCES genres(id)
);
```

| Table | Important columns | What to notice |
| --- | --- | --- |
| `movies` | `id`, `title` | regular entity table |
| `genres` | `id`, `name` | regular entity table |
| `movies_genres` | `movie_id`, `genre_id` | join table declared by `#[JoinTable(...)]` |

That join table is the whole relation. Each row simply says, “this movie is linked to this genre.”

## Relations are loaded explicitly

Do not assume related data appears by magic.

Ask for it in the query:

```php
<?php

$cinema = $cinemas->findOne([
  'where' => ['id' => 1],
  'relations' => ['showtimes'],
])->getData();

$movie = $movies->findOne([
  'where' => ['id' => 42],
  'relations' => ['genres'],
])->getData();
```

That explicitness is a feature. It keeps data access easier to reason about and prevents accidental overfetching.

## Writing through the owner side

Here is the practical pattern for a many-to-one relation:

```php
<?php

$cinema = $cinemas->findOne([
  'where' => ['id' => 1],
])->getFirst();

$showtime = $showtimes->create([
  'startsAt' => '2026-04-12 19:30:00',
]);

$showtime->cinema = $cinema;

$saveResult = $showtimes->save($showtime);
```

Why this works:

- the write goes through `ShowtimeEntity::$cinema`
- that property is the owner side
- the owner side is the place that controls the stored foreign key

## Practical advice

- Learn the owner side first.
- Load relations explicitly.
- Keep inverse collection properties for navigation, not for pretending they own the write.
- Persist through the side with `#[JoinColumn(...)]` or `#[JoinTable(...)]`.

## Next steps

Once relations make sense, move on to [Migrations](./orm-migrations.md) so the schema evolves in step with the model.
