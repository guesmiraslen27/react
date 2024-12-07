<?php
session_start();
require_once '../db_config.php';

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// First-time setup: Insert default bank settings if none exist
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bank_settings");
$stmt->execute();
if ($stmt->fetchColumn() == 0) {
    $stmt = $pdo->prepare("
        INSERT INTO bank_settings (
            bank_name,
            account_number,
            account_holder,
            swift_code,
            iban,
            balance
        ) VALUES (?, ?, ?, ?, ?, 0.00)
    ");
    $stmt->execute([
        '', // Add your bank name
        '', // Add your account number
        '', // Add your name
        '', // Add your SWIFT code
        ''  // Add your IBAN
    ]);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add additional security verification here
        if (!isset($_POST['admin_password']) || !verifyAdminPassword($_POST['admin_password'])) {
            throw new Exception('Invalid admin password');
        }

        $stmt = $pdo->prepare("
            UPDATE bank_settings SET 
            bank_name = ?,
            account_number = ?,
            account_holder = ?,
            swift_code = ?,
            iban = ?,
            updated_at = NOW()
            WHERE id = 1
        ");

        $stmt->execute([
            $_POST['bank_name'],
            $_POST['account_number'],
            $_POST['account_holder'],
            $_POST['swift_code'],
            $_POST['iban']
        ]);

        // Log the change
        logBankSettingsChange($_SESSION['admin_id']);

        $message = "Bank settings updated successfully!";
    } catch (Exception $e) {
        $error = "Error updating bank settings: " . $e->getMessage();
    }
}

// Get current settings
$stmt = $pdo->prepare("SELECT * FROM bank_settings WHERE id = 1");
$stmt->execute();
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bank Settings - Admin</title>
    <link rel="stylesheet" href="admin_styles.css">
</head>
<body>
    <div class="admin-container">
        <h1>Bank Account Settings</h1>
        
        <?php if (isset($message)): ?>
            <div class="success-message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" class="settings-form">
            <div class="form-group">
                <label>Bank Name:</label>
                <input type="text" name="bank_name" value="<?php echo htmlspecialchars($settings['bank_name'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label>Account Number:</label>
                <input type="text" name="account_number" value="<?php echo htmlspecialchars($settings['account_number'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label>Account Holder:</label>
                <input type="text" name="account_holder" value="<?php echo htmlspecialchars($settings['account_holder'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label>SWIFT Code:</label>
                <input type="text" name="swift_code" value="<?php echo htmlspecialchars($settings['swift_code'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>IBAN:</label>
                <input type="text" name="iban" value="<?php echo htmlspecialchars($settings['iban'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Admin Password (Required for changes):</label>
                <input type="password" name="admin_password" required>
            </div>

            <button type="submit" class="save-btn">Save Settings</button>
        </form>

        <div class="balance-info">
            <h2>Current Balance: $<?php echo number_format($settings['balance'], 2); ?></h2>
            <div class="transaction-history">
                <h3>Recent Transactions</h3>
                <?php
                $stmt = $pdo->prepare("
                    SELECT * FROM bank_transactions 
                    ORDER BY created_at DESC 
                    LIMIT 10
                ");
                $stmt->execute();
                $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <table>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i', strtotime($transaction['created_at'])); ?></td>
                        <td><?php echo $transaction['transaction_type']; ?></td>
                        <td>$<?php echo number_format($transaction['amount'], 2); ?></td>
                        <td><?php echo $transaction['status']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
</body>
</html> 