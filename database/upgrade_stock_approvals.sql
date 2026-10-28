USE studbolt_stock;

CREATE TABLE IF NOT EXISTS stock_requests (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 product_id INT NOT NULL, requester_user_id INT NULL,
 requester_username VARCHAR(80) NOT NULL, requester_full_name VARCHAR(160) NOT NULL,
 request_type ENUM('IN','OUT','ADJUST') NOT NULL, qty INT NOT NULL,
 job_ref VARCHAR(120) NOT NULL DEFAULT '', note VARCHAR(255) NOT NULL DEFAULT '',
 status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
 reviewer_user_id INT NULL, reviewer_username VARCHAR(80) NULL, reviewer_full_name VARCHAR(160) NULL,
 review_note VARCHAR(255) NOT NULL DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 reviewed_at TIMESTAMP NULL DEFAULT NULL,
 CONSTRAINT fk_stock_requests_product FOREIGN KEY(product_id) REFERENCES products(id),
 CONSTRAINT fk_stock_requests_requester FOREIGN KEY(requester_user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_stock_requests_reviewer FOREIGN KEY(reviewer_user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_stock_requests_status_created(status, created_at),
 INDEX idx_stock_requests_product(product_id)
) ENGINE=InnoDB;
