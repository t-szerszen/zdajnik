-- migrate:up
CREATE TABLE IF NOT EXISTS analytics_events (
  id int unsigned NOT NULL AUTO_INCREMENT,
  user_id int unsigned DEFAULT NULL,
  event_type varchar(50) NOT NULL,
  event_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_address varchar(45) DEFAULT NULL,
  user_agent varchar(255) DEFAULT NULL,
  metadata JSON DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_analytics_event_type (event_type),
  KEY idx_analytics_event_date (event_date),
  KEY idx_analytics_user_id (user_id),
  KEY idx_analytics_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- migrate:down
DROP TABLE IF EXISTS analytics_events;