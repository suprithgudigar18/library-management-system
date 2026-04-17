<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php"); exit();
}

// ── Auto-create tables safely ─────────────────────────────────────────────────
try { $pdo->exec("CREATE TABLE IF NOT EXISTS `fine_payments` (`id` INT AUTO_INCREMENT PRIMARY KEY,`user_id` INT NOT NULL,`request_id` INT NOT NULL,`amount` DECIMAL(10,2) NOT NULL,`upi_ref` VARCHAR(100) DEFAULT '',`screenshot` VARCHAR(255) DEFAULT '',`status` ENUM('Pending','Verified','Rejected') DEFAULT 'Pending',`submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,`verified_at` DATETIME DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e){}
try { $pdo->exec("CREATE TABLE IF NOT EXISTS `book_purchase_requests` (`id` INT AUTO_INCREMENT PRIMARY KEY,`user_id` INT NOT NULL,`book_title` VARCHAR(255) NOT NULL,`author` VARCHAR(150) DEFAULT '',`reason` TEXT,`status` ENUM('Pending','Reviewed','Ordered','Rejected') DEFAULT 'Pending',`admin_note` TEXT,`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE `book_requests` ADD COLUMN `fine_paid` TINYINT(1) DEFAULT 0"); } catch(Exception $e){}

// ── Correct fine recalculation: Rs5/day ─────────────────────────────────────
$today = new DateTime();
try {
    $allApproved = $pdo->query("SELECT id, due_date FROM book_requests WHERE status='Approved' AND due_date IS NOT NULL AND returned_at IS NULL")->fetchAll();
    foreach ($allApproved as $row) {
        $due = new DateTime($row['due_date']);
        if ($today > $due) {
            $days = (int)$today->diff($due)->days;
            $pdo->prepare("UPDATE book_requests SET fine_amount=? WHERE id=? AND (fine_paid IS NULL OR fine_paid=0)")->execute([$days * 5, $row['id']]);
        } else {
            $pdo->prepare("UPDATE book_requests SET fine_amount=0 WHERE id=? AND (fine_paid IS NULL OR fine_paid=0)")->execute([$row['id']]);
        }
    }
} catch(Exception $e) {}

// ── Handle POST ───────────────────────────────────────────────────────────────
$toast = ''; $toastType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'verify_payment') {
        $pdo->prepare("UPDATE fine_payments SET status='Verified', verified_at=NOW() WHERE id=?")->execute([(int)$_POST['pay_id']]);
        $pdo->prepare("UPDATE book_requests SET fine_amount=0, fine_paid=1 WHERE id=?")->execute([(int)$_POST['req_id']]);
        $toast = "✅ Payment verified! Fine cleared for the student.";
    } elseif ($action === 'reject_payment') {
        $pdo->prepare("UPDATE fine_payments SET status='Rejected' WHERE id=?")->execute([(int)$_POST['pay_id']]);
        $toast = "Payment rejected."; $toastType = 'error';
    } elseif ($action === 'update_purchase') {
        $pdo->prepare("UPDATE book_purchase_requests SET status=?, admin_note=? WHERE id=?")->execute([$_POST['pr_status']??'Pending', trim($_POST['admin_note']??''), (int)$_POST['pr_id']]);
        $toast = "✅ Response sent to student!";
    }
    header("Location: admin_requests_extra.php?toast=".urlencode($toast)."&type=".urlencode($toastType)); exit();
}
if (isset($_GET['toast'])) { $toast = $_GET['toast']; $toastType = $_GET['type'] ?? 'success'; }

