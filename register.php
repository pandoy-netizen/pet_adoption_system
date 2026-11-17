<?php
if (ob_get_level() === 0) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent($file, $line)) {
        session_start();
    } else {
        error_log(
            "Session could not start. Headers already sent at " .
            $file . ":" . $line
        );
        http_response_code(500);
        exit("Session initialization error. Check PHP file encoding.");
    }
}

require_once __DIR__ . '/config/db.php';

$message = "";
$message_type = "";
$email_error = "";

if (isset($_SESSION['email_error'])) {
    $email_error = $_SESSION['email_error'];
    unset($_SESSION['email_error']);
}

function gmailApiRequest($url, $postFields, $headers = [])
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException("PHP cURL extension is not enabled.");
    }

    $ch = curl_init($url);

    if ($ch === false) {
        throw new RuntimeException("Unable to initialize cURL.");
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException("Gmail API connection failed: " . $curlError);
    }

    $decoded = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        $apiError = $decoded['error']['message']
            ?? $decoded['error_description']
            ?? $decoded['error']
            ?? "HTTP " . $httpCode;

        if (is_array($apiError)) {
            $apiError = json_encode($apiError);
        }

        throw new RuntimeException(
            "Google API HTTP " . $httpCode . ": " . $apiError
        );
    }

    if (!is_array($decoded)) {
        throw new RuntimeException("Invalid response from Google API.");
    }

    return $decoded;
}

function getGmailAccessToken()
{
    $clientId = trim(getenv('GOOGLE_CLIENT_ID') ?: '');
    $clientSecret = trim(getenv('GOOGLE_CLIENT_SECRET') ?: '');
    $refreshToken = trim(getenv('GOOGLE_REFRESH_TOKEN') ?: '');

    if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
        throw new RuntimeException(
            "Missing GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET or GOOGLE_REFRESH_TOKEN."
        );
    }

    $response = gmailApiRequest(
        'https://oauth2.googleapis.com/token',
        http_build_query([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ]),
        [
            'Content-Type: application/x-www-form-urlencoded'
        ]
    );

    if (empty($response['access_token'])) {
        throw new RuntimeException("Google did not return an access token.");
    }

    return $response['access_token'];
}

function sendVerificationEmail($email, $fullname, $verification_code)
{
    try {
        $senderEmail = trim(
            getenv('GMAIL_SENDER_EMAIL')
            ?: getenv('PET_ADOPTION_SYSTEM_FROM_EMAIL')
            ?: ''
        );

        if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Invalid GMAIL_SENDER_EMAIL.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Invalid recipient email address.");
        }

        $accessToken = getGmailAccessToken();

        $safeName = htmlspecialchars(
            $fullname,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeCode = htmlspecialchars(
            $verification_code,
            ENT_QUOTES,
            'UTF-8'
        );

        $htmlBody = "
        <div style='
            font-family:Arial,sans-serif;
            max-width:600px;
            margin:auto;
            padding:20px;
            color:#333;
        '>

            <h2 style='
                color:#d4a017;
                text-align:center;
            '>
                PAW Care
            </h2>

            <p>Hello <b>{$safeName}</b>,</p>

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

                <p style='color:#555;'>
                    Your verification code:
                </p>

                <span style='
                    font-size:32px;
                    font-weight:bold;
                    letter-spacing:8px;
                    color:#d4a017;
                '>
                    {$safeCode}
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

        </div>";

        $plainBody =
            "Hello {$fullname},\n\n" .
            "Welcome to PAW Care!\n\n" .
            "Your verification code is: {$verification_code}\n\n" .
            "This code expires in 10 minutes.\n\n" .
            "Thank you,\nPAW Care";

        $boundary = 'pawcare_' . bin2hex(random_bytes(12));

        $subject = 'PAW Care - Email Verification';

        $encodedSubject = '=?UTF-8?B?' .
            base64_encode($subject) .
            '?=';

        $encodedName = '=?UTF-8?B?' .
            base64_encode('PAW Care') .
            '?=';

        $headers = [
            'From: ' . $encodedName . ' <' . $senderEmail . '>',
            'To: <' . $email . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"'
        ];

        $mime = implode("\r\n", $headers) . "\r\n\r\n";

        $mime .= '--' . $boundary . "\r\n";
        $mime .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $mime .= chunk_split(base64_encode($plainBody), 76, "\r\n");
        $mime .= "\r\n";

        $mime .= '--' . $boundary . "\r\n";
        $mime .= "Content-Type: text/html; charset=UTF-8\r\n";
        $mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $mime .= chunk_split(base64_encode($htmlBody), 76, "\r\n");
        $mime .= "\r\n";

        $mime .= '--' . $boundary . "--\r\n";

        $rawMessage = rtrim(
            strtr(base64_encode($mime), '+/', '-_'),
            '='
        );

        $response = gmailApiRequest(
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
            json_encode([
                'raw' => $rawMessage
            ]),
            [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]
        );

        if (empty($response['id'])) {
            throw new RuntimeException(
                "Gmail API did not confirm the sent message."
            );
        }

        error_log("Gmail API: Verification email accepted successfully.");

        return true;

    } catch (Throwable $e) {
        error_log("Gmail API Error: " . $e->getMessage());

        $_SESSION['email_error'] =
            "The verification email could not be sent. Please check Gmail API configuration.";

        return false;
    }
}

