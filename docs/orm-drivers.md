# Drivers

Drivers are the backend families the ORM knows how to talk to.

The supported SQL family is:

- MySQL
- MariaDB
- PostgreSQL
- SQLite
- MSSQL

## Driver tokens and PDO extensions

Use this as the quick reference:

| Driver family | Token in config | `DataSourceType` value | PHP extension |
| --- | --- | --- | --- |
| MySQL | `mysql` | `DataSourceType::MYSQL` | `pdo_mysql` |
| MariaDB | `mariadb` | `DataSourceType::MARIADB` | `pdo_mysql` |
| PostgreSQL | `pgsql` | `DataSourceType::POSTGRESQL` | `pdo_pgsql` |
| SQLite | `sqlite` | `DataSourceType::SQLITE` | `pdo_sqlite` |
| MSSQL | `mssql` | `DataSourceType::MSSQL` | `pdo_sqlsrv` |

Two details are easy to miss:

- PostgreSQL uses the token `pgsql`
- MariaDB uses the same PDO extension family as MySQL
- SQL Server uses the token `mssql`

## Standalone configuration examples

### MySQL

```php
<?php

OrmRuntime::configure([
  'databases' => [
    'mysql' => [
      'cinema' => [
        'host' => '127.0.0.1',
        'user' => 'root',
        'password' => '',
        'port' => 3306,
      ],
    ],
  ],
]);
```

### PostgreSQL

```php
<?php

OrmRuntime::configure([
  'databases' => [
    'pgsql' => [
      'cinema' => [
        'host' => '127.0.0.1',
        'user' => 'postgres',
        'password' => 'secret',
        'port' => 5432,
      ],
    ],
  ],
]);
```

### SQLite

```php
<?php

OrmRuntime::configure([
  'databases' => [
    'sqlite' => [
      'cinema' => [
        'path' => __DIR__ . '/storage/cinema.sqlite',
      ],
    ],
  ],
]);
```

### MSSQL

```php
<?php

OrmRuntime::configure([
  'databases' => [
    'mssql' => [
      'reporting' => [
        'host' => '127.0.0.1',
        'user' => 'sa',
        'password' => 'secret',
        'port' => 1433,
      ],
    ],
  ],
]);
```

If you are connecting to a local SQL Server instance with a self-signed certificate, make sure the SQL Server PDO driver is installed correctly for your PHP runtime. The ORM's MSSQL runtime path already trusts the local development certificate.

## Assegai configuration examples

The CLI can create these config blocks for you, and it helps to know what shape it is producing.

```php
<?php

return [
  'databases' => [
    'mysql' => [
      'catalog' => [
        'host' => '127.0.0.1',
        'user' => 'root',
        'password' => '',
        'port' => 3306,
      ],
    ],
    'pgsql' => [
      'analytics' => [
        'host' => '127.0.0.1',
        'user' => 'postgres',
        'password' => 'secret',
        'port' => 5432,
      ],
    ],
    'sqlite' => [
      'local' => [
        'path' => '.data/local.sqlite',
      ],
    ],
    'mssql' => [
      'reporting' => [
        'host' => '127.0.0.1',
        'user' => 'sa',
        'password' => 'secret',
        'port' => 1433,
      ],
    ],
  ],
];
```

Then modules should usually refer to the data source in fully qualified form:

```php
'data_source' => 'mysql:catalog'
'data_source' => 'pgsql:analytics'
'data_source' => 'sqlite:local'
'data_source' => 'mssql:reporting'
```

## Which driver should you choose?

Use MySQL when:

- you want a familiar SQL deployment path
- your production environment is already MySQL-oriented
- you want MySQL-specific query features

Use MariaDB when:

- your infrastructure is MariaDB-based
- the MySQL-style path matches your environment well
- you want MariaDB without leaving the MySQL-family fluency

Use PostgreSQL when:

- your team already runs PostgreSQL
- you want PostgreSQL-specific query features
- you are comfortable leaning into its dialect-specific strengths

Use SQLite when:

- you want the simplest local setup
- you are building a prototype, small app, test rig, or CLI-oriented tool
- file-backed or in-memory persistence is a good fit

Use MSSQL when:

- your environment already runs SQL Server
- reporting, integration, or compliance requirements are built around SQL Server
- you want the ORM and query builder to stay inside the same SQL Server stack

## Driver choice and the query builder

Driver choice is not just config. It affects what fluent query-builder path makes sense.

For example:

- PostgreSQL can expose `distinctOn(...)` and `returning(...)`
- MySQL and MariaDB can expose `highPriority()` and `onDuplicateKeyUpdate(...)`
- MSSQL can expose `top(...)`
- SQLite stays on the smaller SQLite-valid path

This is why the driver concept matters both at configuration time and at query-construction time.

## Next steps

If you skipped ahead to configuration, go back to the fuller learning track:

1. [Getting Started](./orm-getting-started.md)
2. [Data Sources](./orm-data-sources.md)
3. [Entities](./orm-entities.md)
4. [Relations](./orm-relations.md)
5. [Migrations](./orm-migrations.md)
6. [Working with Entity Manager](./orm-entity-manager.md)
7. [Query Builder](./orm-query-builder.md)