// ── Fetch fine payments — LEFT JOIN to handle missing books gracefully ─────────
try {
    $finePayments = $pdo->query("
        SELECT fp.*,
               COALESCE(u.full_name, u.username, 'Unknown') AS full_name,
               u.username,
               COALESCE(b.title, 'Unknown Book') AS book_title,
               br.fine_amount,
               br.due_date,
               br.id AS br_id
        FROM fine_payments fp
        LEFT JOIN users u         ON u.id  = fp.user_id
        LEFT JOIN book_requests br ON br.id = fp.request_id
        LEFT JOIN books b          ON b.id  = br.book_id
        ORDER BY FIELD(fp.status,'Pending','Rejected','Verified'), fp.submitted_at DESC
    ")->fetchAll();
} catch (Exception $e) { $finePayments = []; }

// ── Fetch purchase requests ───────────────────────────────────────────────────
try {
    $purchaseRequests = $pdo->query("
        SELECT bpr.*, COALESCE(u.full_name, u.username) AS full_name, u.username
        FROM book_purchase_requests bpr
        LEFT JOIN users u ON u.id = bpr.user_id
        ORDER BY FIELD(bpr.status,'Pending','Reviewed','Ordered','Rejected'), bpr.created_at DESC
    ")->fetchAll();
} catch (Exception $e) { $purchaseRequests = []; }

// ── Sidebar counts ────────────────────────────────────────────────────────────
$pendingReqs     = $pdo->query("SELECT COUNT(*) FROM book_requests WHERE status='Pending'")->fetchColumn();
$pendingPay      = count(array_filter($finePayments,     fn($p) => $p['status'] === 'Pending'));
$pendingPurchase = count(array_filter($purchaseRequests, fn($p) => $p['status'] === 'Pending'));
$totalBooks      = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$totalMembers    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$activeLoans     = $pdo->query("SELECT COUNT(*) FROM book_requests WHERE status='Approved'")->fetchColumn();
$totalFines      = $pdo->query("SELECT COALESCE(SUM(fine_amount),0) FROM book_requests WHERE fine_amount>0 AND (fine_paid IS NULL OR fine_paid=0)")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments & Orders — LIBRITE Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;600&display=swap');
:root{--accent:#22d3ee;--onyx:#02040a;--glass:rgba(255,255,255,.03);--border:rgba(255,255,255,.1);
      --red:#f87171;--green:#34d399;--yellow:#fbbf24;--blue:#60a5fa;--muted:rgba(255,255,255,.5);--purple:#a78bfa;}
*{margin:0;padding:0;box-sizing:border-box;}
body{background:var(--onyx);font-family:'Inter',sans-serif;color:white;display:flex;min-height:100vh;}

/* ── Sidebar — matches admin_dashboard.php exactly ── */
.sidebar{position:fixed;top:0;left:0;height:100vh;width:250px;background:rgba(0,0,0,.4);
  padding:2rem 1rem;border-right:1px solid var(--border);z-index:1000;
  box-shadow:2px 0 15px rgba(0,0,0,.3);overflow-y:auto;}
.logo{font-family:'Playfair Display',serif;font-size:1.4rem;color:white;text-align:center;
  margin-bottom:2.5rem;display:flex;align-items:center;justify-content:center;gap:10px;}
.logo-dot{height:40px;width:40px;border-radius:50%;background:var(--accent);display:inline-block;}
.nav-item{display:flex;align-items:center;padding:.8rem 1.2rem;margin:.3rem 0;text-decoration:none;
  color:rgba(255,255,255,.8);border-radius:8px;transition:all .3s;white-space:nowrap;gap:10px;font-size:.9rem;}
.nav-item:hover,.nav-item.active{background:var(--glass);color:var(--accent);}
.nav-item i{width:20px;text-align:center;flex-shrink:0;}
.nav-badge{background:#ef4444;color:white;font-size:.6rem;font-weight:700;
  padding:1px 6px;border-radius:10px;margin-left:auto;}

/* ── Main ── */
.main{margin-left:250px;flex:1;padding:2rem;}

/* ── Page header ── */
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:2rem;}
.page-left{display:flex;align-items:center;gap:14px;}
.page-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;
  justify-content:center;font-size:1.4rem;background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.2);}
