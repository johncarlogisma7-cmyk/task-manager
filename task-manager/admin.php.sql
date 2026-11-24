USE task_manager;

-- Run this once after the login system is installed.
ALTER TABLE users
  ADD COLUMN role ENUM('user', 'admin') NOT NULL DEFAULT 'user' AFTER password_hash,
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role;
