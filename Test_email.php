<?php

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {

    /* =========================================
       DEBUG MODE
    ========================================= */

    $mail->SMTPDebug = 2;
    $mail->Debugoutput = 'html';


    /* =========================================
       GMAIL SMTP
    ========================================= */

    $mail->isSMTP();

    $mail->Host = 'smtp.gmail.com';

    $mail->SMTPAuth = true;

    $mail->Username = 'lesterneildinojales@gmail.com';

    /*
     * IMPORTANT:
     * Put your NEW Google App Password here.
     *
     * Do NOT use your normal Gmail password.
     */
    $mail->Password = 'pvmc rbew vrxq dokg';

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

    $mail->Port = 587;

    $mail->CharSet = 'UTF-8';


    /* =========================================
       SENDER
    ========================================= */

    $mail->setFrom(
        'lesterneildinojales@gmail.com',
        'PAW Care'
    );


    /* =========================================
       RECIPIENT
    ========================================= */

    $mail->addAddress(
        'lesterinojales@gmail.com'
    );


    /* =========================================
       EMAIL
    ========================================= */

    $mail->isHTML(true);

    $mail->Subject = 'PAW Care SMTP Test';

    $mail->Body = '
        <div style="
            font-family: Arial, sans-serif;
            padding: 20px;
        ">

            <h2 style="color:#FF9F43;">
                PAW Care
            </h2>

            <p>
                Hello!
            </p>

            <p>
                This is a test email from the
                <strong>PAW Care Pet Adoption System</strong>.
            </p>

            <p>
                If you received this email, your
                Gmail SMTP configuration is working.
            </p>

            <p>
                Thank you!
            </p>

        </div>
    ';

    $mail->AltBody =
        "PAW Care\n\n" .
        "This is a test email from the PAW Care Pet Adoption System.\n\n" .
        "If you received this email, your Gmail SMTP configuration is working.";


    /* =========================================
       SEND
    ========================================= */

    $mail->send();

    echo "
        <h2 style='color:green;'>
            SUCCESS!
        </h2>

        <p>
            Test email was sent successfully.
        </p>
    ";

} catch (Exception $e) {

    echo "
        <h2 style='color:red;'>
            FAILED!
        </h2>

        <p>
            Email could not be sent.
        </p>

        <p>
            <strong>PHPMailer Error:</strong>
        </p>

        <pre style='
            background:#f5f5f5;
            padding:15px;
            border:1px solid #ddd;
            white-space:pre-wrap;
        '>" .
        htmlspecialchars($mail->ErrorInfo) .
        "</pre>
    ";
}
?>