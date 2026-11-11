<?php
// 1. Include your database connection
require 'config.php'; // Assuming this holds your PDO/mysqli connection

// 2. Include PHPMailer manually using your folder structure
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ... (Your existing code to grab $username, $email, $password, etc.) ...
    
    // Generate the token
    $verify_token = bin2hex(random_bytes(16)); 

    // INSERT INTO DATABASE (Make sure you include email_verified = 0 and the verification_token)
    // -> Add your SQL INSERT logic here like in the previous example.
    
    // Assuming the INSERT was successful:
    if ($inserted) { // Change $inserted to whatever variable holds your execute() result
        
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();                                            
            $mail->Host       = 'smtp.gmail.com'; 
            $mail->SMTPAuth   = true;                                   
            $mail->Username   = 'your_email@gmail.com';   // Your Gmail
            $mail->Password   = 'your_app_password';      // Your Gmail App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         
            $mail->Port       = 587;                                    

            // Recipients
            $mail->setFrom('your_email@gmail.com', 'Pet Adoption Rescue');
            $mail->addAddress($email, $username); // Uses variables from your POST data

            // Build the verification link pointing to YOUR file: verifyemail.php
            // NOTE: Make sure 'pet_adoption_system' matches your folder name in xampp/htdocs
            $verify_link = "http://localhost/pet_adoption_system/verifyemail.php?token=" . $verify_token;

            // Content
            $mail->isHTML(true);                                  
            $mail->Subject = 'Verify your Email Address';
            $mail->Body    = "Click here to verify: <a href='{$verify_link}'>Verify Email</a>";

            $mail->send();
            
            // Redirect or show success message
            echo "Registration successful! Please check your email to verify.";
        } catch (Exception $e) {
            echo "Registration successful, but email could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }
}
?>