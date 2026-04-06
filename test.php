<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php"); exit();
}

// ── Auto-create tables if missing ─────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `fine_payments` (
        `id`           INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`      INT           NOT NULL,
        `request_id`   INT           NOT NULL,
        `amount`       DECIMAL(10,2) NOT NULL,
        `upi_ref`      VARCHAR(100)  DEFAULT '',
        `screenshot`   VARCHAR(255)  DEFAULT '',
        `status`       ENUM('Pending','Verified','Rejected') DEFAULT 'Pending',
        `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `verified_at`  DATETIME  DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `book_purchase_requests` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`     INT          NOT NULL,
        `book_title`  VARCHAR(255) NOT NULL,
        `author`      VARCHAR(150) DEFAULT '',
        `reason`      TEXT,
        `status`      ENUM('Pending','Reviewed','Ordered','Rejected') DEFAULT 'Pending',
        `admin_note`  TEXT,
        `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("ALTER TABLE `book_requests` ADD COLUMN IF NOT EXISTS `fine_paid` TINYINT(1) DEFAULT 0");
} catch (Exception $e) {}

// ── Handle POST actions ───────────────────────────────────────────────────────
$toast = ''; $toastType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'verify_payment') {
        $pay_id = (int)($_POST['pay_id'] ?? 0);
        $req_id = (int)($_POST['req_id'] ?? 0);
        $pdo->prepare("UPDATE fine_payments SET status='Verified', verified_at=NOW() WHERE id=?")->execute([$pay_id]);
        $pdo->prepare("UPDATE book_requests SET fine_amount=0, fine_paid=1 WHERE id=?")->execute([$req_id]);
        $toast = "Payment verified! Fine cleared.";

    } elseif ($action === 'reject_payment') {
        $pay_id = (int)($_POST['pay_id'] ?? 0);
        $pdo->prepare("UPDATE fine_payments SET status='Rejected' WHERE id=?")->execute([$pay_id]);
        $toast = "Payment rejected."; $toastType = 'error';

    } elseif ($action === 'update_purchase') {
        $pr_id  = (int)($_POST['pr_id']    ?? 0);
        $status = $_POST['pr_status']       ?? 'Pending';
        $note   = trim($_POST['admin_note'] ?? '');
        $pdo->prepare("UPDATE book_purchase_requests SET status=?, admin_note=? WHERE id=?")
            ->execute([$status, $note, $pr_id]);
        $toast = "Purchase request updated!";
    }

    header("Location: admin_requests_extra.php?toast=".urlencode($toast)."&type=".urlencode($toastType));
    exit();
}

if (isset($_GET['toast'])) { $toast = $_GET['toast']; $toastType = $_GET['type'] ?? 'success'; }

