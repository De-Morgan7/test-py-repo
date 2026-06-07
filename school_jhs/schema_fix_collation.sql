-- Optional one-time fix: align core tables with fee schema (utf8mb4_unicode_ci).
-- Use if you still see collation errors on joins between students and other tables
-- (e.g. attendance/marks created under MySQL 8 default utf8mb4_0900_ai_ci).
--
-- Run in phpMyAdmin → SQL, or: mysql -u root -p school_jhs < schema_fix_collation.sql

ALTER TABLE students CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