try {
    $required_columns = [
        "email_verified" => "TINYINT(1) NOT NULL DEFAULT 0",
        "verification_code" => "VARCHAR(10) NULL",
        "verification_expires" => "DATETIME NULL"
    ];

    foreach ($required_columns as $column => $definition) {
        $check = $conn->query(
            "SHOW COLUMNS FROM users LIKE '$column'"
        );

        if ($check->num_rows === 0) {
            $conn->query(
                "ALTER TABLE users ADD COLUMN `$column` $definition"
            );
        }

        $check->free();
    }

} catch (mysqli_sql_exception $e) {
    error_log("Database schema error: " . $e->getMessage());
    http_response_code(500);
    exit("Database configuration error.");
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['register'])
) {
    $fullname = trim($_POST['fullname'] ?? "");
    $email = trim($_POST['email'] ?? "");
    $pass = $_POST['password'] ?? "";
    $confirm_pass = $_POST['confirm_password'] ?? "";

    if (empty($fullname)) {
        $message = "Full name is required.";
        $message_type = "error";

    } elseif (strlen($fullname) < 2) {
        $message = "Name must be at least 2 characters.";
        $message_type = "error";

    } elseif (!preg_match("/^[a-zA-Z ]+$/", $fullname)) {
        $message = "Name can only contain letters and spaces.";
        $message_type = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";

    } elseif ($pass !== $confirm_pass) {
        $message = "Passwords do not match!";
        $message_type = "error";

    } elseif (strlen($pass) < 6) {
        $message = "Password must be at least 6 characters.";
        $message_type = "error";

    } else {
        try {
            $stmt = $conn->prepare(
                "SELECT id, email_verified
                 FROM users
                 WHERE email = ?"
            );

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $existing_user = $result->fetch_assoc();

                if ((int)$existing_user['email_verified'] === 1) {
                    $message =
                        "Email is already registered! Please login.";
                    $message_type = "error";

                } else {
                    $user_id = (int)$existing_user['id'];

                    $verification_code = sprintf(
                        "%06d",
                        random_int(0, 999999)
                    );

                    $verification_expires = date(
                        "Y-m-d H:i:s",
                        strtotime("+10 minutes")
                    );

                    $update = $conn->prepare(
                        "UPDATE users
                         SET verification_code = ?,
                             verification_expires = ?
                         WHERE id = ?"
                    );

                    $update->bind_param(
                        "ssi",
                        $verification_code,
                        $verification_expires,
                        $user_id
                    );

                    if ($update->execute()) {
                        $_SESSION['verification_user_id'] = $user_id;
                        $_SESSION['verification_email'] = $email;

                        if (sendVerificationEmail(
                            $email,
                            $fullname,
                            $verification_code
                        )) {
                            header("Location: verify_otp.php");
                            exit;
                        } else {
                            $message =
                                "The verification code was generated, but the verification email could not be sent.";
                            $message_type = "error";
                        }
                    } else {
                        $message =
                            "Unable to create verification code. Please try again.";
                        $message_type = "error";
                    }

                    $update->close();
                }

            } else {
                $hashed_password = password_hash(
                    $pass,
                    PASSWORD_DEFAULT
                );

                $verification_code = sprintf(
                    "%06d",
                    random_int(0, 999999)
                );

                $verification_expires = date(
                    "Y-m-d H:i:s",
                    strtotime("+10 minutes")
                );

                $sql = "
                    INSERT INTO users
                    (
                        username,
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
                    )
                ";

                $insert = $conn->prepare($sql);

                $insert->bind_param(
                    "sssss",
                    $fullname,
                    $email,
                    $hashed_password,
                    $verification_code,
                    $verification_expires
                );

                if ($insert->execute()) {
                    $user_id = $insert->insert_id;

                    $_SESSION['verification_user_id'] = $user_id;
                    $_SESSION['verification_email'] = $email;

                    if (sendVerificationEmail(
                        $email,
                        $fullname,
                        $verification_code
                    )) {
                        header("Location: verify_otp.php");
                        exit;
                    } else {
                        $message =
                            "Account created, but the verification email could not be sent.";
                        $message_type = "error";
                    }
                } else {
                    $message =
                        "Something went wrong while creating your account.";
                    $message_type = "error";
                }

                $insert->close();
            }

            $stmt->close();

        } catch (mysqli_sql_exception $e) {
            error_log("Registration Error: " . $e->getMessage());

            $message =
                "Registration failed due to a database error. Please try again.";
            $message_type = "error";
        }
    }
}

