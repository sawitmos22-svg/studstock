USE studbolt_stock;

ALTER TABLE users
 MODIFY role ENUM('manager','stock_staff','employee') NOT NULL DEFAULT 'employee';
