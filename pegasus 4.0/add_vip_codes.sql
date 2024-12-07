CREATE TABLE vip_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(6) NOT NULL UNIQUE,
    is_used BOOLEAN DEFAULT FALSE,
    used_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (used_by) REFERENCES users(id)
);

-- Insert some example VIP codes
INSERT INTO vip_codes (code) VALUES 
('VIP123'),
('PEG456'),
('FAS789'); 