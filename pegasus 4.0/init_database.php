<?php
require_once 'db_config.php';

try {
    // Read and execute SQL file
    $sql = file_get_contents('pegasusdb.sql');
    $pdo->exec($sql);
    echo "Database initialized successfully!";
} catch(PDOException $e) {
    echo "Error initializing database: " . $e->getMessage();
}
?> 