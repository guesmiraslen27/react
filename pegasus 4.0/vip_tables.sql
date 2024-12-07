-- VIP Memberships table
CREATE TABLE vip_memberships (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    plan_type VARCHAR(10) NOT NULL,
    monthly_fee DECIMAL(10,2) NOT NULL,
    billing_name VARCHAR(100) NOT NULL,
    billing_email VARCHAR(100) NOT NULL,
    billing_address VARCHAR(255) NOT NULL,
    billing_city VARCHAR(100) NOT NULL,
    billing_zip VARCHAR(20) NOT NULL,
    start_date DATETIME NOT NULL,
    status ENUM('active', 'cancelled', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Add VIP columns to users table
ALTER TABLE users
ADD COLUMN is_vip BOOLEAN DEFAULT FALSE,
ADD COLUMN vip_tier VARCHAR(10) DEFAULT NULL; 