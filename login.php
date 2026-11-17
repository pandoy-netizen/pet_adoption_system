<?php
ob_start();

$error = "";

if (session_status() === PHP_SESSION_NONE) {

    if (headers_sent($file, $line)) {
        error_log(
            "Login session error: headers already sent at " .
            $file . ":" . $line
        );
    } else {

        $sessionStarted = @session_start();

        if (!$sessionStarted) {

            error_log(
                "Default PHP session failed. " .
                "Attempting temporary session storage."
            );

            if (session_status() === PHP_SESSION_NONE) {

                $fallbackPath = sys_get_temp_dir() . '/pawcare_sessions';

                if (!is_dir($fallbackPath)) {
                    @mkdir($fallbackPath, 0700, true);
                }

                if (
                    is_dir($fallbackPath) &&
                    is_writable($fallbackPath)
                ) {
                    session_save_path($fallbackPath);
                    $sessionStarted = @session_start();
                }
            }
        }

        if (!$sessionStarted) {
            error_log(
                "Login session initialization failed. " .
                "Session save path: " . session_save_path()
            );
        }
    }
}

require_once __DIR__ . '/config/db.php';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['login'])
) {

    if (session_status() !== PHP_SESSION_ACTIVE) {

        $error = "Unable to start login session. Please try again.";

        error_log(
            "Login blocked: PHP session is not active."
        );

    } else {

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (
            $email === '' ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {

            $error = "Please enter a valid email address.";

        } elseif ($password === '') {

            $error = "Please enter your password.";

        } else {

            try {

                $stmt = $conn->prepare(
                    "SELECT
                        id,
                        username,
                        password,
                        role,
                        email
                     FROM users
                     WHERE email = ?
                     LIMIT 1"
                );

                $stmt->bind_param("s", $email);

                $stmt->execute();

                $result = $stmt->get_result();

                $user = $result->fetch_assoc();

                $stmt->close();

                if (
                    $user &&
                    password_verify(
                        $password,
                        $user['password']
                    )
                ) {

                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['name'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['email'] = $user['email'];

                    $role = strtolower(
                        trim((string)$user['role'])
                    );

                    if ($role === 'admin') {

                        $redirect = 'admin_dashboard.php';

                    } elseif ($role === 'staff') {

                        $redirect = 'staff_dashboard.php';

                    } else {

                        $redirect = 'client_dashboard.php';
                    }

                    session_write_close();

                    header(
                        "Location: " . $redirect,
                        true,
                        302
                    );

                    exit;

                } else {

                    $error = "Invalid email or password!";
                }

            } catch (Throwable $e) {

                error_log(
                    "Login database error: " .
                    $e->getMessage()
                );

                $error = "Database error. Please try again.";
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

    <title>Login | PAW Care</title>

    <script>
        (function () {
            try {
                const savedTheme = localStorage.getItem('theme');

                document.documentElement.setAttribute(
                    'data-theme',
                    savedTheme === 'dark' ? 'dark' : 'light'
                );
            } catch (error) {
                document.documentElement.setAttribute(
                    'data-theme',
                    'light'
                );
            }
        })();
    </script>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <style>
        :root {
            color-scheme: light;

            --primary: #ffd54f;
            --primary-hover: #ffc107;

            --bg: #f4f7f6;
            --text-dark: #2d3748;
            --text-muted: #718096;
            --white: #ffffff;

            --input-bg: #f8fafc;
            --input-border: #e2e8f0;
            --divider: #f1f5f9;

            --shadow: 0 10px 25px rgba(0,0,0,0.05);

            --error-bg: #fff5f5;
            --error-text: #c53030;
            --error-border: #feb2b2;
        }

        html[data-theme="dark"] {
            color-scheme: dark;

            --primary: #ffd54f;
            --primary-hover: #ffc107;

            --bg: #0f172a;
            --text-dark: #f8fafc;
            --text-muted: #94a3b8;
            --white: #1e293b;

            --input-bg: #0f172a;
            --input-border: #334155;
            --divider: #334155;

            --shadow: 0 15px 40px rgba(0,0,0,0.3);

            --error-bg: #451a1a;
            --error-text: #fca5a5;
            --error-border: #7f1d1d;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        html {
            width: 100%;
            min-height: 100%;
            background: var(--bg);
        }

        body {
            background: var(--bg);

            background-image:
                radial-gradient(
                    circle at 20% 30%,
                    rgba(255, 213, 79, 0.1) 0%,
                    transparent 40%
                ),
                radial-gradient(
                    circle at 80% 70%,
                    rgba(255, 213, 79, 0.1) 0%,
                    transparent 40%
                );

            display: flex;
            justify-content: center;
            align-items: center;

            min-height: 100vh;
            min-height: 100dvh;

            width: 100%;
            padding: 20px;

            color: var(--text-dark);

            transition:
                background 0.3s ease,
                color 0.3s ease;
        }

        .login-card {
            background: var(--white);

            padding: 50px 40px;

            border-radius: 24px;
            box-shadow: var(--shadow);

            width: 100%;
            max-width: 420px;

            text-align: center;
            margin: auto;

            border: 1px solid var(--input-border);

            transition:
                background 0.3s ease,
                border-color 0.3s ease;
        }

        .logo-area i {
            font-size: 40px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        h2 {
            color: var(--text-dark);

            font-size: 26px;
            font-weight: 700;

            margin-bottom: 8px;
        }

        p.subtitle {
            color: var(--text-muted);

            font-size: 14px;
            margin-bottom: 30px;
        }

        .input-group {
            text-align: left;
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;

            font-size: 13px;
            font-weight: 600;

            color: var(--text-dark);

            margin-bottom: 8px;
            margin-left: 5px;
        }

        input {
            width: 100%;

            padding: 14px 18px;

            background: var(--input-bg);
            border: 1px solid var(--input-border);

            border-radius: 12px;
            font-size: 15px;

            color: var(--text-dark);

            transition: 0.3s;
        }

        input::placeholder {
            color: var(--text-muted);
            opacity: 0.8;
        }

        input:focus {
            outline: none;

            border-color: var(--primary);
            background: var(--white);

            box-shadow:
                0 0 0 4px rgba(255, 213, 79, 0.15);
        }

        .error-msg {
            background: var(--error-bg);
            color: var(--error-text);

            padding: 12px;

            border-radius: 10px;
            font-size: 13px;

            margin-bottom: 20px;

            border: 1px solid var(--error-border);

            display: flex;
            align-items: center;
            gap: 8px;

            text-align: left;
        }

        button {
            width: 100%;

            padding: 14px;

            background: var(--primary);

            border: none;
            color: #000;

            font-weight: 700;
            font-size: 16px;

            border-radius: 12px;
            cursor: pointer;

            transition: 0.3s;
            margin-top: 10px;

            box-shadow:
                0 4px 12px rgba(255, 213, 79, 0.3);
        }

        button:hover {
            background: var(--primary-hover);

            transform: translateY(-2px);

            box-shadow:
                0 6px 15px rgba(255, 213, 79, 0.4);
        }

        button:focus-visible,
        input:focus-visible,
        a:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 3px;
        }

        .footer-links {
            margin-top: 25px;
            padding-top: 20px;

            border-top: 1px solid var(--divider);
        }

        .register-link {
            display: block;

            color: var(--text-dark);
            text-decoration: none;

            font-size: 14px;
            font-weight: 500;
        }

        .register-link span {
            color: var(--primary-hover);
            font-weight: 700;
        }

        .back-home {
            margin-top: 15px;

            display: inline-block;

            color: var(--text-muted);
            text-decoration: none;

            font-size: 13px;

            transition: 0.2s;
        }

        .back-home:hover {
            color: var(--primary-hover);
        }

        @media (max-width: 480px) {

            body {
                padding: 16px;
            }

            .login-card {
                padding: 35px 24px;
                border-radius: 20px;
            }
        }
    </style>
</head>

<body>

<div class="login-card">

    <div class="logo-area">

        <i class="fa-solid fa-paw"></i>

        <h2>Welcome Back</h2>

        <p class="subtitle">
            Log in to manage your pet adoptions
        </p>

    </div>

    <?php if ($error !== ""): ?>

        <div class="error-msg">

            <i class="fa-solid fa-circle-exclamation"></i>

            <?php
                echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                );
            ?>

        </div>

    <?php endif; ?>

    <form method="POST" action="login.php">

        <div class="input-group">

            <label for="email">
                Email Address
            </label>

            <input
                id="email"
                type="email"
                name="email"
                placeholder="e.g. alex@example.com"
                autocomplete="email"
                value="<?php
                    echo htmlspecialchars(
                        $_POST['email'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>"
                required
            >

        </div>

        <div class="input-group">

            <label for="password">
                Password
            </label>

            <input
                id="password"
                type="password"
                name="password"
                placeholder="••••••••"
                autocomplete="current-password"
                required
            >

        </div>

        <button type="submit" name="login" value="1">
            Sign In
        </button>

    </form>

    <div class="footer-links">

        <a href="register.php" class="register-link">
            Don't have an account?
            <span>Register here</span>
        </a>

        <a href="index.php" class="back-home">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Website
        </a>

    </div>

</div>

</body>
</html>
