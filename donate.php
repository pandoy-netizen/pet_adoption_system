
<?php
session_start();
require_once 'db.php'; // Dapat naglalaman ng $conn (MySQLi)

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $method = $_POST['payment_method'] ?? '';
    $reference = trim($_POST['reference_number'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!$amount || $amount < 1 || $amount > 1000000) {
        $error = 'Please enter a valid donation amount.';
    } elseif (!in_array($method, ['GCash', 'Maya'], true)) {
        $error = 'Please select GCash or Maya.';
    } elseif ($reference === '' || strlen($reference) > 100) {
        $error = 'Please enter a valid payment reference number.';
    } else {
        $proof = null;

        if (isset($_FILES['proof_image']) &&
            $_FILES['proof_image']['error'] !== UPLOAD_ERR_NO_FILE) {

            if ($_FILES['proof_image']['error'] !== UPLOAD_ERR_OK ||
                $_FILES['proof_image']['size'] > 5 * 1024 * 1024) {
                $error = 'Receipt must be 5 MB or smaller.';
            } else {
                $imageInfo = getimagesize($_FILES['proof_image']['tmp_name']);
                $allowed = [
                    IMAGETYPE_JPEG => 'jpg',
                    IMAGETYPE_PNG  => 'png',
                    IMAGETYPE_WEBP => 'webp'
                ];

                if (!$imageInfo ||
                    !isset($allowed[$imageInfo[2]])) {
                    $error = 'Upload a valid JPG, PNG, or WEBP receipt.';
                } else {
                    $folder = __DIR__ . '/uploads/donation_proofs/';

                    if (!is_dir($folder)) {
                        mkdir($folder, 0755, true);
                    }

                    $filename = bin2hex(random_bytes(16)) .
                        '.' . $allowed[$imageInfo[2]];

                    if (move_uploaded_file(
                        $_FILES['proof_image']['tmp_name'],
                        $folder . $filename
                    )) {
                        $proof = $filename;
                    } else {
                        $error = 'Unable to save the receipt.';
                    }
                }
            }
        }

        if ($error === '') {
            // Reference numbers must not be reused.
            $check = $conn->prepare(
                "SELECT id FROM donations
                 WHERE payment_method = ? AND reference_number = ?
                 LIMIT 1"
            );
            $check->bind_param('ss', $method, $reference);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = 'This reference number has already been submitted.';
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO donations
                    (user_id, donor_name, amount, payment_method,
                     reference_number, proof_image, message)
                    SELECT id, name, ?, ?, ?, ?, ?
                    FROM users WHERE id = ?"
                );

                // If your users table uses username/full_name instead
                // of name, change the column above to match your schema.
                $stmt->bind_param(
                    'dsssssi',
                    $amount, $method, $reference,
                    $proof, $message, $user_id
                );

                if ($stmt->execute() && $stmt->affected_rows === 1) {
                    $success = 'Donation submitted! Waiting for admin verification.';
                } else {
                    $error = 'Unable to submit donation. Check your users table.';
                }
                $stmt->close();
            }
            $check->close();
        }
    }
}

$stmt = $conn->prepare(
    "SELECT amount, payment_method, reference_number, status, created_at
     FROM donations WHERE user_id = ? ORDER BY id DESC"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$history = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Donate | Pet Rescue</title>
<style>
*{box-sizing:border-box}
body{margin:0;padding:25px;background:#f5f7fb;
font-family:Arial,sans-serif;color:#263238}
.container{max-width:850px;margin:auto}
.card{background:white;padding:25px;border-radius:18px;
box-shadow:0 5px 22px #0000000b;margin-bottom:22px}
h1{color:#ef8b27}
.qr{text-align:center}
.qr img{width:240px;max-width:100%;border:1px solid #eee;
border-radius:12px;padding:8px}
input,select,textarea{width:100%;padding:12px;margin:7px 0 16px;
border:1px solid #ddd;border-radius:9px;font-size:15px}
button{background:#ff9f43;color:white;border:0;padding:13px 20px;
border-radius:9px;cursor:pointer;font-weight:bold}
.notice{padding:12px;background:#e8f7ec;border-radius:8px;color:#176534}
.error{padding:12px;background:#ffeded;border-radius:8px;color:#a32121}
table{width:100%;border-collapse:collapse}
th,td{text-align:left;padding:10px;border-bottom:1px solid #eee}
.table-wrap{overflow-x:auto}
</style>
</head>
<body>
<div class="container">
<h1>🐾 Support Our Animal Shelter</h1>
<p>Your donation helps provide food, medicine, and care for rescued animals.</p>

<?php if ($success): ?>
<div class="notice"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card qr">
<h2>Donate through GCash / Maya</h2>
<p>1. Open your payment app.</p>
<p>2. Scan the official shelter QR code below.</p>
<p>3. Enter your donation amount and complete payment.</p>
<img src="uploads/donation_qr.png"
     alt="Official shelter donation QR code"
     onerror="this.style.display='none'">
<p><strong>Important:</strong> Check the recipient name before paying.</p>
</div>

<div class="card">
<h2>Submit Your Donation</h2>
<form method="POST" enctype="multipart/form-data">
<label>Donation Amount (₱)</label>
<input type="number" name="amount" min="1" max="1000000"
       step="0.01" required>

<label>Payment Method</label>
<select name="payment_method" required>
<option value="">Select payment method</option>
<option value="GCash">GCash</option>
<option value="Maya">Maya</option>
</select>

<label>Payment Reference Number</label>
<input type="text" name="reference_number" maxlength="100" required>

<label>Upload Payment Receipt (optional)</label>
<input type="file" name="proof_image"
       accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

<label>Message (optional)</label>
<textarea name="message" rows="3" maxlength="1000"
          placeholder="A message for the rescued animals"></textarea>

<button type="submit">Submit Donation</button>
</form>
</div>

<div class="card">
<h2>My Donation History</h2>
<div class="table-wrap">
<table>
<thead><tr>
<th>Amount</th><th>Method</th><th>Reference</th>
<th>Status</th><th>Date</th>
</tr></thead>
<tbody>
<?php while ($row = $history->fetch_assoc()): ?>
<tr>
<td>₱<?= number_format((float)$row['amount'], 2) ?></td>
<td><?= htmlspecialchars($row['payment_method']) ?></td>
<td><?= htmlspecialchars($row['reference_number']) ?></td>
<td><?= htmlspecialchars($row['status']) ?></td>
<td><?= htmlspecialchars($row['created_at']) ?></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>
<a href="client_dashboard.php">← Back to Client Dashboard</a>
</div>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>