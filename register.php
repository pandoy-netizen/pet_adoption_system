<?php
session_start();

/* =========================
   REQUIRE PHPMAILER
========================= */
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


/* =========================
   DATABASE CONNECTION
========================= */

$host = "localhost";
$user = "root";
$password = "";
$database = "pet_adoption_rescue";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}


/* =========================================================
   AUTOMATICALLY DETERMINE NAME COLUMN
========================================================= */

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

    } else {

        die("
            <h2>Database Error</h2>
            <p>Your <b>users</b> table does not have either <b>full_name</b> or <b>name</b>.</p>
            <p>Please check your users table in phpMyAdmin.</p>
        ");
    }
}


/* =========================================================
   CREATE EMAIL VERIFICATION COLUMNS
========================================================= */

/* email_verified */
$check = mysqli_query(
    $conn,
    "SHOW COLUMNS FROM users LIKE 'email_verified'"
);

if ($check && mysqli_num_rows($check) == 0) {

    mysqli_query(
        $conn,
        "ALTER TABLE users
         ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0"
    );
}


/* verification_code */
$check = mysqli_query(
    $conn,
    "SHOW COLUMNS FROM users LIKE 'verification_code'"
);

if ($check && mysqli_num_rows($check) == 0) {

    mysqli_query(
        $conn,
        "ALTER TABLE users
         ADD COLUMN verification_code VARCHAR(10) NULL"
    );
}


/* verification_expires */
$check = mysqli_query(
    $conn,
    "SHOW COLUMNS FROM users LIKE 'verification_expires'"
);

if ($check && mysqli_num_rows($check) == 0) {

    mysqli_query(
        $conn,
        "ALTER TABLE users
         ADD COLUMN verification_expires DATETIME NULL"
    );
}


/* =========================================================
   GET PREVIOUS SMTP ERROR
   This is displayed only AFTER PHP processing.
   It prevents "headers already sent".
========================================================= */

$smtp_error = "";

if (isset($_SESSION['smtp_error'])) {

    $smtp_error = $_SESSION['smtp_error'];

    unset($_SESSION['smtp_error']);
}


/* =========================================================
   PHPMAILER HELPER FUNCTION
========================================================= */

