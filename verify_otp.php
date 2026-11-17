
<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

$user_id = (int)$_SESSION['verification_user_id'];
$email = $_SESSION['verification_email'];

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

            } elseif ((int)$row['email_verified'] === 1) {

                $message = "Account already verified.";
                $verified = true;

            } elseif (
                empty($row['verification_code']) ||
                empty($row['verification_expires'])
            ) {

                $message = "No verification code found.";

            } elseif (
                strtotime($row['verification_expires']) < time()
            ) {

                $message = "This OTP has expired. Please request a new one.";

            } elseif (
                !hash_equals(
                    (string)$row['verification_code'],
                    $entered_otp
                )
            ) {

                $message = "Invalid OTP.";

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

                $verified = $update_stmt->affected_rows === 1;

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

            error_log("OTP Error: " . $e->getMessage());

            $message = "Database error. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Verify OTP - PAW Care</title>

</head>

<body>

<?php if ($message !== ""): ?>

    <p>
        <?php echo htmlspecialchars($message); ?>
    </p>

<?php endif; ?>

<?php if ($verified): ?>

    <p>
        You can now
        <a href="login.php">Login</a>.
    </p>

<?php else: ?>

    <form method="POST">

        <h3>Enter the OTP sent to your email</h3>

        <input
            type="text"
            name="otp"
            placeholder="6-digit code"
            maxlength="6"
            pattern="[0-9]{6}"
            inputmode="numeric"
            autocomplete="one-time-code"
            required
        >

        <button type="submit">
            Verify
        </button>

    </form>

<?php endif; ?>

</body>
</html>
