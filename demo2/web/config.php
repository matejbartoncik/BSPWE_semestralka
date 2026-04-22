<?php
declare(strict_types=1);

return [
    // Uprav podle svych DB udaju z hostingu.
    'host' => getenv('DB_HOST') ?: 'db',
    'port' => (int) (getenv('DB_PORT') ?: '3306'),
    'name' => getenv('DB_NAME') ?: 'custdemo2db',
    'user' => getenv('DB_USER') ?: 'custdemo2',
    'pass' => getenv('DB_PASS') ?: 'kocka16',
    'charset' => 'utf8mb4',
];
