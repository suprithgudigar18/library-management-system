<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php"); exit();
}

// ================== AUTO CREATE TABLES ==================
$pdo->exec("CREATE TABLE IF NOT EXISTS fine_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    request_id INT,
    amount DECIMAL(10,2),
    upi_ref VARCHAR(100),
    screenshot VARCHAR(255),
    status ENUM('Pending','Verified','Rejected') DEFAULT 'Pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at DATETIME
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS book_purchase_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    book_title VARCHAR(255),
    author VARCHAR(150),
    reason TEXT,
    status ENUM('Pending','Reviewed','Ordered','Rejected') DEFAULT 'Pending',
    admin_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

try {
    $pdo->exec("ALTER TABLE book_requests ADD COLUMN fine_paid TINYINT(1) DEFAULT 0");
} catch(Exception $e){}

// ================== ✅ CUSTOM FINE LOGIC ==================
$today = new DateTime();

// Approved books
$approved = $pdo->query("
    SELECT id, due_date 
    FROM book_requests 
    WHERE status='Approved' AND returned_at IS NULL
")->fetchAll();

foreach ($approved as $r) {
    $due = new DateTime($r['due_date']);

    if ($today > $due) {
        $days = $due->diff($today)->days;

        if ($days <= 4) {
            $fine = 20;
        } else {
            $fine = 20 + (($days - 4) * 10);
        }

        $pdo->prepare("UPDATE book_requests SET fine_amount=? WHERE id=? AND fine_paid=0")
            ->execute([$fine, $r['id']]);
    }
}

// Returned books
$returned = $pdo->query("
    SELECT id, due_date, returned_at 
    FROM book_requests 
    WHERE status='Returned'
")->fetchAll();

foreach ($returned as $r) {
    if (!$r['returned_at']) continue;

    $due = new DateTime($r['due_date']);
    $ret = new DateTime($r['returned_at']);

    if ($ret > $due) {
        $days = $due->diff($ret)->days;

        if ($days <= 4) {
            $fine = 20;
        } else {
            $fine = 20 + (($days - 4) * 10);
        }

        $pdo->prepare("UPDATE book_requests SET fine_amount=? WHERE id=? AND fine_paid=0")
            ->execute([$fine, $r['id']]);
    }
}

// ================== HANDLE ACTIONS ==================
$toast = ''; $type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'verify_payment') {
        $pdo->prepare("UPDATE fine_payments SET status='Verified', verified_at=NOW() WHERE id=?")
            ->execute([$_POST['pay_id']]);

        $pdo->prepare("UPDATE book_requests SET fine_amount=0, fine_paid=1 WHERE id=?")
            ->execute([$_POST['req_id']]);

        $toast = "Payment verified!";
    }

    if ($_POST['action'] === 'reject_payment') {
        $pdo->prepare("UPDATE fine_payments SET status='Rejected' WHERE id=?")
            ->execute([$_POST['pay_id']]);

        $toast = "Payment rejected";
        $type = 'error';
    }

    header("Location: admin_requests_extra.php?toast=$toast&type=$type");
    exit();
}

if (isset($_GET['toast'])) {
    $toast = $_GET['toast'];
    $type = $_GET['type'];
}

// ================== FETCH DATA ==================
$finePayments = $pdo->query("
    SELECT fp.*, u.username, b.title, br.due_date
    FROM fine_payments fp
    JOIN users u ON u.id = fp.user_id
    JOIN book_requests br ON br.id = fp.request_id
    JOIN books b ON b.id = br.book_id
    ORDER BY fp.submitted_at DESC
")->fetchAll();

// ================== DASHBOARD STATS ==================
$totalFines = $pdo->query("SELECT SUM(fine_amount) FROM book_requests WHERE fine_paid=0")->fetchColumn();

?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Payments</title>
<style>
body{background:#0f172a;color:white;font-family:sans-serif;padding:20px}
.card{background:#1e293b;padding:20px;border-radius:10px;margin-bottom:15px}
.btn{padding:6px 12px;border:none;border-radius:5px;cursor:pointer}
.green{background:#22c55e}
.red{background:#ef4444}
</style>
</head>

<body>

<h2>💳 Fine Payments</h2>
<p>Total Unpaid Fine: ₹<?= $totalFines ?></p>

<?php foreach($finePayments as $p): ?>
<div class="card">
    <h3><?= $p['title'] ?></h3>
    <p>User: <?= $p['username'] ?></p>
    <p>Amount: ₹<?= $p['amount'] ?></p>

    <?php if($p['status']=='Pending'): ?>
    <form method="POST">
        <input type="hidden" name="action" value="verify_payment">
        <input type="hidden" name="pay_id" value="<?= $p['id'] ?>">
        <input type="hidden" name="req_id" value="<?= $p['request_id'] ?>">
        <button class="btn green">Verify</button>
    </form>

    <form method="POST">
        <input type="hidden" name="action" value="reject_payment">
        <input type="hidden" name="pay_id" value="<?= $p['id'] ?>">
        <button class="btn red">Reject</button>
    </form>
    <?php else: ?>
        <p>Status: <?= $p['status'] ?></p>
    <?php endif; ?>
</div>
<?php endforeach; ?>

</body>
</html>