// ── Fetch data ────────────────────────────────────────────────────────────────
try {
    $finePayments = $pdo->query("
        SELECT fp.*, u.username, u.full_name, b.title AS book_title, br.fine_amount, br.due_date
        FROM fine_payments fp
        JOIN users u ON u.id = fp.user_id
        JOIN book_requests br ON br.id = fp.request_id
        JOIN books b ON b.id = br.book_id
        ORDER BY fp.submitted_at DESC
    ")->fetchAll();
} catch (Exception $e) { $finePayments = []; }

try {
    $purchaseRequests = $pdo->query("
        SELECT bpr.*, u.username, u.full_name
        FROM book_purchase_requests bpr
        JOIN users u ON u.id = bpr.user_id
        ORDER BY bpr.created_at DESC
    ")->fetchAll();
} catch (Exception $e) { $purchaseRequests = []; }

$pendingPay      = count(array_filter($finePayments,     fn($p) => $p['status'] === 'Pending'));
$pendingPurchase = count(array_filter($purchaseRequests, fn($p) => $p['status'] === 'Pending'));
$pendingReqs     = $pdo->query("SELECT COUNT(*) FROM book_requests WHERE status='Pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments & Orders — LIBRITE Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;600&display=swap');
:root{--accent:#22d3ee;--onyx:#02040a;--card:rgba(255,255,255,.03);--border:rgba(255,255,255,.1);--red:#f87171;--green:#34d399;--yellow:#fbbf24;--blue:#60a5fa;--muted:rgba(255,255,255,.5);}
*{margin:0;padding:0;box-sizing:border-box;}
body{background:var(--onyx);font-family:'Inter',sans-serif;color:white;display:flex;min-height:100vh;}

/* Sidebar */
.sidebar{position:fixed;top:0;left:0;height:100vh;width:250px;background:rgba(0,0,0,.5);padding:2rem 1rem;border-right:1px solid var(--border);z-index:100;overflow-y:auto;}
.logo{font-family:'Playfair Display',serif;font-size:1.3rem;text-align:center;margin-bottom:2rem;display:flex;align-items:center;justify-content:center;gap:8px;}
.logo-dot{width:36px;height:36px;border-radius:50%;background:var(--accent);display:inline-block;flex-shrink:0;}
.nav-item{display:flex;align-items:center;padding:.75rem 1rem;margin:.25rem 0;text-decoration:none;color:var(--muted);border-radius:8px;transition:all .25s;font-size:.9rem;gap:10px;}
.nav-item:hover,.nav-item.active{background:rgba(255,255,255,.06);color:var(--accent);}
.nav-item i{width:18px;text-align:center;flex-shrink:0;}
.nav-badge{background:#ef4444;color:white;font-size:.6rem;font-weight:700;padding:1px 6px;border-radius:10px;margin-left:auto;}

/* Main */
.main{margin-left:250px;flex:1;padding:2rem;max-width:1100px;}

/* Header */
.page-header{display:flex;align-items:center;gap:14px;margin-bottom:2rem;}
.page-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;background:rgba(34,211,238,.12);border:1px solid rgba(34,211,238,.25);flex-shrink:0;}
.page-title{font-family:'Playfair Display',serif;font-size:1.8rem;}
.page-sub{font-size:.85rem;color:var(--muted);margin-top:2px;}

/* Summary */
.summary-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin-bottom:2rem;}
.sum-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:1.2rem;text-align:center;}
.sum-val{font-size:1.8rem;font-weight:700;margin:.3rem 0;}
.sum-label{font-size:.75rem;color:var(--muted);}

/* Tabs */
.tabs{display:flex;gap:4px;background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:12px;padding:4px;margin-bottom:1.8rem;width:fit-content;}
.tab-btn{padding:9px 22px;border-radius:9px;border:none;background:transparent;color:var(--muted);font-family:'Inter',sans-serif;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .25s;display:flex;align-items:center;gap:7px;white-space:nowrap;}
.tab-btn.active{background:var(--accent);color:#000;}
.tab-count{font-size:.65rem;font-weight:700;padding:1px 7px;border-radius:10px;}
.tab-btn.active .tab-count{background:rgba(0,0,0,.2);color:#000;}
.tab-btn:not(.active) .tab-count{background:rgba(255,255,255,.1);color:white;}

/* Filter pills */
.filter-row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:1.2rem;}
.filter-pill{padding:4px 14px;border-radius:20px;border:1px solid var(--border);background:var(--card);color:var(--muted);font-size:.76rem;font-weight:600;cursor:pointer;transition:all .2s;}
.filter-pill.active{background:var(--accent);border-color:var(--accent);color:#000;}

/* Request cards */
.req-card{background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:16px;padding:1.3rem 1.5rem;margin-bottom:.9rem;transition:border-color .25s;}
.req-card:hover{border-color:rgba(34,211,238,.3);}
.req-card.bl-yellow{border-left:3px solid var(--yellow);}
.req-card.bl-green {border-left:3px solid var(--green);}
.req-card.bl-red   {border-left:3px solid var(--red);}
.req-card.bl-blue  {border-left:3px solid var(--blue);}
.card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.card-title{font-size:1rem;font-weight:700;color:white;margin-bottom:3px;}
.card-sub{font-size:.8rem;color:var(--muted);}
.card-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:7px;}
.user-chip{display:inline-flex;align-items:center;gap:5px;background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.2);color:var(--accent);font-size:.72rem;padding:2px 9px;border-radius:20px;}
.date-chip{font-size:.72rem;color:var(--muted);}
.amount-chip{font-size:.78rem;font-weight:700;color:var(--red);background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.25);padding:2px 9px;border-radius:20px;}

/* Status pills */
.pill{display:inline-block;font-size:.72rem;font-weight:700;padding:3px 11px;border-radius:20px;}
.pill-Pending {background:rgba(251,191,36,.15);color:var(--yellow);border:1px solid rgba(251,191,36,.3);}
.pill-Verified{background:rgba(52,211,153,.15);color:var(--green);border:1px solid rgba(52,211,153,.3);}
.pill-Rejected{background:rgba(248,113,113,.15);color:var(--red);border:1px solid rgba(248,113,113,.3);}
.pill-Reviewed{background:rgba(96,165,250,.15);color:var(--blue);border:1px solid rgba(96,165,250,.3);}
.pill-Ordered {background:rgba(52,211,153,.15);color:var(--green);border:1px solid rgba(52,211,153,.3);}

/* Boxes */
.reason-box{background:rgba(255,255,255,.04);border:1px solid var(--border);border-radius:8px;padding:10px 14px;font-size:.82rem;color:rgba(255,255,255,.65);margin:10px 0;line-height:1.6;}
.admin-note-box{background:rgba(34,211,238,.07);border:1px solid rgba(34,211,238,.2);border-radius:8px;padding:9px 14px;font-size:.8rem;color:rgba(255,255,255,.6);margin-top:8px;}
.admin-note-box strong{color:var(--accent);}
.upi-ref-box{background:rgba(96,165,250,.08);border:1px solid rgba(96,165,250,.2);border-radius:8px;padding:8px 13px;font-size:.8rem;color:var(--blue);font-family:monospace;margin-top:8px;}

/* Buttons */
.btn-verify{background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.3);color:var(--green);padding:7px 16px;border-radius:9px;font-size:.8rem;font-weight:700;cursor:pointer;transition:all .25s;display:inline-flex;align-items:center;gap:6px;}
.btn-verify:hover{background:var(--green);color:#000;}
.btn-reject{background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);color:var(--red);padding:7px 16px;border-radius:9px;font-size:.8rem;font-weight:700;cursor:pointer;transition:all .25s;display:inline-flex;align-items:center;gap:6px;}
.btn-reject:hover{background:var(--red);color:#000;}
.btn-respond{background:rgba(34,211,238,.08);border:1px solid rgba(34,211,238,.25);color:var(--accent);padding:7px 16px;border-radius:9px;font-size:.8rem;font-weight:700;cursor:pointer;transition:all .25s;display:inline-flex;align-items:center;gap:6px;}
.btn-respond:hover{background:var(--accent);color:#000;}
.btn-save{background:rgba(34,211,238,.1);border:1px solid var(--accent);color:var(--accent);padding:9px 22px;border-radius:9px;font-size:.85rem;font-weight:700;cursor:pointer;transition:all .25s;white-space:nowrap;}
.btn-save:hover{background:var(--accent);color:#000;}
.screenshot-link{display:inline-flex;align-items:center;gap:5px;background:rgba(96,165,250,.1);border:1px solid rgba(96,165,250,.25);color:var(--blue);font-size:.75rem;padding:3px 10px;border-radius:8px;text-decoration:none;}
.screenshot-link:hover{background:rgba(96,165,250,.2);}

/* Inline response form */
.response-form{display:none;margin-top:14px;padding-top:14px;border-top:1px solid var(--border);}
.response-form.open{display:block;animation:fadeIn .25s ease;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.form-grid{display:grid;grid-template-columns:1fr 2fr auto;gap:10px;align-items:end;}
.glass-input{width:100%;padding:9px 13px;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:9px;color:white;font-family:'Inter',sans-serif;font-size:.85rem;outline:none;transition:border-color .25s;}
.glass-input:focus{border-color:var(--accent);}
.glass-input option{background:#111;}
.input-label{font-size:.7rem;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;display:block;margin-bottom:5px;}

/* Empty state */
.empty{text-align:center;padding:4rem 2rem;color:var(--muted);background:var(--card);border:1px solid var(--border);border-radius:16px;}
.empty i{font-size:2.5rem;margin-bottom:1rem;display:block;opacity:.3;}
.empty h3{font-size:1rem;font-weight:600;color:white;margin-bottom:6px;}

/* Toast */
.toast-box{position:fixed;bottom:24px;right:24px;z-index:9999;padding:12px 20px;border-radius:12px;font-weight:700;font-size:.88rem;display:flex;align-items:center;gap:8px;animation:toastIn .4s ease,toastOut .5s 3.5s forwards;}
@keyframes toastIn{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
@keyframes toastOut{to{opacity:0;pointer-events:none}}

@media(max-width:768px){.sidebar{display:none}.main{margin-left:0;padding:1rem}.form-grid{grid-template-columns:1fr}}
</style>
</head>
<body>

<?php if ($toast): ?>
<div class="toast-box" style="background:<?= $toastType==='error'?'#ef4444':'#059669' ?>;color:white">
    <i class="fas fa-<?= $toastType==='error'?'times-circle':'check-circle' ?>"></i>
    <?= htmlspecialchars($toast) ?>
</div>
<?php endif; ?>

<!-- ══ SIDEBAR ══════════════════════════════════════════════════════════════ -->
<aside class="sidebar">
    <div class="logo"><div class="logo-dot"></div>LIBRITE ADMIN</div>
    <a href="admin_dashboard.php"       class="nav-item"><i class="fas fa-home"></i> Dashboard</a>
    <a href="manage_books.php"          class="nav-item"><i class="fas fa-book"></i> Manage Books</a>
    <a href="manage_users.php"          class="nav-item"><i class="fas fa-users"></i> Manage Users</a>
    <a href="book_requests.php"         class="nav-item">
        <i class="fas fa-clipboard-list"></i> Book Requests
        <?php if ($pendingReqs > 0): ?><span class="nav-badge"><?= $pendingReqs ?></span><?php endif; ?>
    </a>
    <a href="admin_requests_extra.php"  class="nav-item active">
        <i class="fas fa-credit-card"></i> Payments & Orders
        <?php if ($pendingPay + $pendingPurchase > 0): ?><span class="nav-badge"><?= $pendingPay + $pendingPurchase ?></span><?php endif; ?>
    </a>
    <a href="admin_forgot_password.php" class="nav-item"><i class="fas fa-lock"></i> Change Password</a>
    <a href="index.php" class="nav-item" style="margin-top:2rem;color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a>
</aside>

<!-- ══ MAIN ═════════════════════════════════════════════════════════════════ -->
<main class="main">

    <div class="page-header">
        <div class="page-icon">💳</div>
        <div>
            <div class="page-title">Payments & Orders</div>
            <div class="page-sub">Verify student fine payments · Respond to book purchase requests</div>
        </div>
    </div>

    <!-- Summary -->
    <div class="summary-row">
        <div class="sum-card"><div class="sum-val" style="color:var(--yellow)"><?= $pendingPay ?></div><div class="sum-label">Payments Pending</div></div>
        <div class="sum-card"><div class="sum-val" style="color:var(--green)"><?= count(array_filter($finePayments,fn($p)=>$p['status']==='Verified')) ?></div><div class="sum-label">Payments Verified</div></div>
        <div class="sum-card"><div class="sum-val" style="color:var(--blue)"><?= $pendingPurchase ?></div><div class="sum-label">Orders Pending</div></div>
        <div class="sum-card"><div class="sum-val" style="color:var(--accent)"><?= count($purchaseRequests) ?></div><div class="sum-label">Total Requests</div></div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" id="tab-pay" onclick="switchTab('pay')">
            <i class="fas fa-credit-card"></i> Fine Payments <span class="tab-count"><?= count($finePayments) ?></span>
        </button>
        <button class="tab-btn" id="tab-order" onclick="switchTab('order')">
            <i class="fas fa-box"></i> Book Orders <span class="tab-count"><?= count($purchaseRequests) ?></span>
        </button>
    </div>

    <!-- ══ FINE PAYMENTS ════════════════════════════════════════════════════ -->
    <div id="pane-pay">
        <div class="filter-row">
            <?php $payStatuses=['All','Pending','Verified','Rejected'];
            foreach ($payStatuses as $s):
                $cnt=$s==='All'?count($finePayments):count(array_filter($finePayments,fn($p)=>$p['status']===$s)); ?>
            <button class="filter-pill <?= $s==='All'?'active':'' ?>" onclick="filterCards('pay','<?= $s ?>',this)"><?= $s ?> (<?= $cnt ?>)</button>
            <?php endforeach; ?>
        </div>

        <?php if (empty($finePayments)): ?>
        <div class="empty">
            <i class="fas fa-receipt"></i>
            <h3>No payment submissions yet</h3>
            <p>When students submit fine payment proofs, they will appear here for your verification.</p>
        </div>
        <?php else: ?>
        <?php foreach ($finePayments as $fp):
            $bl=['Pending'=>'bl-yellow','Verified'=>'bl-green','Rejected'=>'bl-red'][$fp['status']]??'bl-yellow';
        ?>
        <div class="req-card <?= $bl ?> pay-card" data-status="<?= $fp['status'] ?>">
            <div class="card-top">
                <div style="flex:1">
                    <div class="card-title">₹<?= number_format($fp['amount'],2) ?> — Fine Payment</div>
                    <div class="card-sub">Book: <strong style="color:white"><?= htmlspecialchars($fp['book_title']) ?></strong></div>
                    <div class="card-meta">
                        <span class="user-chip"><i class="fas fa-user" style="font-size:.6rem"></i> <?= htmlspecialchars($fp['full_name']?:$fp['username']) ?></span>
                        <span class="amount-chip">₹<?= number_format($fp['amount'],2) ?></span>
                        <span class="date-chip"><i class="fas fa-clock" style="font-size:.6rem"></i> <?= date('d M Y, g:i A',strtotime($fp['submitted_at'])) ?></span>
                    </div>
                </div>
                <span class="pill pill-<?= $fp['status'] ?>"><?= $fp['status'] ?></span>
            </div>

            <?php if ($fp['upi_ref']): ?>
            <div class="upi-ref-box"><i class="fas fa-fingerprint" style="margin-right:6px"></i>UPI Ref: <strong><?= htmlspecialchars($fp['upi_ref']) ?></strong></div>
            <?php endif; ?>

            <div style="margin-top:8px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                <?php if ($fp['screenshot']): ?>
                <a href="<?= htmlspecialchars($fp['screenshot']) ?>" target="_blank" class="screenshot-link">
                    <i class="fas fa-image"></i> View Screenshot
                </a>
                <?php else: ?>
                <span style="font-size:.75rem;color:var(--muted)"><i class="fas fa-image" style="margin-right:4px"></i>No screenshot</span>
                <?php endif; ?>
                <?php if ($fp['due_date']): ?>
                <span style="font-size:.75rem;color:var(--muted)"><i class="fas fa-calendar" style="margin-right:4px"></i>Due was: <?= date('d M Y',strtotime($fp['due_date'])) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($fp['status']==='Pending'): ?>
            <div style="display:flex;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--border)">
                <form method="POST" style="display:inline">
                    <input type="hidden" name="action"  value="verify_payment">
                    <input type="hidden" name="pay_id"  value="<?= $fp['id'] ?>">
                    <input type="hidden" name="req_id"  value="<?= $fp['request_id'] ?>">
                    <button type="submit" class="btn-verify"><i class="fas fa-check-circle"></i> Verify & Clear Fine</button>
                </form>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="reject_payment">
                    <input type="hidden" name="pay_id" value="<?= $fp['id'] ?>">
                    <button type="submit" class="btn-reject"><i class="fas fa-times-circle"></i> Reject</button>
                </form>
            </div>
            <?php elseif ($fp['status']==='Verified'): ?>
            <div style="margin-top:10px;font-size:.8rem;color:var(--green)"><i class="fas fa-check-circle"></i> Verified <?= $fp['verified_at']?date('d M Y',strtotime($fp['verified_at'])):'now' ?> — fine cleared</div>
            <?php elseif ($fp['status']==='Rejected'): ?>
            <div style="margin-top:10px;font-size:.8rem;color:var(--red)"><i class="fas fa-times-circle"></i> Payment was rejected</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ══ BOOK ORDERS ══════════════════════════════════════════════════════ -->
    <div id="pane-order" style="display:none">
        <div class="filter-row">
            <?php $orderStatuses=['All','Pending','Reviewed','Ordered','Rejected'];
            foreach ($orderStatuses as $s):
                $cnt=$s==='All'?count($purchaseRequests):count(array_filter($purchaseRequests,fn($p)=>$p['status']===$s)); ?>
            <button class="filter-pill <?= $s==='All'?'active':'' ?>" onclick="filterCards('order','<?= $s ?>',this)"><?= $s ?> (<?= $cnt ?>)</button>
            <?php endforeach; ?>
        </div>

        <?php if (empty($purchaseRequests)): ?>
        <div class="empty">
            <i class="fas fa-box-open"></i>
            <h3>No book order requests yet</h3>
            <p>When students request unavailable books, they will appear here so you can respond.</p>
        </div>
        <?php else: ?>
        <?php foreach ($purchaseRequests as $pr):
            $blMap=['Pending'=>'bl-yellow','Reviewed'=>'bl-blue','Ordered'=>'bl-green','Rejected'=>'bl-red'];
            $bl=$blMap[$pr['status']]??'bl-yellow';
        ?>
        <div class="req-card <?= $bl ?> order-card" data-status="<?= $pr['status'] ?>">
            <div class="card-top">
                <div style="flex:1">
                    <div class="card-title"><?= htmlspecialchars($pr['book_title']) ?></div>
                    <?php if ($pr['author']): ?><div class="card-sub">by <?= htmlspecialchars($pr['author']) ?></div><?php endif; ?>
                    <div class="card-meta">
                        <span class="user-chip"><i class="fas fa-user" style="font-size:.6rem"></i> <?= htmlspecialchars($pr['full_name']?:$pr['username']) ?></span>
                        <span class="date-chip"><i class="fas fa-clock" style="font-size:.6rem"></i> <?= date('d M Y, g:i A',strtotime($pr['created_at'])) ?></span>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px">
                    <span class="pill pill-<?= $pr['status'] ?>"><?= $pr['status'] ?></span>
                    <button class="btn-respond" onclick="toggleReply('order-<?= $pr['id'] ?>')">
                        <i class="fas fa-reply"></i> Respond
                    </button>
                </div>
            </div>

            <?php if ($pr['reason']): ?>
            <div class="reason-box">
                <i class="fas fa-quote-left" style="opacity:.4;margin-right:6px;font-size:.75rem"></i>
                <?= htmlspecialchars($pr['reason']) ?>
            </div>
            <?php endif; ?>

            <?php if ($pr['admin_note']): ?>
            <div class="admin-note-box">
                <strong><i class="fas fa-reply" style="margin-right:4px"></i>Your response:</strong>
                <?= htmlspecialchars($pr['admin_note']) ?>
            </div>
            <?php endif; ?>

            <!-- Inline response form -->
            <div class="response-form" id="order-<?= $pr['id'] ?>">
                <form method="POST">
                    <input type="hidden" name="action" value="update_purchase">
                    <input type="hidden" name="pr_id"  value="<?= $pr['id'] ?>">
                    <div class="form-grid">
                        <div>
                            <label class="input-label">Update Status</label>
                            <select name="pr_status" class="glass-input">
                                <?php foreach(['Pending','Reviewed','Ordered','Rejected'] as $s): ?>
                                <option value="<?= $s ?>" <?= $pr['status']===$s?'selected':'' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="input-label">Reply / Note to Student</label>
                            <input type="text" name="admin_note" class="glass-input"
                                   placeholder="e.g. Book ordered! Arrives next week. / Not available in budget."
                                   value="<?= htmlspecialchars($pr['admin_note']??'') ?>">
                        </div>
                        <div>
                            <label class="input-label">&nbsp;</label>
                            <button type="submit" class="btn-save"><i class="fas fa-paper-plane"></i> Send</button>
                        </div>
                    </div>
                    <div style="margin-top:10px;font-size:.73rem;color:var(--muted)">
                        <i class="fas fa-info-circle" style="margin-right:4px"></i>
                        <strong style="color:var(--yellow)">Pending</strong> → 
                        <strong style="color:var(--blue)">Reviewed</strong> (acknowledged) → 
                        <strong style="color:var(--green)">Ordered</strong> (book ordered!) → 
                        <strong style="color:var(--red)">Rejected</strong> (cannot fulfil)
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

</main>

<script>
function switchTab(t) {
    ['pay','order'].forEach(n => {
        document.getElementById('pane-'+n).style.display = n===t ? 'block' : 'none';
        document.getElementById('tab-'+n).classList.toggle('active', n===t);
    });
}
function filterCards(pane, status, btn) {
    document.querySelectorAll('#pane-'+pane+' .'+(pane==='pay'?'pay':'order')+'-card').forEach(card => {
        card.style.display = (status==='All'||card.dataset.status===status) ? 'block' : 'none';
    });
    btn.closest('.filter-row').querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
}
function toggleReply(id) {
    const f = document.getElementById(id);
    f.classList.toggle('open');
    if (f.classList.contains('open')) f.querySelector('input,select').focus();
}
</script>
</body>
</html>