<?php
// PAW CARE LOGIN SYSTEM
// Start session before any HTML output

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Railway MySQL Database Connection
require_once __DIR__ . '/config/db.php';

$error = "";

if (isset($_POST['login'])) {

    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare(
        "SELECT id, username, password, role, email FROM users WHERE email = ?"
    );

    if ($stmt) {

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];

            if ($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: client_dashboard.php");
            }

            exit();

        } else {
            $error = "Invalid email or password!";
        }

        $stmt->close();

    } else {
        $error = "Database error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | PAW Care</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #ffd54f;
            --primary-hover: #ffc107;
            --bg: #f4f7f6;
            --text-dark: #2d3748;
            --text-muted: #718096;
            --white: #ffffff;
            --shadow: 0 10px 25px rgba(0,0,0,0.05);
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
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            transition: 0.3s;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(255, 213, 79, 0.15);
        }

        .error-msg {
            background: #fff5f5;
            color: #c53030;
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            border: 1px solid #feb2b2;

            display: flex;
            align-items: center;
            gap: 8px;
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
            box-shadow: 0 4px 12px rgba(255, 213, 79, 0.3);
        }

        button:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 213, 79, 0.4);
        }

        .footer-links {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
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
        <p class="subtitle">Log in to manage your pet adoptions</p>
    </div>

    <?php if ($error != ""): ?>
        <div class="error-msg">
            <i class="fa-solid fa-circle-exclamation"></i>
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">

        <div class="input-group">
            <label>Email Address</label>
            <input
                type="email"
                name="email"
                placeholder="e.g. alex@example.com"
                required
            >
        </div>

        <div class="input-group">
            <label>Password</label>
            <input
                type="password"
                name="password"
                placeholder="••••••••"
                required
            >
        </div>

        <button type="submit" name="login">Sign In</button>

    </form>

    <div class="footer-links">
        <a href="register.php" class="register-link">
            Don't have an account? <span>Register here</span>
        </a>

        <a href="index.php" class="back-home">
            <i class="fa-solid fa-arrow-left"></i> Back to Website
        </a>
    </div>

</div>

</body>
</html>
