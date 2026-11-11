<?php
session_start();

/* =========================
   DATABASE CONNECTION
========================= */
$host = "localhost";
$user = "root";
$password = "";
$database = "pet_adoption_rescue";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

/* =========================
   CHECK VERIFICATION SESSION
========================= */
if (!isset($_SESSION['verification_user_id']) || !isset($_SESSION['verification_email'])) {
    header("Location: register.php");
    exit;
}

$user_id = (int) $_SESSION['verification_user_id'];
$email = $_SESSION['verification_email'];

$message = "";
$message_type = "";

/* =========================
   AUTOMATICALLY DETERMINE NAME COLUMN
========================= */
$name_column = "";

$check_full_name = mysqli_query(
    $conn,
    "SHOW COLUMNS FROM users LIKE 'full_name'"
);

if ($check_full_name && mysqli_num_rows($check_full_name) > 0) {
    $name_column = "full_name";
} else {
    $check_name = mysqli_query(
        $conn,
        "SHOW COLUMNS FROM users LIKE 'name'"
    );

    if ($check_name && mysqli_num_rows($check_name) > 0) {
        $name_column = "name";
    }
}

/* =========================================================
   VERIFY OTP
========================================================= */
if (isset($_POST['verify'])) {

    $entered_code = trim($_POST['verification_code'] ?? "");

    if (empty($entered_code)) {

        $message = "Please enter the verification code.";
        $message_type = "error";

    } elseif (!preg_match('/^[0-9]{6}$/', $entered_code)) {

        $message = "The verification code must contain exactly 6 digits.";
        $message_type = "error";

    } else {

        /* =========================
           GET USER VERIFICATION DATA
        ========================= */
        $stmt = $conn->prepare("
            SELECT id, email, email_verified, verification_code, verification_expires
            FROM users
            WHERE id = ? AND email = ?
            LIMIT 1
        ");

        $stmt->bind_param("is", $user_id, $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {

            $message = "Verification account not found. Please register again.";
            $message_type = "error";

        } else {

            $account = $result->fetch_assoc();

            /* =========================
               CHECK IF ALREADY VERIFIED
            ========================= */
            if ((int)$account['email_verified'] === 1) {

                unset($_SESSION['verification_user_id']);
                unset($_SESSION['verification_email']);

                header("Location: login.php?verified=1");
                exit;
            }

            /* =========================
               CHECK CODE
            ========================= */
            if (
                empty($account['verification_code']) ||
                $entered_code !== $account['verification_code']
            ) {

                $message = "Incorrect verification code. Please try again.";
                $message_type = "error";

            }

            /* =========================
               CHECK EXPIRATION
            ========================= */
            elseif (
                empty($account['verification_expires']) ||
                strtotime($account['verification_expires']) < time()
            ) {

                $message = "Your verification code has expired. Please request a new code.";
                $message_type = "error";

            }

            /* =========================
               VERIFICATION SUCCESS
            ========================= */
            else {

                $update = $conn->prepare("
                    UPDATE users
                    SET email_verified = 1,
                        verification_code = NULL,
                        verification_expires = NULL
                    WHERE id = ?
                ");

                $update->bind_param("i", $user_id);

                if ($update->execute()) {

                    /* Clear verification session */
                    unset($_SESSION['verification_user_id']);
                    unset($_SESSION['verification_email']);

                    header("Location: login.php?verified=1");
                    exit;

                } else {

                    $message = "Unable to verify your account. Please try again.";
                    $message_type = "error";
                }
            }
        }
    }
}

/* =========================================================
   RESEND VERIFICATION CODE
========================================================= */
if (isset($_POST['resend'])) {

    /* Get user's name */
    $fullname = "User";

    if ($name_column !== "") {

        $stmt = $conn->prepare("
            SELECT `$name_column`, email_verified
            FROM users
            WHERE id = ? AND email = ?
            LIMIT 1
        ");

        $stmt->bind_param("is", $user_id, $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $user_data = $result->fetch_assoc();

            if ((int)$user_data['email_verified'] === 1) {

                unset($_SESSION['verification_user_id']);
                unset($_SESSION['verification_email']);

                header("Location: login.php?verified=1");
                exit;
            }

            if (!empty($user_data[$name_column])) {
                $fullname = $user_data[$name_column];
            }
        }
    }

    /* =========================
       GENERATE NEW OTP
    ========================= */
    $new_code = sprintf("%06d", random_int(0, 999999));

    $new_expiration = date(
        "Y-m-d H:i:s",
        strtotime("+10 minutes")
    );

    /* =========================
       SAVE NEW OTP
    ========================= */
    $update = $conn->prepare("
        UPDATE users
        SET verification_code = ?,
            verification_expires = ?
        WHERE id = ? AND email = ?
    ");

    $update->bind_param(
        "ssis",
        $new_code,
        $new_expiration,
        $user_id,
        $email
    );

    if ($update->execute()) {

        /* =========================
           SEND EMAIL
        ========================= */

        require 'PHPMailer/src/Exception.php';
        require 'PHPMailer/src/PHPMailer.php';
        require 'PHPMailer/src/SMTP.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {

            $mail->isSMTP();

            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;

            /*
             * IMPORTANT:
             * Use your Gmail address here.
             */
            $mail->Username = 'your_email@gmail.com';

            /*
             * Use your NEW Google App Password here.
             * Do NOT use your normal Gmail password.
             */
            $mail->Password = 'YOUR_NEW_GOOGLE_APP_PASSWORD';

            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom(
                'your_email@gmail.com',
                'PAW Care'
            );

            $mail->addAddress(
                $email,
                $fullname
            );

            $mail->isHTML(true);

            $mail->Subject = 'PAW Care - New Verification Code';

            $mail->Body = "
                <div style='font-family: Arial, sans-serif;'>
                    <h2 style='color:#d4a017;'>PAW Care</h2>

                    <p>Hello <b>" . htmlspecialchars($fullname) . "</b>,</p>

                    <p>You requested a new email verification code.</p>

                    <p>Your new verification code is:</p>

                    <div style='
                        font-size:32px;
                        font-weight:bold;
                        letter-spacing:8px;
                        color:#d4a017;
                        margin:20px 0;
                    '>
                        $new_code
                    </div>

                    <p>This code will expire in <b>10 minutes</b>.</p>

                    <p>If you did not request this code, you can safely ignore this email.</p>

                    <br>

                    <p>
                        Thank you,<br>
                        <b>PAW Care</b>
                    </p>
                </div>
            ";

            $mail->AltBody =
                "Hello $fullname,\n\n" .
                "Your new PAW Care verification code is: $new_code\n\n" .
                "This code will expire in 10 minutes.\n\n" .
                "Thank you,\nPAW Care";

            $mail->send();

            $message = "A new verification code has been sent to your email.";
            $message_type = "success";

        } catch (Exception $e) {

            $message = "The new code was generated, but the email could not be sent. Please check your SMTP settings.";
            $message_type = "error";
        }

    } else {

        $message = "Unable to generate a new verification code.";
        $message_type = "error";
    }
}
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
    font-family: 'Segoe UI', sans-serif;
    background: #f4f7f6;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    margin: 0;
}

.verify-container {
    background: white;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    width: 380px;
    max-width: 90%;
    text-align: center;
}

.icon {
    width: 70px;
    height: 70px;
    background: #d4a017;
    color: white;
    border-radius: 50%;
    margin: 0 auto 20px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 32px;
}

h2 {
    color: #333;
    margin-bottom: 10px;
}

.description {
    color: #666;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 20px;
}

.email {
    font-weight: bold;
    color: #333;
    word-break: break-word;
}

input {
    width: 100%;
    padding: 14px;
    margin-bottom: 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    box-sizing: border-box;
    text-align: center;
    font-size: 24px;
    letter-spacing: 8px;
}

input:focus {
    outline: none;
    border-color: #d4a017;
}

button {
    width: 100%;
    padding: 13px;
    background: #d4a017;
    border: none;
    color: white;
    font-weight: bold;
    border-radius: 6px;
    cursor: pointer;
    transition: 0.3s;
}

button:hover {
    background: #b8870f;
}

.resend-button {
    margin-top: 12px;
    background: #555;
}

.resend-button:hover {
    background: #333;
}

.error {
    color: #d00000;
    background: #ffeaea;
    padding: 10px;
    border-radius: 6px;
    font-size: 14px;
    margin-bottom: 15px;
}

.success {
    color: #198754;
    background: #e8f7ee;
    padding: 10px;
    border-radius: 6px;
    font-size: 14px;
    margin-bottom: 15px;
}

.back-link {
    display: block;
    margin-top: 18px;
    color: #666;
    text-decoration: none;
    font-size: 13px;
}

.back-link:hover {
    color: #d4a017;
}

.note {
    margin-top: 20px;
    color: #888;
    font-size: 12px;
    line-height: 1.5;
}

</style>

</head>

<body>

<div class="verify-container">

    <div class="icon">
        ✉
    </div>

    <h2>Verify Your Email</h2>

    <div class="description">

        We sent a 6-digit verification code to:

        <div class="email">
            <?php echo htmlspecialchars($email); ?>
        </div>

    </div>

    <?php if ($message != ""): ?>

        <div class="<?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>

    <!-- VERIFY FORM -->
    <form method="POST">

        <input
            type="text"
            name="verification_code"
            placeholder="000000"
            maxlength="6"
            pattern="[0-9]{6}"
            inputmode="numeric"
            autocomplete="one-time-code"
            required
        >

        <button
            type="submit"
            name="verify"
        >
            Verify Email
        </button>

    </form>

    <!-- RESEND FORM -->
    <form method="POST">

        <button
            type="submit"
            name="resend"
            class="resend-button"
        >
            Resend Code
        </button>

    </form>

    <div class="note">
        Your verification code is valid for 10 minutes.
    </div>

    <a href="register.php" class="back-link">
        ← Back to Registration
    </a>

</div>

</body>
</html>