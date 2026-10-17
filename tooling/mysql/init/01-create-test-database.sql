CREATE DATABASE IF NOT EXISTS laravel_dusk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON laravel_dusk.* TO 'laravel' @'%';

CREATE DATABASE IF NOT EXISTS laravel_phpunit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON laravel_phpunit.* TO 'laravel' @'%';