.page-title{font-family:'Playfair Display',serif;font-size:1.8rem;}
.page-sub{font-size:.82rem;color:var(--muted);margin-top:2px;}
.back-btn{display:flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;
  background:var(--glass);border:1px solid var(--border);color:rgba(255,255,255,.6);
  text-decoration:none;font-size:.82rem;transition:all .25s;}
.back-btn:hover{border-color:var(--accent);color:var(--accent);}

/* ── Stats (same style as admin_dashboard) ── */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:1.2rem;margin-bottom:2rem;}
.stat-card{background:var(--glass);backdrop-filter:blur(10px);border:1px solid var(--border);
  border-radius:16px;padding:1.3rem;text-align:center;}
.stat-value{font-size:1.8rem;font-weight:700;margin:.4rem 0;}
.stat-label{color:rgba(255,255,255,.6);font-size:.8rem;}
.stat-icon{font-size:1.3rem;margin-bottom:.3rem;}

/* ── Fine rule reminder ── */
.fine-rule-box{background:rgba(251,191,36,.06);border:1px solid rgba(251,191,36,.2);
  border-radius:10px;padding:10px 16px;font-size:.8rem;color:rgba(255,255,255,.55);margin-bottom:1.5rem;}
.fine-rule-box strong{color:var(--yellow);}

/* ── Tabs ── */
.tabs{display:flex;gap:4px;background:rgba(255,255,255,.04);border:1px solid var(--border);
  border-radius:12px;padding:4px;margin-bottom:1.8rem;width:fit-content;}
.tab-btn{padding:9px 22px;border-radius:9px;border:none;background:transparent;color:var(--muted);
  font-family:'Inter',sans-serif;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .25s;
  display:flex;align-items:center;gap:7px;white-space:nowrap;}
.tab-btn.active{background:var(--accent);color:#000;}
.tab-count{font-size:.65rem;font-weight:700;padding:1px 7px;border-radius:10px;}
.tab-btn.active .tab-count{background:rgba(0,0,0,.2);color:#000;}
.tab-btn:not(.active) .tab-count{background:rgba(255,255,255,.1);color:white;}

/* ── Filter pills ── */
.filter-row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:1.2rem;}
.filter-pill{padding:4px 14px;border-radius:20px;border:1px solid var(--border);
  background:var(--glass);color:var(--muted);font-size:.76rem;font-weight:600;cursor:pointer;transition:all .2s;}
.filter-pill.active{background:var(--accent);border-color:var(--accent);color:#000;}

/* ── Request cards ── */
.req-card{background:rgba(255,255,255,.03);border:1px solid var(--border);
  border-radius:16px;padding:1.3rem 1.5rem;margin-bottom:.9rem;transition:border-color .25s;}
.req-card:hover{border-color:rgba(34,211,238,.3);}
.req-card.bl-y{border-left:3px solid var(--yellow);}
.req-card.bl-g{border-left:3px solid var(--green);}
.req-card.bl-r{border-left:3px solid var(--red);}
.req-card.bl-b{border-left:3px solid var(--blue);}
.card-top{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.card-title{font-size:1rem;font-weight:700;color:white;margin-bottom:3px;}
.card-sub{font-size:.8rem;color:var(--muted);}
.card-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:8px;}
.chip{display:inline-flex;align-items:center;gap:4px;font-size:.72rem;padding:2px 9px;border-radius:20px;}
.chip-user{background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.2);color:var(--accent);}
.chip-amt{background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.25);color:var(--red);font-weight:700;}
.chip-days{background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.25);color:var(--yellow);font-weight:700;}
.chip-date{color:var(--muted);}

/* ── Status pills ── */
.pill{display:inline-block;font-size:.72rem;font-weight:700;padding:3px 11px;border-radius:20px;}
.pill-Pending {background:rgba(251,191,36,.15);color:var(--yellow);border:1px solid rgba(251,191,36,.3);}
.pill-Verified{background:rgba(52,211,153,.15);color:var(--green);border:1px solid rgba(52,211,153,.3);}
.pill-Rejected{background:rgba(248,113,113,.15);color:var(--red);border:1px solid rgba(248,113,113,.3);}
.pill-Reviewed{background:rgba(96,165,250,.15);color:var(--blue);border:1px solid rgba(96,165,250,.3);}
.pill-Ordered {background:rgba(52,211,153,.15);color:var(--green);border:1px solid rgba(52,211,153,.3);}
.pill-Responded{background:rgba(167,139,250,.15);color:var(--purple);border:1px solid rgba(167,139,250,.3);}

