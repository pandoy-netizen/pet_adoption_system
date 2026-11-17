<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    if (headers_sent($file, $line)) {
        error_log("OTP headers already sent: $file:$line");
        http_response_code(500);
        exit("Session initialization error. Check PHP file encoding.");
    }

    if (!session_start()) {
        http_response_code(500);
        exit("Unable to initialize session.");
    }
}

require_once __DIR__ . '/config/db.php';

$message = "";
$verified = false;

if (
    !isset($_SESSION['verification_user_id']) ||
    !isset($_SESSION['verification_email'])
) {
    header("Location: register.php");
    exit;
}

$user_id = (int) $_SESSION['verification_user_id'];
$email = (string) $_SESSION['verification_email'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $entered_otp = trim($_POST['otp'] ?? '');

    if (!preg_match('/^[0-9]{6}$/', $entered_otp)) {

        $message = "Please enter a valid 6-digit OTP.";

    } else {

        try {

            $stmt = $conn->prepare(
                "SELECT
                    id,
                    email_verified,
                    verification_code,
                    verification_expires
                 FROM users
                 WHERE id = ?
                   AND email = ?
                 LIMIT 1"
            );

            $stmt->bind_param("is", $user_id, $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $row = $result->fetch_assoc();

            $stmt->close();

            if (!$row) {

                $message = "User not found.";

            } elseif ((int) $row['email_verified'] === 1) {

                $verified = true;
                $message = "Your account is already verified.";

            } elseif (
                empty($row['verification_code']) ||
                empty($row['verification_expires'])
            ) {

                $message = "No verification code found.";

            } elseif (
                strtotime($row['verification_expires']) === false ||
                strtotime($row['verification_expires']) < time()
            ) {

                $message = "This OTP has expired. Please request a new one.";

            } elseif (
                !hash_equals(
                    (string) $row['verification_code'],
                    $entered_otp
                )
            ) {

                $message = "Invalid verification code. Please try again.";

            } else {

                $update_stmt = $conn->prepare(
                    "UPDATE users
                     SET email_verified = 1,
                         verification_code = NULL,
                         verification_expires = NULL
                     WHERE id = ?
                       AND email = ?
                       AND email_verified = 0"
                );

                $update_stmt->bind_param(
                    "is",
                    $user_id,
                    $email
                );

                $update_stmt->execute();

                $verified = (
                    $update_stmt->affected_rows === 1
                );

                $update_stmt->close();

                if ($verified) {

                    unset(
                        $_SESSION['verification_user_id'],
                        $_SESSION['verification_email']
                    );

                    $message = "Account verified successfully!";

                } else {

                    $message = "Verification failed. Please try again.";

                }
            }

        } catch (mysqli_sql_exception $e) {

            error_log("OTP Database Error: " . $e->getMessage());
            $message = "Database error. Please try again.";

        } catch (Throwable $e) {

            error_log("OTP Verification Error: " . $e->getMessage());
            $message = "Verification failed. Please try again.";
        }
    }
}

$safe_email = htmlspecialchars(
    $email,
    ENT_QUOTES,
    'UTF-8'
);

$safe_message = htmlspecialchars(
    $message,
    ENT_QUOTES,
    'UTF-8'
);
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
        We've sent a 6-digit verification code
        to your email address.
        Enter the code below to activate your account.
    </p>

    <div class="email-display">
        <?php echo $safe_email; ?>
    </div>

    <?php if ($message !== ""): ?>

        <div class="message-box error-message">
            <?php echo $safe_message; ?>
        </div>

    <?php endif; ?>

    <form method="POST" id="otpForm">

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
