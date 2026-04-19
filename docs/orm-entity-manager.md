# Working with Entity Manager

The entity manager is the central access point to ORM persistence work.

Repositories are built on top of it. That means the entity manager is not a competing concept. It is the broader coordination layer underneath the more convenient entity-scoped API.

## Repository versus entity manager

Use a repository when:

- the code is centered on one entity type
- you are writing normal app-level service logic
- you want the simplest day-to-day CRUD path

Use the entity manager when:

- the workflow spans multiple entity types
- you are writing infrastructure or generic package code
- you are in a standalone script and want one central entry point
- you want lower-level control without going all the way down to raw SQL

## Getting the entity manager

In standalone usage, the entity manager comes from the data source:

```php
<?php

$entityManager = $dataSource->manager;
```

If you already have a repository, you also have access to the same manager:

```php
<?php

$entityManager = $moviesRepository->manager;
```

## Creating an entity

The entity manager can create an entity instance from plain input:

```php
<?php

use App\Entities\MovieEntity;

$movie = $entityManager->create(MovieEntity::class, [
  'title' => 'Midnight Harbor',
  'synopsis' => 'A ferry terminal becomes the meeting point for strangers with linked pasts.',
  'isNowShowing' => true,
]);
```

Why this is useful:

- you get an entity-shaped object
- defaults and mapping happen in one place
- you do not have to manually populate every property by hand

## Saving an entity

```php
<?php

$saveResult = $entityManager->save($movie);

if ($saveResult->isError()) {
  throw $saveResult->getLatestError();
}
```

`save()` is the smooth default write path for most application work:

- insert when the record is new
- update when the record already exists

## Finding records

```php
<?php

$movies = $entityManager->find(MovieEntity::class, [
  'where' => ['isNowShowing' => true],
  'order' => ['id' => 'DESC'],
  'limit' => 20,
])->getData();
```

To fetch a single record:

```php
<?php

$movie = $entityManager->findOne(MovieEntity::class, [
  'where' => ['id' => 42],
])->getFirst();
```

The important thing to notice is that the entity class is explicit on entity-manager calls. Repositories do not need that extra argument because they are already bound to one entity type.

## Updating records

With the entity manager, the method shape is:

```php
update(entityClass, partialEntity, conditions)
```

Example:

```php
<?php

$updateResult = $entityManager->update(
  MovieEntity::class,
  ['isNowShowing' => false],
  ['id' => 42],
);

if ($updateResult->isError()) {
  throw $updateResult->getLatestError();
}
```

That ordering is worth remembering:

- first the entity class
- then the partial update payload
- then the criteria describing which rows should change

## Removing records

```php
<?php

$deleteResult = $entityManager->remove($movie);
```

If your entity uses soft-delete patterns such as `ChangeRecorderTrait`, prefer the soft-delete workflow your application has standardized on instead of reaching straight for hard delete every time.

## Cross-entity workflows

This is one of the clearest places where the entity manager shines.

Imagine a workflow that creates a cinema, then creates its first showtime:

```php
<?php

use App\Entities\CinemaEntity;
use App\Entities\ShowtimeEntity;

$cinema = $entityManager->create(CinemaEntity::class, [
  'name' => 'Riverside Screens',
]);

$entityManager->save($cinema);

$showtime = $entityManager->create(ShowtimeEntity::class, [
  'startsAt' => '2026-04-12 20:00:00',
]);

$showtime->cinema = $cinema;

$entityManager->save($showtime);
```

This kind of workflow would still be possible through repositories, but the entity manager makes the coordination role more obvious.

## Practical advice

- Prefer repositories for feature services.
- Reach for the entity manager when the workflow is broader than one entity.
- Remember that entity-manager methods take the entity class explicitly.
- Treat the entity manager as the center of the ORM, not an "advanced feature nobody should use".

## Next steps

Once the entity manager feels clear, move on to [Query Builder](./orm-query-builder.md).
