<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id'])) { header("Location: user_login.php"); exit(); }

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

// ── Profile Photo Upload ──────────────────────────────────────────────────────
$uploadMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $file    = $_FILES['profile_photo'];
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (in_array($file['type'], $allowed) && $file['size'] < 2 * 1024 * 1024) {
        $ext   = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fname = "user_{$user_id}_" . time() . ".$ext";
        $dest  = "uploads/profiles/$fname";
        @mkdir("uploads/profiles", 0755, true);
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $pdo->prepare("UPDATE users SET profile_photo=? WHERE id=?")->execute([$dest, $user_id]);
            $uploadMsg = 'photo_ok';
        }
    } else { $uploadMsg = 'photo_err'; }
}

// ── Profile Save ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $pdo->prepare("UPDATE users SET full_name=?, reg_no=?, department=?, dob=?, address=?, phone=? WHERE id=?")
        ->execute([
            trim($_POST['full_name'] ?? ''), trim($_POST['reg_no'] ?? ''),
            trim($_POST['department'] ?? ''), $_POST['dob'] ?: null,
            trim($_POST['address'] ?? ''), trim($_POST['phone'] ?? ''), $user_id
        ]);
    $uploadMsg = 'profile_ok';
}

// ── Book Request ──────────────────────────────────────────────────────────────
$requestMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_book_id'])) {
    $book_id = (int)$_POST['request_book_id'];
    $chk = $pdo->prepare("SELECT id FROM book_requests WHERE user_id=? AND book_id=? AND status IN ('Pending','Approved')");
    $chk->execute([$user_id, $book_id]);
    if ($chk->fetch()) {
        $requestMsg = 'already_requested';
    } else {
        $copyChk = $pdo->prepare("SELECT copies FROM books WHERE id=? AND copies > 0");
        $copyChk->execute([$book_id]);
        if (!$copyChk->fetch()) {
            $requestMsg = 'no_copies';
        } else {
            $pdo->prepare("INSERT INTO book_requests (user_id, book_id, status) VALUES (?,?,'Pending')")->execute([$user_id, $book_id]);
            $requestMsg = 'success';
        }
    }
}

// ── Purchase Request ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_title'])) {
    $ptitle  = trim($_POST['purchase_title']  ?? '');
    $pauthor = trim($_POST['purchase_author'] ?? '');
    $preason = trim($_POST['purchase_reason'] ?? '');
    if ($ptitle) {
        $pdo->prepare("INSERT INTO book_purchase_requests (user_id, book_title, author, reason) VALUES (?,?,?,?)")
            ->execute([$user_id, $ptitle, $pauthor, $preason]);
        $uploadMsg = 'purchase_ok';
    }
}

// ── Fine Payment Submission ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $req_id  = (int)$_POST['pay_request_id'];
    $upi_ref = trim($_POST['upi_ref'] ?? '');
    $amount  = (float)$_POST['pay_amount'];
    $screenshot = '';
    if (isset($_FILES['pay_screenshot']) && $_FILES['pay_screenshot']['error'] === 0) {
        $ext  = pathinfo($_FILES['pay_screenshot']['name'], PATHINFO_EXTENSION);
        $fname = "pay_{$user_id}_" . time() . ".$ext";
        @mkdir("uploads/payments", 0755, true);
        if (move_uploaded_file($_FILES['pay_screenshot']['tmp_name'], "uploads/payments/$fname")) {
            $screenshot = "uploads/payments/$fname";
        }
    }
    $pdo->prepare("INSERT INTO fine_payments (user_id, request_id, amount, upi_ref, screenshot) VALUES (?,?,?,?,?)")
        ->execute([$user_id, $req_id, $amount, $upi_ref, $screenshot]);
    $uploadMsg = 'payment_ok';
}

// ── Fetch user data ───────────────────────────────────────────────────────────
$userData = $pdo->prepare("SELECT * FROM users WHERE id=?");
$userData->execute([$user_id]);
$userData = $userData->fetch();
$profilePhoto = $userData['profile_photo'] ?? '';

// ── Fetch books ───────────────────────────────────────────────────────────────
$books = $pdo->query("SELECT * FROM books ORDER BY title ASC")->fetchAll();

