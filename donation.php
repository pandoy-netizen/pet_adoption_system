
<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

if (empty($_SESSION['donation_csrf'])) {
    $_SESSION['donation_csrf'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(
        $_SESSION['donation_csrf'],
        $_POST['csrf_token'] ?? ''
    )) {
        exit('Invalid request. Please refresh the page.');
    }

    $donor = trim($_POST['donor_name'] ?? '');
    $amount = filter_var(
        $_POST['amount'] ?? '',
        FILTER_VALIDATE_FLOAT
    );
    $method = $_POST['payment_method'] ?? '';
    $reference = trim($_POST['reference_number'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $proof = null;
    $error = '';

    if ($donor === '' || strlen($donor) > 150) {
        $error = 'Please enter your name (maximum 150 characters).';
    } elseif ($amount === false || $amount < 1 || $amount > 1000000) {
        $error = 'Enter a donation amount between ₱1 and ₱1,000,000.';
    } elseif (!in_array($method, ['GCash', 'Maya'], true)) {
        $error = 'Please select GCash or Maya.';
    } elseif ($reference === '' || strlen($reference) > 100) {
        $error = 'Enter a valid payment reference number.';
    } elseif (strlen($message) > 1000) {
        $error = 'Your message must be 1,000 characters or fewer.';
    }

    // Optional receipt image upload.
    if (
        $error === '' &&
        isset($_FILES['proof_image']) &&
        $_FILES['proof_image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        if (
            $_FILES['proof_image']['error'] !== UPLOAD_ERR_OK ||
            $_FILES['proof_image']['size'] > 5 * 1024 * 1024
        ) {
            $error = 'The receipt must be 5 MB or smaller.';
        } else {
            $info = getimagesize($_FILES['proof_image']['tmp_name']);
            $types = [
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_WEBP => 'webp'
            ];

            if (!$info || !isset($types[$info[2]])) {
                $error = 'Upload a valid JPG, PNG, or WEBP receipt.';
            } else {
                $dir = __DIR__ . '/uploads/donation_proofs/';

                if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                    $error = 'Unable to create the receipt folder.';
                } else {
                    $proof = bin2hex(random_bytes(16)) .
                        '.' . $types[$info[2]];

                    if (!move_uploaded_file(
                        $_FILES['proof_image']['tmp_name'],
                        $dir . $proof
                    )) {
                        $error = 'Unable to save your receipt.';
                        $proof = null;
                    }
                }
            }
        }
    }

    if ($error === '') {
        // Stop a reference number from being submitted more than once.
        $check = $conn->prepare(
            "SELECT id FROM donations
             WHERE payment_method = ? AND reference_number = ?
             LIMIT 1"
        );
        $check->bind_param('ss', $method, $reference);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'That payment reference has already been submitted.';
        }
        $check->close();
    }

    if ($error === '') {
        $stmt = $conn->prepare(
            "INSERT INTO donations
            (user_id, donor_name, amount, payment_method,
             reference_number, proof_image, message)
            VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'isdssss',
            $user_id,
            $donor,
            $amount,
            $method,
            $reference,
            $proof,
            $message
        );

        if ($stmt->execute()) {
            $success = 'Donation submitted! Your payment is pending admin verification.';
        } else {
            $error = 'Could not submit your donation. Please try again.';
            if ($proof && file_exists(__DIR__ . '/uploads/donation_proofs/' . $proof)) {
                unlink(__DIR__ . '/uploads/donation_proofs/' . $proof);
            }
        }
        $stmt->close();
    }
}

$stmt = $conn->prepare(
    "SELECT amount, payment_method, reference_number, status, created_at
     FROM donations
     WHERE user_id = ?
     ORDER BY id DESC"
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
<title>Donate to Animals | Pet Rescue</title>
<style>
*{box-sizing:border-box}
body{margin:0;padding:24px;background:#f5f7fb;
font-family:Arial,sans-serif;color:#263238}
.container{max-width:900px;margin:auto}
h1{color:#ef8b27}
.card{background:#fff;padding:24px;border-radius:16px;
margin:20px 0;box-shadow:0 4px 18px #0000000a}
.qr{text-align:center}
.qr img{width:250px;max-width:100%;padding:8px;
border:1px solid #eee;border-radius:12px}
label{display:block;font-weight:bold;margin-top:12px}
input,select,textarea{width:100%;padding:12px;margin:8px 0 12px;
border:1px solid #ddd;border-radius:8px;font-size:15px}
button{padding:13px 20px;border:0;border-radius:9px;
background:#ff9f43;color:white;font-weight:bold;cursor:pointer}
.notice{padding:12px;border-radius:8px;margin:12px 0}
.success{background:#e8f7ec;color:#176534}
.error{background:#ffeded;color:#a32121}
.table-wrap{overflow-x:auto}
table{width:100%;min-width:560px;border-collapse:collapse}
th,td{text-align:left;padding:11px;border-bottom:1px solid #eee}
th{background:#fff3e5}
a{color:#b9670a}
</style>
</head>
<body>
<div class="container">
<h1>🐾 Support Our Animal Shelter</h1>
<p>Your donation helps us care for rescued animals.</p>

<?php if ($success): ?>
<div class="notice success"><?= e($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="notice error"><?= e($error) ?></div>
<?php endif; ?>

<div class="card qr">
<h2>Donate via GCash or Maya</h2>
<p>1. Open your payment app.</p>
<p>2. Scan the shelter's official QR code.</p>
<p>3. Pay, then enter your transaction details below.</p>

<img src="uploads/donation_qr.png"
     alt="Shelter donation QR code"
     onerror="this.style.display='none'">

<p><strong>Important:</strong> Confirm the recipient name in your payment app before sending money.</p>
</div>

<div class="card">
<h2>Submit Your Donation</h2>
<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="csrf_token"
       value="<?= e($_SESSION['donation_csrf']) ?>">

<label>Donor Name</label>
<input type="text" name="donor_name" maxlength="150" required>

<label>Donation Amount (₱)</label>
<input type="number" name="amount" min="1" max="1000000"
       step="0.01" required>

<label>Payment Method</label>
<select name="payment_method" required>
<option value="">Choose payment method</option>
<option value="GCash">GCash</option>
<option value="Maya">Maya</option>
</select>

<label>Payment Reference Number</label>
<input type="text" name="reference_number" maxlength="100" required>

<label>Receipt Screenshot (optional, JPG/PNG/WEBP)</label>
<input type="file" name="proof_image"
       accept=".jpg,.jpeg,.png,.webp" >

<label>Message (optional)</label>
<textarea name="message" maxlength="1000" rows="3"
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
<?php if ($history->num_rows > 0): ?>
<?php while ($row = $history->fetch_assoc()): ?>
<tr>
<td>₱<?= number_format((float)$row['amount'], 2) ?></td>
<td><?= e($row['payment_method']) ?></td>
<td><?= e($row['reference_number']) ?></td>
<td><?= e($row['status']) ?></td>
<td><?= e($row['created_at']) ?></td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="5">You have not submitted any donations yet.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

<p><a href="donation.php">🐾 Donate to Animals</a></p>
</div>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>