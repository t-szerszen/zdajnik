-- migrate:up
CREATE TABLE admin_audit_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_user_id INT UNSIGNED NOT NULL,
  action VARCHAR(100) NOT NULL,
  target_type VARCHAR(100) NOT NULL,
  target_id INT UNSIGNED DEFAULT NULL,
  old_value LONGTEXT DEFAULT NULL,
  new_value LONGTEXT DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_user (admin_user_id),
  KEY idx_action (action),
  KEY idx_target (target_type, target_id),
  KEY idx_created_at (created_at),
  CONSTRAINT admin_audit_logs_ibfk_1 FOREIGN KEY (admin_user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_polish_ci;

-- migrate:down
DROP TABLE IF EXISTS admin_audit_logs;
