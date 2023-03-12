<?php

return [
  'app_name' => 'assegai_app',
  'version' => '0.0.1',
  'description' => 'A simple REST API for the testing Assegai project.',
  'company_name' => 'Assegai Inc.',
  'default_password_hash_algo' => '2y',
  'databases' => [
    'mysql' => [
      'assegai_test_db' => [
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
        'port' => 3306,
      ],
    ],
    'pgsql' => [
      'db_name' => [
        'host' => 'localhost',
        'user' => '',
        'password' => '',
        'port' => 5432,
      ],
    ],
    'sqlite' => [
      'db_name' => [
        'path' => '.data/db_name.sq3',
      ],
    ],
    'mongodb' => [],
  ],
  'request' => [
    'DEFAULT_LIMIT' => 10,
    'DEFAULT_SKIP' => 0,
  ],
  'authentication' => [
    'secret' => 'SECRET_KEY',
    'strategies' => [
      'local' => 'Assegai\\Core\\Authentication\\Strategies\\LocalStrategy',
      'jwt' => 'Assegai\\Core\\Authentication\\Strategies\\JWTStrategy',
      'oauth' => 'Assegai\\Core\\Authentication\\Strategies\\OAuthStrategy',
    ],
    'default_strategy' => 'local',
    'jwt' => [
      'audience' => 'https://yourdomain.com',
      'issuer' => 'assegai',
      'lifespan' => '1 hour',
      'entityName' => 'user',
      'entityClassName' => 'Assegai\\App\\Users\\Entities\\UserEntity',
      'entityIdFieldname' => 'email',
      'entityPasswordFieldname' => 'password',
    ],
  ],
];