<?php
session_start();
require_once 'db_config.php';
require_once 'vendor/autoload.php'; // For PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid session']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

try {
    // Simulate payment processing with a payment gateway
    $paymentSuccess = processPayment($data['payment']);
    
    if (!$paymentSuccess) {
        echo json_encode(['success' => false, 'error' => 'Payment failed']);
        exit();
    }

    $pdo->beginTransaction();

    // Generate 3 unique VIP codes (1 promotional + 2 invitation)
    $vipCodes = [];
    
    // Generate first code (promotional)
    $promoCode = generateUniqueVIPCode($pdo, 'PRO');
    $vipCodes[] = $promoCode;
    
    // Generate 2 invitation codes
    for ($i = 0; $i < 2; $i++) {
        $inviteCode = generateUniqueVIPCode($pdo, 'INV');
        $vipCodes[] = $inviteCode;
    }

    // Create membership record
    $stmt = $pdo->prepare("
        INSERT INTO vip_memberships (
            user_id, 
            plan_type, 
            annual_renewal_fee,
            next_renewal_date,
            billing_name,
            billing_email,
            billing_address,
            billing_city,
            billing_zip,
            card_last_four,
            card_expiry,
            start_date,
            member_codes
        ) VALUES (?, ?, 20.00, DATE_ADD(NOW(), INTERVAL 1 YEAR), ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
    ");
    
    // Get card details
    $cardNumber = preg_replace('/\s+/', '', $data['payment']['cardNumber']);
    $lastFour = substr($cardNumber, -4);
    $cardExpiry = $data['payment']['expiry'];
    
    $stmt->execute([
        $_SESSION['user_id'],
        $data['plan'],
        $data['billing']['fullName'],
        $data['billing']['email'],
        $data['billing']['address'],
        $data['billing']['city'],
        $data['billing']['zipCode'],
        $lastFour,
        $cardExpiry,
        json_encode($vipCodes) // Store codes as JSON
    ]);

    $membershipId = $pdo->lastInsertId();

    // Insert the codes into vip_codes table with their types
    foreach ($vipCodes as $index => $code) {
        $codeType = ($index === 0) ? 'promotion' : 'invitation';
        
        $stmt = $pdo->prepare("
            INSERT INTO vip_codes (
                code, 
                code_type,
                owner_member_id,
                times_used,
                created_at
            ) VALUES (?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$code, $codeType, $membershipId]);
        
        $codeId = $pdo->lastInsertId();
        
        // Link code to member
        $stmt = $pdo->prepare("
            INSERT INTO vip_member_codes (member_id, code_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$membershipId, $codeId]);
    }

    // Record the initial transaction
    $stmt = $pdo->prepare("
        INSERT INTO vip_renewal_transactions 
        (membership_id, amount, status) 
        VALUES (?, 0.00, 'successful')
    ");
    $stmt->execute([$membershipId]);

    // Update user's VIP status
    $stmt = $pdo->prepare("
        UPDATE users 
        SET is_vip = TRUE, 
            vip_tier = ?,
            vip_expiry = DATE_ADD(NOW(), INTERVAL 1 YEAR)
        WHERE id = ?
    ");
    $stmt->execute([$data['plan'], $_SESSION['user_id']]);

    $pdo->commit();

    // Send email with VIP codes
    $emailSent = sendVIPCodesEmail(
        $data['billing']['email'],
        $data['billing']['fullName'],
        $vipCodes,
        $data['plan']
    );

    if (!$emailSent) {
        error_log("Failed to send VIP codes email to: " . $data['billing']['email']);
    }

    // Update session with VIP status
    $_SESSION['is_vip'] = true;
    $_SESSION['vip_tier'] = $data['plan'];

    // If this user was invited, process referral bonus
    if (isset($_SESSION['used_invitation_code'])) {
        $stmt = $pdo->prepare("
            SELECT vc.owner_member_id 
            FROM vip_codes vc 
            WHERE vc.code = ? AND vc.code_type = 'invitation'
        ");
        $stmt->execute([$_SESSION['used_invitation_code']]);
        $inviterInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($inviterInfo) {
            calculateAndSaveReferralBonus(
                $inviterInfo['owner_member_id'],
                $_SESSION['user_id'],
                $data['plan']
            );
        }

        // Clear the session variable
        unset($_SESSION['used_invitation_code']);
    }

    // Process the payment through bank
    $success = processPayment(
        $amount,
        'vip_membership',
        $membershipId,
        "VIP Membership payment for user #" . $_SESSION['user_id']
    );

    if (!$success) {
        echo json_encode(['success' => false, 'error' => 'Payment processing failed']);
        exit();
    }

    echo json_encode([
        'success' => true,
        'orderId' => $membershipId,
        'vipCodes' => $vipCodes
    ]);

} catch(PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

function generateUniqueVIPCode($pdo, $prefix) {
    do {
        // Generate a random 8-character code
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = $prefix;
        for ($i = 0; $i < 5; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        // Check if code exists
        $stmt = $pdo->prepare("SELECT id FROM vip_codes WHERE code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetch());

    return $code;
}

function sendVIPCodesEmail($email, $name, $codes, $tier) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your-email@gmail.com'; // Your Gmail
        $mail->Password = 'your-app-password'; // Your Gmail app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('your-email@gmail.com', 'Pegasus Fashion');
        $mail->addAddress($email, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Pegasus Fashion VIP Codes';
        
        // Email body
        $body = "
            <h2>Welcome to Pegasus Fashion VIP!</h2>
            <p>Dear $name,</p>
            <p>Thank you for becoming a {$tier} VIP member. Here are your exclusive codes:</p>
            <div style='margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px;'>
                <h3>Your Personal Promotional Code</h3>
                <p style='font-size: 18px; color: #e44d26;'>{$codes[0]}</p>
                <p>Use this code during checkout for special VIP discounts!</p>
            </div>
            <div style='margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px;'>
                <h3>Your VIP Invitation Codes</h3>
                <p>Share these codes with friends to invite them to become VIP members:</p>
                <ul>
                    <li>Invitation Code 1: {$codes[1]}</li>
                    <li>Invitation Code 2: {$codes[2]}</li>
                </ul>
                <p>These invitation codes can be used anytime to grant VIP access!</p>
            </div>
            <p>Best regards,<br>Pegasus Fashion Team</p>
        ";
        
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email error: {$mail->ErrorInfo}");
        return false;
    }
}

// Function to simulate payment processing
function processPayment($paymentData) {
    // In a real application, this would integrate with a payment gateway like Stripe
    // For now, we'll simulate a successful payment
    try {
        // Validate card number (basic check)
        $cardNumber = preg_replace('/\s+/', '', $paymentData['cardNumber']);
        if (strlen($cardNumber) < 13 || strlen($cardNumber) > 16) {
            return false;
        }

        // Validate expiry (basic check)
        $expiry = explode('/', $paymentData['expiry']);
        if (count($expiry) !== 2) {
            return false;
        }

        // Validate CVV (basic check)
        if (strlen($paymentData['cvv']) !== 3) {
            return false;
        }

        // Simulate payment processing delay
        sleep(1);
        
        return true; // Payment successful
    } catch (Exception $e) {
        error_log("Payment processing error: " . $e->getMessage());
        return false;
    }
}

function getBonusPercentage($amount) {
    if ($amount >= 1000) {
        return 0.11; // 11% for investments of $1000 or more
    } elseif ($amount >= 650) {
        return 0.09; // 9% for investments between $650-$999
    } else {
        return 0.045; // 4.5% for investments between $325-$649
    }
}

function calculateAndSaveReferralBonus($inviterId, $invitedUserId, $amount) {
    global $pdo;
    
    try {
        // Check frozen amounts first for both codes and equal pairs
        checkFrozenAmountsFirst($inviterId);

        // Get the invitation code details
        $stmt = $pdo->prepare("
            SELECT vc.id, vc.current_pair_count, vc.invite_pairs, vc.frozen_bonus
            FROM vip_codes vc
            WHERE vc.code = ? AND vc.code_type = 'invitation'
        ");
        $stmt->execute([$_SESSION['used_invitation_code']]);
        $codeDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$codeDetails) return;

        // Check if this specific code has frozen bonus
        if ($codeDetails['frozen_bonus'] > 0) {
            error_log("Regular bonus processing blocked for code {$_SESSION['used_invitation_code']} - Has frozen bonus pending");
            
            // Process only the frozen bonus for this code
            $frozenBonusAmount = $codeDetails['frozen_bonus'] * 0.09;
            processFrozenBonus($inviterId, $codeDetails['id'], $frozenBonusAmount);
            return;
        }

        // Rest of the existing function code...
        // (Only proceeds if this specific code has no frozen bonus)
    } catch(PDOException $e) {
        error_log("Error in bonus processing: " . $e->getMessage());
    }
}

function sendBonusNotificationEmail($inviterId, $bonusAmount, $percentageText, $code, $pairNumber, $frozenAmount = 0) {
    try {
        // Get inviter's email
        $stmt = $pdo->prepare("
            SELECT vm.billing_email, vm.billing_name 
            FROM vip_memberships vm 
            WHERE vm.id = ?
        ");
        $stmt->execute([$inviterId]);
        $inviter = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$inviter) return;

        $mail = new PHPMailer(true);
        
        // Server settings (same as before)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your-email@gmail.com';
        $mail->Password = 'your-app-password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('your-email@gmail.com', 'Pegasus Fashion');
        $mail->addAddress($inviter['billing_email'], $inviter['billing_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'VIP Invitation Update';
        
        $body = "
            <h2>VIP Invitation Update</h2>
            <p>Dear {$inviter['billing_name']},</p>
        ";

        if ($bonusAmount > 0) {
            $body .= "
                <p>You've earned a {$percentageText} referral bonus of \${$bonusAmount} for completing your {$pairNumber}th pair of invitations using code {$code}!</p>
            ";
        }

        if ($frozenAmount > 0) {
            $body .= "
                <p>Due to different membership tiers in this pair, \${$frozenAmount} has been added to your frozen bonus balance.</p>
                <p>You'll receive 9% of this amount when you complete your next successful invitation with this code!</p>
            ";
        }

        $body .= "
            <p>Keep sharing your invitation code to earn more bonuses!</p>
            <p>Best regards,<br>Pegasus Fashion Team</p>
        ";
        
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
    } catch (Exception $e) {
        error_log("Error sending bonus notification email: " . $e->getMessage());
    }
}

// Add this function to handle investment increase after bonus
function increaseInvestmentAmount($inviterId, $bonusAmount) {
    global $pdo;
    
    try {
        // Get current membership details
        $stmt = $pdo->prepare("
            SELECT id, membership_amount 
            FROM vip_memberships 
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$inviterId]);
        $membership = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$membership) return;

        $currentAmount = floatval($membership['membership_amount']);

        // Only increase investment if amount is between $325 and $649
        if ($currentAmount >= 325 && $currentAmount <= 649) {
            $newAmount = $currentAmount + $bonusAmount;
            
            $pdo->beginTransaction();

            // Update membership amount
            $stmt = $pdo->prepare("
                UPDATE vip_memberships 
                SET membership_amount = ? 
                WHERE id = ?
            ");
            $stmt->execute([$newAmount, $inviterId]);

            // Record the automatic upgrade
            $stmt = $pdo->prepare("
                INSERT INTO vip_membership_upgrades (
                    membership_id,
                    previous_amount,
                    upgrade_amount,
                    total_amount,
                    upgrade_type
                ) VALUES (?, ?, ?, ?, 'bonus_reinvestment')
            ");
            $stmt->execute([
                $inviterId,
                $currentAmount,
                $bonusAmount,
                $newAmount
            ]);

            $pdo->commit();

            // Send notification email about the investment increase
            sendInvestmentIncreaseEmail(
                $inviterId, 
                $bonusAmount, 
                $newAmount
            );
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error increasing investment amount: " . $e->getMessage());
    }
}

function sendInvestmentIncreaseEmail($memberId, $increaseAmount, $newTotal) {
    try {
        // Get member's email
        $stmt = $pdo->prepare("
            SELECT vm.billing_email, vm.billing_name 
            FROM vip_memberships vm 
            WHERE vm.id = ?
        ");
        $stmt->execute([$memberId]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$member) return;

        $mail = new PHPMailer(true);
        
        // Server settings (same as before)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your-email@gmail.com';
        $mail->Password = 'your-app-password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('your-email@gmail.com', 'Pegasus Fashion');
        $mail->addAddress($member['billing_email'], $member['billing_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'VIP Investment Automatically Increased';
        
        $body = "
            <h2>Investment Increase Notification</h2>
            <p>Dear {$member['billing_name']},</p>
            <p>Great news! As part of our VIP bonus reinvestment program, your recent bonus of \${$increaseAmount} 
            has been automatically added to your VIP investment.</p>
            <p>Your investment details:</p>
            <ul>
                <li>Bonus Amount Added: \${$increaseAmount}</li>
                <li>New Total Investment: \${$newTotal}</li>
            </ul>
            <p>This automatic reinvestment helps you grow your VIP status and potential future benefits!</p>
            <p>Best regards,<br>Pegasus Fashion Team</p>
        ";
        
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
    } catch (Exception $e) {
        error_log("Error sending investment increase email: " . $e->getMessage());
    }
}

// Add this function to check frozen amounts first
function checkFrozenAmountsFirst($inviterId) {
    global $pdo;
    
    try {
        // Check invitation codes first
        $stmt = $pdo->prepare("
            SELECT id, code 
            FROM vip_codes 
            WHERE owner_member_id = ? 
            AND code_type = 'invitation'
        ");
        $stmt->execute([$inviterId]);
        $codes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check each code independently
        foreach ($codes as $code) {
            // Get last two invites for this code
            $stmt = $pdo->prepare("
                SELECT vm.membership_amount 
                FROM vip_invitations vi
                JOIN vip_memberships vm ON vi.invited_user_id = vm.user_id
                WHERE vi.code_id = ? 
                ORDER BY vi.invitation_date DESC
                LIMIT 2
            ");
            $stmt->execute([$code['id']]);
            $amounts = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($amounts) == 2) {
                $priceDifference = abs($amounts[0] - $amounts[1]);
                if ($priceDifference > 0) {
                    // Store frozen amount for this specific code
                    $stmt = $pdo->prepare("
                        UPDATE vip_codes 
                        SET frozen_bonus = frozen_bonus + ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$priceDifference, $code['id']]);

                    // Record frozen transaction
                    $stmt = $pdo->prepare("
                        INSERT INTO vip_frozen_bonus_transactions 
                        (code_id, inviter_id, amount, transaction_type)
                        VALUES (?, ?, ?, 'freeze')
                    ");
                    $stmt->execute([$code['id'], $inviterId, $priceDifference]);
                }
            }
        }

        // Check equal pairs independently
        $pairTotals = [];
        foreach ($codes as $code) {
            $stmt = $pdo->prepare("
                SELECT SUM(vm.membership_amount) as pair_total
                FROM vip_invitations vi
                JOIN vip_memberships vm ON vi.invited_user_id = vm.user_id
                WHERE vi.code_id = ?
                ORDER BY vi.invitation_date DESC
                LIMIT 2
            ");
            $stmt->execute([$code['id']]);
            $pairTotal = $stmt->fetch(PDO::FETCH_ASSOC)['pair_total'];
            if ($pairTotal) {
                $pairTotals[] = floatval($pairTotal);
            }
        }

        if (!empty($pairTotals)) {
            $minPairTotal = min($pairTotals);
            $maxPairTotal = max($pairTotals);
            
            if ($maxPairTotal > $minPairTotal) {
                $priceDifference = $maxPairTotal - $minPairTotal;
                
                // Store frozen bonus for equal pairs
                $stmt = $pdo->prepare("
                    INSERT INTO vip_equal_pairs_bonuses (
                        member_id,
                        pair_count,
                        bonus_amount,
                        frozen_bonus_amount,
                        total_bonus_amount
                    ) VALUES (?, 0, 0, ?, ?)
                ");
                $stmt->execute([
                    $inviterId,
                    $priceDifference,
                    $priceDifference * 0.09
                ]);
            }
        }
    } catch (PDOException $e) {
        error_log("Error checking frozen amounts: " . $e->getMessage());
    }
}

// Modify the calculateAndSaveReferralBonus function to call this first
function calculateAndSaveReferralBonus($inviterId, $invitedUserId, $amount) {
    global $pdo;
    
    try {
        // Check frozen amounts first for both codes and equal pairs
        checkFrozenAmountsFirst($inviterId);

        // Get the invitation code details
        $stmt = $pdo->prepare("
            SELECT vc.id, vc.current_pair_count, vc.invite_pairs, vc.frozen_bonus
            FROM vip_codes vc
            WHERE vc.code = ? AND vc.code_type = 'invitation'
        ");
        $stmt->execute([$_SESSION['used_invitation_code']]);
        $codeDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$codeDetails) return;

        // Check if this specific code has frozen bonus
        if ($codeDetails['frozen_bonus'] > 0) {
            error_log("Regular bonus processing blocked for code {$_SESSION['used_invitation_code']} - Has frozen bonus pending");
            
            // Process only the frozen bonus for this code
            $frozenBonusAmount = $codeDetails['frozen_bonus'] * 0.09;
            processFrozenBonus($inviterId, $codeDetails['id'], $frozenBonusAmount);
            return;
        }

        // Rest of the existing function code...
        // (Only proceeds if this specific code has no frozen bonus)
    } catch(PDOException $e) {
        error_log("Error in bonus processing: " . $e->getMessage());
    }
}

function sendEqualPairsBonusEmail($memberId, $bonusAmount, $frozenBonus, $totalBonus, $pairCount, $percentage) {
    try {
        // Get member's email
        $stmt = $pdo->prepare("
            SELECT vm.billing_email, vm.billing_name 
            FROM vip_memberships vm 
            WHERE vm.id = ?
        ");
        $stmt->execute([$memberId]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$member) return;

        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your-email@gmail.com';
        $mail->Password = 'your-app-password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('your-email@gmail.com', 'Pegasus Fashion');
        $mail->addAddress($member['billing_email'], $member['billing_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Equal Pairs Bonus Achieved!';
        
        $percentageText = ($percentage * 100) . '%';
        
        $body = "
            <h2>Congratulations on Your Equal Pairs Bonus!</h2>
            <p>Dear {$member['billing_name']},</p>
            <p>You've achieved {$pairCount} successful pairs across all your invitation codes!</p>
            <p>Your bonus details:</p>
            <ul>
                <li>Base Bonus ({$percentageText}): \${$bonusAmount}</li>
        ";

        if ($frozenBonus > 0) {
            $body .= "<li>Unfrozen Bonus Amount: \${$frozenBonus}</li>";
        }

        $body .= "
                <li>Total Bonus Amount: \${$totalBonus}</li>
            </ul>
            <p>Keep growing your network evenly to earn more bonuses!</p>
            <p>Best regards,<br>Pegasus Fashion Team</p>
        ";
        
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
    } catch (Exception $e) {
        error_log("Error sending equal pairs bonus email: " . $e->getMessage());
    }
}

// Add this function to handle frozen equal pairs bonus
function checkAndReleaseFrozenEqualPairsBonus($inviterId) {
    global $pdo;
    
    try {
        // Get the latest equal pairs bonus with frozen amount
        $stmt = $pdo->prepare("
            SELECT id, frozen_bonus_amount 
            FROM vip_equal_pairs_bonuses 
            WHERE member_id = ? 
            AND frozen_bonus_amount > 0 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$inviterId]);
        $frozenBonus = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($frozenBonus && $frozenBonus['frozen_bonus_amount'] > 0) {
            $pdo->beginTransaction();

            // Record the release of frozen bonus
            $stmt = $pdo->prepare("
                INSERT INTO vip_equal_pairs_bonus_releases (
                    equal_pairs_bonus_id,
                    member_id,
                    released_amount,
                    created_at
                ) VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([
                $frozenBonus['id'],
                $inviterId,
                $frozenBonus['frozen_bonus_amount']
            ]);

            // Reset frozen bonus amount
            $stmt = $pdo->prepare("
                UPDATE vip_equal_pairs_bonuses 
                SET frozen_bonus_amount = 0 
                WHERE id = ?
            ");
            $stmt->execute([$frozenBonus['id']]);

            // If investment amount is between $325 and $649, increase it
            $stmt = $pdo->prepare("
                SELECT membership_amount 
                FROM vip_memberships 
                WHERE id = ?
            ");
            $stmt->execute([$inviterId]);
            $membership = $stmt->fetch(PDO::FETCH_ASSOC);
            $currentAmount = floatval($membership['membership_amount']);

            if ($currentAmount >= 325 && $currentAmount <= 649) {
                increaseInvestmentAmount($inviterId, $frozenBonus['frozen_bonus_amount']);
            }

            $pdo->commit();

            // Send notification email
            sendFrozenBonusReleaseEmail(
                $inviterId,
                $frozenBonus['frozen_bonus_amount']
            );
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error releasing frozen equal pairs bonus: " . $e->getMessage());
    }
}

function sendFrozenBonusReleaseEmail($memberId, $releasedAmount) {
    try {
        // Get member's email
        $stmt = $pdo->prepare("
            SELECT vm.billing_email, vm.billing_name 
            FROM vip_memberships vm 
            WHERE vm.id = ?
        ");
        $stmt->execute([$memberId]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$member) return;

        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your-email@gmail.com';
        $mail->Password = 'your-app-password';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('your-email@gmail.com', 'Pegasus Fashion');
        $mail->addAddress($member['billing_email'], $member['billing_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Frozen Equal Pairs Bonus Released!';
        
        $body = "
            <h2>Frozen Bonus Released!</h2>
            <p>Dear {$member['billing_name']},</p>
            <p>Great news! Your frozen equal pairs bonus of \${$releasedAmount} has been released!</p>
            <p>This bonus has been added to your account due to your continued success in growing your VIP network.</p>
            <p>Keep up the great work!</p>
            <p>Best regards,<br>Pegasus Fashion Team</p>
        ";
        
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
    } catch (Exception $e) {
        error_log("Error sending frozen bonus release email: " . $e->getMessage());
    }
}

// Add this function to check if member has any frozen bonus
function hasFrozenBonus($inviterId, $codeId = null) {
    global $pdo;
    
    try {
        if ($codeId) {
            // Check for specific code frozen bonus
            $stmt = $pdo->prepare("
                SELECT frozen_bonus 
                FROM vip_codes 
                WHERE id = ? AND frozen_bonus > 0
            ");
            $stmt->execute([$codeId]);
        } else {
            // Check for equal pairs frozen bonus
            $stmt = $pdo->prepare("
                SELECT frozen_bonus_amount 
                FROM vip_equal_pairs_bonuses 
                WHERE member_id = ? 
                AND frozen_bonus_amount > 0 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$inviterId]);
        }
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && ($result['frozen_bonus'] > 0 || $result['frozen_bonus_amount'] > 0);
    } catch (PDOException $e) {
        error_log("Error checking frozen bonus: " . $e->getMessage());
        return false;
    }
}

// Add helper function to process frozen bonus for a specific code
function processFrozenBonus($inviterId, $codeId, $frozenBonusAmount) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();

        // Record frozen bonus payment
        $stmt = $pdo->prepare("
            INSERT INTO vip_code_bonus_payments 
            (code_id, inviter_id, pair_number, bonus_amount, bonus_type)
            VALUES (?, ?, ?, ?, 'frozen')
        ");
        $stmt->execute([
            $codeId,
            $inviterId,
            0, // No pair number for frozen bonus
            $frozenBonusAmount
        ]);

        // Reset frozen bonus
        $stmt = $pdo->prepare("
            UPDATE vip_codes 
            SET frozen_bonus = 0 
            WHERE id = ?
        ");
        $stmt->execute([$codeId]);

        // Handle investment increase if applicable
        $stmt = $pdo->prepare("
            SELECT membership_amount 
            FROM vip_memberships 
            WHERE id = ?
        ");
        $stmt->execute([$inviterId]);
        $membership = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentAmount = floatval($membership['membership_amount']);

        if ($currentAmount >= 325 && $currentAmount <= 649) {
            increaseInvestmentAmount($inviterId, $frozenBonusAmount);
        }

        $pdo->commit();

        // Send notification
        sendBonusNotificationEmail(
            $inviterId, 
            0, // Regular bonus is 0
            'N/A',
            $code,
            0, // No pair number
            $frozenBonusAmount
        );
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error processing frozen bonus: " . $e->getMessage());
    }
}
?> 