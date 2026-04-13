<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php"); exit();
}

// ── Handle Purchase Request Response ─────────────────────────────────────────
$toast = ''; $toastType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'update_purchase') {
        $pr_id  = (int)$_POST['pr_id'];
        $status = $_POST['pr_status'] ?? 'Pending';
        $note   = trim($_POST['admin_note'] ?? '');
        $pdo->prepare("UPDATE book_purchase_requests SET status=?, admin_note=? WHERE id=?")
            ->execute([$status, $note, $pr_id]);
        $toast = "Purchase request updated!";
    }
    header("Location: admin_dashboard.php?toast=".urlencode($toast)); exit();
}
if (isset($_GET['toast'])) $toast = $_GET['toast'];

// ── Live stats ────────────────────────────────────────────────────────────────
$totalBooks   = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$totalMembers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalFines   = $pdo->query("SELECT COALESCE(SUM(fine_amount),0) FROM book_requests WHERE fine_amount > 0")->fetchColumn();
$pendingReqs  = $pdo->query("SELECT COUNT(*) FROM book_requests WHERE status='Pending'")->fetchColumn();
$activeLoans  = $pdo->query("SELECT COUNT(*) FROM book_requests WHERE status='Approved'")->fetchColumn();

// ── Purchase requests ─────────────────────────────────────────────────────────
$purchaseRequests = $pdo->query("
    SELECT bpr.*, u.username, u.full_name
    FROM book_purchase_requests bpr
    JOIN users u ON u.id = bpr.user_id
    ORDER BY bpr.created_at DESC
")->fetchAll();
$pendingPurchaseCount = count(array_filter($purchaseRequests, fn($p) => $p['status'] === 'Pending'));

// ── Fine payments pending ─────────────────────────────────────────────────────
$pendingPayments = $pdo->query("SELECT COUNT(*) FROM fine_payments WHERE status='Pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LIBRITE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;600&display=swap');
        :root { --primary-accent:#22d3ee; --deep-onyx:#02040a; --glass:rgba(255,255,255,.03); --glass-border:rgba(255,255,255,.1); }
        *{ margin:0; padding:0; box-sizing:border-box; }
        body,html{ background-color:var(--deep-onyx); font-family:'Inter',sans-serif; color:white; }
        .dashboard{ display:flex; min-height:100vh; }

        /* Sidebar */
        .hamburger{ background:none; border:none; color:white; font-size:1.5rem; cursor:pointer; margin-right:15px; z-index:1001; display:flex; flex-direction:column; justify-content:center; height:40px; width:40px; border-radius:8px; transition:background .3s; }
        .hamburger:hover{ background:rgba(255,255,255,.08); }
        .hamburger span{ display:block; width:22px; height:3px; background:var(--primary-accent); margin:3px 0; border-radius:3px; transition:all .3s ease; }
        .hamburger.open span:nth-child(1){ transform:translateY(6px) rotate(45deg); }
        .hamburger.open span:nth-child(2){ opacity:0; }
        .hamburger.open span:nth-child(3){ transform:translateY(-6px) rotate(-45deg); }
        .sidebar{ position:fixed; top:0; left:0; height:100vh; width:250px; background:rgba(0,0,0,.4); padding:2rem 1rem; border-right:1px solid var(--glass-border); transform:translateX(0); transition:transform .3s ease,box-shadow .3s; z-index:1000; box-shadow:2px 0 15px rgba(0,0,0,.3); }
        .sidebar.collapsed{ transform:translateX(-100%); box-shadow:none; }
        .logo{ font-family:'Playfair Display',serif; font-size:1.4rem; color:white; text-align:center; margin-bottom:2.5rem; display:flex; align-items:center; justify-content:center; gap:10px; }
        .logo-dot{ height:40px; width:40px; border-radius:50%; background:var(--primary-accent); display:inline-block; }
        .nav-item{ display:block; padding:.8rem 1.2rem; margin:.3rem 0; text-decoration:none; color:rgba(255,255,255,.8); border-radius:8px; transition:all .3s; white-space:nowrap; }
        .nav-item:hover,.nav-item.active{ background:var(--glass); color:var(--primary-accent); }
        .nav-item i{ margin-right:10px; width:20px; text-align:center; }

        /* Main */
        .main-content{ flex:1; padding:2rem; overflow-y:auto; transition:margin-left .3s ease; margin-left:250px; }
        .sidebar.collapsed + .main-content{ margin-left:0; }
        .header{ display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; }
        .welcome{ font-family:'Playfair Display',serif; font-size:1.8rem; }

        /* Stats */
        .stats-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1.5rem; margin-bottom:2.5rem; }
        .stat-card{ background:var(--glass); backdrop-filter:blur(10px); border:1px solid var(--glass-border); border-radius:16px; padding:1.5rem; text-align:center; }
        .stat-value{ font-size:2rem; font-weight:700; margin:.5rem 0; }
        .stat-label{ color:rgba(255,255,255,.7); font-size:.9rem; }
        .stat-icon{ font-size:1.4rem; margin-bottom:.4rem; }

        /* Actions */
        .actions-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1.2rem; }
        .action-btn{ display:block; padding:1.2rem; text-align:center; background:var(--glass); border:1px solid var(--glass-border); border-radius:16px; color:white; text-decoration:none; transition:all .3s; backdrop-filter:blur(10px); position:relative; }
        .action-btn:hover{ transform:translateY(-3px); box-shadow:0 5px 15px rgba(0,0,0,.3); border-color:var(--primary-accent); }
        .action-btn i{ display:block; font-size:1.8rem; margin-bottom:.6rem; color:var(--primary-accent); }
        .badge-count{ position:absolute; top:10px; right:10px; background:#ef4444; color:white; font-size:.65rem; font-weight:700; min-width:20px; height:20px; border-radius:10px; display:flex; align-items:center; justify-content:center; padding:0 4px; }

        /* Section header */
        .section-header{ display:flex; align-items:center; justify-content:space-between; margin:2.5rem 0 1.2rem; }
        .section-title{ font-family:'Playfair Display',serif; font-size:1.4rem; }
        .section-badge{ background:#ef4444; color:white; font-size:.7rem; font-weight:700; padding:3px 10px; border-radius:20px; margin-left:10px; }

        /* Purchase request cards */
        .purchase-card{ background:var(--glass); border:1px solid var(--glass-border); border-radius:16px; padding:1.4rem; margin-bottom:1rem; transition:border-color .3s; }
        .purchase-card:hover{ border-color:var(--primary-accent); }
        .purchase-card.pending{ border-left:3px solid #fbbf24; }
        .purchase-card.reviewed{ border-left:3px solid #22d3ee; }
        .purchase-card.ordered{ border-left:3px solid #34d399; }
        .purchase-card.rejected{ border-left:3px solid #f87171; }

        .pc-header{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
        .pc-title{ font-size:1.1rem; font-weight:700; color:white; margin-bottom:4px; }
        .pc-author{ font-size:.85rem; color:rgba(255,255,255,.5); }
        .pc-meta{ font-size:.78rem; color:rgba(255,255,255,.4); margin-top:6px; }
        .pc-reason{ background:rgba(255,255,255,.04); border:1px solid var(--glass-border); border-radius:8px; padding:10px 14px; font-size:.85rem; color:rgba(255,255,255,.7); margin:10px 0; line-height:1.5; }
        .pc-user{ display:inline-flex; align-items:center; gap:6px; background:rgba(34,211,238,.1); border:1px solid rgba(34,211,238,.2); color:#22d3ee; font-size:.75rem; padding:3px 10px; border-radius:20px; }

        /* Status badge */
        .status-pill{ display:inline-block; font-size:.72rem; font-weight:700; padding:3px 11px; border-radius:20px; }
        .pill-Pending { background:rgba(251,191,36,.15); color:#fbbf24; border:1px solid rgba(251,191,36,.3); }
        .pill-Reviewed{ background:rgba(34,211,238,.15); color:#22d3ee; border:1px solid rgba(34,211,238,.3); }
        .pill-Ordered { background:rgba(52,211,153,.15); color:#34d399; border:1px solid rgba(52,211,153,.3); }
        .pill-Rejected{ background:rgba(248,113,113,.15); color:#f87171; border:1px solid rgba(248,113,113,.3); }

        /* Response form */
        .response-form{ display:none; margin-top:14px; padding-top:14px; border-top:1px solid var(--glass-border); }
        .response-form.open{ display:block; }
        .form-row{ display:grid; grid-template-columns:1fr 1fr auto; gap:10px; align-items:end; }
        .glass-input{ width:100%; padding:10px 14px; background:rgba(255,255,255,.06); border:1px solid var(--glass-border); border-radius:10px; color:white; font-family:'Inter',sans-serif; font-size:.88rem; outline:none; transition:border-color .3s; }
        .glass-input:focus{ border-color:var(--primary-accent); }
        .glass-input option{ background:#0d1117; color:white; }
        .btn-respond{ padding:10px 20px; background:rgba(34,211,238,.1); border:1px solid var(--primary-accent); color:var(--primary-accent); border-radius:10px; font-weight:700; font-size:.85rem; cursor:pointer; transition:all .3s; white-space:nowrap; }
        .btn-respond:hover{ background:var(--primary-accent); color:#000; }

        /* Admin note display */
        .admin-note-box{ background:rgba(34,211,238,.07); border:1px solid rgba(34,211,238,.2); border-radius:8px; padding:10px 14px; font-size:.82rem; color:rgba(255,255,255,.6); margin-top:8px; }
        .admin-note-box strong{ color:#22d3ee; }

        /* Toggle reply btn */
        .btn-reply{ background:none; border:1px solid var(--glass-border); color:rgba(255,255,255,.5); font-size:.78rem; padding:5px 14px; border-radius:8px; cursor:pointer; transition:all .3s; }
        .btn-reply:hover{ border-color:var(--primary-accent); color:var(--primary-accent); }

        /* Empty state */
        .empty-state{ text-align:center; padding:3rem; color:rgba(255,255,255,.3); background:var(--glass); border:1px solid var(--glass-border); border-radius:16px; }

        /* Filter tabs */
        .filter-tabs{ display:flex; gap:8px; flex-wrap:wrap; margin-bottom:1.2rem; }
        .filter-tab{ padding:5px 16px; border-radius:20px; font-size:.78rem; font-weight:600; cursor:pointer; transition:all .2s; border:1px solid var(--glass-border); background:var(--glass); color:rgba(255,255,255,.5); }
        .filter-tab.active{ background:var(--primary-accent); color:#000; border-color:var(--primary-accent); }

        /* Toast */
        .toast{ position:fixed; bottom:24px; right:24px; z-index:9999; background:#34d399; color:black; padding:12px 20px; border-radius:12px; font-weight:700; font-size:.9rem; animation:slideUp .4s ease, fadeOut .5s ease 3.5s forwards; }
        @keyframes slideUp{ from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1} }
        @keyframes fadeOut{ to{opacity:0;pointer-events:none} }

        .sidebar-overlay{ display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.6); z-index:999; opacity:0; transition:opacity .3s; }
        .sidebar-overlay.visible{ display:block; opacity:1; }
        @media(max-width:768px){ .sidebar{width:280px;max-width:90%;} .main-content{margin-left:0;padding:1.5rem;} .form-row{grid-template-columns:1fr;} }
    </style>
</head>
<body>

<?php if ($toast): ?>
<div class="toast"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($toast) ?></div>
<?php endif; ?>

<div class="dashboard">
    <aside class="sidebar" id="sidebar">
        <div class="logo"><div class="logo-dot"></div>LIBRITE ADMIN</div>
        <a href="admin_dashboard.php" class="nav-item active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="manage_books.php"    class="nav-item"><i class="fas fa-book"></i> Manage Books</a>
        <a href="manage_users.php"    class="nav-item"><i class="fas fa-users"></i> Manage Users</a>
        <a href="book_requests.php"   class="nav-item"><i class="fas fa-clipboard-list"></i> Book Requests
            <?php if ($pendingReqs > 0): ?><span style="background:#ef4444;color:white;font-size:.65rem;padding:1px 6px;border-radius:10px;margin-left:6px;"><?= $pendingReqs ?></span><?php endif; ?>
        </a>
        <a href="admin_requests_extra.php" class="nav-item"><i class="fas fa-credit-card"></i> Payments & Orders
            <?php if ($pendingPayments > 0 || $pendingPurchaseCount > 0): ?>
            <span style="background:#ef4444;color:white;font-size:.65rem;padding:1px 6px;border-radius:10px;margin-left:6px;"><?= $pendingPayments + $pendingPurchaseCount ?></span>
            <?php endif; ?>
        </a>
        <a href="report.php"    class="nav-item"><i class="fas fa-chart-bar"></i> Reports & Analytics</a>
        <a href="admin_forgot_password.php" class="nav-item"><i class="fas fa-lock"></i> Change Password</a>
        <a href="index.php" class="nav-item" style="margin-top:2rem;color:#ff6b6b;"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </aside>

    <main class="main-content">
        <div class="header">
            <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
            <h1 class="welcome">Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h1>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-label">Total Books</div>
                <div class="stat-value" style="color:#22d3ee"><?= number_format($totalBooks) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-label">Members</div>
                <div class="stat-value" style="color:#a78bfa"><?= number_format($totalMembers) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📖</div>
                <div class="stat-label">Active Loans</div>
                <div class="stat-value" style="color:#34d399"><?= number_format($activeLoans) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-label">Pending Requests</div>
                <div class="stat-value" style="color:#fbbf24"><?= number_format($pendingReqs) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-label">Total Fines</div>
                <div class="stat-value" style="color:#f87171">₹<?= number_format($totalFines, 2) ?></div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h2 style="margin:2rem 0 1rem;font-family:'Playfair Display',serif;">Quick Actions</h2>
        <div class="actions-grid">
            <a href="manage_books.php" class="action-btn"><i class="fas fa-book"></i>Manage Books</a>
            <a href="book_requests.php" class="action-btn">
                <i class="fas fa-clipboard-list"></i>Book Requests
                <?php if ($pendingReqs > 0): ?><span class="badge-count"><?= $pendingReqs ?></span><?php endif; ?>
            </a>
            <a href="manage_users.php" class="action-btn"><i class="fas fa-users"></i>Manage Users</a>
            <a href="admin_requests_extra.php" class="action-btn">
                <i class="fas fa-credit-card"></i>Payments & Orders
                <?php if ($pendingPayments + $pendingPurchaseCount > 0): ?><span class="badge-count"><?= $pendingPayments + $pendingPurchaseCount ?></span><?php endif; ?>
            </a>
            <a href="report.php" class="action-btn">
    <i class="fas fa-chart-bar"></i>Reports & Analytics
</a>
            <a href="change_password.php" class="action-btn"><i class="fas fa-lock"></i>Change Password</a>
        </div>

        <!-- ═══ BOOK PURCHASE REQUESTS ══════════════════════════════════════════ -->
        <div class="section-header">
            <div style="display:flex;align-items:center">
                <span class="section-title">📋 Book Purchase Requests</span>
                <?php if ($pendingPurchaseCount > 0): ?>
                <span class="section-badge"><?= $pendingPurchaseCount ?> pending</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter tabs -->
        <div class="filter-tabs">
            <button class="filter-tab active" onclick="filterPurchase('All', this)">All (<?= count($purchaseRequests) ?>)</button>
            <?php
            $statuses = ['Pending','Reviewed','Ordered','Rejected'];
            foreach ($statuses as $s):
                $cnt = count(array_filter($purchaseRequests, fn($p)=>$p['status']===$s));
                if ($cnt > 0):
            ?>
            <button class="filter-tab" onclick="filterPurchase('<?= $s ?>', this)"><?= $s ?> (<?= $cnt ?>)</button>
            <?php endif; endforeach; ?>
        </div>

        <?php if (empty($purchaseRequests)): ?>
        <div class="empty-state">
            <i class="fas fa-box-open" style="font-size:2.5rem;margin-bottom:1rem;display:block;"></i>
            <p>No book purchase requests from students yet.</p>
        </div>

        <?php else: ?>
        <div id="purchaseList">
        <?php foreach ($purchaseRequests as $pr):
            $statusClass = strtolower($pr['status']);
        ?>
        <div class="purchase-card <?= $statusClass ?>" data-status="<?= $pr['status'] ?>">
            <div class="pc-header">
                <div style="flex:1">
                    <div class="pc-title"><?= htmlspecialchars($pr['book_title']) ?></div>
                    <?php if ($pr['author']): ?>
                    <div class="pc-author">by <?= htmlspecialchars($pr['author']) ?></div>
                    <?php endif; ?>
                    <div class="pc-meta">
                        <span class="pc-user"><i class="fas fa-user" style="font-size:.65rem"></i> <?= htmlspecialchars($pr['full_name'] ?: $pr['username']) ?></span>
                        &nbsp;·&nbsp; <?= date('d M Y, g:i A', strtotime($pr['created_at'])) ?>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                    <span class="status-pill pill-<?= $pr['status'] ?>"><?= $pr['status'] ?></span>
                    <button class="btn-reply" onclick="toggleReply(<?= $pr['id'] ?>)">
                        <i class="fas fa-reply"></i> Respond
                    </button>
                </div>
            </div>

            <?php if ($pr['reason']): ?>
            <div class="pc-reason">
                <i class="fas fa-quote-left" style="color:rgba(255,255,255,.3);margin-right:6px;font-size:.8rem"></i>
                <?= htmlspecialchars($pr['reason']) ?>
            </div>
            <?php endif; ?>

            <?php if ($pr['admin_note']): ?>
            <div class="admin-note-box">
                <strong>Your response:</strong> <?= htmlspecialchars($pr['admin_note']) ?>
            </div>
            <?php endif; ?>

            <!-- Response Form -->
            <div class="response-form" id="reply-<?= $pr['id'] ?>">
                <form method="POST">
                    <input type="hidden" name="action"  value="update_purchase">
                    <input type="hidden" name="pr_id"   value="<?= $pr['id'] ?>">
                    <div class="form-row">
                        <div>
                            <label style="font-size:.75rem;color:rgba(255,255,255,.4);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Update Status</label>
                            <select name="pr_status" class="glass-input">
                                <?php foreach(['Pending','Reviewed','Ordered','Rejected'] as $s): ?>
                                <option value="<?= $s ?>" <?= $pr['status']===$s?'selected':'' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:.75rem;color:rgba(255,255,255,.4);display:block;margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px">Note to Student</label>
                            <input type="text" name="admin_note" class="glass-input"
                                   placeholder="e.g. Book has been ordered, will arrive next week..."
                                   value="<?= htmlspecialchars($pr['admin_note'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn-respond">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </main>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
    // Hamburger
    const hamburger = document.getElementById('hamburger');
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebarOverlay');
    hamburger.addEventListener('click', () => {
        const c = sidebar.classList.toggle('collapsed');
        hamburger.classList.toggle('open', !c);
        if (window.innerWidth <= 768) { overlay.classList.toggle('visible', !c); document.body.style.overflow = c ? 'auto' : 'hidden'; }
    });
    overlay.addEventListener('click', () => { sidebar.classList.add('collapsed'); hamburger.classList.remove('open'); overlay.classList.remove('visible'); document.body.style.overflow='auto'; });

    // Toggle reply form
    function toggleReply(id) {
        const form = document.getElementById('reply-' + id);
        form.classList.toggle('open');
    }

    // Filter purchase requests
    function filterPurchase(status, btn) {
        document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.purchase-card').forEach(card => {
            card.style.display = (status === 'All' || card.dataset.status === status) ? 'block' : 'none';
        });
    }
</script>
</body>
</html>