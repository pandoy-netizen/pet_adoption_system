<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$loggedIn = isset($_SESSION['user_id']);
$role = strtolower((string)($_SESSION['role'] ?? 'client'));
$dashboardUrl = $role === 'admin' ? 'admin_dashboard.php' : ($role === 'staff' ? 'staff_dashboard.php' : 'client_dashboard.php');
$startUrl = $loggedIn ? $dashboardUrl : 'login.php';
?>
<!DOCTYPE html>

                    <html>

                    <head>

                        <meta charset="UTF-8">

                    </head>

                    <body style="
                        margin:0;
                        padding:0;
                        background:#f8f9fa;
                        font-family:Arial,Helvetica,sans-serif;
                    ">

                        <div style="
                            max-width:650px;
                            margin:30px auto;
                            background:#ffffff;
                            border-radius:16px;
                            overflow:hidden;
                            box-shadow:0 10px 30px rgba(0,0,0,0.10);
                        ">

                            

                            <div style="
                                background:#FF9F43;
                                padding:30px;
                                text-align:center;
                                color:white;
                            ">

                                <h1 style="
                                    margin:0;
                                    font-size:28px;
                                ">
                                    San Pedro AnimalShelter
                                </h1>

                                <p style="
                                    margin:8px 0 0;
                                    font-size:15px;
                                ">
                                    Rescue. Adopt. Love.
                                </p>

                            </div>


                            

                            <div style="
                                padding:35px;
                            ">

                                <h2 style="
                                    color:#2D3436;
                                    margin-top:0;
                                ">
                                    Hello ' .
                                    htmlspecialchars($user_name) .
                                    '!
                                </h2>


                                <p style="
                                    color:#636e72;
                                    line-height:1.7;
                                ">

                                    Thank you for submitting an
                                    adoption request through the
                                    San Pedro AnimalShelter Pet
                                    Adoption & Rescue System.

                                </p>


                                

                                <div style="
                                    background:#fff7ed;
                                    border-left:5px solid #FF9F43;
                                    padding:20px;
                                    margin:25px 0;
                                    border-radius:8px;
                                ">

                                    <h3 style="
                                        color:#FF9F43;
                                        margin-top:0;
                                    ">
                                        Adoption Request Details
                                    </h3>


                                    <p style="
                                        color:#2D3436;
                                        margin:8px 0;
                                    ">

                                        <strong>Pet Name:</strong>
                                        ' .
                                        htmlspecialchars($pet_name) .
                                        '

                                    </p>


                                    <p style="
                                        color:#2D3436;
                                        margin:8px 0;
                                    ">

                                        <strong>Breed:</strong>
                                        ' .
                                        htmlspecialchars($pet_breed) .
                                        '

                                    </p>


                                    <p style="
                                        color:#2D3436;
                                        margin:8px 0;
                                    ">

                                        <strong>Age:</strong>
                                        ' .
                                        htmlspecialchars($pet_age) .
                                        ' Years

                                    </p>


                                    <p style="
                                        color:#2D3436;
                                        margin:8px 0;
                                    ">

                                        <strong>Applicant:</strong>
                                        ' .
                                        htmlspecialchars($user_name) .
                                        '

                                    </p>


                                    <p style="
                                        color:#2D3436;
                                        margin:8px 0;
                                    ">

                                        <strong>Email:</strong>
                                        ' .
                                        htmlspecialchars($user_email) .
                                        '

                                    </p>

                                </div>


                                

                                <div style="
                                    background:#f8f9fa;
                                    padding:20px;
                                    border-radius:10px;
                                    margin-top:25px;
                                ">

                                    <h3 style="
                                        color:#2D3436;
                                        margin-top:0;
                                    ">
                                        What happens next?
                                    </h3>

                                    <p style="
                                        color:#636e72;
                                        line-height:1.7;
                                        margin-bottom:0;
                                    ">

                                        Your adoption request has
                                        been successfully recorded.
                                        Our shelter staff will review
                                        your request and contact you
                                        regarding the next steps.

                                    </p>

                                </div>


                                <p style="
                                    color:#636e72;
                                    line-height:1.7;
                                    margin-top:30px;
                                ">

                                    Thank you for helping give animals
                                    a second chance at a loving home.

                                </p>


                                <p style="
                                    color:#2D3436;
                                    font-weight:bold;
                                    margin-bottom:0;
                                ">

                                    San Pedro AnimalShelter

                                </p>

                                <p style="
                                    color:#FF9F43;
                                    margin-top:5px;
                                ">

                                    Rescue. Adopt. Love.

                                </p>

                            </div>


                            

                            <div style="
                                background:#2D3436;
                                color:#ffffff;
                                text-align:center;
                                padding:20px;
                                font-size:13px;
                            ">

                                &copy; 2026 Pet Adoption & Rescue System

                            </div>

                        </div>

                    </body>

                    </html>
                    ';


                    

                    $mail->AltBody =
                        "SAN PEDRO ANIMALSHELTER\n\n" .

                        "Hello " .
                        $user_name .
                        "!\n\n" .

                        "Thank you for submitting an adoption " .
                        "request through the San Pedro AnimalShelter " .
                        "Pet Adoption & Rescue System.\n\n" .

                        "ADOPTION REQUEST DETAILS\n" .

                        "Pet Name: " .
                        $pet_name .
                        "\n" .

                        "Breed: " .
                        $pet_breed .
                        "\n" .

                        "Age: " .
                        $pet_age .
                        " Years\n" .

                        "Applicant: " .
                        $user_name .
                        "\n" .

                        "Email: " .
                        $user_email .
                        "\n\n" .

                        "WHAT HAPPENS NEXT?\n" .

                        "Your adoption request has been successfully " .
                        "recorded. Our shelter staff will review " .
                        "your request and contact you regarding " .
                        "the next steps.\n\n" .

                        "Thank you for helping give animals a second " .
                        "chance at a loving home.\n\n" .

                        "San Pedro AnimalShelter\n" .

                        "Rescue. Adopt. Love.";


                    

                    $mail->send();


                    

                    $message = "
                    <div class='alert success'>

                        <i class='fa-solid fa-circle-check'></i>

                        Thank you, " .
                        htmlspecialchars($user_name) .
                        "! Your adoption request has been sent
                        successfully. A confirmation email has been
                        sent to <strong>" .
                        htmlspecialchars($user_email) .
                        "</strong>.

                    </div>
                    ";


                } catch (Exception $e) {

                    

                    $message = "
                    <div class='alert error'>

                        <i class='fa-solid fa-circle-exclamation'></i>

                        Your adoption request was saved successfully,
                        but the confirmation email could not be sent.
                        Please check your email settings.

                    </div>
                    ";

                    

                    error_log(
                        "PHPMailer Error: " .
                        $mail->ErrorInfo
                    );
                }


            } else {

                $message = "
                <div class='alert error'>

                    <i class='fa-solid fa-circle-exclamation'></i>

                    Oops! Your adoption request could not be saved.
                    Please try again.

                </div>
                ";
            }

            mysqli_stmt_close($insert_stmt);


        } else {

            $message = "
            <div class='alert error'>

                <i class='fa-solid fa-circle-exclamation'></i>

                Database error. Please try again.

            </div>
            ";
        }
    }
}




