<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$message = '';
$message_type = 'error';
$verified = false;

if (empty($_SESSION['verification_user_id']) || empty($_SESSION['verification_email'])) {
    header('Location: register.php');
    exit;
}

$user_id = (int) $_SESSION['verification_user_id'];
$email = (string) $_SESSION['verification_email'];

if (empty($_SESSION['email_verify_csrf'])) {
    $_SESSION['email_verify_csrf'] = bin2hex(random_bytes(32));
}

function verify_escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function verify_send_email($email, $name, $code) {
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';

    $username = getenv('SMTP_USER') ?: '';
    $password = getenv('SMTP_PASSWORD') ?: '';
    $from = getenv('SMTP_FROM') ?: $username;
    $host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $port = (int) (getenv('SMTP_PORT') ?: 587);

    if ($username === '' || $password === '' || $from === '') {
        throw new RuntimeException('SMTP environment variables are missing.');
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $username;
    $mail->Password = $password;
    $mail->Port = $port;
    $mail->SMTPSecure = $port === 465
        ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($from, 'PAW Care');
    $mail->addAddress($email, $name);
    $mail->isHTML(true);
    $mail->Subject = 'PAW Care - Email Verification Code';
    $safe_name = verify_escape($name);
    $safe_code = verify_escape($code);
    $mail->Body = '<div style="font-family:Arial,sans-serif"><h2 style="color:#d4a017">PAW Care</h2><p>Hello ' . $safe_name . ',</p><p>Your verification code is:</p><p style="font-size:30px;font-weight:bold;letter-spacing:6px;color:#d4a017">' . $safe_code . '</p><p>This code expires in 10 minutes.</p><p>If you did not request this, please ignore this email.</p></div>';
    $mail->AltBody = "Hello $name,\nYour PAW Care verification code is: $code\nThis code expires in 10 minutes.";
    $mail->send();
}

try {
    $stmt = $conn->prepare('SELECT id, email_verified FROM users WHERE id = ? AND email = ? LIMIT 1');
    $stmt->bind_param('is', $user_id, $email);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$account) {
        unset($_SESSION['verification_user_id'], $_SESSION['verification_email']);
        header('Location: register.php');
        exit;
    }
    if ((int) $account['email_verified'] === 1) {
        $verified = true;
        $message = 'Your account is already verified.';
        $message_type = 'success';
    }
} catch (Throwable $e) {
    error_log('Email verification lookup error: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load verification details. Please try again later.');
}

