-- SmartPark database initialization
-- This file runs before the application migrations on first start
-- It ensures the database and user exist with correct charset

CREATE DATABASE IF NOT EXISTS smartpark CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS smartpark_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON smartpark.* TO 'smartpark'@'%';
GRANT ALL PRIVILEGES ON smartpark_testing.* TO 'smartpark'@'%';
FLUSH PRIVILEGES;