$sql = "SELECT * FROM pets WHERE status='Available'";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Unable to load pets: " . mysqli_error($conn));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        San Pedro AnimalShelter | Find Your Best Friend
    </title>

    <link rel="manifest" href="manifest.json">

    <meta name="theme-color" content="#FF9F43">


    

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">


    

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


    <style>

        :root {
            --primary: #FF9F43;
            --primary-dark: #ee8a2e;
            --secondary: #2D3436;
            --text-main: #2d3436;
            --text-muted: #636e72;
            --bg-light: #f8f9fa;
            --white: #ffffff;
            --shadow: 0 10px 30px rgba(0,0,0,0.08);
            --shadow-hover: 0 20px 40px rgba(255, 159, 67, 0.15);
            --transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        }


        html {
            scroll-behavior: smooth;
            overflow-x: hidden;
        }


        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }


        body {
            background: var(--bg-light);
            color: var(--text-main);
            line-height: 1.6;
            overflow-x: hidden;
        }


        .reveal {
            opacity: 0;
            transform: translateY(50px);
            transition: all 0.8s cubic-bezier(0.5, 0, 0, 1);
        }


        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }


        .reveal-left {
            opacity: 0;
            transform: translateX(-50px);
            transition: all 0.8s cubic-bezier(0.5, 0, 0, 1);
        }


        .reveal-left.active {
            opacity: 1;
            transform: translateX(0);
        }


        .reveal-right {
            opacity: 0;
            transform: translateX(50px);
            transition: all 0.8s cubic-bezier(0.5, 0, 0, 1);
        }


        .reveal-right.active {
            opacity: 1;
            transform: translateX(0);
        }


        .reveal-zoom {
            opacity: 0;
            transform: scale(0.8);
            transition: all 0.8s cubic-bezier(0.5, 0, 0, 1);
        }


        .reveal-zoom.active {
            opacity: 1;
            transform: scale(1);
        }


        

        .navbar {
            position: fixed;
            width: 100%;
            top: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: clamp(15px, 2vw, 25px) 8%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.05);
            z-index: 1000;
            transition: var(--transition);
            animation: slideDownNav 0.8s ease-out;
        }


        @keyframes slideDownNav {

            from {
                transform: translateY(-100%);
            }

            to {
                transform: translateY(0);
            }

        }


        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            z-index: 1001; 
        }


        .logo img {
            height: clamp(35px, 4vw, 45px);
            transition:
                transform 0.4s
                cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }


        .logo:hover img {
            transform: rotate(-15deg) scale(1.1);
        }


        .logo h2 {
            color: var(--secondary);
            font-weight: 800;
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
            letter-spacing: -0.5px;
        }


        .logo span {
            color: var(--primary);
        }


        .menu-toggle {
            display: none;
        }


        .nav-links {
            display: flex;
            align-items: center;
            gap: clamp(15px, 3vw, 35px);
        }


        .nav-links a {
            text-decoration: none;
            color: var(--text-main);
            font-weight: 600;
            font-size: clamp(0.85rem, 1.5vw, 0.95rem);
            transition: var(--transition);
            position: relative;
        }


        .nav-links a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 3px;
            bottom: -6px;
            left: 0;
            background-color: var(--primary);
            transition: width 0.3s ease;
            border-radius: 2px;
        }


        .nav-links a:hover {
            color: var(--primary);
        }


        .nav-links a:hover::after {
            width: 100%;
        }


        .btn-login {
            background: var(--primary);
            color: white !important;
            padding:
                clamp(8px, 1.5vw, 10px)
                clamp(15px, 3vw, 25px);
            border-radius: 50px;
            box-shadow:
                0 4px 15px rgba(255, 159, 67, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }


        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background:
                linear-gradient(
                    90deg,
                    transparent,
                    rgba(255,255,255,0.4),
                    transparent
                );
            transition: left 0.5s ease;
        }


        .btn-login:hover::before {
            left: 100%;
        }


        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow:
                0 8px 25px rgba(255, 159, 67, 0.5);
        }


        

        .hero {
            position: relative;
            height: clamp(60vh, 90vh, 900px);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding-top: 80px;
            overflow: hidden;
            clip-path: ellipse(150% 100% at 50% 0%);
        }


        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                linear-gradient(
                    rgba(0,0,0,0.5),
                    rgba(0,0,0,0.7)
                ),
                url("ui.png")
                center/cover no-repeat;
            z-index: -1;
            animation:
                kenBurns 20s infinite alternate ease-in-out;
        }


        @keyframes kenBurns {

            0% {
                transform: scale(1);
            }

            100% {
                transform: scale(1.15);
            }

        }


        .hero-content {
            width: 100%;
            max-width: min(100%, 800px);
            color: white;
            padding: 20px;
            z-index: 1;
        }


        .hero-content .section-tag {
            display: inline-block;
            animation: fadeDown 1s ease 0.5s both;
            color: var(--primary);
            background: rgba(255,255,255,0.1);
            padding: 5px 15px;
            border-radius: 20px;
            margin-bottom: 15px;
        }


        .hero-content h1 {
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 20px;
            animation: fadeUp 1s ease 0.7s both;
            text-shadow:
                0 5px 15px rgba(0,0,0,0.3);
        }


        .hero-content p {
            font-size: clamp(1rem, 2vw, 1.2rem);
            margin-bottom: clamp(20px, 4vw, 40px);
            font-weight: 300;
            animation: fadeUp 1s ease 0.9s both;
        }


        .hero-btn {
            background: var(--primary);
            color: white;
            text-decoration: none;
            font-weight: 600;
            padding:
                clamp(12px, 2vw, 18px)
                clamp(25px, 4vw, 45px);
            border-radius: 50px;
            font-size: clamp(0.9rem, 1.5vw, 1.1rem);
            display: inline-block;
            box-shadow:
                0 10px 30px rgba(255, 159, 67, 0.4);
            animation: zoomIn 1s ease 1.1s both;
            transition: var(--transition);
        }


        .hero-btn:hover {
            background: var(--white);
            color: var(--primary);
            transform: translateY(-5px) scale(1.05);
        }


        @keyframes fadeUp {

            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }

        }


        @keyframes fadeDown {

            from {
                opacity: 0;
                transform: translateY(-30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }

        }


        @keyframes zoomIn {

            from {
                opacity: 0;
                transform: scale(0.8);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }

        }


        

        .pets {
            padding: clamp(50px, 8vw, 100px) 8%;
            text-align: center;
        }


        .section-tag {
            color: var(--primary);
            font-weight: 800;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 2px;
            display: block;
        }


        .pets h2 {
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            margin: 10px 0 20px;
            color: var(--secondary);
            font-weight: 800;
        }


        .pet-container {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(min(100%, 300px), 1fr)
                );
            gap: clamp(20px, 3vw, 35px);
            margin-top: clamp(30px, 5vw, 50px);
            width: 100%;
        }


        .pet-card {
            background: var(--white);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition:
                all 0.5s
                cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            border: 1px solid rgba(0,0,0,0.02);
            display: flex;
            flex-direction: column;
            width: 100%;
        }


        .pet-card:hover {
            transform: translateY(-15px);
            box-shadow: var(--shadow-hover);
        }


        .pet-img-container {
            position: relative;
            width: 100%;
            height: clamp(220px, 30vh, 280px);
            overflow: hidden;
        }


        .pet-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.8s ease;
        }


        .pet-card:hover img {
            transform: scale(1.15) rotate(1deg);
        }


        .badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255,255,255,0.95);
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--primary);
            box-shadow:
                0 4px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(5px);
            animation: pulseBadge 2s infinite;
        }


        @keyframes pulseBadge {

            0% {
                box-shadow:
                    0 0 0 0
                    rgba(255,159,67,0.4);
            }

            70% {
                box-shadow:
                    0 0 0 10px
                    rgba(255,159,67,0);
            }

            100% {
                box-shadow:
                    0 0 0 0
                    rgba(255,159,67,0);
            }

        }


        .pet-card-content {
            padding: clamp(20px, 2.5vw, 30px);
            text-align: left;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }


        .pet-card h3 {
            font-size: clamp(1.3rem, 2vw, 1.6rem);
            margin-bottom: 8px;
            color: var(--secondary);
            font-weight: 700;
            transition: color 0.3s;
        }


        .pet-card:hover h3 {
            color: var(--primary);
        }


        .pet-info {
            color: var(--text-muted);
            font-size: clamp(0.85rem, 1.5vw, 0.95rem);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }


        .pet-info i {
            color: var(--primary);
        }


        .adopt-btn {
            display: block;
            width: 100%;
            background: var(--bg-light);
            color: var(--secondary);
            padding: 14px;
            border: 2px solid transparent;
            cursor: pointer;
            border-radius: 14px;
            text-align: center;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            transition: var(--transition);
            margin-top: auto;
        }


        .adopt-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow:
                0 8px 20px rgba(255,159,67,0.3);
        }


        

        .about {
            padding: clamp(60px, 8vw, 120px) 8%;
            background: var(--white);
            position: relative;
            overflow: hidden;
        }


        .about-grid {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(min(100%, 450px), 1fr)
                );
            gap: clamp(40px, 5vw, 80px);
            align-items: center;
        }


        .about-text {
            text-align: left;
        }


        .about-text h2 {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 800;
            line-height: 1.2;
            margin: 10px 0 20px;
            color: var(--secondary);
        }


        .about-info {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(min(100%, 200px), 1fr)
                );
            gap: 20px;
            margin-top: 40px;
        }


        .info-box {
            padding: clamp(20px, 2vw, 30px);
            background: var(--bg-light);
            border-radius: 24px;
            transition: var(--transition);
            border: 1px solid rgba(0,0,0,0.02);
        }


        .info-box:hover {
            transform: translateY(-10px);
            background: white;
            box-shadow: var(--shadow-hover);
        }


        .info-box i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 15px;
            transition: transform 0.3s;
        }


        .info-box:hover i {
            transform: scale(1.2) rotate(10deg);
        }


        .info-box h4 {
            margin-bottom: 8px;
            font-size: clamp(1rem, 1.5vw, 1.1rem);
            font-weight: 700;
            color: var(--secondary);
        }


        .info-box p {
            font-size: clamp(0.85rem, 1.2vw, 0.9rem);
            color: var(--text-muted);
        }


        .about-image {
            position: relative;
        }


        .about-image::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 20px;
            left: -20px;
            background: var(--primary);
            border-radius: 30px;
            z-index: 0;
            opacity: 0.1;
        }


        .about-image img {
            width: 100%;
            max-width: min(100%, 800px);
            height: auto;
            border-radius: 30px;
            box-shadow: var(--shadow);
            object-fit: cover;
            position: relative;
            z-index: 1;
            transition: transform 0.5s;
        }


        .about-image:hover img {
            transform: scale(1.02);
        }


        

        footer {
            background: var(--secondary);
            color: #bdc3c7;
            padding: clamp(50px, 6vw, 80px) 8% 30px;
            text-align: center;
            position: relative;
        }


        .footer-logo h2 {
            color: white;
            margin-bottom: 25px;
            font-size: clamp(1.8rem, 3vw, 2.5rem);
            font-weight: 800;
        }


        .social-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 40px;
        }


        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            color: white;
            font-size: 1.2rem;
            transition: all 0.3s;
        }


        .social-links a:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-5px) scale(1.1);
            box-shadow:
                0 10px 20px rgba(255,159,67,0.3);
        }


        .copyright {
            font-size: clamp(0.8rem, 1.5vw, 0.9rem);
            border-top:
                1px solid rgba(255,255,255,0.1);
            padding-top: 30px;
        }


        

        .alert {
            padding: 18px 25px;
            text-align: center;
            font-weight: 600;
            z-index: 3000;
            position: fixed;
            top: 100px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 12px;
            width: clamp(300px, 90%, 600px);
            box-shadow:
                0 15px 40px rgba(0,0,0,0.15);
            animation:
                popDown
                0.6s
                cubic-bezier(0.68,-0.55,0.265,1.55)
                forwards;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }


        @keyframes popDown {

            0% {
                top: -50px;
                opacity: 0;
                transform:
                    translateX(-50%)
                    scale(0.8);
            }

            100% {
                top: 100px;
                opacity: 1;
                transform:
                    translateX(-50%)
                    scale(1);
            }

        }


        @keyframes fadeOutUp {

            to {
                top: -50px;
                opacity: 0;
                transform:
                    translateX(-50%)
                    scale(0.8);
            }

        }


        .success {
            background: #fff;
            color: #155724;
            border-left: 6px solid #28a745;
        }


        .success i {
            color: #28a745;
            font-size: 1.2rem;
        }


        .error {
            background: #fff;
            color: #721c24;
            border-left: 6px solid #dc3545;
        }


        .error i {
            color: #dc3545;
            font-size: 1.2rem;
        }


        

        .modal {
            display: none;
            position: fixed;
            z-index: 2500;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.4s ease;
        }


        .modal.show {
            opacity: 1;
        }


        .modal-content {
            background: #fff;
            border-radius: 24px;
            width: clamp(320px, 90%, 850px);
            max-height: 90vh;
            overflow-y: auto;
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(min(100%, 350px), 1fr)
                );
            position: relative;
            transform:
                scale(0.8)
                translateY(50px);
            opacity: 0;
            transition:
                all 0.5s
                cubic-bezier(0.34,1.56,0.64,1);
            box-shadow:
                0 25px 50px rgba(0,0,0,0.3);
        }


        .modal.show .modal-content {
            transform:
                scale(1)
                translateY(0);
            opacity: 1;
        }


        .modal-img {
            width: 100%;
            height: clamp(250px, 40vh, 100%);
            object-fit: cover;
            object-position: center;
            border-radius: 24px 0 0 24px;
        }


        .modal-body {
            padding: clamp(25px, 4vw, 45px);
            position: relative;
            text-align: left;
        }


        .close-btn {
            position: absolute;
            top: 20px;
            right: 25px;
            font-size: 1.8rem;
            cursor: pointer;
            color: var(--text-muted);
            transition: all 0.3s;
            background: var(--bg-light);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }


        .close-btn:hover {
            background: #ffeaa7;
            color: #d63031;
            transform: rotate(90deg);
        }


        .modal-body h2 {
            color: var(--secondary);
            margin: 5px 0 10px;
            font-size: clamp(1.8rem, 3vw, 2.2rem);
            font-weight: 800;
        }


        .modal-body p {
            color: var(--text-muted);
            font-size: clamp(0.9rem, 1.5vw, 1rem);
            margin-bottom: 20px;
            line-height: 1.7;
        }


        .adopt-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }


        .adopt-form input {
            padding: 15px;
            border: 2px solid #eee;
            border-radius: 12px;
            font-family: 'Poppins';
            font-size: 0.95rem;
            outline: none;
            transition: var(--transition);
            width: 100%;
            background: #fdfdfd;
        }


        .adopt-form input:focus {
            border-color: var(--primary);
            background: #fff;
            box-shadow:
                0 0 0 4px
                rgba(255,159,67,0.1);
        }


        .submit-adopt {
            background: var(--primary);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            font-size: 1.1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }


        .submit-adopt:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow:
                0 10px 20px rgba(255,159,67,0.3);
        }


        

        @media (max-width: 768px) {

            .menu-toggle {
                display: block;
                background: none;
                border: none;
                font-size: 1.8rem;
                color: var(--secondary);
                cursor: pointer;
                z-index: 1001;
            }

            .nav-links {
                position: absolute;
                top: 100%;
                left: 0;
                width: 100%;
                background: rgba(255, 255, 255, 0.98);
                flex-direction: column;
                align-items: center;
                padding: 0;
                max-height: 0;
                overflow: hidden;
                box-shadow: 0 10px 20px rgba(0,0,0,0.1);
                transition: max-height 0.4s ease, padding 0.4s ease;
            }

            .nav-links.active {
                max-height: 400px;
                padding: 20px 0;
                border-top: 1px solid #eee;
            }

            .nav-links a {
                margin: 10px 0;
                font-size: 1.1rem;
            }

            .btn-login {
                margin-top: 10px;
                width: 80%;
                text-align: center;
            }

            .modal-img {
                border-radius: 24px 24px 0 0;
            }

        }


        .photo-unavailable { min-height:300px; background:linear-gradient(135deg,#fff3e1,#f9d6a5); border-radius:30px; display:grid; place-items:center; }
        .photo-unavailable::after { content:'🐾 San Pedro AnimalShelter'; font-size:1.5rem; font-weight:700; color:#9a5b1d; }
        .journey { padding:100px 8%; background:#f8f9fa; text-align:center; scroll-margin-top:95px; }
        .journey-head { max-width:750px; margin:0 auto 44px; }
        .journey-head h2 { font-size:clamp(2rem,4vw,3rem); color:#2d3436; margin:10px 0 15px; line-height:1.2; }
        .journey-head p { color:#636e72; }
        .journey-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:22px; }
        .journey-card { position:relative; text-align:left; background:white; border:1px solid #f0e8dd; padding:30px 25px; border-radius:24px; box-shadow:0 10px 30px rgba(30,40,45,.04); transition:transform .3s,box-shadow .3s; }
        .journey-card:hover { transform:translateY(-7px); box-shadow:0 18px 35px rgba(255,159,67,.14); }
        .journey-icon { display:grid; place-items:center; width:58px; height:58px; border-radius:17px; color:#e98b22; background:#fff2e0; font-size:1.5rem; margin-bottom:24px; }
        .journey-number { position:absolute; top:32px; right:25px; font-size:.85rem; font-weight:800; color:#b8bbc1; }
        .journey-card h3 { font-size:1.12rem; margin-bottom:10px; }
        .journey-card p { color:#636e72; font-size:.91rem; line-height:1.75; }
        .journey-action { margin-top:48px; }
        .journey-action .hero-btn { animation:none; }
        .journey-action .hero-btn i { margin-left:10px; }
        .impact-section { display:grid; grid-template-columns:1.3fr 1fr; align-items:center; gap:40px; padding:90px 8%; background:#172333; color:white; overflow:hidden; }
        .impact-inner h2 { font-size:clamp(2rem,3.6vw,3rem); line-height:1.2; margin:15px 0 20px; }
        .impact-inner p { max-width:650px; color:#cbd5e1; line-height:1.9; }
        .impact-link { display:inline-flex; gap:12px; align-items:center; margin-top:24px; text-decoration:none; color:#ffad53; font-weight:700; }
        .impact-art { min-height:270px; border:1px solid rgba(255,255,255,.12); border-radius:28px; background:radial-gradient(circle at 70% 20%,rgba(255,159,67,.3),transparent 60%),#243449; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:22px; }
        .impact-art i { font-size:7rem; color:#ff9f43; }
        .impact-art span { font-weight:700; font-size:1.1rem; }
        #about { scroll-margin-top:95px; }
        .about-info a { color:#d47b18; overflow-wrap:anywhere; }
        @media(max-width:1050px) { .journey-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media(max-width:650px) { .journey { padding:70px 6%; } .journey-grid { grid-template-columns:1fr; } .impact-section { grid-template-columns:1fr; padding:65px 6%; } .impact-art { min-height:210px; } .impact-art i { font-size:5rem; } }
        @media(prefers-reduced-motion:reduce) { .reveal,.reveal-left,.reveal-right,.reveal-zoom { opacity:1; transform:none; transition:none; } html { scroll-behavior:auto; } }

    </style>

</head>

<body>







<header class="navbar" id="navbar">

    <a href="#" class="logo">

        <img
            src="sanpedrologo.jpg"
            alt="San Pedro AnimalShelter Logo"
        />

        <h2>
            SanPedro<span>AnimalShelter</span>
        </h2>

    </a>

    
    <button class="menu-toggle" id="mobile-menu-btn">
        <i class="fa-solid fa-bars"></i>
    </button>


    <nav class="nav-links" id="nav-links">

        <a href="index.php">
            Home
        </a>

        <a href="#how-it-works">
            How It Works
        </a>

        <a href="#about">
            About
        </a>


        <?php if(isset($_SESSION['user_id'])): ?>

            <a
                href="<?php echo htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8'); ?>"
                class="btn-login"
            >
                My Dashboard
            </a>

        <?php else: ?>

            <a
                href="login.php"
                class="btn-login"
            >
                Login
            </a>

        <?php endif; ?>

    </nav>

</header>




<section class="hero">

    <div class="hero-content">

        <span class="section-tag">
            Rescue. Adopt. Love.
        </span>


        <h1>
            Every Paw Deserves a Loving Home
        </h1>


        <p>
            Every rescued animal deserves a safe home. Discover how our shelter connects compassionate people with pets in need, and begin your adoption journey with confidence.
        </p>


        <a
            href="#how-it-works"
            class="hero-btn"
        >

            Explore Adoption

            <i
                class="fa-solid fa-arrow-right"
                style="margin-left: 8px;"
            ></i>

        </a>

    </div>

</section>




<section class="journey" id="how-it-works">
    <div class="journey-head reveal">
        <span class="section-tag">Simple, Responsible Adoption</span>
        <h2>How your adoption journey works</h2>
        <p>From finding a companion to welcoming them home, we're here to guide you through every step.</p>
    </div>
    <div class="journey-grid">
        <article class="journey-card reveal-zoom"><div class="journey-icon"><i class="fa-solid fa-user-plus"></i></div><span class="journey-number">01</span><h3>Create an account</h3><p>Sign up and complete your client profile so our shelter team can get to know you.</p></article>
        <article class="journey-card reveal-zoom"><div class="journey-icon"><i class="fa-solid fa-paw"></i></div><span class="journey-number">02</span><h3>Find your companion</h3><p>Browse available pets and explore matching recommendations in your client dashboard.</p></article>
        <article class="journey-card reveal-zoom"><div class="journey-icon"><i class="fa-solid fa-clipboard-check"></i></div><span class="journey-number">03</span><h3>Submit an application</h3><p>Apply for a pet and follow your application status as the shelter reviews your request.</p></article>
        <article class="journey-card reveal-zoom"><div class="journey-icon"><i class="fa-solid fa-house-heart"></i></div><span class="journey-number">04</span><h3>Welcome them home</h3><p>Coordinate the next steps with the shelter team when your application is approved.</p></article>
    </div>
    <div class="journey-action reveal"><a class="hero-btn" href="<?php echo htmlspecialchars($startUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $loggedIn ? 'Open My Dashboard' : 'Get Started'; ?> <i class="fa-solid fa-arrow-right"></i></a></div>
</section>
<section class="impact-section" id="rescue-care"><div class="impact-inner reveal-left"><span class="section-tag">Rescue. Adopt. Love.</span><h2>Small acts of care create life-changing stories.</h2><p>Our shelter brings together rescue, responsible adoption, and a community committed to animal welfare. You can make a difference by adopting, reporting animals in need, or supporting the shelter.</p><a href="#about" class="impact-link">Learn about our mission <i class="fa-solid fa-arrow-right"></i></a></div><div class="impact-art reveal-right"><i class="fa-solid fa-paw"></i><span>Give them a second chance.</span></div></section>



<section
    class="about"
    id="about"
>

    <div class="about-grid">


        <div class="about-text reveal-left">

            <span class="section-tag">
                Our Mission
            </span>


            <h2>
                We believe every paw deserves a home
            </h2>


            <p>

                SanPedro AnimalShelter is more than just
                an adoption platform. We are a community
                of animal lovers dedicated to giving a
                second chance to those who need it most.
                Together, we can make a difference.

            </p>


            <div class="about-info">


                <div
                    class="info-box reveal"
                    style="transition-delay: 0.1s;"
                >

                    <i class="fa-solid fa-envelope"></i>

                    <h4>
                        Facebook
                    </h4>

                    <p>
                        <a href="https://www.facebook.com/sanpedrominianimelshelter" target="_blank" rel="noopener noreferrer">Message our shelter page</a>
                    </p>

                </div>


                <div
                    class="info-box reveal"
                    style="transition-delay: 0.2s;"
                >

                    <i class="fa-solid fa-phone"></i>

                    <h4>
                        Call Us
                    </h4>

                    <p>
                        Contact us through our Facebook page
                    </p>

                </div>


                <div
                    class="info-box reveal"
                    style="transition-delay: 0.3s;"
                >

                    <i class="fa-solid fa-location-dot"></i>

                    <h4>
                        Visit Us
                    </h4>

                    <p>
                        San Pedro, Laguna, Philippines
                    </p>

                </div>


                <div
                    class="info-box reveal"
                    style="transition-delay: 0.4s;"
                >

                    <i class="fa-solid fa-heart"></i>

                    <h4>
                        Support
                    </h4>

                    <p>
                        Donation & Volunteers
                    </p>

                </div>


            </div>

        </div>


        <div class="about-image reveal-right">

            <img
                src="uploads/1773549198_stray dog.jpg"
                alt="About San Pedro AnimalShelter" onerror="this.style.display='none';this.parentElement.classList.add('photo-unavailable');this.parentElement.setAttribute('aria-label','Shelter photo unavailable')"
            >

        </div>

    </div>

</section>




<footer class="reveal">


    <div
        class="footer-logo reveal"
        style="transition-delay: 0.1s;"
    >

        <h2>
            SanPedro<span>AnimalShelter</span>
        </h2>

    </div>


    <div
        class="social-links reveal"
        style="transition-delay: 0.2s;"
    >

        <a
            href="https://www.facebook.com/sanpedrominianimelshelter"
            target="_blank"
            onclick="return confirm(
                'Paalala: Siguraduhing naka-login ka sa iyong sariling Facebook account para makita ang page na ito.\n\nGusto mo bang magpatuloy?'
            );"
        >

            <i class="fa-brands fa-facebook-f"></i>

        </a>

    </div>


    <div
        class="copyright reveal"
        style="transition-delay: 0.3s;"
    >

        &copy; 2026 Pet Adoption & Rescue System.

        Made with

        <i
            class="fa-solid fa-heart"
            style="
                color:var(--primary);
                margin:0 5px;
                animation:pulseBadge 1s infinite alternate;
            "
        ></i>

        for animals.

    </div>

</footer>




<script>


    

    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const navLinks = document.getElementById('nav-links');
    if (mobileMenuBtn && navLinks) {
        mobileMenuBtn.setAttribute('aria-expanded', 'false');
        mobileMenuBtn.addEventListener('click', () => {
            const open = navLinks.classList.toggle('active');
            mobileMenuBtn.setAttribute('aria-expanded', String(open));
            const icon = mobileMenuBtn.querySelector('i');
            icon.classList.toggle('fa-bars', !open);
            icon.classList.toggle('fa-xmark', open);
        });
        navLinks.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
            navLinks.classList.remove('active');
            mobileMenuBtn.setAttribute('aria-expanded', 'false');
            const icon = mobileMenuBtn.querySelector('i');
            icon.classList.add('fa-bars');
            icon.classList.remove('fa-xmark');
        }));
    }

    window.addEventListener('scroll', () => {

        const navbar =
            document.getElementById('navbar');


        if (window.scrollY > 50) {

            navbar.style.padding =
                'clamp(10px, 1.5vw, 15px) 8%';

            navbar.style.background =
                'rgba(255, 255, 255, 0.98)';

            navbar.style.boxShadow =
                '0 10px 30px rgba(0,0,0,0.1)';

        } else {

            navbar.style.padding =
                'clamp(15px, 2vw, 25px) 8%';

            navbar.style.background =
                'rgba(255, 255, 255, 0.95)';

            navbar.style.boxShadow =
                '0 2px 20px rgba(0,0,0,0.05)';
        }

    });


    

    const observerOptions = {

        threshold: 0.1,

        rootMargin:
            "0px 0px -50px 0px"

    };


    const observer =
        new IntersectionObserver(
            (entries) => {

                entries.forEach(entry => {

                    if (entry.isIntersecting) {

                        entry.target
                            .classList
                            .add('active');

                        observer
                            .unobserve(
                                entry.target
                            );
                    }

                });

            },
            observerOptions
        );


    document
        .querySelectorAll(
            '.reveal, .reveal-left, .reveal-right, .reveal-zoom'
        )
        .forEach(el => {

            observer.observe(el);

        });


    

    setTimeout(() => {

        const alertBox =
            document.querySelector('.alert');


        if (alertBox) {

            alertBox.style.animation =
                'fadeOutUp 0.6s cubic-bezier(0.68,-0.55,0.265,1.55) forwards';


            setTimeout(() => {

                alertBox.remove();

            }, 600);

        }

    }, 4500);


    

    if ('serviceWorker' in navigator) {

        navigator
            .serviceWorker
            .register('/service-worker.js')

            .then(() => {

                console.log(
                    "Service Worker Registered"
                );

            })

            .catch(err => {

                console.log(
                    "Service Worker Failed:",
                    err
                );

            });

    }

</script>


</body>
</html>
