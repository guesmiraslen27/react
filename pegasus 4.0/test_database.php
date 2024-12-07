<?php
require_once 'db_config.php';

try {
    // Test users table
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Database Tables:</h2>";
    foreach ($tables as $table) {
        echo "- $table<br>";
        
        // Show table structure
        $columns = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($columns);
        echo "</pre>";
    }

    // Test insert into users table
    $testUser = [
        'username' => 'test_user_' . time(),
        'email' => 'test' . time() . '@test.com',
        'password' => password_hash('test123', PASSWORD_DEFAULT),
        'phone' => '1234567890',
        'address' => 'Test Address'
    ];

    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, phone, address, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $testUser['username'],
        $testUser['email'],
        $testUser['password'],
        $testUser['phone'],
        $testUser['address']
    ]);

    echo "<h2>Test user created successfully with ID: " . $pdo->lastInsertId() . "</h2>";

} catch(PDOException $e) {
    echo "<h2>Error:</h2>";
    echo $e->getMessage();
}
?> 