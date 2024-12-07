<?php
require_once 'db_config.php';

try {
    // Test database connection
    $stmt = $pdo->query("SELECT 1");
    echo "Database connection successful!<br>";

    // Show all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Available Tables:</h3>";
    foreach ($tables as $table) {
        echo "- $table <br>";
    }

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 