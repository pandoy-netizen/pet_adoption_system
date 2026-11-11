
<?php
session_start();
require_once __DIR__ . '/db.php';

if (
    !isset($_SESSION['user_id']) ||
    strtolower(trim((string)($_SESSION['role'] ?? ''))) !== 'admin'
) {
    http_response_code(403);
    exit('Access denied. Please sign in using an admin account.');
}

if (empty($_SESSION['donation_csrf'])) {
    $_SESSION['donation_csrf'] = bin2hex(random_bytes(32));
}

function admin_donation_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['donation_csrf'], $token)
    ) {
        http_response_code(403);
        exit('Invalid request token. Refresh and try again.');
    }

    $id = filter_var(
        $_POST['donation_id'] ?? '',
        FILTER_VALIDATE_INT
    );
    $action = $_POST['action'] ?? '';

    if (!$id || !in_array($action, ['Verified', 'Rejected'], true)) {
        $error = 'Invalid donation action.';
    } else {
        $stmt = $conn->prepare(
            "UPDATE donations
             SET status = ?
             WHERE id = ? AND status = 'Pending'"
        );

        $stmt->bind_param('si', $action, $id);
        $stmt->execute();

        if ($stmt->affected_rows === 1) {
            $notice = "Donation #$id updated to $action.";
        } else {
            $error = 'Donation was not found or has already been reviewed.';
        }

        $stmt->close();
    }
}

$totals = $conn->query(
    "SELECT
        COALESCE(SUM(
            CASE WHEN status = 'Verified' THEN amount ELSE 0 END
        ), 0) AS verified_total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
        COUNT(*) AS submission_count
     FROM donations"
)->fetch_assoc();

$result = $conn->query(
    "SELECT id, user_id, donor_name, amount, payment_method,
            reference_number, proof_image, message, status, created_at
     FROM donations
     ORDER BY created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Donation Management | Admin</title>
<style>
*{box-sizing:border-box}
body{
    margin:0;
    padding:24px;
    background:#f5f7fb;
    font-family:Arial,sans-serif;
    color:#263238
}
.container{max-width:1400px;margin:auto}
h1{color:#ef8b27}
.cards{display:flex;gap:16px;flex-wrap:wrap;margin:24px 0}
.card{
    background:white;
    padding:20px;
    border-radius:14px;
    flex:1;
    min-width:200px;
    box-shadow:0 4px 18px rgba(0,0,0,.06)
}
.number{font-size:25px;font-weight:bold}
.table-wrap{overflow-x:auto;background:white;border-radius:12px}
table{width:100%;min-width:1050px;border-collapse:collapse}
th,td{
    text-align:left;
    vertical-align:top;
    padding:12px;
    border-bottom:1px solid #eee
}
th{background:#fff3e5}
button{
    border:0;
    border-radius:7px;
    padding:9px 11px;
    color:white;
    font-weight:bold;
    cursor:pointer;
    margin:3px
}
.verify{background:#218838}
.reject{background:#c0392b}
.notice,.error{padding:12px;border-radius:8px;margin:12px 0}
.notice{background:#e8f7ec;color:#176534}
.error{background:#ffeded;color:#a32121}
a{color:#b9670a}
</style>
</head>
<body>
<div class="container">

<h1>🐾 Donation Management</h1>
<p>Review donation submissions and payment details.</p>

<?php if ($notice): ?>
<div class="notice"><?= admin_donation_escape($notice) ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="error"><?= admin_donation_escape($error) ?></div>
<?php endif; ?>

<div class="cards">
    <div class="card">
        <h3>Total Verified Donations</h3>
        <div class="number">
            ₱<?= number_format((float)$totals['verified_total'], 2) ?>
        </div>
    </div>

    <div class="card">
        <h3>Pending Donations</h3>
        <div class="number"><?= (int)$totals['pending_count'] ?></div>
    </div>

    <div class="card">
        <h3>Total Submissions</h3>
        <div class="number"><?= (int)$totals['submission_count'] ?></div>
    </div>
</div>

<div class="table-wrap">
<table>
<thead>
<tr>
    <th>ID</th>
    <th>Donor</th>
    <th>Amount</th>
    <th>Payment</th>
    <th>Reference</th>
    <th>Receipt</th>
    <th>Message</th>
    <th>Status</th>
    <th>Date</th>
    <th>Action</th>
</tr>
</thead>
<tbody>
<?php if ($result && $result->num_rows > 0): ?>
    <?php while ($d = $result->fetch_assoc()): ?>
    <tr>
        <td>
            #<?= (int)$d['id'] ?><br>
            <small>User ID: <?= (int)$d['user_id'] ?></small>
        </td>

        <td><?= admin_donation_escape($d['donor_name']) ?></td>

        <td>₱<?= number_format((float)$d['amount'], 2) ?></td>

        <td><?= admin_donation_escape($d['payment_method']) ?></td>

        <td><?= admin_donation_escape($d['reference_number']) ?></td>

        <td>
            <?php if (!empty($d['proof_image'])): ?>
                <a
                    href="uploads/donation_receipts/<?= rawurlencode(basename($d['proof_image'])) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >View Receipt</a>
            <?php else: ?>
                No receipt
            <?php endif; ?>
        </td>

        <td><?= nl2br(admin_donation_escape($d['message'] ?? '')) ?></td>

        <td><strong><?= admin_donation_escape($d['status']) ?></strong></td>

        <td><?= admin_donation_escape($d['created_at']) ?></td>

        <td>
            <?php if ($d['status'] === 'Pending'): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token"
                    value="<?= admin_donation_escape($_SESSION['donation_csrf']) ?>">

                <input type="hidden" name="donation_id"
                    value="<?= (int)$d['id'] ?>">

                <button class="verify" type="submit"
                    name="action" value="Verified"
                    onclick="return confirm('Confirm you have verified this payment in GCash or Maya?')">
                    Verify
                </button>

                <button class="reject" type="submit"
                    name="action" value="Rejected"
                    onclick="return confirm('Reject this donation submission?')">
                    Reject
                </button>
            </form>
            <?php else: ?>
                Reviewed
            <?php endif; ?>
        </td>
    </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr><td colspan="10">No donation submissions yet.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<p><a href="admin_dashboard.php">← Back to Admin Dashboard</a></p>
</div>
</body>
</html>
<?php
$conn->close();
?>