function sendVerificationEmail($email, $fullname, $verification_code)
{
    $mail = new PHPMailer(true);

    try {

        /* =========================
           SMTP SERVER SETTINGS
        ========================= */

        $mail->isSMTP();

        $mail->Host = 'smtp.gmail.com';

        $mail->SMTPAuth = true;


        /*
         * =====================================================
         * YOUR GMAIL ADDRESS
         * =====================================================
         */

        $mail->Username = 'lesterneildinojales@gmail.com';


        /*
         * =====================================================
         * YOUR NEW GOOGLE APP PASSWORD
         *
         * IMPORTANT:
         * DO NOT USE YOUR NORMAL GMAIL PASSWORD.
         *
         * Example format:
         * xxxx xxxx xxxx xxxx
         *
         * Remove spaces if necessary.
         * =====================================================
         */

        $mail->Password = 'pvmc rbew vrxq dokg';


        /*
         * Gmail TLS
         */

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = 587;


        /*
         * =====================================================
         * IMPORTANT
         *
         * SMTPDebug MUST be 0.
         *
         * If SMTPDebug outputs directly to the page,
         * it can cause:
         *
         * "Cannot modify header information"
         *
         * because header() must happen before page output.
         * =====================================================
         */

        $mail->SMTPDebug = 0;


        /* =========================
           SENDER
        ========================= */

        $mail->setFrom(
            'lesterneildinojales@gmail.com',
            'PAW Care'
        );


        /* =========================
           RECEIVER
        ========================= */

        $mail->addAddress(
            $email,
            $fullname
        );


        /* =========================
           EMAIL CONTENT
        ========================= */

        $mail->isHTML(true);

        $mail->Subject = 'PAW Care - Email Verification';


        /*
         * Protect the user's name from HTML injection.
         */

        $safe_name = htmlspecialchars(
            $fullname,
            ENT_QUOTES,
            'UTF-8'
        );


        /*
         * EMAIL BODY
         */

        $mail->Body = "

            <div style='
                font-family: Arial, sans-serif;
                max-width: 600px;
                margin: auto;
                padding: 20px;
                color: #333;
            '>

                <h2 style='
                    color:#d4a017;
                    text-align:center;
                '>
                    PAW Care
                </h2>

                <p>
                    Hello <b>$safe_name</b>,
                </p>

                <p>
                    Welcome to <b>PAW Care</b>!
                </p>

                <p>
                    Thank you for creating an account.
                    Please use the verification code below
                    to verify your email address.
                </p>


                <div style='
                    background:#f8f8f8;
                    padding:25px;
                    text-align:center;
                    margin:25px 0;
                    border-radius:10px;
                    border:1px solid #eeeeee;
                '>

                    <p style='
                        margin:0 0 10px 0;
                        color:#555;
                    '>
                        Your verification code:
                    </p>

                    <span style='
                        font-size:32px;
                        font-weight:bold;
                        letter-spacing:8px;
                        color:#d4a017;
                    '>
                        $verification_code
                    </span>

                </div>


                <p>
                    This verification code will expire
                    in <b>10 minutes</b>.
                </p>


                <p>
                    If you did not create this account,
                    please ignore this email.
                </p>


                <br>


                <p>
                    Thank you,<br>
                    <b>PAW Care</b>
                </p>

            </div>

        ";


        /* =========================
           PLAIN TEXT EMAIL
        ========================= */

        $mail->AltBody =
            "Hello $fullname,\n\n" .
            "Welcome to PAW Care!\n\n" .
            "Your email verification code is: $verification_code\n\n" .
            "This code will expire in 10 minutes.\n\n" .
            "If you did not create this account, please ignore this email.\n\n" .
            "Thank you,\n" .
            "PAW Care";


        /* =========================
           SEND EMAIL
        ========================= */

        $mail->send();

        return true;


    } catch (Exception $e) {

        /*
         * =====================================================
         * IMPORTANT FIX
         *
         * DO NOT USE echo HERE.
         *
         * echo would send output before header()
         * and cause:
         *
         * Cannot modify header information
         *
         * Instead, save the error in SESSION.
         * =====================================================
         */

        $_SESSION['smtp_error'] =
            "PHPMailer Error: " .
            $mail->ErrorInfo;

        return false;
    }
}


/* =========================================================
   REGISTER LOGIC
========================================================= */

$message = "";
$message_type = "";