/* ── Info boxes ── */
.info-box{border-radius:8px;padding:9px 13px;font-size:.8rem;margin-top:8px;line-height:1.6;}
.box-upi{background:rgba(96,165,250,.08);border:1px solid rgba(96,165,250,.2);color:var(--blue);font-family:monospace;}
.box-reason{background:rgba(255,255,255,.04);border:1px solid var(--border);color:rgba(255,255,255,.65);}
.box-admin{background:rgba(34,211,238,.07);border:1px solid rgba(34,211,238,.2);color:rgba(255,255,255,.7);}
.box-admin strong{color:var(--accent);}

/* ── Action buttons ── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:9px;
  font-size:.82rem;font-weight:700;cursor:pointer;border:none;transition:all .25s;}
.btn-v{background:rgba(52,211,153,.12);border:1px solid rgba(52,211,153,.35);color:var(--green);}
.btn-v:hover{background:var(--green);color:#000;}
.btn-r{background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.35);color:var(--red);}
.btn-r:hover{background:var(--red);color:#000;}
.btn-resp{background:rgba(34,211,238,.08);border:1px solid rgba(34,211,238,.25);color:var(--accent);}
.btn-resp:hover{background:var(--accent);color:#000;}
.btn-send{background:rgba(34,211,238,.1);border:1px solid var(--accent);color:var(--accent);
  padding:9px 20px;border-radius:9px;font-size:.85rem;font-weight:700;cursor:pointer;
  transition:all .25s;white-space:nowrap;}
.btn-send:hover{background:var(--accent);color:#000;}
.screenshot-link{display:inline-flex;align-items:center;gap:5px;background:rgba(96,165,250,.1);
  border:1px solid rgba(96,165,250,.25);color:var(--blue);font-size:.75rem;
  padding:3px 10px;border-radius:8px;text-decoration:none;}
.screenshot-link:hover{background:rgba(96,165,250,.2);}

/* ── Inline response form ── */
.resp-form{display:none;margin-top:14px;padding-top:14px;border-top:1px solid var(--border);}
.resp-form.open{display:block;animation:fi .25s ease;}
@keyframes fi{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.fg{display:grid;grid-template-columns:170px 1fr auto;gap:10px;align-items:end;}
.gi{width:100%;padding:9px 13px;background:rgba(255,255,255,.06);border:1px solid var(--border);
  border-radius:9px;color:white;font-family:'Inter',sans-serif;font-size:.85rem;outline:none;transition:border-color .25s;}
.gi:focus{border-color:var(--accent);}
.gi option{background:#111;}
.lbl{font-size:.7rem;color:var(--muted);text-transform:uppercase;letter-spacing:.6px;display:block;margin-bottom:5px;}
.status-legend{margin-top:10px;font-size:.72rem;color:var(--muted);display:flex;gap:14px;flex-wrap:wrap;}

/* ── Empty state ── */
.empty{text-align:center;padding:4rem 2rem;color:var(--muted);background:var(--glass);
  border:1px solid var(--border);border-radius:16px;}
.empty i{font-size:2.5rem;margin-bottom:1rem;display:block;opacity:.3;}
.empty h3{color:white;margin-bottom:6px;font-size:1rem;}

/* ── Toast ── */
.toast-box{position:fixed;bottom:24px;right:24px;z-index:9999;padding:12px 20px;
  border-radius:12px;font-weight:700;font-size:.88rem;display:flex;align-items:center;gap:8px;
  animation:ti .4s ease,to .5s 3.5s forwards;}
@keyframes ti{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
@keyframes to{to{opacity:0;pointer-events:none}}

@media(max-width:900px){.sidebar{display:none}.main{margin-left:0;padding:1rem}.fg{grid-template-columns:1fr}}
</style>
</head>
<body>

<?php if ($toast): ?>
<div class="toast-box" style="background:<?= $toastType==='error'?'#dc2626':'#059669' ?>;color:white">
    <i class="fas fa-<?= $toastType==='error'?'times-circle':'check-circle' ?>"></i>
    <?= htmlspecialchars($toast) ?>
</div>
<?php endif; ?>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="logo"><div class="logo-dot"></div>LIBRITE ADMIN</div>
    <a href="admin_dashboard.php"       class="nav-item"><i class="fas fa-home"></i> Dashboard</a>
    <a href="manage_books.php"          class="nav-item"><i class="fas fa-book"></i> Manage Books</a>
    <a href="manage_users.php"          class="nav-item"><i class="fas fa-users"></i> Manage Users</a>
    <a href="book_requests.php"         class="nav-item">
        <i class="fas fa-clipboard-list"></i> Book Requests
        <?php if($pendingReqs>0):?><span class="nav-badge"><?=$pendingReqs?></span><?php endif;?>
    </a>
    <a href="admin_requests_extra.php"  class="nav-item active">
        <i class="fas fa-credit-card"></i> Payments & Orders
        <?php if($pendingPay+$pendingPurchase>0):?><span class="nav-badge"><?=$pendingPay+$pendingPurchase?></span><?php endif;?>
    </a>
    <a href="report.php" class="nav-item"><i class="fas fa-chart-bar"></i> Reports & Analytics </a>
    <a href="admin_forgot_password.php" class="nav-item"><i class="fas fa-lock"></i> Change Password</a>
    <a href="index.php" class="nav-item" style="margin-top:2rem;color:#f87171;"><i class="fas fa-sign-out-alt"></i> Logout</a>
</aside>

<!-- MAIN -->
<main class="main">
    <div class="page-header">
        <div class="page-left">
            <div class="page-icon">💳</div>
            <div>
                <div class="page-title">Payments & Orders</div>
                <div class="page-sub">Verify fine payments · Respond to book purchase requests</div>
            </div>
        </div>
        <a href="admin_dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <!-- Stats bar -->
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon">📚</div><div class="stat-label">Total Books</div><div class="stat-value" style="color:var(--accent)"><?=number_format($totalBooks)?></div></div>
        <div class="stat-card"><div class="stat-icon">👥</div><div class="stat-label">Members</div><div class="stat-value" style="color:var(--purple)"><?=number_format($totalMembers)?></div></div>
        <div class="stat-card"><div class="stat-icon">📖</div><div class="stat-label">Currently Borrowed</div><div class="stat-value" style="color:var(--green)"><?=number_format($activeLoans)?></div></div>
        <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-label">Waiting for Approval</div><div class="stat-value" style="color:var(--yellow)"><?=number_format($pendingReqs)?></div></div>
        <div class="stat-card"><div class="stat-icon">💰</div><div class="stat-label">Unpaid Fines</div><div class="stat-value" style="color:var(--red)">₹<?=number_format($totalFines,2)?></div></div>
        <div class="stat-card"><div class="stat-icon">🔔</div><div class="stat-label">Pending Actions</div><div class="stat-value" style="color:var(--blue)"><?=$pendingPay+$pendingPurchase?></div></div>
    </div>

    <div class="fine-rule-box">
        <i class="fas fa-calculator" style="margin-right:6px"></i>
        <strong>Fine Rule: ₹5 per day</strong> — starts from day 1 after the 10-day loan period. 5 days late = ₹25. Auto-updated every page load.
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" id="tab-pay" onclick="switchTab('pay')">
            <i class="fas fa-credit-card"></i> Fine Payments <span class="tab-count"><?=count($finePayments)?></span>
        </button>
        <button class="tab-btn" id="tab-order" onclick="switchTab('order')">
            <i class="fas fa-box"></i> Book Orders <span class="tab-count"><?=count($purchaseRequests)?></span>
        </button>
    </div>

    <!-- ══ FINE PAYMENTS ═══════════════════════════════════════════════════ -->
    <div id="pane-pay">
        <div class="filter-row">
            <?php foreach(['All','Pending','Verified','Rejected'] as $s):
                $cnt=$s==='All'?count($finePayments):count(array_filter($finePayments,fn($p)=>$p['status']===$s));
            ?><button class="filter-pill <?=$s==='All'?'active':''?>" onclick="fc('pay','<?=$s?>',this)"><?=$s?> (<?=$cnt?>)</button><?php endforeach;?>
        </div>

        <?php if(empty($finePayments)):?>
        <div class="empty">
            <i class="fas fa-receipt"></i>
            <h3>No payment submissions yet</h3>
            <p>When students submit fine payment proofs from their dashboard, they appear here.</p>
            <p style="margin-top:8px;font-size:.75rem;opacity:.6">Students go to: Fine Management tab → Submit Payment Screenshot</p>
        </div>
        <?php else: foreach($finePayments as $fp):
            $bl=['Pending'=>'bl-y','Verified'=>'bl-g','Rejected'=>'bl-r'][$fp['status']]??'bl-y';
            $due=$fp['due_date']?new DateTime($fp['due_date']):null;
            $daysLate=$due&&$today>$due?(int)$today->diff($due)->days:0;
            $calcFine=$daysLate*5;
        ?>
        <div class="req-card <?=$bl?> pay-card" data-status="<?=$fp['status']?>">
            <div class="card-top">
                <div style="flex:1">
                    <div class="card-title"><i class="fas fa-rupee-sign" style="font-size:.85rem;margin-right:3px"></i><?=number_format($fp['amount'],2)?> Fine Payment</div>
                    <div class="card-sub">Book: <strong style="color:white"><?=htmlspecialchars($fp['book_title'])?></strong></div>
                    <div class="card-meta">
                        <span class="chip chip-user"><i class="fas fa-user" style="font-size:.6rem"></i><?=htmlspecialchars($fp['full_name'])?></span>
                        <span class="chip chip-amt">Submitted: ₹<?=number_format($fp['amount'],2)?></span>
                        <?php if($daysLate>0):?><span class="chip chip-days"><i class="fas fa-calendar-times" style="font-size:.6rem"></i><?=$daysLate?> days late — calculated ₹<?=$calcFine?></span><?php endif;?>
                        <span class="chip chip-date"><i class="fas fa-clock" style="font-size:.6rem"></i><?=date('d M Y, g:i A',strtotime($fp['submitted_at']))?></span>
                    </div>
                </div>
                <span class="pill pill-<?=$fp['status']?>"><?=$fp['status']?></span>
            </div>

            <?php if($fp['upi_ref']):?>
            <div class="info-box box-upi"><i class="fas fa-fingerprint" style="margin-right:6px"></i>UPI Ref: <strong><?=htmlspecialchars($fp['upi_ref'])?></strong></div>
            <?php endif;?>

            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:10px">
                <?php if($fp['screenshot']):?>
                <a href="<?=htmlspecialchars($fp['screenshot'])?>" target="_blank" class="screenshot-link"><i class="fas fa-image"></i> View Payment Screenshot</a>
                <?php else:?><span style="font-size:.75rem;color:var(--muted)"><i class="fas fa-image" style="opacity:.4;margin-right:4px"></i>No screenshot uploaded</span><?php endif;?>
                <?php if($fp['due_date']):?><span style="font-size:.75rem;color:var(--muted)"><i class="fas fa-calendar" style="margin-right:4px"></i>Due was: <?=date('d M Y',strtotime($fp['due_date']))?></span><?php endif;?>
            </div>

            <?php if($fp['status']==='Pending'):?>
            <div style="display:flex;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid var(--border);flex-wrap:wrap">
                <form method="POST" style="display:inline" onsubmit="return confirm('Verify this payment and clear the fine?')">
                    <input type="hidden" name="action"  value="verify_payment">
                    <input type="hidden" name="pay_id"  value="<?=$fp['id']?>">
                    <input type="hidden" name="req_id"  value="<?=$fp['request_id']?>">
                    <button type="submit" class="btn btn-v"><i class="fas fa-check-circle"></i> Verify & Clear Fine</button>
                </form>
                <form method="POST" style="display:inline" onsubmit="return confirm('Reject this payment?')">
                    <input type="hidden" name="action" value="reject_payment">
                    <input type="hidden" name="pay_id" value="<?=$fp['id']?>">
                    <button type="submit" class="btn btn-r"><i class="fas fa-times-circle"></i> Reject Payment</button>
                </form>
            </div>
            <?php elseif($fp['status']==='Verified'):?>
            <div style="margin-top:10px;padding:8px 12px;border-radius:8px;font-size:.82rem;color:var(--green);background:rgba(52,211,153,.08);border:1px solid rgba(52,211,153,.2)">
                <i class="fas fa-check-circle"></i> Verified on <?=$fp['verified_at']?date('d M Y, g:i A',strtotime($fp['verified_at'])):'N/A'?> — fine cleared for student
            </div>
            <?php else:?>
            <div style="margin-top:10px;padding:8px 12px;border-radius:8px;font-size:.82rem;color:var(--red);background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.2)">
                <i class="fas fa-times-circle"></i> Payment was rejected — student needs to resubmit
            </div>
            <?php endif;?>
        </div>
        <?php endforeach; endif;?>
    </div>

    <!-- ══ BOOK ORDERS ══════════════════════════════════════════════════════ -->
    <div id="pane-order" style="display:none">
        <div class="filter-row">
            <?php foreach(['All','Pending','Reviewed','Ordered','Rejected'] as $s):
                $cnt=$s==='All'?count($purchaseRequests):count(array_filter($purchaseRequests,fn($p)=>$p['status']===$s));
            ?><button class="filter-pill <?=$s==='All'?'active':''?>" onclick="fc('order','<?=$s?>',this)"><?=$s?> (<?=$cnt?>)</button><?php endforeach;?>
        </div>

        <?php if(empty($purchaseRequests)):?>
        <div class="empty">
            <i class="fas fa-box-open"></i>
            <h3>No book order requests yet</h3>
            <p>Students submit requests from their "Request a Book" tab — they appear here.</p>
        </div>
        <?php else: foreach($purchaseRequests as $pr):
            $blm=['Pending'=>'bl-y','Reviewed'=>'bl-b','Ordered'=>'bl-g','Rejected'=>'bl-r'];
            $bl=$blm[$pr['status']]??'bl-y';
            // Show "Responded" badge if admin_note exists
            $hasResponse = !empty($pr['admin_note']);
        ?>
        <div class="req-card <?=$bl?> order-card" data-status="<?=$pr['status']?>">
            <div class="card-top">
                <div style="flex:1">
                    <div class="card-title"><i class="fas fa-book" style="font-size:.85rem;margin-right:5px;opacity:.5"></i><?=htmlspecialchars($pr['book_title'])?></div>
                    <?php if($pr['author']):?><div class="card-sub">by <?=htmlspecialchars($pr['author'])?></div><?php endif;?>
                    <div class="card-meta">
                        <span class="chip chip-user"><i class="fas fa-user" style="font-size:.6rem"></i><?=htmlspecialchars($pr['full_name'])?></span>
                        <span class="chip chip-date"><i class="fas fa-clock" style="font-size:.6rem"></i><?=date('d M Y, g:i A',strtotime($pr['created_at']))?></span>
                        <?php if($hasResponse):?>
                        <span class="pill pill-Responded" style="font-size:.65rem;padding:2px 8px">✅ Responded</span>
                        <?php endif;?>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                    <span class="pill pill-<?=$pr['status']?>"><?=$pr['status']?></span>
                    <button class="btn btn-resp" onclick="tr('order-<?=$pr['id']?>')">
                        <i class="fas fa-reply"></i> <?=$hasResponse?'Edit Response':'Respond'?>
                    </button>
                </div>
            </div>

            <?php if($pr['reason']):?>
            <div class="info-box box-reason"><i class="fas fa-quote-left" style="opacity:.35;margin-right:6px;font-size:.75rem"></i><?=htmlspecialchars($pr['reason'])?></div>
            <?php endif;?>

            <?php if($hasResponse):?>
            <div class="info-box box-admin">
                <strong><i class="fas fa-reply" style="margin-right:4px"></i>Your response (student can see this):</strong><br>
                <span style="margin-top:4px;display:block;font-size:.85rem"><?=htmlspecialchars($pr['admin_note'])?></span>
            </div>
            <?php endif;?>

            <!-- Inline response form -->
            <div class="resp-form" id="order-<?=$pr['id']?>">
                <p style="font-size:.78rem;color:var(--muted);margin-bottom:12px">
                    <i class="fas fa-info-circle" style="margin-right:4px;color:var(--accent)"></i>
                    Your response will be shown to the student instantly in their <strong style="color:white">Request a Book</strong> tab.
                </p>
                <form method="POST">
                    <input type="hidden" name="action" value="update_purchase">
                    <input type="hidden" name="pr_id"  value="<?=$pr['id']?>">
                    <div class="fg">
                        <div>
                            <label class="lbl">Update Status</label>
                            <select name="pr_status" class="gi">
                                <option value="Pending"  <?=$pr['status']==='Pending' ?'selected':''?>>⏳ Pending</option>
                                <option value="Reviewed" <?=$pr['status']==='Reviewed'?'selected':''?>>👀 Reviewed</option>
                                <option value="Ordered"  <?=$pr['status']==='Ordered' ?'selected':''?>>✅ Ordered</option>
                                <option value="Rejected" <?=$pr['status']==='Rejected'?'selected':''?>>❌ Rejected</option>
                            </select>
                        </div>
                        <div>
                            <label class="lbl">Message to Student</label>
                            <input type="text" name="admin_note" class="gi"
                                   placeholder="e.g. Book ordered! Arriving next week. / Not in budget this semester."
                                   value="<?=htmlspecialchars($pr['admin_note']??'')?>">
                        </div>
                        <div><label class="lbl">&nbsp;</label>
                            <button type="submit" class="btn-send"><i class="fas fa-paper-plane"></i> Send</button>
                        </div>
                    </div>
                    <div class="status-legend">
                        <span><span style="color:var(--yellow)">⏳ Pending</span> = not reviewed</span>
                        <span><span style="color:var(--blue)">👀 Reviewed</span> = acknowledged</span>
                        <span><span style="color:var(--green)">✅ Ordered</span> = book ordered!</span>
                        <span><span style="color:var(--red)">❌ Rejected</span> = can't fulfil</span>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; endif;?>
    </div>
</main>

<script>
function switchTab(t){['pay','order'].forEach(n=>{document.getElementById('pane-'+n).style.display=n===t?'block':'none';document.getElementById('tab-'+n).classList.toggle('active',n===t);});}
function fc(pane,status,btn){
    const cls=pane==='pay'?'.pay-card':'.order-card';
    document.querySelectorAll('#pane-'+pane+' '+cls).forEach(c=>{c.style.display=(status==='All'||c.dataset.status===status)?'block':'none';});
    btn.closest('.filter-row').querySelectorAll('.filter-pill').forEach(p=>p.classList.remove('active'));
    btn.classList.add('active');
}
function tr(id){const f=document.getElementById(id);f.classList.toggle('open');if(f.classList.contains('open'))setTimeout(()=>f.querySelector('input[type=text]')?.focus(),50);}
</script>
</body>
</html>