// ── Fetch requests ────────────────────────────────────────────────────────────
$myReqStmt = $pdo->prepare("
    SELECT br.*, b.title, b.author, b.shelf, b.category
    FROM book_requests br JOIN books b ON b.id = br.book_id
    WHERE br.user_id = ? ORDER BY br.requested_at DESC LIMIT 20
");
$myReqStmt->execute([$user_id]);
$myRequests = $myReqStmt->fetchAll();

// ── Auto fines (₹10 per day from day 1 of being overdue) ─────────────────────
$today = new DateTime();
foreach ($myRequests as &$req) {
    if ($req['status'] === 'Approved' && $req['due_date'] && !$req['returned_at'] && !($req['fine_paid'] ?? 0)) {
        $due = new DateTime($req['due_date']);
        if ($today > $due) {
            $days = (int)$today->diff($due)->days;
            $fine = $days * 10; // ₹10 per day, every day overdue
            $pdo->prepare("UPDATE book_requests SET fine_amount=? WHERE id=?")->execute([$fine, $req['id']]);
            $req['fine_amount'] = $fine;
        }
    }
}
unset($req);

$totalFineAmt = array_sum(array_column(
    array_filter($myRequests, fn($r) => ($r['fine_amount'] ?? 0) > 0 && !$r['returned_at']),
    'fine_amount'
));

// ── Pending fine payments ─────────────────────────────────────────────────────
$pendingPayments = $pdo->prepare("SELECT request_id FROM fine_payments WHERE user_id=? AND status='Pending'");
$pendingPayments->execute([$user_id]);
$pendingPayIds = array_column($pendingPayments->fetchAll(), 'request_id');

// ── New approvals ─────────────────────────────────────────────────────────────
$newApprovals = $pdo->prepare("
    SELECT br.id, b.title, br.due_date FROM book_requests br JOIN books b ON b.id=br.book_id
    WHERE br.user_id=? AND br.status='Approved' AND (br.notified IS NULL OR br.notified=0)
");
$newApprovals->execute([$user_id]);
$newApprovals = $newApprovals->fetchAll();
foreach ($newApprovals as $na) $pdo->prepare("UPDATE book_requests SET notified=1 WHERE id=?")->execute([$na['id']]);

// ── AI: last 3 categories requested ──────────────────────────────────────────
$lastCats = array_unique(array_column(array_slice($myRequests, 0, 5), 'category'));
$aiBooks  = [];
if (!empty($lastCats)) {
    $placeholders = implode(',', array_fill(0, count($lastCats), '?'));
    $aiStmt = $pdo->prepare("SELECT * FROM books WHERE category IN ($placeholders) AND status='Available' ORDER BY RAND() LIMIT 4");
    $aiStmt->execute($lastCats);
    $aiBooks = $aiStmt->fetchAll();
}

// ── UPI Config ────────────────────────────────────────────────────────────────
$UPI_ID   = "library@upi";
$UPI_NAME = "LIBRITE Library";
$QR_IMAGE = "uploads/qr_code.jpeg";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | LIBRITE</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root { --bg:#02040a; --card:#0d1117; --card2:#161b22; --border:#30363d; --accent:#238636; --muted:#8b949e; --main:#c9d1d9; --err:#f85149; --warn:#e3b341; --blue:#58a6ff; }
*{box-sizing:border-box;}
body{background:var(--bg);color:var(--main);font-family:'Space Grotesk',sans-serif;margin:0;}
.card{background:var(--card);border:1px solid var(--border);border-radius:12px;}
.dark-input{background:var(--card);border:1px solid var(--border);color:white;border-radius:6px;padding:8px 12px;outline:none;width:100%;}
.dark-input:focus{border-color:var(--blue);}
.badge{padding:2px 10px;border-radius:20px;font-size:.75rem;font-weight:600;display:inline-block;}
.badge-available{background:rgba(35,134,54,.15);color:#3fb950;border:1px solid rgba(63,185,80,.3);}
.badge-borrowed{background:rgba(210,153,34,.15);color:#d29922;border:1px solid rgba(210,153,34,.3);}
.badge-lost{background:rgba(248,81,73,.15);color:#f85149;border:1px solid rgba(248,81,73,.3);}
.badge-pending{background:rgba(88,166,255,.15);color:#58a6ff;border:1px solid rgba(88,166,255,.3);}
.badge-approved{background:rgba(35,134,54,.15);color:#3fb950;border:1px solid rgba(63,185,80,.3);}
.badge-rejected{background:rgba(248,81,73,.15);color:#f85149;border:1px solid rgba(248,81,73,.3);}
th{background:#161b22;padding:12px 20px;color:var(--muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border);}
td{padding:12px 20px;border-bottom:1px solid var(--border);vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover{background:rgba(255,255,255,.02);}
.tab-active{border-bottom:2px solid var(--blue);color:var(--blue);}
.tab-inactive{border-bottom:2px solid transparent;color:var(--muted);}
#profilePanel{position:fixed;top:0;right:-440px;width:420px;height:100vh;background:var(--card);border-left:1px solid var(--border);z-index:9000;overflow-y:auto;transition:right .35s cubic-bezier(.4,0,.2,1);}
#profilePanel.open{right:0;box-shadow:-20px 0 60px rgba(0,0,0,.6);}
#panelOverlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:8999;}
#panelOverlay.open{display:block;}
.avatar-ring{width:96px;height:96px;border-radius:50%;background:conic-gradient(#238636 0deg,#3fb950 120deg,#58a6ff 240deg,#238636 360deg);padding:3px;position:relative;cursor:pointer;}
.avatar-inner{width:100%;height:100%;border-radius:50%;overflow:hidden;background:#21262d;display:flex;align-items:center;justify-content:center;}
.avatar-overlay{position:absolute;inset:0;border-radius:50%;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s;}
.avatar-ring:hover .avatar-overlay{opacity:1;}
.profile-hero{background:linear-gradient(135deg,#0d2818 0%,#0d1117 50%,#0d1526 100%);border-bottom:1px solid var(--border);padding:28px 24px 22px;}
.info-row{display:flex;align-items:flex-start;gap:12px;padding:11px 0;border-bottom:1px solid rgba(48,54,61,.5);}
.info-row:last-child{border-bottom:none;}
.info-label{color:var(--muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px;}
.info-value{color:var(--main);font-size:.875rem;font-weight:500;}
.info-icon{width:32px;height:32px;border-radius:8px;background:rgba(88,166,255,.1);border:1px solid rgba(88,166,255,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;}
.btn-save{background:#238636;color:white;border:none;padding:9px 20px;border-radius:8px;font-weight:600;cursor:pointer;font-size:.875rem;}
.btn-save:hover{background:#2ea043;}
.btn-cancel{background:transparent;color:var(--muted);border:1px solid var(--border);padding:9px 16px;border-radius:8px;cursor:pointer;}
.file-input-label{display:flex;align-items:center;gap:8px;padding:8px 14px;border:1px dashed var(--border);border-radius:8px;cursor:pointer;font-size:.82rem;color:var(--muted);}
.file-input-label:hover{border-color:var(--blue);color:var(--blue);}
input[type=file]{display:none;}
#toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(60px);opacity:0;transition:all .3s;z-index:9999;border-radius:999px;padding:10px 22px;font-size:.875rem;font-weight:600;}
#toast.show{transform:translateX(-50%) translateY(0);opacity:1;}
.header-avatar{width:36px;height:36px;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid var(--accent);font-weight:700;font-size:.82rem;background:#21262d;}
.books-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;}
.book-card{background:var(--card);border:1px solid var(--border);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;transition:transform .2s,box-shadow .2s,border-color .2s;}
.book-card:hover{transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,0,0,.5);border-color:#58a6ff44;}
.book-card-img{height:160px;overflow:hidden;position:relative;background:#161b22;}
.book-card-img img{width:100%;height:100%;object-fit:cover;}
.img-placeholder{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;background:linear-gradient(135deg,#161b22,#1f2937);}
.copies-pill{position:absolute;top:8px;right:8px;background:rgba(0,0,0,.75);border:1px solid rgba(255,255,255,.15);border-radius:20px;padding:2px 9px;font-size:.7rem;font-weight:600;color:white;}
.book-card-body{padding:12px 14px;flex:1;display:flex;flex-direction:column;gap:4px;}
.book-card-title{font-weight:700;color:white;font-size:.88rem;line-height:1.3;}
.book-card-author{color:var(--muted);font-size:.76rem;}
.book-card-footer{padding:8px 14px 14px;}
.btn-req-card{width:100%;padding:8px;border-radius:8px;font-weight:600;font-size:.8rem;cursor:pointer;border:none;transition:all .2s;}
.btn-req-card.can{background:#238636;color:white;}
.btn-req-card.can:hover{background:#2ea043;}
.btn-req-card.done{background:rgba(88,166,255,.1);color:#58a6ff;border:1px solid rgba(88,166,255,.3);cursor:default;}
.btn-req-card.none{background:#21262d;color:var(--muted);cursor:not-allowed;}

/* AI Assistant */
.ai-bubble{background:linear-gradient(135deg,#0d2818,#0d1526);border:1px solid rgba(35,134,54,.3);border-radius:16px;padding:20px;}
.ai-msg{background:var(--card2);border-radius:10px;padding:12px 14px;margin-bottom:8px;font-size:.875rem;}
.ai-msg.user{background:rgba(88,166,255,.1);border:1px solid rgba(88,166,255,.2);margin-left:20px;}
.ai-msg.bot{border:1px solid var(--border);}
.ai-typing span{display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--muted);margin:0 2px;animation:bounce .8s infinite;}
.ai-typing span:nth-child(2){animation-delay:.15s;}
.ai-typing span:nth-child(3){animation-delay:.3s;}
@keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}

/* Fine payment */
.fine-bar{background:linear-gradient(90deg,rgba(248,81,73,.12),rgba(248,81,73,.04));border:1px solid rgba(248,81,73,.25);border-radius:12px;padding:16px 20px;}
.pay-method-btn{padding:8px 18px;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;border:none;}
.pay-method-btn.active{background:#238636;color:white;}
.pay-method-btn.inactive{background:transparent;color:var(--muted);border:1px solid var(--border);}
.qr-wrap{background:white;border-radius:14px;padding:14px;display:inline-flex;}
.qr-wrap img{width:200px;height:200px;border-radius:4px;}
.step-badge{width:24px;height:24px;border-radius:50%;background:rgba(35,134,54,.2);border:1px solid rgba(63,185,80,.3);color:#3fb950;font-size:.72rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;}

@media(max-width:480px){#profilePanel{width:100vw;right:-100vw;}.books-grid{grid-template-columns:repeat(2,1fr);}}

/* ══ FLOATING AI CHATBOT ══════════════════════════════════════════════════ */
#ai-fab{position:fixed;bottom:28px;right:28px;z-index:8000;width:58px;height:58px;border-radius:50%;
    background:linear-gradient(135deg,#238636,#1a7f37);border:2px solid rgba(63,185,80,.4);
    box-shadow:0 8px 30px rgba(35,134,54,.45);cursor:pointer;display:flex;align-items:center;
    justify-content:center;font-size:1.5rem;transition:transform .25s,box-shadow .25s;
    animation:fabPulse 2.5s ease-in-out infinite;}
#ai-fab:hover{transform:scale(1.12);box-shadow:0 12px 40px rgba(35,134,54,.6);}
@keyframes fabPulse{0%,100%{box-shadow:0 8px 30px rgba(35,134,54,.45)}50%{box-shadow:0 8px 40px rgba(35,134,54,.7)}}
#ai-fab .notif-dot{position:absolute;top:2px;right:2px;width:12px;height:12px;border-radius:50%;
    background:#f85149;border:2px solid var(--bg);display:none;}
#ai-fab.has-notif .notif-dot{display:block;}

#ai-chat-box{position:fixed;bottom:100px;right:28px;z-index:8000;width:370px;max-height:560px;
    background:var(--card);border:1px solid rgba(63,185,80,.25);border-radius:20px;
    box-shadow:0 20px 60px rgba(0,0,0,.7);display:none;flex-direction:column;overflow:hidden;
    animation:chatSlideUp .3s cubic-bezier(.175,.885,.32,1.275);}
#ai-chat-box.open{display:flex;}
@keyframes chatSlideUp{from{opacity:0;transform:translateY(20px) scale(.95)}to{opacity:1;transform:translateY(0) scale(1)}}

.ai-chat-header{background:linear-gradient(135deg,#0d2818,#0d1526);padding:14px 18px;
    display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.06);}
.ai-chat-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#238636,#58a6ff);
    display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;}
.ai-chat-title{font-weight:700;color:white;font-size:.9rem;}
.ai-chat-status{font-size:.7rem;color:#3fb950;display:flex;align-items:center;gap:4px;}
.ai-chat-status::before{content:'';width:6px;height:6px;border-radius:50%;background:#3fb950;display:inline-block;}
.ai-close-btn{width:28px;height:28px;border-radius:8px;border:none;background:rgba(255,255,255,.06);
    color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.ai-close-btn:hover{background:rgba(255,255,255,.12);color:white;}

.ai-messages{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:8px;
    scrollbar-width:thin;scrollbar-color:var(--border) transparent;}
.ai-messages::-webkit-scrollbar{width:4px;}
.ai-messages::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px;}

.ai-msg-bot{background:var(--card2);border:1px solid var(--border);border-radius:12px 12px 12px 4px;
    padding:10px 13px;font-size:.82rem;line-height:1.55;color:var(--main);max-width:90%;align-self:flex-start;white-space:pre-line;}
.ai-msg-user{background:rgba(35,134,54,.15);border:1px solid rgba(63,185,80,.25);
    border-radius:12px 12px 4px 12px;padding:10px 13px;font-size:.82rem;color:#c9d1d9;
    max-width:85%;align-self:flex-end;text-align:right;}
.ai-book-card{background:rgba(88,166,255,.06);border:1px solid rgba(88,166,255,.2);border-radius:10px;
    padding:9px 12px;margin-top:6px;cursor:pointer;transition:background .2s;}
.ai-book-card:hover{background:rgba(88,166,255,.12);}
.ai-book-card-title{font-weight:700;color:white;font-size:.8rem;}
.ai-book-card-meta{font-size:.7rem;color:var(--muted);margin-top:2px;}
.ai-book-desc{font-size:.72rem;color:rgba(255,255,255,.5);margin-top:4px;line-height:1.45;}

.ai-typing-indicator{display:flex;align-items:center;gap:4px;padding:10px 13px;
    background:var(--card2);border:1px solid var(--border);border-radius:12px 12px 12px 4px;
    max-width:70px;align-self:flex-start;}
.ai-typing-indicator span{width:6px;height:6px;border-radius:50%;background:var(--muted);
    animation:aiDot .8s infinite;}
.ai-typing-indicator span:nth-child(2){animation-delay:.15s;}
.ai-typing-indicator span:nth-child(3){animation-delay:.3s;}
@keyframes aiDot{0%,100%{transform:translateY(0);opacity:.5}50%{transform:translateY(-4px);opacity:1}}

.ai-quick-pills{display:flex;gap:6px;flex-wrap:wrap;padding:8px 14px;border-top:1px solid rgba(255,255,255,.04);}
.ai-pill{font-size:.7rem;padding:4px 10px;border-radius:20px;border:1px solid rgba(88,166,255,.2);
    background:rgba(88,166,255,.06);color:#58a6ff;cursor:pointer;transition:all .2s;white-space:nowrap;}
.ai-pill:hover{background:rgba(88,166,255,.15);border-color:#58a6ff;}

.ai-input-row{padding:10px 14px;border-top:1px solid rgba(255,255,255,.06);display:flex;gap:8px;align-items:center;}
.ai-text-input{flex:1;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;
    color:white;padding:8px 12px;font-size:.82rem;outline:none;font-family:'Space Grotesk',sans-serif;transition:border-color .2s;}
.ai-text-input:focus{border-color:#238636;}
.ai-text-input::placeholder{color:var(--muted);}
.ai-send-btn{width:34px;height:34px;border-radius:10px;border:none;background:#238636;color:white;
    cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .2s;}
.ai-send-btn:hover{background:#2ea043;}
@media(max-width:480px){#ai-chat-box{width:calc(100vw - 32px);right:16px;bottom:90px;}#ai-fab{right:16px;bottom:20px;}}
</style>
</head>
<body>
<div id="toast"></div>

<?php
$toastMsgs = [
    'photo_ok'=>['✅ Profile photo updated!','#238636'],
    'profile_ok'=>['✅ Profile saved!','#238636'],
    'photo_err'=>['❌ Use JPG/PNG under 2MB.','#f85149'],
    'purchase_ok'=>['✅ Book request sent to admin!','#238636'],
    'payment_ok'=>['✅ Payment submitted for verification!','#238636'],
];
if (isset($toastMsgs[$uploadMsg])): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast('<?= $toastMsgs[$uploadMsg][0] ?>','<?= $toastMsgs[$uploadMsg][1] ?>'));</script>
<?php endif; ?>

<!-- APPROVAL POPUP -->
<?php if (!empty($newApprovals)): ?>
<div id="approvalOverlay" class="fixed inset-0 z-[9999] flex items-center justify-center p-4" style="background:rgba(0,0,0,.85);backdrop-filter:blur(6px)">
  <div class="card max-w-md w-full p-6 text-center" style="animation:popIn .4s cubic-bezier(.175,.885,.32,1.275)">
    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background:rgba(35,134,54,.2);border:1px solid rgba(63,185,80,.3)">
        <i data-lucide="check-circle-2" class="w-8 h-8 text-emerald-400"></i>
    </div>
    <h2 class="text-xl font-bold text-white mb-2">🎉 Request Approved!</h2>
    <?php foreach ($newApprovals as $na): ?>
    <p class="mb-1" style="color:var(--muted)"><strong class="text-white"><?= htmlspecialchars($na['title']) ?></strong> approved.
    <?php if ($na['due_date']): ?><br><span class="text-yellow-400 text-sm">Return by: <strong><?= date('d M Y', strtotime($na['due_date'])) ?></strong></span><?php endif; ?></p>
    <?php endforeach; ?>
    <p class="text-xs mt-3 mb-5" style="color:var(--muted)">Collect from the library counter. Return within 4 days to avoid fines.</p>
    <button onclick="document.getElementById('approvalOverlay').remove()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-2 rounded-lg font-bold">Got it!</button>
  </div>
</div>
<?php endif; ?>

<div id="panelOverlay" onclick="closeProfile()"></div>

<!-- ══ PROFILE PANEL ══════════════════════════════════════════════════════════ -->
<div id="profilePanel">
    <div class="profile-hero">
        <div class="flex items-start justify-between mb-5">
            <span class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted)">My Profile</span>
            <button onclick="closeProfile()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/10">
                <i data-lucide="x" class="w-4 h-4" style="color:var(--muted)"></i>
            </button>
        </div>
        <div class="flex items-end gap-4">
            <div class="avatar-ring" onclick="switchProfileTab('edit');document.getElementById('photoFileInput').click()">
                <div class="avatar-inner">
                    <?php if ($profilePhoto && file_exists($profilePhoto)): ?>
                        <img src="<?= htmlspecialchars($profilePhoto) ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <span class="text-2xl font-bold text-white"><?= strtoupper(substr($userData['full_name'] ?: $username, 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="avatar-overlay"><i data-lucide="camera" class="w-5 h-5 text-white"></i></div>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-white font-bold text-lg truncate"><?= htmlspecialchars($userData['full_name'] ?: $username) ?></h3>
                <?php if (!empty($userData['reg_no'])): ?><p class="text-xs font-mono mt-0.5" style="color:var(--muted)"><?= htmlspecialchars($userData['reg_no']) ?></p><?php endif; ?>
                <?php if (!empty($userData['department'])): ?><span class="text-xs px-2 py-0.5 rounded-full mt-1.5 inline-block" style="background:rgba(35,134,54,.2);color:#3fb950;border:1px solid rgba(35,134,54,.3)"><?= htmlspecialchars($userData['department']) ?></span><?php endif; ?>
            </div>
        </div>
        <?php
        $totalReqs   = count($myRequests);
        $activeBooks = count(array_filter($myRequests, fn($r) => $r['status']==='Approved' && !($r['returned_at']??null)));
        $totalFine   = array_sum(array_column(array_filter($myRequests, fn($r) => ($r['fine_amount']??0)>0), 'fine_amount'));
        ?>
        <div class="grid grid-cols-3 gap-2 mt-5">
            <div class="text-center p-2.5 rounded-lg" style="background:rgba(255,255,255,.04);border:1px solid var(--border)">
                <div class="text-lg font-bold text-white"><?= $totalReqs ?></div>
                <div class="text-[10px]" style="color:var(--muted)">Requests</div>
            </div>
            <div class="text-center p-2.5 rounded-lg" style="background:rgba(255,255,255,.04);border:1px solid var(--border)">
                <div class="text-lg font-bold text-emerald-400"><?= $activeBooks ?></div>
                <div class="text-[10px]" style="color:var(--muted)">Holding</div>
            </div>
            <div class="text-center p-2.5 rounded-lg" style="background:rgba(255,255,255,.04);border:1px solid var(--border)">
                <div class="text-lg font-bold <?= $totalFine>0?'text-red-400':'text-white' ?>">₹<?= $totalFine ?></div>
                <div class="text-[10px]" style="color:var(--muted)">Fine</div>
            </div>
        </div>
    </div>
    <div class="flex border-b" style="border-color:var(--border)">
        <button onclick="switchProfileTab('view')" id="ptab-view" class="flex-1 py-3 text-sm font-medium tab-active">Details</button>
        <button onclick="switchProfileTab('edit')" id="ptab-edit" class="flex-1 py-3 text-sm font-medium tab-inactive">Edit Profile</button>
    </div>
    <div id="ppane-view" class="p-5">
        <?php $fields=[['icon'=>'user','label'=>'Full Name','val'=>$userData['full_name']??'—'],['icon'=>'hash','label'=>'Register No.','val'=>$userData['reg_no']??'—'],['icon'=>'graduation-cap','label'=>'Department','val'=>$userData['department']??'—'],['icon'=>'phone','label'=>'Phone','val'=>$userData['phone']??'—'],['icon'=>'mail','label'=>'Username','val'=>$username],['icon'=>'map-pin','label'=>'Address','val'=>$userData['address']??'—']];
        foreach ($fields as $f): ?>
        <div class="info-row">
            <div class="info-icon"><i data-lucide="<?= $f['icon'] ?>" class="w-3.5 h-3.5" style="color:#58a6ff"></i></div>
            <div><div class="info-label"><?= $f['label'] ?></div><div class="info-value"><?= htmlspecialchars($f['val']) ?></div></div>
        </div>
        <?php endforeach; ?>
        <div class="mt-6 pt-5" style="border-top:1px solid var(--border)">
            <a href="index.php" class="flex items-center gap-2 text-sm font-medium text-red-400"><i data-lucide="log-out" class="w-4 h-4"></i> Logout</a>
        </div>
    </div>
    <div id="ppane-edit" class="p-5 hidden">
        <form method="POST" enctype="multipart/form-data" class="mb-5 pb-5" style="border-bottom:1px solid var(--border)">
            <p class="text-xs font-semibold uppercase tracking-wider mb-2.5" style="color:var(--muted)">Profile Photo</p>
            <div class="flex items-center gap-3">
                <label class="file-input-label flex-1" for="photoFileInput"><i data-lucide="upload" class="w-4 h-4"></i><span id="fileLabel">Choose image</span></label>
                <input type="file" id="photoFileInput" name="profile_photo" accept="image/*" onchange="previewPhoto(this)">
                <button type="submit" class="btn-save">Upload</button>
            </div>
        </form>
        <form method="POST">
            <input type="hidden" name="save_profile" value="1">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Full Name</label><input type="text" name="full_name" class="dark-input" value="<?= htmlspecialchars($userData['full_name']??'') ?>"></div>
                    <div><label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Register No.</label><input type="text" name="reg_no" class="dark-input" value="<?= htmlspecialchars($userData['reg_no']??'') ?>"></div>
                </div>
                <div><label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Department</label>
                <select name="department" class="dark-input">
                    <option value="">— Select —</option>
                    <?php foreach(['BCA','Information Technology','BCom','BBA','MBA','MCA','Other'] as $d): ?>
                    <option value="<?= $d ?>" <?= ($userData['department']??'')===$d?'selected':'' ?>><?= $d ?></option>
                    <?php endforeach; ?>
                </select></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">DOB</label><input type="date" name="dob" class="dark-input" value="<?= htmlspecialchars($userData['dob']??'') ?>"></div>
                    <div><label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Phone</label><input type="text" name="phone" class="dark-input" value="<?= htmlspecialchars($userData['phone']??'') ?>"></div>
                </div>
                <div><label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Address</label><textarea name="address" rows="3" class="dark-input resize-none"><?= htmlspecialchars($userData['address']??'') ?></textarea></div>
            </div>
            <div class="flex gap-3 mt-5">
                <button type="submit" class="btn-save flex-1">Save Changes</button>
                <button type="button" class="btn-cancel" onclick="switchProfileTab('view')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ══ HEADER ════════════════════════════════════════════════════════════════ -->
<header style="background:var(--card);border-bottom:1px solid var(--border)" class="sticky top-0 z-50 px-6 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div style="background:#238636" class="p-2 rounded-lg"><i data-lucide="book-open" class="w-5 h-5 text-white"></i></div>
        <span class="font-bold text-white text-lg">LIBRITE</span>
    </div>
    <div class="flex items-center gap-3">
        <?php $pendingCount = count(array_filter($myRequests, fn($r)=>$r['status']==='Pending')); ?>
        <?php if ($pendingCount > 0): ?>
        <span class="text-xs px-2 py-1 rounded-full" style="background:rgba(227,179,65,.2);color:#e3b341;border:1px solid rgba(227,179,65,.3)">
            <i data-lucide="clock" class="inline w-3 h-3"></i> <?= $pendingCount ?> pending
        </span>
        <?php endif; ?>
        <?php if ($totalFineAmt > 0): ?>
        <button onclick="switchTab('fines')" class="text-xs px-2 py-1 rounded-full" style="background:rgba(248,81,73,.2);color:#f85149;border:1px solid rgba(248,81,73,.3)">
            <i data-lucide="alert-triangle" class="inline w-3 h-3"></i> ₹<?= $totalFineAmt ?> fine
        </button>
        <?php endif; ?>
        <div class="header-avatar" onclick="openProfile()">
            <?php if ($profilePhoto && file_exists($profilePhoto)): ?>
                <img src="<?= htmlspecialchars($profilePhoto) ?>" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
                <?= strtoupper(substr($userData['full_name']?:$username, 0, 1)) ?>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- ══ MAIN ══════════════════════════════════════════════════════════════════ -->
<div class="max-w-6xl mx-auto px-4 py-8">

<div class="mb-8 pb-6" style="border-bottom:1px solid var(--border)">
    <h1 class="text-2xl font-bold text-white">Welcome back, <?= htmlspecialchars($userData['full_name']?:$username) ?> 👋</h1>
    <p class="italic mt-1 text-sm" style="color:var(--muted)">"A reader lives a thousand lives before he dies."</p>
    <div class="flex gap-3 mt-3 flex-wrap">
        <div class="text-xs px-3 py-1.5 rounded" style="background:rgba(227,179,65,.1);border-left:3px solid var(--warn)">📅 Loan limit: <strong>4 days</strong></div>
        <div class="text-xs px-3 py-1.5 rounded" style="background:rgba(248,81,73,.1);border-left:3px solid var(--err)">💰 Fine: ₹20 after due date, +₹10 per 2 extra days</div>
    </div>
</div>

<!-- TABS -->
<div class="flex gap-4 mb-6 border-b overflow-x-auto" style="border-color:var(--border)">
    <button onclick="switchTab('browse')"   id="tab-browse"   class="tab-btn pb-3 text-sm font-medium tab-active whitespace-nowrap">📚 Browse Books</button>
    <button onclick="switchTab('requests')" id="tab-requests" class="tab-btn pb-3 text-sm font-medium tab-inactive whitespace-nowrap">🗂 My Requests (<?= count($myRequests) ?>)</button>
    <button onclick="switchTab('fines')"    id="tab-fines"    class="tab-btn pb-3 text-sm font-medium tab-inactive whitespace-nowrap">
        💳 Fine<?php if($totalFineAmt>0): ?> <span class="text-red-400 font-bold">₹<?= $totalFineAmt ?></span><?php endif; ?>
    </button>
    <button onclick="switchTab('purchase')" id="tab-purchase" class="tab-btn pb-3 text-sm font-medium tab-inactive whitespace-nowrap">📋 Request a Book</button>
</div>

<!-- ══ BROWSE PANE ══════════════════════════════════════════════════════════ -->
<div id="pane-browse">
    <?php if ($requestMsg === 'success'): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium" style="background:rgba(35,134,54,.15);border:1px solid rgba(63,185,80,.3);color:#3fb950">✅ Request submitted! Admin will review shortly.</div>
    <?php elseif ($requestMsg === 'already_requested'): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium" style="background:rgba(248,81,73,.1);border:1px solid rgba(248,81,73,.3);color:#f85149">⚠️ You already have an active request for that book.</div>
    <?php elseif ($requestMsg === 'no_copies'): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium" style="background:rgba(248,81,73,.1);border:1px solid rgba(248,81,73,.3);color:#f85149">⚠️ No copies available right now. Try requesting a purchase below!</div>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row gap-3 mb-5">
        <div class="relative flex-1">
            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color:var(--muted)"></i>
            <input type="text" id="searchInput" onkeyup="filterBooks()" placeholder="Search title, author, ISBN..." class="dark-input pl-9 py-2.5">
        </div>
        <select id="statusFilter" onchange="filterBooks()" class="dark-input" style="width:auto"><option value="All">All Status</option><option>Available</option><option>Borrowed</option><option>Lost</option></select>
        <select id="catFilter" onchange="filterBooks()" class="dark-input" style="width:auto"><option value="All">All Categories</option><option>Fiction</option><option>Non-Fiction</option><option>Reference</option><option>Academic</option></select>
    </div>

    <?php if (empty($books)): ?>
    <div class="card p-12 text-center" style="color:var(--muted)"><i data-lucide="book" class="w-10 h-10 mx-auto mb-3 opacity-30"></i><p>No books yet.</p></div>
    <?php else:
    $requestedBookIds = array_column(array_filter($myRequests, fn($r)=>in_array($r['status'],['Pending','Approved'])), 'book_id');
    ?>
    <div class="books-grid" id="booksGrid">
    <?php foreach ($books as $b):
        $alreadyReq  = in_array($b['id'], $requestedBookIds);
        $copies      = (int)($b['copies'] ?? 0);
        $canRequest  = $copies > 0 && !$alreadyReq && $b['status'] === 'Available';
        if ($alreadyReq)      { $btnClass='done'; $btnLabel='✓ Requested'; }
        elseif ($canRequest)  { $btnClass='can';  $btnLabel='Request'; }
        else                  { $btnClass='none'; $btnLabel='Unavailable'; }
    ?>
    <div class="book-card" data-title="<?= strtolower(htmlspecialchars($b['title'])) ?>" data-author="<?= strtolower(htmlspecialchars($b['author'])) ?>" data-isbn="<?= strtolower(htmlspecialchars($b['isbn']??'')) ?>" data-status="<?= htmlspecialchars($b['status']) ?>" data-cat="<?= htmlspecialchars($b['category']??'') ?>">
        <div class="book-card-img">
            <?php
            // Generate deterministic but varied cover color & seed based on book id
            $coverSeed = $b['id'] * 37 + crc32($b['title']) % 1000;
            $coverSeed = abs($coverSeed) % 1000;
            $coverUrl  = "https://picsum.photos/seed/book{$coverSeed}/300/200";
            $catColors = ['Fiction'=>'#7c3aed','Non-Fiction'=>'#0369a1','Academic'=>'#047857','Reference'=>'#b45309','default'=>'#1e3a5f'];
            $cat = $b['category'] ?? 'default';
            $bgColor = $catColors[$cat] ?? $catColors['default'];
            ?>
            <img src="<?= $coverUrl ?>" alt="<?= htmlspecialchars($b['title']) ?>"
                 loading="lazy"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"
                 style="width:100%;height:100%;object-fit:cover;">
            <div class="img-placeholder" style="display:none;background:linear-gradient(135deg,<?= $bgColor ?>88,<?= $bgColor ?>44)">
                <i data-lucide="book-open" class="w-8 h-8 opacity-40"></i>
                <span class="text-xs opacity-60 px-3 text-center leading-tight font-medium text-white"><?= htmlspecialchars(substr($b['title'],0,30)) ?></span>
            </div>
            <span class="copies-pill"><?= $copies ?> copies</span>
            <span style="position:absolute;bottom:8px;left:8px" class="badge badge-<?= strtolower($b['status']) ?>"><?= $b['status'] ?></span>
        </div>
        <div class="book-card-body">
            <div class="book-card-title"><?= htmlspecialchars($b['title']) ?></div>
            <div class="book-card-author"><?= htmlspecialchars($b['author']) ?></div>
            <div class="text-xs mt-1" style="color:var(--muted)">📍 <?= htmlspecialchars($b['shelf']??'—') ?> · <span style="color:var(--blue)"><?= htmlspecialchars($b['category']??'') ?></span></div>
            <?php if (!empty($b['description'])): ?>
            <div class="text-xs mt-2 leading-relaxed" style="color:var(--muted);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= htmlspecialchars($b['description']) ?></div>
            <?php endif; ?>
        </div>
        <div class="book-card-footer">
            <?php if ($alreadyReq): ?>
                <button class="btn-req-card done" disabled>✓ Requested</button>
            <?php else: ?>
                <form method="POST"><input type="hidden" name="request_book_id" value="<?= $b['id'] ?>">
                <button type="submit" class="btn-req-card <?= $btnClass ?>" <?= !$canRequest?'disabled':'' ?>><?= $btnLabel ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <div class="mt-4 text-sm" style="color:var(--muted)"><span id="recordCount">Showing <?= count($books) ?> books</span></div>
    <?php endif; ?>
</div>

<!-- ══ MY REQUESTS PANE ══════════════════════════════════════════════════════ -->
<div id="pane-requests" class="hidden">
    <?php if (empty($myRequests)): ?>
    <div class="card p-12 text-center" style="color:var(--muted)"><i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 opacity-30"></i><p>No requests yet.</p></div>
    <?php else: ?>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr><th class="text-left">Book</th><th class="text-left">Requested</th><th class="text-left">Status</th><th class="text-left">Due Date</th><th class="text-left">Fine</th></tr></thead>
                <tbody>
                <?php foreach ($myRequests as $req):
                    $isOverdue = $req['status']==='Approved' && $req['due_date'] && !$req['returned_at'] && strtotime($req['due_date'])<time();
                ?>
                <tr>
                    <td><div class="font-semibold text-white"><?= htmlspecialchars($req['title']) ?></div><div class="text-xs" style="color:var(--muted)"><?= htmlspecialchars($req['author']) ?></div></td>
                    <td class="text-sm" style="color:var(--muted)"><?= date('d M Y', strtotime($req['requested_at'])) ?></td>
                    <td><span class="badge badge-<?= strtolower($req['status']) ?>"><?= $req['status'] ?></span></td>
                    <td class="text-sm <?= $isOverdue?'text-red-400 font-bold':'' ?>" style="<?= !$isOverdue?'color:var(--muted)':'' ?>"><?= $req['due_date']?date('d M Y',strtotime($req['due_date'])):'—' ?><?php if($isOverdue):?> ⚠️<?php endif;?></td>
                    <td><?php if(($req['fine_amount']??0)>0):?><span class="text-red-400 font-bold">₹<?= number_format($req['fine_amount'],2) ?></span><?php else:?><span style="color:var(--muted)">—</span><?php endif;?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ══ FINE MANAGEMENT PANE ══════════════════════════════════════════════════ -->
<div id="pane-fines" class="hidden">
    <div class="fine-bar mb-6 flex items-center justify-between flex-wrap gap-4">
        <div>
            <div class="text-xs uppercase tracking-widest mb-1" style="color:var(--muted)">Total Fine Due</div>
            <div class="text-3xl font-bold text-red-400">₹<?= number_format($totalFineAmt, 2) ?></div>
            <?php if ($totalFineAmt == 0): ?><div class="text-emerald-400 text-sm font-semibold mt-1">✅ No pending fines!</div><?php endif; ?>
        </div>
        <?php if ($totalFineAmt > 0): ?>
        <div class="flex gap-2">
            <button onclick="setPayTab('qr')"  id="ptab-qr"  class="pay-method-btn active">📷 QR Code</button>
            <button onclick="setPayTab('upi')" id="ptab-upi" class="pay-method-btn inactive">📱 UPI ID</button>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($totalFineAmt > 0): ?>
    <div class="grid md:grid-cols-2 gap-6 mb-6">

        <!-- QR Scan -->
        <div id="ppay-qr" class="card p-6">
            <h3 class="font-bold text-white mb-1 flex items-center gap-2"><i data-lucide="qr-code" class="w-5 h-5 text-emerald-400"></i> Scan & Pay</h3>
            <p class="text-xs mb-5" style="color:var(--muted)">Open PhonePe / GPay / Paytm and scan to pay</p>
            <div class="flex justify-center mb-4">
                <?php if (file_exists($QR_IMAGE)): ?>
                    <!-- Clicking QR opens UPI deep link -->
                    <a href="upi://pay?pa=<?= urlencode($UPI_ID) ?>&pn=<?= urlencode($UPI_NAME) ?>&am=<?= $totalFineAmt ?>&cu=INR&tn=LibraryFine">
                        <div class="qr-wrap cursor-pointer hover:opacity-90 transition-opacity">
                            <img src="<?= htmlspecialchars($QR_IMAGE) ?>" alt="Scan to Pay">
                        </div>
                    </a>
                <?php else: ?>
                    <!-- Auto-generate QR using Google Charts API -->
                    <?php $upiLink = "upi://pay?pa=".urlencode($UPI_ID)."&pn=".urlencode($UPI_NAME)."&am=".$totalFineAmt."&cu=INR&tn=LibraryFine"; ?>
                    <a href="<?= htmlspecialchars($upiLink) ?>">
                        <div class="qr-wrap cursor-pointer">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($upiLink) ?>" alt="Scan to Pay" style="width:200px;height:200px">
                        </div>
                    </a>
                <?php endif; ?>
            </div>
            <div class="text-center text-xs mb-4" style="color:var(--muted)">
                Pay <strong class="text-red-400">₹<?= number_format($totalFineAmt, 2) ?></strong> to <strong class="text-white"><?= htmlspecialchars($UPI_NAME) ?></strong><br>
                <span style="color:var(--blue)">Tap the QR on mobile to open payment app directly</span>
            </div>
            <!-- Submit payment proof -->
            <?php
            $fineRows = array_filter($myRequests, fn($r) => ($r['fine_amount']??0)>0 && !$r['returned_at']);
            $firstFine = reset($fineRows);
            ?>
            <?php if ($firstFine): ?>
            <div style="border-top:1px solid var(--border);padding-top:14px;margin-top:4px">
                <p class="text-xs font-semibold mb-3" style="color:var(--muted)">AFTER PAYMENT — Submit proof</p>
                <form method="POST" enctype="multipart/form-data" class="space-y-3">
                    <input type="hidden" name="submit_payment" value="1">
                    <input type="hidden" name="pay_request_id" value="<?= $firstFine['id'] ?>">
                    <input type="hidden" name="pay_amount" value="<?= $totalFineAmt ?>">
                    <input type="text" name="upi_ref" class="dark-input" placeholder="UPI Transaction ID / Reference (optional)">
                    <label class="file-input-label" for="payScreenshot"><i data-lucide="upload" class="w-4 h-4"></i> Upload Screenshot (optional)</label>
                    <input type="file" id="payScreenshot" name="pay_screenshot" accept="image/*">
                    <button type="submit" class="btn-save w-full">✅ Submit Payment for Verification</button>
                </form>
                <?php if (in_array($firstFine['id'], $pendingPayIds)): ?>
                <div class="mt-3 text-center text-xs px-3 py-2 rounded-lg" style="background:rgba(88,166,255,.1);border:1px solid rgba(88,166,255,.3);color:#58a6ff">
                    ⏳ Payment verification pending — admin will confirm shortly
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- UPI Manual -->
        <div id="ppay-upi" class="card p-6 hidden">
            <h3 class="font-bold text-white mb-1 flex items-center gap-2"><i data-lucide="smartphone" class="w-5 h-5 text-blue-400"></i> Pay via UPI ID</h3>
            <p class="text-xs mb-4" style="color:var(--muted)">Copy & paste in any payment app</p>
            <div class="flex items-center justify-between p-3 rounded-lg mb-5" style="background:var(--card2);border:1px solid var(--border)">
                <div><div class="text-xs mb-0.5" style="color:var(--muted)">UPI ID</div><div class="text-white font-mono font-semibold" id="upiIdText"><?= htmlspecialchars($UPI_ID) ?></div></div>
                <button onclick="copyUPI()" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white" style="background:var(--blue)">Copy</button>
            </div>
            <?php $steps=["Open PhonePe, Google Pay or any UPI app","Tap 'Send Money' → 'Pay via UPI ID'","Paste UPI: <strong class='text-white'>".htmlspecialchars($UPI_ID)."</strong>","Enter amount <strong class='text-red-400'>₹".number_format($totalFineAmt,2)."</strong> and pay","Submit screenshot below to notify admin"];
            foreach ($steps as $i=>$s): ?>
            <div class="flex items-start gap-3 mb-3"><div class="step-badge"><?= $i+1 ?></div><p class="text-sm" style="color:var(--muted)"><?= $s ?></p></div>
            <?php endforeach; ?>
            <a href="upi://pay?pa=<?= urlencode($UPI_ID) ?>&pn=<?= urlencode($UPI_NAME) ?>&am=<?= $totalFineAmt ?>&cu=INR&tn=LibraryFine"
               class="flex items-center justify-center gap-2 mt-4 p-3 rounded-lg font-bold text-white text-sm" style="background:#238636;text-decoration:none">
                <i data-lucide="external-link" class="w-4 h-4"></i> Open UPI App — Pay ₹<?= number_format($totalFineAmt, 2) ?>
            </a>
        </div>

        <!-- Fine breakdown table -->
        <div class="card overflow-hidden md:col-span-2">
            <div class="px-5 py-4 border-b flex items-center gap-2" style="border-color:var(--border)"><i data-lucide="list" class="w-4 h-4 text-red-400"></i><h3 class="font-bold text-white">Fine Breakdown</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead><tr><th class="text-left">Book</th><th class="text-left">Due Date</th><th class="text-left">Days Late</th><th class="text-left">Fine</th><th class="text-left">Payment</th></tr></thead>
                    <tbody>
                    <?php foreach (array_filter($myRequests, fn($r)=>($r['fine_amount']??0)>0) as $fr):
                        $due = new DateTime($fr['due_date']); $overdue = (int)$today->diff($due)->days;
                        $isPaid = in_array($fr['id'], $pendingPayIds);
                    ?>
                    <tr>
                        <td><div class="font-semibold text-white"><?= htmlspecialchars($fr['title']) ?></div></td>
                        <td class="text-sm text-red-400 font-semibold"><?= date('d M Y', strtotime($fr['due_date'])) ?></td>
                        <td class="text-sm" style="color:var(--warn)"><?= $overdue ?> days</td>
                        <td class="font-bold text-red-400">₹<?= number_format($fr['fine_amount'],2) ?></td>
                        <td><?php if($isPaid):?><span class="badge" style="background:rgba(88,166,255,.1);color:#58a6ff;border:1px solid rgba(88,166,255,.3)">⏳ Pending</span><?php else:?><span class="badge" style="background:rgba(248,81,73,.1);color:#f85149;border:1px solid rgba(248,81,73,.3)">Unpaid</span><?php endif;?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 text-xs" style="color:var(--muted);border-top:1px solid var(--border)">💡 After paying, submit your screenshot above. Admin will verify and clear your fine.</div>
        </div>
    </div>
    <?php else: ?>
    <div class="card p-12 text-center">
        <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background:rgba(35,134,54,.2);border:1px solid rgba(63,185,80,.3)">
            <i data-lucide="check-circle-2" class="w-8 h-8 text-emerald-400"></i>
        </div>
        <p class="text-white font-bold text-lg">All Clear!</p>
        <p class="text-sm mt-1" style="color:var(--muted)">No outstanding fines. Keep returning books on time! 🎉</p>
    </div>
    <?php endif; ?>
</div>


<!-- ══ BOOK PURCHASE REQUEST PANE ══════════════════════════════════════════════ -->
<div id="pane-purchase" class="hidden">
    <div class="grid md:grid-cols-2 gap-6">

        <!-- Request Form -->
        <div class="card p-6">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:rgba(88,166,255,.1)"><i data-lucide="package-plus" class="w-5 h-5" style="color:#58a6ff"></i></div>
                <div><h3 class="font-bold text-white">Request a New Book</h3><p class="text-xs" style="color:var(--muted)">Can't find a book? Ask the library to order it!</p></div>
            </div>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Book Title <span class="text-red-400">*</span></label>
                    <input type="text" name="purchase_title" class="dark-input" placeholder="e.g. Atomic Habits" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Author (optional)</label>
                    <input type="text" name="purchase_author" class="dark-input" placeholder="e.g. James Clear">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Why do you need this book?</label>
                    <textarea name="purchase_reason" rows="3" class="dark-input resize-none" placeholder="e.g. Required for semester project, personal interest..."></textarea>
                </div>
                <button type="submit" class="btn-save w-full flex items-center justify-center gap-2">
                    <i data-lucide="send" class="w-4 h-4"></i> Send Request to Admin
                </button>
            </form>
        </div>

        <!-- My previous requests -->
        <div>
            <h3 class="font-bold text-white mb-3 flex items-center gap-2"><i data-lucide="history" class="w-4 h-4" style="color:var(--blue)"></i> My Purchase Requests</h3>
            <?php
            $myPurchaseReqs = $pdo->prepare("SELECT * FROM book_purchase_requests WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
            $myPurchaseReqs->execute([$user_id]);
            $myPurchaseReqs = $myPurchaseReqs->fetchAll();
            ?>
            <?php if (empty($myPurchaseReqs)): ?>
            <div class="card p-6 text-center" style="color:var(--muted)"><i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-30"></i><p class="text-sm">No requests yet. Use the form to request a book!</p></div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($myPurchaseReqs as $pr):
                    $sIcons=['Pending'=>'⏳','Reviewed'=>'👀','Ordered'=>'✅','Rejected'=>'❌'];
                    $sStyles=['Pending'=>'background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.3);color:#fbbf24','Reviewed'=>'background:rgba(96,165,250,.12);border:1px solid rgba(96,165,250,.3);color:#60a5fa','Ordered'=>'background:rgba(52,211,153,.12);border:1px solid rgba(52,211,153,.3);color:#34d399','Rejected'=>'background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.3);color:#f87171'];
                    $sStyle=$sStyles[$pr['status']]??$sStyles['Pending'];
                    $sIcon=$sIcons[$pr['status']]??'⏳';
                    $borderColor=$pr['status']=='Ordered'?'#34d399':($pr['status']=='Rejected'?'#f87171':($pr['status']=='Reviewed'?'#60a5fa':'#fbbf24'));
                ?>
                <div class="card p-4" style="border-left:3px solid <?= $borderColor ?>">
                    <div class="flex items-start justify-between gap-2 flex-wrap">
                        <div style="flex:1">
                            <div class="font-semibold text-white"><?= htmlspecialchars($pr['book_title']) ?></div>
                            <?php if ($pr['author']): ?><div class="text-xs mt-0.5" style="color:var(--muted)">by <?= htmlspecialchars($pr['author']) ?></div><?php endif; ?>
                            <div class="text-xs mt-1" style="color:var(--muted)">Requested on <?= date('d M Y', strtotime($pr['created_at'])) ?></div>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full whitespace-nowrap" style="<?= $sStyle ?>"><?= $sIcon ?> <?= $pr['status'] ?></span>
                    </div>
                    <?php if ($pr['reason']): ?>
                    <div class="mt-2 text-xs px-3 py-2 rounded-lg" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);color:var(--muted)">
                        Your reason: <?= htmlspecialchars($pr['reason']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($pr['admin_note']): ?>
                    <div class="mt-2 px-3 py-2 rounded-lg" style="background:rgba(34,211,238,.08);border:1px solid rgba(34,211,238,.25)">
                        <div class="text-xs font-bold mb-1" style="color:#22d3ee">📩 Admin Response:</div>
                        <div class="text-sm" style="color:rgba(255,255,255,.85)"><?= htmlspecialchars($pr['admin_note']) ?></div>
                    </div>
                    <?php else: ?>
                    <div class="mt-2 text-xs px-3 py-2 rounded-lg" style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);color:var(--muted)">⏳ Waiting for admin response...</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</div><!-- /container -->

<!-- AI Chat Script -->
<!-- ══ FLOATING AI CHATBOT ═══════════════════════════════════════════════════ -->
<!-- FAB button -->
<div id="ai-fab" onclick="toggleAIChat()" title="LIBRITE AI Assistant">
    🤖
    <div class="notif-dot"></div>
</div>

<!-- Chat box -->
<div id="ai-chat-box">
    <div class="ai-chat-header">
        <div class="flex items-center gap-3">
            <div class="ai-chat-avatar">🤖</div>
            <div>
<<<<<<< HEAD
                <div class="ai-chat-title"><b>LIBRITE AI</b></div>
=======
                <div class="ai-chat-title">LIBRITE AI</div>
>>>>>>> 25d499b07ce8f47dc503da4dec55cd9fdf5ab226
                <div class="ai-chat-status">Online · Ready to help</div>
            </div>
        </div>
        <button class="ai-close-btn" onclick="toggleAIChat()">✕</button>
    </div>

    <div class="ai-messages" id="aiMessages">
        <div class="ai-msg-bot">👋 Hi <?= htmlspecialchars($userData['full_name']?:$username) ?>! I'm LIBRITE AI.<br><br>I can help you:<br>📚 Find & describe books<br>✨ Suggest reads for you<br>💳 Explain fines & rules<br><br>Ask me anything!</div>
    </div>

    <div class="ai-quick-pills" id="aiQuickPills">
        <span class="ai-pill" onclick="aiQuick('Suggest books for me')">✨ Suggest books</span>
        <span class="ai-pill" onclick="aiQuick('What genres do you have?')">📂 Genres</span>
        <span class="ai-pill" onclick="aiQuick('Explain the fine system')">💰 Fines</span>
        <span class="ai-pill" onclick="aiQuick('How long can I borrow?')">📅 Loan rules</span>
    </div>

    <div class="ai-input-row">
        <input type="text" class="ai-text-input" id="aiFloatInput"
               placeholder="Ask about books, fines, rules..."
               onkeydown="if(event.key==='Enter')sendFloatAI()">
        <button class="ai-send-btn" onclick="sendFloatAI()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
        </button>
    </div>
</div>

<script>
lucide.createIcons();

const BOOKS_DATA = <?= json_encode(array_map(fn($b)=>[
    'id'         => $b['id'],
    'title'      => $b['title'],
    'author'     => $b['author'],
    'category'   => $b['category'] ?? '',
    'status'     => $b['status'],
    'copies'     => (int)($b['copies'] ?? 0),
    'shelf'      => $b['shelf'] ?? '',
    'description'=> $b['description'] ?? '',
    'isbn'       => $b['isbn'] ?? '',
], $books)) ?>;
const USER_CATS = <?= json_encode($lastCats) ?>;
const USER_NAME = "<?= htmlspecialchars($userData['full_name']?:$username) ?>";

// ── Floating AI Chat ──────────────────────────────────────────────────────────
let aiOpen = false;
let aiFirstOpen = true;

function toggleAIChat() {
    aiOpen = !aiOpen;
    const box = document.getElementById('ai-chat-box');
    const fab = document.getElementById('ai-fab');
    box.classList.toggle('open', aiOpen);
    fab.classList.remove('has-notif');
    if (aiOpen && aiFirstOpen) {
        aiFirstOpen = false;
        document.getElementById('aiFloatInput').focus();
    }
}

function aiQuick(msg) {
    document.getElementById('aiFloatInput').value = msg;
    sendFloatAI();
    document.getElementById('aiQuickPills').style.display = 'none';
}

function sendFloatAI() {
    const input = document.getElementById('aiFloatInput');
    const msg   = input.value.trim();
    if (!msg) return;
    appendAIMsg(msg, 'user');
    input.value = '';
    const typing = appendAITyping();
    setTimeout(() => {
        typing.remove();
        const reply = getAIReply(msg);
        if (typeof reply === 'string') {
            appendAIMsg(reply, 'bot');
        } else {
            // Array of {text, books}
            appendAIMsg(reply.text, 'bot');
            if (reply.books && reply.books.length) {
                reply.books.forEach(b => appendAIBookCard(b));
            }
        }
    }, 700 + Math.random() * 400);
}

function appendAIMsg(text, type) {
    const box = document.getElementById('aiMessages');
    const el  = document.createElement('div');
    el.className = type === 'user' ? 'ai-msg-user' : 'ai-msg-bot';
    el.style.whiteSpace = 'pre-line';
    el.textContent = text;
    box.appendChild(el);
    box.scrollTop = box.scrollHeight;
    return el;
}

function appendAITyping() {
    const box = document.getElementById('aiMessages');
    const el  = document.createElement('div');
    el.className = 'ai-typing-indicator';
    el.innerHTML = '<span></span><span></span><span></span>';
    box.appendChild(el);
    box.scrollTop = box.scrollHeight;
    return el;
}

function appendAIBookCard(book) {
    const box = document.getElementById('aiMessages');
    const el  = document.createElement('div');
    el.className = 'ai-book-card';
    const coverSeed = (book.id * 37) % 1000;
    const desc = book.description ? book.description.substring(0, 100) + (book.description.length > 100 ? '…' : '') : '';
    el.innerHTML = \`
        <div style="display:flex;gap:10px;align-items:flex-start">
            <img src="https://picsum.photos/seed/book\${coverSeed}/50/70"
                 style="width:40px;height:55px;object-fit:cover;border-radius:5px;flex-shrink:0"
                 onerror="this.style.display='none'">
            <div style="flex:1;min-width:0">
                <div class="ai-book-card-title">\${book.title}</div>
                <div class="ai-book-card-meta">by \${book.author} · \${book.category} · \${book.copies} cop\${book.copies!==1?'ies':'y'} · Shelf: \${book.shelf||'—'}</div>
                \${desc ? \`<div class="ai-book-desc">\${desc}</div>\` : ''}
                <div style="margin-top:6px">
                    <span class="ai-pill" style="font-size:.65rem;padding:2px 8px" onclick="document.getElementById('searchInput').value='\${book.title}';switchTab('browse');filterBooks()">🔍 Find in library</span>
                </div>
            </div>
        </div>
    \`;
    box.appendChild(el);
    box.scrollTop = box.scrollHeight;
}

function getAIReply(msg) {
    const q = msg.toLowerCase();

    // ── Book description request ──────────────────────────────────────────────
    if (q.includes('describe') || q.includes('about the book') || q.includes('tell me about') || q.includes('what is') || q.includes('summary')) {
        // Try to match a book title in the query
        const matched = BOOKS_DATA.filter(b => q.includes(b.title.toLowerCase()));
        if (matched.length > 0) {
            const b = matched[0];
            const desc = b.description || 'No description available in our records.';
            return { text: `📖 Here's about "${b.title}" by ${b.author}:

${desc}

Category: ${b.category} | Shelf: ${b.shelf||'—'} | Status: ${b.status}`, books: [] };
        }
        // No match — search for partial
        const words = q.split(' ').filter(w => w.length > 3);
        const partial = BOOKS_DATA.filter(b => words.some(w => b.title.toLowerCase().includes(w)));
        if (partial.length > 0) {
            return { text: `I found ${partial.length} book(s) that might match. Here's what I know:`, books: partial.slice(0,3) };
        }
        return "I couldn't find that book. Try searching in the Browse Books tab, or tell me the exact title!";
    }

    // ── Suggest / Recommend ───────────────────────────────────────────────────
    if (q.includes('suggest') || q.includes('recommend') || q.includes('what should i read') || q.includes('good book')) {
        const avail = BOOKS_DATA.filter(b => b.copies > 0 && b.status === 'Available');
        if (USER_CATS.length > 0) {
            const matched = avail.filter(b => USER_CATS.includes(b.category));
            const picks   = matched.length >= 2 ? matched : avail;
            const chosen  = picks.sort(() => Math.random() - .5).slice(0, 3);
            return {
                text: USER_CATS.length
                    ? `Based on your interest in ${USER_CATS.join(', ')}, here are ${chosen.length} picks for you ✨`
                    : `Here are some great books available right now ✨`,
                books: chosen
            };
        }
        const picks = avail.sort(() => Math.random() - .5).slice(0, 3);
        return picks.length
            ? { text: `Here are some books available right now ✨`, books: picks }
            : "No books are available right now. Check back soon!";
    }

    // ── Search by author ──────────────────────────────────────────────────────
    if (q.includes('by ') || q.includes('author') || q.includes('written by')) {
        const byMatch = q.match(/by\s+(.+)/);
        const searchName = byMatch ? byMatch[1].replace(/[?.!]/g,'').trim() : '';
        if (searchName.length > 2) {
            const found = BOOKS_DATA.filter(b => b.author.toLowerCase().includes(searchName));
            if (found.length > 0) {
                return { text: `Found ${found.length} book(s) by "${searchName}":`, books: found.slice(0,4) };
            }
            return `I couldn't find books by "${searchName}" in our library. Try the search bar in Browse Books!`;
        }
        return "Who's the author you're looking for? Try: "books by Chetan Bhagat"";
    }

    // ── Genre / Category ──────────────────────────────────────────────────────
    if (q.includes('genre') || q.includes('categor') || q.includes('fiction') || q.includes('academic') || q.includes('reference') || q.includes('non-fiction')) {
        const cats = [...new Set(BOOKS_DATA.map(b => b.category).filter(Boolean))];
        let targetCat = null;
        if (q.includes('fiction') && !q.includes('non')) targetCat = 'Fiction';
        else if (q.includes('non-fiction') || q.includes('nonfiction')) targetCat = 'Non-Fiction';
        else if (q.includes('academic')) targetCat = 'Academic';
        else if (q.includes('reference')) targetCat = 'Reference';

        if (targetCat) {
            const catBooks = BOOKS_DATA.filter(b => b.category === targetCat && b.copies > 0).slice(0, 3);
            return catBooks.length
                ? { text: `Here are some ${targetCat} books available:`, books: catBooks }
                : `No ${targetCat} books available right now.`;
        }
        return `We have books in: ${cats.join(', ')}.

Just ask me like: "show me Fiction books" or "suggest Academic books"!`;
    }

    // ── Fine system ───────────────────────────────────────────────────────────
    if (q.includes('fine') || q.includes('overdue') || q.includes('penalty') || q.includes('late')) {
        return "💰 Fine System:

• Loan period: 4 days
• Fine: ₹10 per day after due date
• Example: 5 days late = ₹50 fine

To pay: Go to the 💳 Fine tab → scan QR or copy UPI ID → submit payment screenshot for admin verification.";
    }

    // ── Loan rules ────────────────────────────────────────────────────────────
    if (q.includes('borrow') || q.includes('loan') || q.includes('how long') || q.includes('days') || q.includes('rules')) {
        return "📅 Borrowing Rules:

• Max loan period: 4 days
• Return on time to avoid ₹10/day fine
• Request books from Browse Books tab
• Admin approves within 1-2 days
• Collect from library counter once approved";
    }

    // ── Available books count ─────────────────────────────────────────────────
    if (q.includes('how many') || q.includes('total books') || q.includes('available')) {
        const avail  = BOOKS_DATA.filter(b => b.copies > 0 && b.status === 'Available').length;
        const total  = BOOKS_DATA.length;
        return `📚 Library Stats:

• Total books: ${total}
• Currently available: ${avail}
• Borrowed/unavailable: ${total - avail}

Browse all books in the Browse Books tab!`;
    }

    // ── Popular / trending ────────────────────────────────────────────────────
    if (q.includes('popular') || q.includes('trending') || q.includes('best')) {
        const avail = BOOKS_DATA.filter(b => b.copies > 0 && b.status === 'Available');
        const picks = avail.sort(() => Math.random() - .5).slice(0, 3);
        return picks.length
            ? { text: `Here are some popular picks from our collection 🔥`, books: picks }
            : "No books available right now!";
    }

    // ── Greeting ──────────────────────────────────────────────────────────────
    if (q.includes('hi') || q.includes('hello') || q.includes('hey') || q.includes('hii')) {
        return `Hello ${USER_NAME}! 👋 I'm LIBRITE AI.

I can help you:
📚 Suggest & describe books
🔍 Search by author or genre
💰 Explain fines & loan rules

What are you looking for today?`;
    }

    // ── Thank you ──────────────────────────────────────────────────────────────
    if (q.includes('thank') || q.includes('thanks')) {
        return "You're welcome! 😊 Happy reading! Come back anytime you need help finding a book.";
    }

    // ── Default ───────────────────────────────────────────────────────────────
    return `I didn't quite get that 🤔

Try asking:
• "Suggest books for me"
• "Describe Atomic Habits"
• "Books by Shakespeare"
• "Show me Fiction books"
• "How does the fine system work?"`;
}

<?php if(empty($userData['full_name'])): ?>setTimeout(()=>{openProfile();switchProfileTab('edit');},700);<?php endif; ?>
</script>
</body>
</html>