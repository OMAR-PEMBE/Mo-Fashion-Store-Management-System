-- LOCAL DEVELOPMENT ONLY. Run as a MySQL administrator after replacing both
-- password placeholders. Never commit a copy containing real passwords.
-- These users have privileges only on their own development/test databases.
CREATE DATABASE IF NOT EXISTS mfbms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS mfbms_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mfbms_app'@'localhost' IDENTIFIED BY 'REPLACE_WITH_DEVELOPMENT_PASSWORD';
CREATE USER 'mfbms_test'@'localhost' IDENTIFIED BY 'REPLACE_WITH_TEST_PASSWORD';
GRANT ALL PRIVILEGES ON mfbms.* TO 'mfbms_app'@'localhost';
GRANT ALL PRIVILEGES ON mfbms_testing.* TO 'mfbms_test'@'localhost';

-- Phase 24 backup restore checks. Each account may only rebuild its own scratch
-- copy; app:verify-backup wipes it after every check. Safe to run again.
CREATE DATABASE IF NOT EXISTS mfbms_restore_check CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS mfbms_testing_restore_check CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON mfbms_restore_check.* TO 'mfbms_app'@'localhost';
GRANT ALL PRIVILEGES ON mfbms_testing_restore_check.* TO 'mfbms_test'@'localhost';
