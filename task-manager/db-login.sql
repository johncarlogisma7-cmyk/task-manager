USE task_manager;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- For an existing tasks table, add a user_id column so tasks can belong to an account.
-- Existing task rows remain unassigned (user_id = NULL) until you assign them manually.
ALTER TABLE tasks
  ADD COLUMN user_id INT UNSIGNED NULL AFTER id,
  ADD INDEX idx_tasks_user_status (user_id, status);

ALTER TABLE tasks
  ADD CONSTRAINT fk_tasks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
