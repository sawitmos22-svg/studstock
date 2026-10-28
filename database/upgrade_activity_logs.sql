CREATE TABLE IF NOT EXISTS activity_logs (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, username VARCHAR(80) NOT NULL,
 full_name VARCHAR(160) NULL, event_type VARCHAR(30) NOT NULL, details VARCHAR(255) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_activity_date(created_at), INDEX idx_activity_user(user_id)
) ENGINE=InnoDB;
