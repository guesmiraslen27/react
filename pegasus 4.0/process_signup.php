<?php
// At the top of the file
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation checks
        if (empty($username) || empty($email) || empty($password) || empty($phone) || empty($address)) {
            throw new Exception('All fields are required');
        }

        if ($password !== $confirm_password) {
            throw new Exception('Passwords do not match');
        }

        if (strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters long');
        }

        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            throw new Exception('Username already exists');
        }

        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            throw new Exception('Email already exists');
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user
        $stmt = $pdo->prepare("
            INSERT INTO users (
                username, 
                email, 
                phone,
                address,
                password, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $username,
            $email,
            $phone,
            $address,
            $hashed_password
        ]);

        $userId = $pdo->lastInsertId();

        // Start session and set user data
        session_start();
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;

        echo json_encode([
            'success' => true,
            'message' => 'Registration successful'
        ]);

    } catch (Exception $e) {
        error_log("Signup error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request method'
    ]);
}
?> 