-- Create database
CREATE DATABASE IF NOT EXISTS pegasusdb;
USE pegasusdb;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_vip BOOLEAN DEFAULT FALSE,
    vip_tier VARCHAR(10) DEFAULT NULL,
    vip_expiry DATE NULL,
    phone VARCHAR(20) AFTER email,
    address TEXT AFTER phone
);

-- Products table
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255),
    category VARCHAR(50),
    stock INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cart table
CREATE TABLE cart (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    size VARCHAR(5) NOT NULL,
    quantity INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Modified VIP Codes table to track usage without limiting reuse
CREATE TABLE vip_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(6) NOT NULL UNIQUE,
    times_used INT DEFAULT 0,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    code_type ENUM('promotion', 'invitation') NOT NULL DEFAULT 'invitation',
    owner_member_id INT,
    FOREIGN KEY (owner_member_id) REFERENCES vip_memberships(id),
    successful_invites INT DEFAULT 0,
    invite_pairs INT DEFAULT 0,
    current_pair_count INT DEFAULT 0,
    frozen_bonus DECIMAL(10,2) DEFAULT 0.00
);

-- Add VIP Code Usage History table
CREATE TABLE vip_code_usage (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code_id INT NOT NULL,
    user_id INT NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (code_id) REFERENCES vip_codes(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Modified VIP Memberships table to remove monthly fee and focus on annual renewal
CREATE TABLE vip_memberships (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    plan_type VARCHAR(10) NOT NULL,
    annual_renewal_fee DECIMAL(10,2) DEFAULT 20.00,
    next_renewal_date DATE NOT NULL,
    billing_name VARCHAR(100) NOT NULL,
    billing_email VARCHAR(100) NOT NULL,
    billing_address VARCHAR(255) NOT NULL,
    billing_city VARCHAR(100) NOT NULL,
    billing_zip VARCHAR(20) NOT NULL,
    card_last_four VARCHAR(4) NOT NULL,
    card_expiry VARCHAR(5) NOT NULL,
    start_date DATETIME NOT NULL,
    status ENUM('active', 'cancelled', 'expired') DEFAULT 'active',
    auto_renewal BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    member_codes VARCHAR(255) DEFAULT NULL
);

-- Add table for renewal transactions
CREATE TABLE vip_renewal_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    membership_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'successful', 'failed') DEFAULT 'pending',
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    error_message TEXT,
    FOREIGN KEY (membership_id) REFERENCES vip_memberships(id)
);

-- Orders table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    shipping_address TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Order Items table
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    size VARCHAR(5) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Insert initial VIP codes
INSERT INTO vip_codes (code) VALUES 
('VIP123'),
('PEG456'),
('FAS789');

-- Insert initial products
INSERT INTO products (name, description, price, image_url, category, stock) VALUES 
('Metal Style Hoodie', 'Black hoodie with metallic design', 50.3, 'metal-hoodie.jpg', 'hoodies', 100),
('THOR Motocross T-Shirt', 'Black t-shirt with minimalist design', 42.5, 'thor-tshirt.jpg', 't-shirts', 150),
('Medusa Gothic Hoodie', 'Black hoodie with gothic Medusa design on the back', 50.3, 'medusa-hoodie.jpg', 'hoodies', 75),
('Medusa Gothic T-Shirt', 'Black t-shirt with elegant Medusa design in grayscale', 30.5, 'medusa-tshirt.jpeg', 't-shirts', 100),
('Medusa Gothic Leather Jacket', 'Black leather jacket with hand-painted Medusa design on the back', 119.9, 'medusa-jacket.jpg', 'jackets', 50),
('Poseidon Metal Style Hoodie', 'Black hoodie with minimalist metal design pattern', 62.4, 'poseidon-hoodie.webp', 'hoodies', 100);

-- Add table to track VIP member codes
CREATE TABLE vip_member_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,
    code_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES vip_memberships(id),
    FOREIGN KEY (code_id) REFERENCES vip_codes(id)
);

-- Add table to track invitation history
CREATE TABLE vip_invitations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code_id INT NOT NULL,
    inviter_id INT NOT NULL,
    invited_user_id INT NOT NULL,
    invitation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (code_id) REFERENCES vip_codes(id),
    FOREIGN KEY (inviter_id) REFERENCES vip_memberships(id),
    FOREIGN KEY (invited_user_id) REFERENCES users(id)
);

-- Add table to track referral bonuses
CREATE TABLE vip_referral_bonuses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    inviter_id INT NOT NULL,
    invited_user_id INT NOT NULL,
    bonus_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inviter_id) REFERENCES vip_memberships(id),
    FOREIGN KEY (invited_user_id) REFERENCES users(id)
);

-- Add table to track bonus payments per code
CREATE TABLE vip_code_bonus_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code_id INT NOT NULL,
    inviter_id INT NOT NULL,
    pair_number INT NOT NULL,
    bonus_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (code_id) REFERENCES vip_codes(id),
    FOREIGN KEY (inviter_id) REFERENCES vip_memberships(id)
);

-- Add table to track frozen bonus transactions
CREATE TABLE vip_frozen_bonus_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code_id INT NOT NULL,
    inviter_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    transaction_type ENUM('freeze', 'unfreeze') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (code_id) REFERENCES vip_codes(id),
    FOREIGN KEY (inviter_id) REFERENCES vip_memberships(id)
);

-- Add table for membership upgrades
CREATE TABLE vip_membership_upgrades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    membership_id INT NOT NULL,
    previous_amount DECIMAL(10,2) NOT NULL,
    upgrade_amount DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    upgrade_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (membership_id) REFERENCES vip_memberships(id)
);

-- Modify vip_membership_upgrades table to include upgrade type
ALTER TABLE vip_membership_upgrades 
ADD COLUMN upgrade_type ENUM('manual', 'bonus_reinvestment') DEFAULT 'manual';

-- Modify the equal pairs bonuses table to include frozen bonus tracking
DROP TABLE IF EXISTS vip_equal_pairs_bonuses;
CREATE TABLE vip_equal_pairs_bonuses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,
    pair_count INT NOT NULL,
    bonus_amount DECIMAL(10,2) NOT NULL,
    frozen_bonus_amount DECIMAL(10,2) DEFAULT 0.00,
    total_bonus_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES vip_memberships(id)
);

-- Add table to track equal pairs bonus releases
CREATE TABLE vip_equal_pairs_bonus_releases (
    id INT PRIMARY KEY AUTO_INCREMENT,
    equal_pairs_bonus_id INT NOT NULL,
    member_id INT NOT NULL,
    released_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equal_pairs_bonus_id) REFERENCES vip_equal_pairs_bonuses(id),
    FOREIGN KEY (member_id) REFERENCES vip_memberships(id)
);

-- Modify vip_code_bonus_payments to include bonus type
ALTER TABLE vip_code_bonus_payments 
ADD COLUMN bonus_type ENUM('regular', 'frozen') DEFAULT 'regular';

-- Add bank account settings table
CREATE TABLE bank_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    bank_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    account_holder VARCHAR(100) NOT NULL,
    swift_code VARCHAR(20),
    iban VARCHAR(50),
    balance DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Add transactions table to track all money movements
CREATE TABLE bank_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_type ENUM('incoming', 'outgoing') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    source_type ENUM('cart', 'vip_membership', 'vip_bonus') NOT NULL,
    reference_id INT NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add admin logs table
CREATE TABLE admin_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);