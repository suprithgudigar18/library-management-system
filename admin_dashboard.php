<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php"); exit();
}

// ── Live stats ────────────────────────────────────────────────────────────────
$totalBooks   = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$totalMembers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalFines   = $pdo->query("SELECT COALESCE(SUM(fine_amount),0) FROM book_requests WHERE fine_amount > 0")->fetchColumn();
$pendingReqs  = $pdo->query("SELECT COUNT(*) FROM book_requests WHERE status='Pending'")->fetchColumn();
$activeLoans  = $pdo->query("SELECT COUNT(*) FROM book_requests WHERE status='Approved'")->fetchColumn();
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
        .main-content{ flex:1; padding:2rem; overflow-y:auto; transition:margin-left .3s ease; margin-left:250px; }
        .sidebar.collapsed + .main-content{ margin-left:0; }
        .header{ display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; }
        .welcome{ font-family:'Playfair Display',serif; font-size:1.8rem; }
        .stats-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1.5rem; margin-bottom:2.5rem; }
        .stat-card{ background:var(--glass); backdrop-filter:blur(10px); border:1px solid var(--glass-border); border-radius:16px; padding:1.5rem; text-align:center; }
        .stat-value{ font-size:2rem; font-weight:700; margin:.5rem 0; }
        .stat-label{ color:rgba(255,255,255,.7); font-size:.9rem; }
        .stat-icon{ font-size:1.4rem; margin-bottom:.4rem; }
        .actions-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1.2rem; }
        .action-btn{ display:block; padding:1.2rem; text-align:center; background:var(--glass); border:1px solid var(--glass-border); border-radius:16px; color:white; text-decoration:none; transition:all .3s; backdrop-filter:blur(10px); position:relative; }
        .action-btn:hover{ transform:translateY(-3px); box-shadow:0 5px 15px rgba(0,0,0,.3); border-color:var(--primary-accent); }
        .action-btn i{ display:block; font-size:1.8rem; margin-bottom:.6rem; color:var(--primary-accent); }
        .badge-count{ position:absolute; top:10px; right:10px; background:#ef4444; color:white; font-size:.65rem; font-weight:700; min-width:20px; height:20px; border-radius:10px; display:flex; align-items:center; justify-content:center; padding:0 4px; }
        .sidebar-overlay{ display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.6); z-index:999; opacity:0; transition:opacity .3s; }
        .sidebar-overlay.visible{ display:block; opacity:1; }
        @media(max-width:768px){ .sidebar{width:280px;max-width:90%;} .main-content{margin-left:0;padding:1.5rem;} }
    </style>
</head>
<body>
<div class="dashboard">
    <aside class="sidebar" id="sidebar">
        <div class="logo"><div class="logo-dot"></div>LIBRITE ADMIN</div>
        <a href="admin_dashboard.php" class="nav-item active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="manage_books.php"    class="nav-item"><i class="fas fa-book"></i> Manage Books</a>
        <a href="manage_users.php"    class="nav-item"><i class="fas fa-users"></i> Manage Users</a>
        <a href="book_requests.php"   class="nav-item"><i class="fas fa-clipboard-list"></i> Book Requests
            <?php if ($pendingReqs > 0): ?><span style="background:#ef4444;color:white;font-size:.65rem;padding:1px 6px;border-radius:10px;margin-left:6px;"><?= $pendingReqs ?></span><?php endif; ?>
        </a>
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
            <a href="change_password.php" class="action-btn"><i class="fas fa-lock"></i>Change Password</a>
        </div>
    </main>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<script>
    const hamburger = document.getElementById('hamburger');
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebarOverlay');
    hamburger.addEventListener('click', () => {
        const c = sidebar.classList.toggle('collapsed');
        hamburger.classList.toggle('open', !c);
        if (window.innerWidth <= 768) { overlay.classList.toggle('visible', !c); document.body.style.overflow = c ? 'auto' : 'hidden'; }
    });
    overlay.addEventListener('click', () => { sidebar.classList.add('collapsed'); hamburger.classList.remove('open'); overlay.classList.remove('visible'); document.body.style.overflow='auto'; });
</script>
</body>
</html>