<?php
session_start();
// Include database connection here

if (!isset($_SESSION['verify_email'])) {
    header("Location: register.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_SESSION['verify_email'];
    $entered_otp = $_POST['otp'];

    // Fetch the OTP and Expiry from the database
    $stmt = $conn->prepare("SELECT otp, otp_expiry FROM users WHERE email = ? AND is_verified = 0");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $current_time = date("Y-m-d H:i:s");

        // Check if OTP matches
        if ($row['otp'] == $entered_otp) {
            // Check if OTP is expired
            if ($current_time <= $row['otp_expiry']) {
                
                // Success! Mark user as verified and clear the OTP
                $update_stmt = $conn->prepare("UPDATE users SET is_verified = 1, otp = NULL, otp_expiry = NULL WHERE email = ?");
                $update_stmt->bind_param("s", $email);
                $update_stmt->execute();

                unset($_SESSION['verify_email']);
                echo "Account verified successfully! You can now <a href='login.php'>Login</a>.";
                
            } else {
                echo "This OTP has expired. Please request a new one.";
            }
        } else {
            echo "Invalid OTP.";
        }
    } else {
        echo "User not found or already verified.";
    }
}
?>

<!-- Simple Verification Form -->
<form method="POST">
    <h3>Enter the OTP sent to your email</h3>
    <input type="text" name="otp" placeholder="6-digit code" required>
    <button type="submit">Verify</button>
</form>