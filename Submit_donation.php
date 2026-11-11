<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: donate.php");
    exit;
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli(
    "localhost",
    "root",
    "",
    "pet_adoption_rescue"
);

$conn->set_charset("utf8mb4");

function stopWithError($message)
{
    echo '<!DOCTYPE html><html lang="en"><head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <title>Donation Error</title></head>
          <body style="font-family:Arial;padding:35px;background:#f5f7fb">
          <h2>Donation not submitted</h2><p>'
          . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
          . '</p><p><a href="donate.php">Return to Donations</a></p>
          </body></html>';
    exit;
}

$userId = filter_var(
    $_SESSION['user_id'],
    FILTER_VALIDATE_INT
);

if (!$userId || $userId < 1) {
    stopWithError("Your login session is invalid. Please log in again.");
}

$donorName = trim($_POST['donor_name'] ?? '');
$donorEmail = trim($_POST['donor_email'] ?? '');
$amount = $_POST['amount'] ?? '';
$paymentMethod = $_POST['payment_method'] ?? '';
$purpose = $_POST['donation_purpose'] ?? '';
$referenceNumber = trim($_POST['reference_number'] ?? '');

if ($donorName === '' || strlen($donorName) > 150) {
    stopWithError("Please enter a valid donor name.");
}

if ($donorEmail !== '' &&
    (!filter_var($donorEmail, FILTER_VALIDATE_EMAIL) ||
     strlen($donorEmail) > 150)) {
    stopWithError("Please enter a valid email address.");
}

if (!is_numeric($amount) ||
    (float)$amount < 1 ||
    (float)$amount > 100000) {
    stopWithError("Donation must be between ₱1 and ₱100,000.");
}

$amount = number_format((float)$amount, 2, '.', '');

$allowedMethods = ['GCash', 'Maya'];

$allowedPurposes = [
    'General Fund',
    'Pet Food',
    'Medical Care',
    'Rescue Operations'
];

if (!in_array($paymentMethod, $allowedMethods, true)) {
    stopWithError("Invalid payment method.");
}

if (!in_array($purpose, $allowedPurposes, true)) {
    stopWithError("Invalid donation purpose.");
}

if ($referenceNumber === '' || strlen($referenceNumber) > 100) {
    stopWithError("Please enter your payment reference number.");
}

if (empty($_FILES['proof_image']) ||
    $_FILES['proof_image']['error'] !== UPLOAD_ERR_OK) {
    stopWithError("Please upload your payment receipt.");
}

$file = $_FILES['proof_image'];

if ($file['size'] <= 0 || $file['size'] > 5 * 1024 * 1024) {
    stopWithError("Your receipt must be smaller than 5 MB.");
}

if (!is_uploaded_file($file['tmp_name'])) {
    stopWithError("Invalid receipt upload.");
}

/* Validate the actual image type, not just the file extension. */
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
];

if (!isset($allowedTypes[$mime]) ||
    @getimagesize($file['tmp_name']) === false) {
    stopWithError("Upload a valid JPG, PNG, or WebP receipt image.");
}

/* Reject duplicate references for the same payment method. */
$check = $conn->prepare(
    "SELECT id FROM donations
     WHERE payment_method = ? AND reference_number = ?
     LIMIT 1"
);

$check->bind_param("ss", $paymentMethod, $referenceNumber);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    $check->close();
    stopWithError("This payment reference has already been submitted.");
}

$check->close();

/* Create the upload directory if it does not exist. */
$uploadDir = __DIR__ . '/uploads/donation_proofs/';

if (!is_dir($uploadDir) &&
    !mkdir($uploadDir, 0755, true)) {
    stopWithError("Unable to prepare the receipt upload directory.");
}

if (!is_writable($uploadDir)) {
    stopWithError("The receipt upload directory is not writable.");
}

/* Use a random filename instead of the uploaded filename. */
$filename = bin2hex(random_bytes(20))
          . '.' . $allowedTypes[$mime];

$destination = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    stopWithError("Unable to save your receipt. Please try again.");
}

$relativePath = 'uploads/donation_proofs/' . $filename;

try {
    $stmt = $conn->prepare(
        "INSERT INTO donations
        (
            user_id,
            donor_name,
            donor_email,
            amount,
            payment_method,
            donation_purpose,
            reference_number,
            proof_image,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')"
    );

    $stmt->bind_param(
        "issdssss",
        $userId,
        $donorName,
        $donorEmail,
        $amount,
        $paymentMethod,
        $purpose,
        $referenceNumber,
        $relativePath
    );

    $stmt->execute();
    $stmt->close();

} catch (Throwable $e) {
    if (is_file($destination)) {
        unlink($destination);
    }

    error_log("Donation submission error: " . $e->getMessage());

    stopWithError(
        "We could not save your donation. Please check your database setup."
    );
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Donation Submitted</title>
</head>

<body style="margin:0;background:#f5f7fb;font-family:Arial,sans-serif">
<div style="max-width:550px;margin:70px auto;padding:35px 25px;background:white;
            border-radius:18px;text-align:center;box-shadow:0 8px 30px #0000000d">

    <div style="font-size:55px">🐾</div>
    <h1 style="color:#16a34a">Thank You for Donating!</h1>

    <p style="line-height:1.8;color:#667085">
        Your donation submission has been saved successfully.
        Your payment is currently <strong>Pending</strong> while shelter
        staff verify your transaction and receipt.
    </p>

    <a href="client_dashboard.php"
       style="display:inline-block;margin-top:18px;padding:13px 22px;
              border-radius:9px;background:#ff9f43;color:white;
              text-decoration:none;font-weight:bold">
        Return to Dashboard
    </a>
</div>
</body>
</html>