if (isset($_SESSION['email_error'])) {
    $email_error = $_SESSION['email_error'];
    unset($_SESSION['email_error']);
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
<script>(function(){try{var theme=localStorage.getItem('theme');document.documentElement.setAttribute('data-theme',theme==='dark'?'dark':'light')}catch(e){document.documentElement.setAttribute('data-theme','light')}})();</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root{color-scheme:light;--primary:#ffd54f;--primary-hover:#ffc107;--bg:#f4f7f6;--text-dark:#2d3748;--text-muted:#718096;--surface:#fff;--input-bg:#f8fafc;--border:#e2e8f0;--shadow:0 10px 25px rgba(0,0,0,.08);--glow:rgba(255,213,79,.1);--error-bg:#fff5f5;--error-text:#c53030;--error-border:#feb2b2;--success-bg:#ecfdf5;--success-text:#047857}
html[data-theme="dark"]{color-scheme:dark;--primary:#f59e0b;--primary-hover:#fbbf24;--bg:#0f172a;--text-dark:#f8fafc;--text-muted:#94a3b8;--surface:#1e293b;--input-bg:#0f172a;--border:#334155;--shadow:0 18px 48px rgba(0,0,0,.35);--glow:rgba(245,158,11,.08);--error-bg:#452027;--error-text:#fecaca;--error-border:#7f1d1d;--success-bg:#064e3b;--success-text:#a7f3d0}
*{box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:var(--bg);background-image:radial-gradient(circle at 20% 30%,var(--glow),transparent 40%),radial-gradient(circle at 80% 70%,var(--glow),transparent 40%);display:flex;justify-content:center;align-items:center;min-height:100vh;min-height:100dvh;margin:0;padding:20px;color:var(--text-dark)}
.login-container{background:var(--surface);padding:45px 40px;border-radius:24px;box-shadow:var(--shadow);border:1px solid var(--border);width:100%;max-width:420px;text-align:center}
h2{color:var(--text-dark);font-size:26px;font-weight:700;margin:0 0 12px}
.register-subtitle{font-size:14px;color:var(--text-muted);margin:0 0 28px}
.logo-paw{font-size:40px;color:var(--primary);margin-bottom:10px}
input{width:100%;padding:14px 18px;margin-bottom:15px;background:var(--input-bg);color:var(--text-dark);border:1px solid var(--border);border-radius:12px;font-family:inherit;font-size:15px;transition:border-color .2s,box-shadow .2s}
input::placeholder{color:var(--text-muted);opacity:.8}
input:focus{outline:none;border-color:var(--primary);background:var(--surface);box-shadow:0 0 0 4px rgba(255,213,79,.15)}
button{width:100%;padding:14px;background:var(--primary);border:none;color:#111827;font-weight:700;font-size:16px;border-radius:12px;cursor:pointer;transition:.3s;margin-top:8px}
button:hover{background:var(--primary-hover);transform:translateY(-2px)}
.error,.success{font-size:13px;padding:12px;border-radius:10px;margin-bottom:18px;text-align:left}
.error{background:var(--error-bg);color:var(--error-text);border:1px solid var(--error-border)}
.success{background:var(--success-bg);color:var(--success-text);border:1px solid var(--success-text)}
.login-link{margin-top:22px;padding-top:20px;border-top:1px solid var(--border);display:block;color:var(--text-dark);text-decoration:none;font-size:14px}
.login-link strong{color:var(--primary-hover)}
.back-home{margin-top:15px;display:block;color:var(--text-muted);text-decoration:none;font-size:13px}
.login-link:hover,.back-home:hover{color:var(--primary-hover)}
.smtp-error-box{background:var(--error-bg);border:1px solid var(--error-border);color:var(--error-text);padding:15px;margin-bottom:20px;border-radius:10px;text-align:left;font-size:13px;overflow-wrap:anywhere}
.smtp-error-box strong{color:var(--error-text)}
@media(max-width:480px){body{padding:16px}.login-container{padding:35px 24px;border-radius:20px}}
</style>
</head>

<body>

<div class="login-container">

    <div class="logo-paw"><i class="fa-solid fa-paw"></i></div>
    <h2>Create Your Account</h2>
    <p class="register-subtitle">Join us and give a pet a loving home</p>

    <?php if ($message != ""): ?>

        <div class="<?php echo htmlspecialchars($message_type); ?>">

            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>

    <?php if ($email_error != ""): ?>

        <div class="smtp-error-box">

            <strong>Gmail API Error:</strong>

            <br><br>

            <?php echo htmlspecialchars($email_error); ?>

            <br><br>

            <small>
                Please check your Gmail API settings
                in Railway Variables.
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
                    $_POST['fullname'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
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
                    $_POST['email'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
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
        Already have an account? <strong>Login here</strong>
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
