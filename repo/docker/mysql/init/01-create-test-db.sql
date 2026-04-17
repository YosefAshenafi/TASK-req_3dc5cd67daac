-- Create the test database alongside the main database on first MySQL init.
-- This file runs automatically when the mysql-data volume is first created.
CREATE DATABASE IF NOT EXISTS `smartpark_test`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `smartpark_test`.* TO 'smartpark'@'%';
FLUSH PRIVILEGES;
