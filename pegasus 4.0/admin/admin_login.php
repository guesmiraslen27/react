<?php
session_start();
require_once '../db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Add your admin credentials here
    $admin_username = "your_admin_username";
    $admin_password = "your_admin_password";

    if ($username === $admin_username && $password === $admin_password) {
        $_SESSION['admin_id'] = 1;
        header('Location: bank_settings.php');
        exit();
    } else {
        $error = "Invalid credentials";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link rel="stylesheet" href="admin_styles.css">
</head>
<body>
    <div class="admin-login-container">
        <h1>Admin Login</h1>
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" class="login-form">
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html> 