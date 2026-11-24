<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user_id']) || strtolower(trim((string)($_SESSION['role'] ?? ''))) !== 'admin') {
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

try {
    $columns = [];
    $columnResult = $conn->query('SHOW COLUMNS FROM donations');
    while ($column = $columnResult->fetch_assoc()) {
        $columns[$column['Field']] = true;
    }
} catch (Throwable $e) {
    error_log('Donation schema lookup: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load donations. Please verify the donations table exists.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['donation_csrf'], $token)) {
        http_response_code(403);
        exit('Invalid request token. Refresh and try again.');
    }

    $id = filter_var($_POST['donation_id'] ?? '', FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';

    if (!$id || $id < 1 || !in_array($action, ['Verified', 'Rejected'], true)) {
        $error = 'Invalid donation action.';
    } else {
        try {
            $set = 'status = ?';
            if (isset($columns['verified_at'])) {
                $set .= ', verified_at = CASE WHEN ? = \'Verified\' THEN NOW() ELSE NULL END';
                $stmt = $conn->prepare("UPDATE donations SET $set WHERE id = ? AND status = 'Pending'");
                $stmt->bind_param('ssi', $action, $action, $id);
            } else {
                $stmt = $conn->prepare("UPDATE donations SET $set WHERE id = ? AND status = 'Pending'");
                $stmt->bind_param('si', $action, $id);
            }
            $stmt->execute();
            if ($stmt->affected_rows === 1) {
                $notice = "Donation #$id updated to $action.";
            } else {
                $error = 'Donation was not found or has already been reviewed.';
            }
            $stmt->close();
        } catch (Throwable $e) {
            error_log('Donation review failed: ' . $e->getMessage());
            $error = 'Unable to update the donation. Please try again.';
        }
    }
}

try {
    $totals = $conn->query("SELECT COALESCE(SUM(CASE WHEN status = 'Verified' THEN amount ELSE 0 END), 0) AS verified_total, SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_count, COUNT(*) AS submission_count FROM donations")->fetch_assoc();
    $messageColumn = isset($columns['donation_purpose']) ? 'donation_purpose' : (isset($columns['message']) ? 'message' : null);
    $messageSelect = $messageColumn ? "`$messageColumn` AS donation_message" : "'' AS donation_message";
    $emailSelect = isset($columns['donor_email']) ? 'donor_email' : "'' AS donor_email";
    $result = $conn->query("SELECT id, user_id, donor_name, amount, payment_method, reference_number, proof_image, $messageSelect, $emailSelect, status, created_at FROM donations ORDER BY created_at DESC, id DESC");
} catch (Throwable $e) {
    error_log('Donation listing failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load donation records. Please check the server logs.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Donation Management | Admin</title>
<style>
*{box-sizing:border-box}
body{margin:0;padding:24px;background:#f5f7fb;font-family:Arial,sans-serif;color:#263238}
.container{max-width:1400px;margin:auto}
h1{color:#ef8b27}
.cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin:24px 0}
.card{background:white;padding:20px;border-radius:14px;box-shadow:0 4px 18px rgba(0,0,0,.06)}
.card h3{margin:0 0 12px;font-size:16px}
.number{font-size:25px;font-weight:bold}
.table-wrap{overflow-x:auto;background:white;border-radius:12px;box-shadow:0 4px 18px rgba(0,0,0,.04)}
table{width:100%;min-width:1050px;border-collapse:collapse}
th,td{text-align:left;vertical-align:top;padding:12px;border-bottom:1px solid #eee;overflow-wrap:anywhere}
th{background:#fff3e5}
button{border:0;border-radius:7px;padding:10px 13px;color:white;font-weight:bold;cursor:pointer;margin:3px}
.verify{background:#218838}.reject{background:#c0392b}
.notice,.error{padding:12px;border-radius:8px;margin:12px 0}
.notice{background:#e8f7ec;color:#176534}.error{background:#ffeded;color:#a32121}
a{color:#b9670a}
.actions{display:flex;flex-wrap:wrap;gap:6px}
.actions button{margin:0}
.status{display:inline-block;padding:6px 10px;border-radius:999px;background:#edf0f5;font-weight:bold}
@media(max-width:700px){
body{padding:14px}
h1{font-size:24px;margin-top:12px}
.cards{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:18px 0}
.card{padding:14px;min-width:0}
.card:first-child{grid-column:1/-1}
.card h3{font-size:13px;line-height:1.4}
.number{font-size:22px}
.table-wrap{background:transparent;box-shadow:none;overflow:visible}
table,tbody,tr,td{display:block;width:100%;min-width:0}
thead{display:none}
table{border-collapse:separate}
tr{background:white;border-radius:14px;padding:14px;margin-bottom:14px;box-shadow:0 4px 14px rgba(0,0,0,.06)}
td{display:grid;grid-template-columns:100px minmax(0,1fr);gap:10px;align-items:start;padding:9px 0;border-bottom:1px solid #edf0f5;font-size:14px;line-height:1.5}
td:last-child{border-bottom:0}
td::before{content:attr(data-label);font-weight:700;color:#64748b}
td[data-label="Action"] form{width:100%}
.actions{display:flex;gap:8px}
.actions button{flex:1;min-width:0}
.empty-row{display:block;text-align:center}
.empty-row::before{content:none}
}
@media(max-width:370px){td{grid-template-columns:80px minmax(0,1fr);font-size:13px}}
</style>
</head>
<body>
<div class="container">
<h1>🐾 Donation Management</h1>
<p>Review donation submissions and payment details.</p>
<?php if ($notice): ?><div class="notice"><?= admin_donation_escape($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error"><?= admin_donation_escape($error) ?></div><?php endif; ?>
<div class="cards">
<div class="card"><h3>Total Verified Donations</h3><div class="number">₱<?= number_format((float)$totals['verified_total'], 2) ?></div></div>
<div class="card"><h3>Pending Donations</h3><div class="number"><?= (int)$totals['pending_count'] ?></div></div>
<div class="card"><h3>Total Submissions</h3><div class="number"><?= (int)$totals['submission_count'] ?></div></div>
</div>
<div class="table-wrap"><table>
<thead><tr><th>ID</th><th>Donor</th><th>Amount</th><th>Payment</th><th>Reference</th><th>Receipt</th><th>Purpose / Message</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
<tbody>
<?php if ($result && $result->num_rows > 0): ?>
<?php while ($d = $result->fetch_assoc()): ?>
<tr>
<td data-label="ID">#<?= (int)$d['id'] ?> <small>(User ID: <?= (int)$d['user_id'] ?>)</small></td>
<td data-label="Donor"><?= admin_donation_escape($d['donor_name']) ?><?php if (!empty($d['donor_email'])): ?><div><small><?= admin_donation_escape($d['donor_email']) ?></small></div><?php endif; ?></td>
<td data-label="Amount">₱<?= number_format((float)$d['amount'], 2) ?></td>
<td data-label="Payment"><?= admin_donation_escape($d['payment_method']) ?></td>
<td data-label="Reference"><?= admin_donation_escape($d['reference_number']) ?></td>
<td data-label="Receipt">
<?php if (!empty($d['proof_image'])): ?>
<?php
$receiptName = basename(str_replace('\\', '/', (string)$d['proof_image']));
$receiptPath = 'uploads/donation_receipts/' . rawurlencode($receiptName);
$storedPath = str_replace('\\', '/', ltrim((string)$d['proof_image'], '/'));
if (preg_match('~^uploads/(?:Donation_proofs|donation_receipts)/[A-Za-z0-9._-]+$~', $storedPath)) {
    $receiptPath = implode('/', array_map('rawurlencode', explode('/', $storedPath)));
}
?>
<a href="<?= admin_donation_escape($receiptPath) ?>" target="_blank" rel="noopener noreferrer">View Receipt</a>
<?php else: ?>No receipt<?php endif; ?>
</td>
<td data-label="Purpose"><?= nl2br(admin_donation_escape($d['donation_message'] ?? '')) ?></td>
<td data-label="Status"><span class="status"><?= admin_donation_escape($d['status']) ?></span></td>
<td data-label="Date"><?= admin_donation_escape($d['created_at']) ?></td>
<td data-label="Action">
<?php if ($d['status'] === 'Pending'): ?>
<form method="POST" class="actions">
<input type="hidden" name="csrf_token" value="<?= admin_donation_escape($_SESSION['donation_csrf']) ?>">
<input type="hidden" name="donation_id" value="<?= (int)$d['id'] ?>">
<button class="verify" type="submit" name="action" value="Verified" onclick="return confirm('Confirm you have verified this payment in GCash or Maya?')">Verify</button>
<button class="reject" type="submit" name="action" value="Rejected" onclick="return confirm('Reject this donation submission?')">Reject</button>
</form>
<?php else: ?>Reviewed<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?><tr><td class="empty-row" colspan="10">No donation submissions yet.</td></tr><?php endif; ?>
</tbody></table></div>
<p><a href="admin_dashboard.php">← Back to Admin Dashboard</a></p>
</div>
</body>
</html>
<?php $conn->close(); ?>