if (isset($_POST['register'])) {


    /* =========================
       GET FORM DATA
    ========================= */

    $fullname = trim(
        $_POST['fullname'] ?? ""
    );

    $email = trim(
        $_POST['email'] ?? ""
    );

    $pass = $_POST['password'] ?? "";

    $confirm_pass = $_POST['confirm_password'] ?? "";


    /* =========================
       VALIDATIONS
    ========================= */

    if (empty($fullname)) {

        $message =
            "Full name is required.";

        $message_type =
            "error";


    } elseif (strlen($fullname) < 2) {

        $message =
            "Name must be at least 2 characters.";

        $message_type =
            "error";


    } elseif (!preg_match("/^[a-zA-Z ]+$/", $fullname)) {

        $message =
            "Name can only contain letters and spaces.";

        $message_type =
            "error";


    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message =
            "Please enter a valid email address.";

        $message_type =
            "error";


    } elseif ($pass !== $confirm_pass) {

        $message =
            "Passwords do not match!";

        $message_type =
            "error";


    } elseif (strlen($pass) < 6) {

        $message =
            "Password must be at least 6 characters.";

        $message_type =
            "error";


    } else {


        /* =================================================
           CHECK IF EMAIL ALREADY EXISTS
        ================================================= */

        $stmt = $conn->prepare(
            "SELECT id, email_verified
             FROM users
             WHERE email = ?"
        );


        if (!$stmt) {

            $message =
                "Database error: " . $conn->error;

            $message_type =
                "error";

        } else {

            $stmt->bind_param(
                "s",
                $email
            );

            $stmt->execute();

            $result =
                $stmt->get_result();


            /* =================================================
               EMAIL ALREADY EXISTS
            ================================================= */

            if ($result->num_rows > 0) {

                $existing_user =
                    $result->fetch_assoc();


                /* =========================
                   ALREADY VERIFIED
                ========================= */

                if (
                    (int)$existing_user['email_verified']
                    === 1
                ) {

                    $message =
                        "Email is already registered! Please login.";

                    $message_type =
                        "error";

                }


                /* =========================
                   EXISTS BUT NOT VERIFIED
                ========================= */

                else {

                    $user_id =
                        $existing_user['id'];


                    /*
                     * Generate new 6 digit OTP
                     */

                    $verification_code =
                        sprintf(
                            "%06d",
                            random_int(
                                0,
                                999999
                            )
                        );


                    /*
                     * OTP expires after 10 minutes
                     */

                    $verification_expires =
                        date(
                            "Y-m-d H:i:s",
                            strtotime("+10 minutes")
                        );


                    /* =========================
                       UPDATE OTP
                    ========================= */

                    $update = $conn->prepare(
                        "UPDATE users
                         SET verification_code = ?,
                             verification_expires = ?
                         WHERE id = ?"
                    );


                    if (!$update) {

                        $message =
                            "Database error while creating verification code.";

                        $message_type =
                            "error";

                    } else {

                        $update->bind_param(
                            "ssi",
                            $verification_code,
                            $verification_expires,
                            $user_id
                        );


                        if ($update->execute()) {


                            $_SESSION['verification_user_id'] =
                                $user_id;

                            $_SESSION['verification_email'] =
                                $email;


                            /* =========================
                               SEND EMAIL
                            ========================= */

                            if (
                                sendVerificationEmail(
                                    $email,
                                    $fullname,
                                    $verification_code
                                )
                            ) {


                                /*
                                 * IMPORTANT:
                                 *
                                 * Nothing has been echoed.
                                 *
                                 * Therefore header() is safe.
                                 */

                                header(
                                    "Location: verify_email.php"
                                );

                                exit;


                            } else {


                                /*
                                 * Email failed.
                                 *
                                 * sendVerificationEmail()
                                 * already saved the SMTP error
                                 * inside the session.
                                 */

                                $message =
                                    "The verification code was generated, but the verification email could not be sent.";

                                $message_type =
                                    "error";
                            }

                        } else {

                            $message =
                                "Unable to create verification code. Please try again.";

                            $message_type =
                                "error";
                        }
                    }
                }

            }


            /* =================================================
               NEW USER
            ================================================= */

            else {


                /*
                 * Secure password hash
                 */

                $hashed_password =
                    password_hash(
                        $pass,
                        PASSWORD_DEFAULT
                    );


                /*
                 * Generate 6 digit OTP
                 */

                $verification_code =
                    sprintf(
                        "%06d",
                        random_int(
                            0,
                            999999
                        )
                    );


                /*
                 * OTP expires in 10 minutes
                 */

                $verification_expires =
                    date(
                        "Y-m-d H:i:s",
                        strtotime("+10 minutes")
                    );


                /* =========================
                   INSERT NEW USER
                ========================= */

                $sql =
                    "INSERT INTO users
                    (
                        `$name_column`,
                        email,
                        password,
                        role,
                        email_verified,
                        verification_code,
                        verification_expires
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        'adopter',
                        0,
                        ?,
                        ?
                    )";


                $insert =
                    $conn->prepare($sql);


                if (!$insert) {

                    die(
                        "Database error while preparing registration: "
                        . $conn->error
                    );
                }


                $insert->bind_param(
                    "sssss",
                    $fullname,
                    $email,
                    $hashed_password,
                    $verification_code,
                    $verification_expires
                );


                if ($insert->execute()) {


                    $user_id =
                        $insert->insert_id;


                    $_SESSION['verification_user_id'] =
                        $user_id;

                    $_SESSION['verification_email'] =
                        $email;


                    /* =========================
                       SEND VERIFICATION EMAIL
                    ========================= */

                    if (
                        sendVerificationEmail(
                            $email,
                            $fullname,
                            $verification_code
                        )
                    ) {


                        /*
                         * No output happened before this.
                         * Redirect is safe.
                         */

                        header(
                            "Location: verify_email.php"
                        );

                        exit;


                    } else {


                        /*
                         * SMTP error is stored in SESSION.
                         */

                        $message =
                            "Account created, but the verification email could not be sent.";

                        $message_type =
                            "error";
                    }

                } else {

                    $message =
                        "Something went wrong while creating your account.";

                    $message_type =
                        "error";
                }
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Register - PAW Care</title>


<style>

body {
    font-family: 'Segoe UI', sans-serif;
    background: #f4f7f6;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    margin: 0;
}

.login-container {
    background: white;
    padding: 40px;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    width: 350px;
    text-align: center;
}

h2 {
    color: #333;
    margin-bottom: 25px;
}

input {
    width: 100%;
    padding: 12px;
    margin-bottom: 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    box-sizing: border-box;
}

input:focus {
    outline: none;
    border-color: #d4a017;
}

button {
    width: 100%;
    padding: 12px;
    background: #d4a017;
    border: none;
    color: white;
    font-weight: bold;
    border-radius: 5px;
    cursor: pointer;
    transition: 0.3s;
}

button:hover {
    background: #b8870f;
}

.error {
    color: red;
    font-size: 14px;
    margin-bottom: 15px;
}

.success {
    color: green;
    font-size: 14px;
    margin-bottom: 15px;
}

.login-link {
    margin-top: 15px;
    display: block;
    color: #28a745;
    text-decoration: none;
    font-size: 13px;
}

.back-home {
    margin-top: 15px;
    display: block;
    color: #666;
    text-decoration: none;
    font-size: 13px;
}


/* =========================
   SMTP ERROR BOX
========================= */

.smtp-error-box {
    background: #fff0f0;
    border: 2px solid #d00000;
    color: #900;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 10px;
    text-align: left;
    font-family: Arial, sans-serif;
    font-size: 13px;
    word-break: break-word;
}

.smtp-error-box strong {
    color: #700;
}

</style>

</head>


<body>


<div class="login-container">


    <h2>Create Your Account</h2>


    <?php if ($message != ""): ?>

        <div class="<?php echo htmlspecialchars($message_type); ?>">

            <?php
            echo htmlspecialchars($message);
            ?>

        </div>

    <?php endif; ?>


    <?php
    /*
     * =====================================================
     * SHOW SMTP ERROR HERE
     *
     * This is SAFE because PHP has already finished
     * all header redirects before reaching this HTML.
     * =====================================================
     */

    if ($smtp_error != ""):
    ?>

        <div class="smtp-error-box">

            <strong>
                SMTP Error:
            </strong>

            <br><br>

            <?php
            echo htmlspecialchars($smtp_error);
            ?>

            <br><br>

            <small>
                Please check your Gmail address and Google
                App Password in register.php.
            </small>

        </div>

    <?php endif; ?>


    <form method="POST">


        <input
            type="text"
            name="fullname"
            placeholder="Full Name"
            value="<?php
                echo htmlspecialchars(
                    $_POST['fullname'] ?? ''
                );
            ?>"
            required
        >


        <input
            type="email"
            name="email"
            placeholder="Email Address"
            value="<?php
                echo htmlspecialchars(
                    $_POST['email'] ?? ''
                );
            ?>"
            required
        >


        <input
            type="password"
            name="password"
            placeholder="Create Password"
            required
        >


        <input
            type="password"
            name="confirm_password"
            placeholder="Confirm Password"
            required
        >


        <button
            type="submit"
            name="register"
        >
            Register
        </button>


    </form>


    <a
        href="login.php"
        class="login-link"
    >
        Already have an account? Login here →
    </a>


    <a
        href="index.php"
        class="back-home"
    >
        ← Back to Website
    </a>


</div>


</body>

</html>