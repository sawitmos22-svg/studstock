USE studbolt_stock;

ALTER TABLE transactions
 ADD COLUMN user_username VARCHAR(80) NULL,
 ADD COLUMN user_full_name VARCHAR(160) NULL;

UPDATE transactions t
JOIN users u ON u.id = t.user_id
SET t.user_username = u.username,
    t.user_full_name = u.full_name;

ALTER TABLE transactions
 MODIFY user_id INT NULL,
 MODIFY user_username VARCHAR(80) NOT NULL,
 MODIFY user_full_name VARCHAR(160) NOT NULL,
 DROP FOREIGN KEY transactions_ibfk_2,
 ADD CONSTRAINT fk_transactions_user
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