if (!$verified && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    $action = (string) ($_POST['action'] ?? '');

    if (!hash_equals($_SESSION['email_verify_csrf'], $csrf)) {
        $message = 'Invalid request. Refresh the page and try again.';
    } elseif ($action === 'verify') {
        $otp = trim((string) ($_POST['otp'] ?? ''));
        if (!preg_match('/^[0-9]{6}$/', $otp)) {
            $message = 'Please enter a valid 6-digit OTP.';
        } else {
            try {
                $stmt = $conn->prepare('SELECT verification_code, verification_expires FROM users WHERE id = ? AND email = ? AND email_verified = 0 LIMIT 1');
                $stmt->bind_param('is', $user_id, $email);
                $stmt->execute();
                $data = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$data || empty($data['verification_code']) || empty($data['verification_expires'])) {
                    $message = 'No active verification code. Please request a new one.';
                } else {
                    $stmt = $conn->prepare('SELECT (CAST(? AS DATETIME) >= UTC_TIMESTAMP()) AS valid_time');
                    $stmt->bind_param('s', $data['verification_expires']);
                    $stmt->execute();
                    $not_expired = (int) $stmt->get_result()->fetch_assoc()['valid_time'] === 1;
                    $stmt->close();

                    if (!$not_expired) {
                        $message = 'This OTP has expired. Please request a new one.';
                    } elseif (!hash_equals((string) $data['verification_code'], $otp)) {
                        $message = 'Invalid verification code. Please try again.';
                    } else {
                        $stmt = $conn->prepare('UPDATE users SET email_verified = 1, verification_code = NULL, verification_expires = NULL WHERE id = ? AND email = ? AND email_verified = 0 AND verification_code = ? AND verification_expires >= UTC_TIMESTAMP()');
                        $stmt->bind_param('iss', $user_id, $email, $otp);
                        $stmt->execute();
                        $verified = $stmt->affected_rows === 1;
                        $stmt->close();
                        if ($verified) {
                            unset($_SESSION['verification_user_id'], $_SESSION['verification_email']);
                            $message = 'Account verified successfully!';
                            $message_type = 'success';
                        } else {
                            $message = 'Verification failed or code expired. Please try again.';
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log('Email OTP verification error: ' . $e->getMessage());
                $message = 'Verification is temporarily unavailable. Please try again.';
            }
        }
    } elseif ($action === 'resend') {
        $last_sent = (int) ($_SESSION['verify_resend_at'] ?? 0);
        if (time() - $last_sent < 60) {
            $message = 'Please wait 60 seconds before requesting another code.';
        } else {
            $_SESSION['verify_resend_at'] = time();
            try {
                $name_column = 'name';
                $columns = $conn->query("SHOW COLUMNS FROM users");
                $names = [];
                while ($column = $columns->fetch_assoc()) {
                    $names[] = $column['Field'];
                }
                if (in_array('full_name', $names, true)) {
                    $name_column = 'full_name';
                } elseif (!in_array('name', $names, true)) {
                    $name_column = in_array('username', $names, true) ? 'username' : '';
                }
                $display_name = 'User';
                if ($name_column !== '') {
                    $stmt = $conn->prepare("SELECT `$name_column` AS display_name FROM users WHERE id = ? AND email = ? AND email_verified = 0 LIMIT 1");
                    $stmt->bind_param('is', $user_id, $email);
                    $stmt->execute();
                    $record = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($record && !empty($record['display_name'])) {
                        $display_name = (string) $record['display_name'];
                    }
                }

                $new_code = sprintf('%06d', random_int(0, 999999));
                $stmt = $conn->prepare('UPDATE users SET verification_code = ?, verification_expires = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE) WHERE id = ? AND email = ? AND email_verified = 0');
                $stmt->bind_param('sis', $new_code, $user_id, $email);
                $stmt->execute();
                $updated = $stmt->affected_rows === 1;
                $stmt->close();
                if (!$updated) {
                    throw new RuntimeException('Pending user not found.');
                }
                verify_send_email($email, $display_name, $new_code);
                $message = 'A new verification code has been sent. Check your inbox and spam folder.';
                $message_type = 'success';
            } catch (Throwable $e) {
                error_log('Verification resend error: ' . $e->getMessage());
                $message = 'Unable to send verification email. Please check SMTP configuration or try again later.';
            }
        }
    }
}

$safe_email = verify_escape($email);
$safe_message = verify_escape($message);
$safe_csrf = verify_escape($_SESSION['email_verify_csrf']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Verify Email - PAW Care</title>

<style>
* {
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f4f7f6;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    margin: 0;
    padding: 20px;
    color: #333;
}

.verification-container {
    background: #ffffff;
    padding: 40px;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 430px;
    text-align: center;
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(15px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.logo-icon {
    width: 72px;
    height: 72px;
    margin: 0 auto 20px;
    background: #fff8e5;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.logo-icon svg {
    width: 38px;
    height: 38px;
    fill: #d4a017;
}

h2 {
    color: #333;
    font-size: 25px;
    font-weight: 700;
    margin: 0 0 12px;
}

.subtitle {
    color: #777;
    font-size: 14px;
    line-height: 1.7;
    margin: 0 0 25px;
}

.email-display {
    background: #f8f9fa;
    border: 1px solid #eeeeee;
    padding: 12px;
    border-radius: 8px;
    color: #555;
    font-size: 13px;
    font-weight: 600;
    overflow-wrap: anywhere;
    margin-bottom: 25px;
}

.otp-label {
    display: block;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 10px;
    color: #444;
}

.otp-input {
    width: 100%;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    text-align: center;
    font-size: 25px;
    font-weight: 700;
    letter-spacing: 12px;
    color: #333;
    background: #fff;
    outline: none;
    transition: 0.3s;
    margin-bottom: 20px;
}

.otp-input:focus {
    border-color: #d4a017;
    box-shadow: 0 0 0 3px rgba(212, 160, 23, 0.12);
}

.otp-input::placeholder {
    font-size: 14px;
    font-weight: 400;
    letter-spacing: normal;
    color: #aaa;
}

.verify-button,
.login-button {
    width: 100%;
    display: block;
    padding: 14px;
    background: #d4a017;
    border: none;
    color: white;
    font-weight: 700;
    font-size: 14px;
    border-radius: 7px;
    cursor: pointer;
    text-decoration: none;
    transition: 0.3s;
}

.verify-button:hover,
.login-button:hover {
    background: #b8870f;
    transform: translateY(-1px);
}

.verify-button:disabled {
    opacity: 0.65;
    cursor: not-allowed;
    transform: none;
}

.message-box {
    padding: 13px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 13px;
    line-height: 1.6;
}

.error-message {
    background: #fff0f0;
    border: 1px solid #ffcccc;
    color: #c62828;
}

.success-message {
    background: #eaf8ee;
    border: 1px solid #b8e3c2;
    color: #218838;
}

.info-text {
    margin-top: 22px;
    font-size: 12px;
    color: #999;
    line-height: 1.7;
}

.back-link {
    display: inline-block;
    margin-top: 20px;
    color: #777;
    text-decoration: none;
    font-size: 13px;
}

.back-link:hover {
    color: #d4a017;
}

.success-icon {
    width: 85px;
    height: 85px;
    background: #eaf8ee;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 25px;
    animation: popIn 0.5s ease;
}

.success-icon svg {
    width: 48px;
    height: 48px;
    stroke: #28a745;
    fill: none;
    stroke-width: 3;
    stroke-linecap: round;
    stroke-linejoin: round;
}

@keyframes popIn {
    from {
        opacity: 0;
        transform: scale(0.6);
    }

    to {
        opacity: 1;
        transform: scale(1);
    }
}

.success-title {
    color: #333;
    margin-bottom: 12px;
}

.success-description {
    color: #777;
    font-size: 14px;
    line-height: 1.8;
    margin-bottom: 25px;
}

.security-note {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 7px;
    margin-top: 22px;
    color: #999;
    font-size: 12px;
}

@media (max-width: 480px) {
    .verification-container {
        padding: 30px 22px;
    }

    h2 {
        font-size: 22px;
    }

    .otp-input {
        font-size: 22px;
        letter-spacing: 9px;
    }
}
</style>
</head>

<body>

<div class="verification-container">

<?php if ($verified): ?>

    <div class="success-icon">
        <svg viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M7 12.5l3.2 3.2L17 9"></path>
        </svg>
    </div>

    <h2 class="success-title">
        Email Verified!
    </h2>

    <p class="success-description">
        Congratulations! Your email address has been
        successfully verified.
        Your PAW Care account is now ready to use.
    </p>

    <div class="message-box success-message">
        <?php echo $safe_message; ?>
    </div>

    <a href="login.php" class="login-button">
        Continue to Login
    </a>

    <div class="security-note">
        Your account is now verified and ready.
    </div>

<?php else: ?>

    <div class="logo-icon">
        <svg viewBox="0 0 24 24">
            <circle cx="4.5" cy="11" r="2"></circle>
            <circle cx="9" cy="6" r="2"></circle>
            <circle cx="15" cy="6" r="2"></circle>
            <circle cx="19.5" cy="11" r="2"></circle>
            <path d="M12 12c-2.5 0-4 2-5.5 4.2C5 18.4 6.6 21 9 21c1.2 0 2-.7 3-.7s1.8.7 3 .7c2.4 0 4-2.6 2.5-4.8C16 14 14.5 12 12 12z"></path>
        </svg>
    </div>

    <h2>Verify Your Email</h2>

    <p class="subtitle">
        Enter the 6-digit verification code sent
        to your email address.
        Enter the code below to activate your account.
    </p>

    <div class="email-display">
        <?php echo $safe_email; ?>
    </div>

    <?php if ($message !== ""): ?>

        <div class="message-box <?php echo $message_type === "success" ? "success-message" : "error-message"; ?>">
            <?php echo $safe_message; ?>
        </div>

    <?php endif; ?>

    <form method="POST" id="otpForm">
        <input type="hidden" name="csrf_token" value="<?php echo $safe_csrf; ?>">
        <input type="hidden" name="action" value="verify">

        <label class="otp-label" for="otp">
            Verification Code
        </label>

        <input
            class="otp-input"
            type="text"
            id="otp"
            name="otp"
            placeholder="000000"
            maxlength="6"
            minlength="6"
            pattern="[0-9]{6}"
            inputmode="numeric"
            autocomplete="one-time-code"
            required
            autofocus
        >

        <button
            type="submit"
            class="verify-button"
            id="verifyButton"
        >
            Verify Code
        </button>

    </form>

    <form method="POST" style="margin-top:12px">
        <input type="hidden" name="csrf_token" value="<?php echo $safe_csrf; ?>">
        <input type="hidden" name="action" value="resend">
        <button type="submit" class="verify-button" style="background:#555">Resend Code</button>
    </form>

    <p class="info-text">
        Your verification code is valid for 10 minutes.
        Please check your inbox or spam folder.
    </p>

    <a href="register.php" class="back-link">
        ← Back to Registration
    </a>

    <div class="security-note">
        Secure Email Verification
    </div>

<?php endif; ?>

</div>

<script>
const otpInput = document.getElementById('otp');
const otpForm = document.getElementById('otpForm');
const verifyButton = document.getElementById('verifyButton');

if (otpInput) {
    otpInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
    });
}

if (otpForm && verifyButton) {
    otpForm.addEventListener('submit', function (event) {
        if (!otpForm.checkValidity()) {
            return;
        }

        verifyButton.disabled = true;
        verifyButton.textContent = 'Verifying...';
    });
}
</script>

</body>
</html>
