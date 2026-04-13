<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php"); exit();
}

// ── Date range filter ─────────────────────────────────────────────────────────
$range  = $_GET['range'] ?? '30';   // 7 | 30 | 90 | 365 | all
$sql_range = ($range === 'all') ? '' : "AND created_at >= DATE_SUB(NOW(), INTERVAL {$range} DAY)";
$br_range  = ($range === 'all') ? '' : "AND br.created_at >= DATE_SUB(NOW(), INTERVAL {$range} DAY)";

// ── Overview stats ────────────────────────────────────────────────────────────
$totalBooks     = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$totalMembers   = $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$activeLoans    = $pdo->query("SELECT COUNT(*) FROM book_requests WHERE status='Approved'")->fetchColumn();
$totalFines     = $pdo->query("SELECT COALESCE(SUM(fine_amount),0) FROM book_requests WHERE fine_amount > 0")->fetchColumn();
$collectedFines = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM fine_payments WHERE status='Approved'")->fetchColumn();

// ── Borrowing stats ───────────────────────────────────────────────────────────
$totalBorrowed  = $pdo->query("SELECT COUNT(*) FROM book_requests WHERE status IN ('Approved','Returned')")->fetchColumn();
$totalReturned  = $pdo->query("SELECT COUNT(*) FROM book_requests WHERE status='Returned'")->fetchColumn();
$totalOverdue   = $pdo->query("SELECT COUNT(*) FROM book_requests WHERE status='Approved' AND due_date < NOW()")->fetchColumn();

// Borrowing trend (last 30 days or selected range)
$days = min((int)$range ?: 30, 365);
$borrowTrend = $pdo->query("
  SELECT COUNT(*) FROM users as day, COUNT(*) as cnt
    FROM book_requests
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
    GROUP BY DATE(registered_at)
    ORDER BY day ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── New members over time ─────────────────────────────────────────────────────
$newMembers = $pdo->query("
    SELECT DATE(created_at) as day, COUNT(*) as cnt
    FROM users
    WHERE role='user' AND created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
")->fetchAll(PDO::FETCH_ASSOC);

$newMembersTotal = $pdo->query("
    SELECT COUNT(*) FROM users WHERE role='user'
    AND created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
")->fetchColumn();

// ── Most borrowed books ───────────────────────────────────────────────────────
$topBooks = $pdo->query("
    SELECT b.title, b.author, COUNT(br.id) as borrow_count
    FROM book_requests br
    JOIN books b ON b.id = br.book_id
    GROUP BY br.book_id
    ORDER BY borrow_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// ── Most active members ───────────────────────────────────────────────────────
$topMembers = $pdo->query("
    SELECT u.full_name, u.username, u.email, COUNT(br.id) as borrow_count
    FROM book_requests br
    JOIN users u ON u.id = br.user_id
    GROUP BY br.user_id
    ORDER BY borrow_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// ── Book status breakdown ─────────────────────────────────────────────────────
$statusBreakdown = $pdo->query("
    SELECT status, COUNT(*) as cnt FROM book_requests GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);
$statusMap = [];
foreach ($statusBreakdown as $s) $statusMap[$s['status']] = (int)$s['cnt'];

// ── Purchase requests summary ─────────────────────────────────────────────────
$purchaseSummary = $pdo->query("
    SELECT status, COUNT(*) as cnt FROM book_purchase_requests GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);
$purchaseMap = [];
foreach ($purchaseSummary as $p) $purchaseMap[$p['status']] = (int)$p['cnt'];

// ── Books by category ─────────────────────────────────────────────────────────
$booksByCategory = $pdo->query("
    SELECT COALESCE(NULLIF(category,''),'Uncategorized') as category, COUNT(*) as cnt
    FROM books
    GROUP BY category
    ORDER BY cnt DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// ── Recent activity feed (last 15 events) ─────────────────────────────────────
$recentActivity = $pdo->query("
    SELECT 'borrow' as type, u.full_name, b.title, br.created_at, br.status
    FROM book_requests br
    JOIN users u ON u.id = br.user_id
    JOIN books b ON b.id = br.book_id
    ORDER BY br.created_at DESC
    LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);

// ── Overdue list ──────────────────────────────────────────────────────────────
$overdueList = $pdo->query("
    SELECT u.full_name, u.email, b.title,
           br.due_date, DATEDIFF(NOW(), br.due_date) as days_overdue, br.fine_amount
    FROM book_requests br
    JOIN users u ON u.id = br.user_id
    JOIN books b ON b.id = br.book_id
    WHERE br.status='Approved' AND br.due_date < NOW()
    ORDER BY days_overdue DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics – LIBRITE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;600&display=swap');

        :root {
            --primary:   #22d3ee;
            --bg:        #02040a;
            --glass:     rgba(255,255,255,.04);
            --gb:        rgba(255,255,255,.1);
            --text:      rgba(255,255,255,.85);
            --muted:     rgba(255,255,255,.45);
            --success:   #34d399;
            --warn:      #fbbf24;
            --danger:    #f87171;
            --purple:    #a78bfa;
        }
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body { background:var(--bg); font-family:'Inter',sans-serif; color:var(--text); }

        /* ── Topbar ─────────────────────────────────── */
        .topbar {
            display:flex; align-items:center; justify-content:space-between;
            padding:1rem 2rem; border-bottom:1px solid var(--gb);
            position:sticky; top:0; z-index:100;
            background:rgba(2,4,10,.92); backdrop-filter:blur(12px);
        }
        .topbar-left { display:flex; align-items:center; gap:14px; }
        .back-btn {
            display:inline-flex; align-items:center; gap:7px;
            color:var(--primary); text-decoration:none; font-size:.88rem;
            padding:6px 14px; border:1px solid rgba(34,211,238,.3); border-radius:8px;
            transition:all .25s;
        }
        .back-btn:hover { background:rgba(34,211,238,.08); }
        .page-title { font-family:'Playfair Display',serif; font-size:1.4rem; }

        /* ── Filter bar ─────────────────────────────── */
        .filter-bar {
            display:flex; align-items:center; gap:8px; flex-wrap:wrap;
            padding:.8rem 2rem; border-bottom:1px solid var(--gb);
            background:rgba(255,255,255,.015);
        }
        .filter-bar span { font-size:.8rem; color:var(--muted); margin-right:4px; }
        .range-btn {
            padding:5px 14px; border-radius:20px; font-size:.78rem; font-weight:600;
            cursor:pointer; border:1px solid var(--gb); background:none; color:var(--muted);
            transition:all .2s; text-decoration:none;
        }
        .range-btn:hover { border-color:var(--primary); color:var(--primary); }
        .range-btn.active { background:var(--primary); color:#000; border-color:var(--primary); }

        /* Print / Export button */
        .export-btns { margin-left:auto; display:flex; gap:8px; }
        .btn-export {
            display:inline-flex; align-items:center; gap:6px;
            padding:6px 14px; border-radius:8px; font-size:.8rem; font-weight:600;
            cursor:pointer; border:1px solid; transition:all .25s; text-decoration:none;
        }
        .btn-print  { border-color:rgba(34,211,238,.4); color:var(--primary); background:rgba(34,211,238,.07); }
        .btn-print:hover  { background:var(--primary); color:#000; }

        /* ── Content ────────────────────────────────── */
        .content { max-width:1300px; margin:0 auto; padding:2rem; }

        /* ── Section heading ────────────────────────── */
        .section-title {
            font-family:'Playfair Display',serif; font-size:1.2rem;
            margin:2.4rem 0 1.2rem; display:flex; align-items:center; gap:10px;
        }
        .section-title::after {
            content:''; flex:1; height:1px; background:var(--gb);
        }

        /* ── Stat cards grid ────────────────────────── */
        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1.2rem; }
        .stat-card {
            background:var(--glass); border:1px solid var(--gb); border-radius:16px;
            padding:1.4rem 1.2rem; text-align:center;
            transition:border-color .25s, transform .25s;
        }
        .stat-card:hover { border-color:var(--primary); transform:translateY(-2px); }
        .stat-icon { font-size:1.5rem; margin-bottom:.5rem; }
        .stat-value { font-size:1.9rem; font-weight:700; margin:.3rem 0; }
        .stat-label { font-size:.8rem; color:var(--muted); }

        /* ── Charts grid ────────────────────────────── */
        .charts-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(420px,1fr)); gap:1.4rem; }
        .chart-card {
            background:var(--glass); border:1px solid var(--gb); border-radius:16px; padding:1.4rem;
        }
        .chart-card-title { font-size:.95rem; font-weight:600; margin-bottom:1rem; color:var(--text); }
        canvas { max-height:260px; }

        /* ── Table ──────────────────────────────────── */
        .table-card {
            background:var(--glass); border:1px solid var(--gb); border-radius:16px;
            overflow:hidden; margin-bottom:1.4rem;
        }
        .table-head {
            display:flex; align-items:center; justify-content:space-between;
            padding:1rem 1.4rem; border-bottom:1px solid var(--gb);
        }
        .table-head-title { font-size:.95rem; font-weight:600; }
        table { width:100%; border-collapse:collapse; }
        thead tr { background:rgba(255,255,255,.03); }
        th { padding:.75rem 1.2rem; font-size:.75rem; text-transform:uppercase; letter-spacing:.5px; color:var(--muted); text-align:left; }
        td { padding:.7rem 1.2rem; font-size:.85rem; border-top:1px solid rgba(255,255,255,.04); }
        tr:hover td { background:rgba(255,255,255,.02); }
        .rank { font-weight:700; color:var(--primary); }
        .pill {
            display:inline-block; padding:2px 10px; border-radius:20px; font-size:.7rem; font-weight:700;
        }
        .pill-green  { background:rgba(52,211,153,.12); color:#34d399; border:1px solid rgba(52,211,153,.25); }
        .pill-yellow { background:rgba(251,191,36,.12); color:#fbbf24; border:1px solid rgba(251,191,36,.25); }
        .pill-red    { background:rgba(248,113,113,.12); color:#f87171; border:1px solid rgba(248,113,113,.25); }
        .pill-blue   { background:rgba(34,211,238,.12); color:#22d3ee;  border:1px solid rgba(34,211,238,.25); }

        /* ── Activity feed ──────────────────────────── */
        .feed-item {
            display:flex; align-items:flex-start; gap:12px;
            padding:.75rem 1.2rem; border-top:1px solid rgba(255,255,255,.04);
        }
        .feed-icon {
            width:32px; height:32px; border-radius:8px; display:flex;
            align-items:center; justify-content:center; font-size:.85rem; flex-shrink:0;
        }
        .feed-icon.borrow { background:rgba(34,211,238,.12); color:#22d3ee; }
        .feed-icon.return { background:rgba(52,211,153,.12); color:#34d399; }
        .feed-body { flex:1; }
        .feed-name { font-size:.88rem; font-weight:600; }
        .feed-sub  { font-size:.78rem; color:var(--muted); }
        .feed-time { font-size:.72rem; color:var(--muted); flex-shrink:0; padding-top:2px; }

        /* ── Progress bar ───────────────────────────── */
        .bar-wrap { margin-top:.4rem; }
        .bar-row { display:flex; align-items:center; gap:10px; margin:.5rem 0; }
        .bar-label { font-size:.8rem; color:var(--muted); width:160px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .bar-track { flex:1; height:7px; background:rgba(255,255,255,.07); border-radius:4px; overflow:hidden; }
        .bar-fill  { height:100%; border-radius:4px; transition:width .5s ease; }
        .bar-count { font-size:.78rem; color:var(--muted); width:28px; text-align:right; }

        /* ── Donut legends ──────────────────────────── */
        .donut-legend { display:flex; flex-wrap:wrap; gap:10px 16px; margin-top:1rem; }
        .donut-legend-item { display:flex; align-items:center; gap:6px; font-size:.78rem; color:var(--muted); }
        .legend-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }

        /* ── Overdue highlight ──────────────────────── */
        .overdue-days { color:#f87171; font-weight:700; }

        /* ── Empty state ────────────────────────────── */
        .empty { text-align:center; padding:2.5rem; color:var(--muted); font-size:.88rem; }

        /* ── Print styles ───────────────────────────── */
        @media print {
            body { background:#fff !important; color:#000 !important; }
            .topbar, .filter-bar, .export-btns, .back-btn { display:none !important; }
            .stat-card, .chart-card, .table-card { border:1px solid #ccc !important; background:#fff !important; break-inside:avoid; }
            .content { padding:0 !important; max-width:100% !important; }
            .charts-grid { grid-template-columns:1fr 1fr !important; }
            th, td { color:#000 !important; }
            .stat-value, .stat-label, .section-title, .chart-card-title, .table-head-title, .bar-label { color:#000 !important; }
        }
        @media(max-width:680px) {
            .charts-grid { grid-template-columns:1fr; }
            .content { padding:1rem; }
            .topbar { padding:.8rem 1rem; }
            .filter-bar { padding:.6rem 1rem; }
        }
    </style>
</head>
<body>

<!-- ── Topbar ────────────────────────────────────────────────────────────────── -->
<div class="topbar">
    <div class="topbar-left">
        <a href="admin_dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <h1 class="page-title">📊 Reports &amp; Analytics</h1>
    </div>
    <div class="export-btns">
        <button class="btn-export btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Print / Save PDF
        </button>
    </div>
</div>

<!-- ── Filter bar ─────────────────────────────────────────────────────────────── -->
<div class="filter-bar">
    <span>Show data for:</span>
    <?php
    $ranges = ['7'=>'Last 7 days','30'=>'Last 30 days','90'=>'Last 90 days','365'=>'Last year','all'=>'All time'];
    foreach ($ranges as $val => $label):
    ?>
    <a href="?range=<?= $val ?>" class="range-btn <?= $range===$val?'active':'' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<div class="content">

    <!-- ═══ OVERVIEW STATS ═══════════════════════════════════════════════════════ -->
    <div class="section-title"><i class="fas fa-tachometer-alt" style="color:var(--primary)"></i> Overview</div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📚</div>
            <div class="stat-label">Total Books</div>
            <div class="stat-value" style="color:var(--primary)"><?= number_format($totalBooks) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-label">Total Members</div>
            <div class="stat-value" style="color:var(--purple)"><?= number_format($totalMembers) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🆕</div>
            <div class="stat-label">New Members (period)</div>
            <div class="stat-value" style="color:#34d399"><?= number_format($newMembersTotal) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📖</div>
            <div class="stat-label">Total Borrowed</div>
            <div class="stat-value" style="color:var(--primary)"><?= number_format($totalBorrowed) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-label">Returned</div>
            <div class="stat-value" style="color:var(--success)"><?= number_format($totalReturned) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📌</div>
            <div class="stat-label">Active Loans</div>
            <div class="stat-value" style="color:var(--warn)"><?= number_format($activeLoans) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏰</div>
            <div class="stat-label">Overdue</div>
            <div class="stat-value" style="color:var(--danger)"><?= number_format($totalOverdue) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-label">Total Fines Raised</div>
            <div class="stat-value" style="color:var(--danger)">₹<?= number_format($totalFines,2) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💳</div>
            <div class="stat-label">Fines Collected</div>
            <div class="stat-value" style="color:var(--success)">₹<?= number_format($collectedFines,2) ?></div>
        </div>
    </div>

    <!-- ═══ CHARTS ROW 1 ═════════════════════════════════════════════════════════ -->
    <div class="section-title"><i class="fas fa-chart-line" style="color:var(--primary)"></i> Trends</div>
    <div class="charts-grid">

        <!-- Borrowing trend line chart -->
        <div class="chart-card">
            <div class="chart-card-title">📈 Daily Borrowing Activity</div>
            <canvas id="borrowChart"></canvas>
        </div>

        <!-- New members line chart -->
        <div class="chart-card">
            <div class="chart-card-title">👤 New Member Registrations</div>
            <canvas id="memberChart"></canvas>
        </div>

    </div>

    <!-- ═══ CHARTS ROW 2 ═════════════════════════════════════════════════════════ -->
    <div class="charts-grid" style="margin-top:1.4rem">

        <!-- Request status donut -->
        <div class="chart-card">
            <div class="chart-card-title">🔄 Loan Request Status Breakdown</div>
            <canvas id="statusChart"></canvas>
            <div class="donut-legend">
                <?php
                $statusColors = ['Approved'=>'#22d3ee','Returned'=>'#34d399','Pending'=>'#fbbf24','Rejected'=>'#f87171','Cancelled'=>'#a78bfa'];
                foreach ($statusMap as $s => $c):
                ?>
                <div class="donut-legend-item">
                    <div class="legend-dot" style="background:<?= $statusColors[$s] ?? '#888' ?>"></div>
                    <?= $s ?> (<?= $c ?>)
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Books by category donut -->
        <div class="chart-card">
            <div class="chart-card-title">📂 Books by Category</div>
            <canvas id="categoryChart"></canvas>
            <div class="donut-legend" id="catLegend"></div>
        </div>

    </div>

    <!-- ═══ PURCHASE REQUESTS SUMMARY ════════════════════════════════════════════ -->
    <?php if (!empty($purchaseMap)): ?>
    <div class="section-title"><i class="fas fa-shopping-cart" style="color:var(--primary)"></i> Purchase Requests Summary</div>
    <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
        <?php
        $pColors = ['Pending'=>'var(--warn)','Reviewed'=>'var(--primary)','Ordered'=>'var(--success)','Rejected'=>'var(--danger)'];
        foreach ($purchaseMap as $s => $c):
        ?>
        <div class="stat-card">
            <div class="stat-label"><?= $s ?></div>
            <div class="stat-value" style="color:<?= $pColors[$s] ?? 'white' ?>"><?= $c ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ═══ TOP BOOKS ═════════════════════════════════════════════════════════════ -->
    <div class="section-title"><i class="fas fa-fire" style="color:var(--primary)"></i> Most Borrowed Books</div>
    <?php
    $maxBorrow = $topBooks[0]['borrow_count'] ?? 1;
    $barColors = ['#22d3ee','#34d399','#fbbf24','#a78bfa','#f87171','#22d3ee','#34d399','#fbbf24','#a78bfa','#f87171'];
    ?>
    <div class="table-card">
        <div class="table-head">
            <span class="table-head-title">Top 10 Books</span>
            <span style="font-size:.78rem;color:var(--muted)">by total borrow count</span>
        </div>
        <?php if (empty($topBooks)): ?>
        <div class="empty">No borrowing data yet.</div>
        <?php else: ?>
        <table>
            <thead><tr>
                <th>#</th><th>Title</th><th>Author</th><th>Borrows</th><th>Trend</th>
            </tr></thead>
            <tbody>
            <?php foreach ($topBooks as $i => $b): ?>
            <tr>
                <td><span class="rank"><?= $i+1 ?></span></td>
                <td><?= htmlspecialchars($b['title']) ?></td>
                <td style="color:var(--muted)"><?= htmlspecialchars($b['author'] ?? '—') ?></td>
                <td><span class="pill pill-blue"><?= $b['borrow_count'] ?></span></td>
                <td style="width:180px">
                    <div class="bar-track">
                        <div class="bar-fill" style="width:<?= round(($b['borrow_count']/$maxBorrow)*100) ?>%;background:<?= $barColors[$i] ?>"></div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ═══ TOP MEMBERS ═══════════════════════════════════════════════════════════ -->
    <div class="section-title"><i class="fas fa-star" style="color:var(--primary)"></i> Most Active Members</div>
    <div class="table-card">
        <div class="table-head">
            <span class="table-head-title">Top 10 Members</span>
            <span style="font-size:.78rem;color:var(--muted)">by total borrows</span>
        </div>
        <?php if (empty($topMembers)): ?>
        <div class="empty">No member data yet.</div>
        <?php else: ?>
        <table>
            <thead><tr>
                <th>#</th><th>Name</th><th>Username</th><th>Email</th><th>Total Borrows</th>
            </tr></thead>
            <tbody>
            <?php foreach ($topMembers as $i => $m): ?>
            <tr>
                <td><span class="rank"><?= $i+1 ?></span></td>
                <td><?= htmlspecialchars($m['full_name'] ?: $m['username']) ?></td>
                <td style="color:var(--muted)">@<?= htmlspecialchars($m['username']) ?></td>
                <td style="color:var(--muted);font-size:.8rem"><?= htmlspecialchars($m['email']) ?></td>
                <td><span class="pill pill-blue"><?= $m['borrow_count'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ═══ OVERDUE BOOKS ═════════════════════════════════════════════════════════ -->
    <?php if (!empty($overdueList)): ?>
    <div class="section-title"><i class="fas fa-exclamation-triangle" style="color:var(--danger)"></i> Overdue Books</div>
    <div class="table-card">
        <div class="table-head">
            <span class="table-head-title" style="color:var(--danger)">⚠️ Currently Overdue</span>
            <span style="font-size:.78rem;color:var(--muted)"><?= count($overdueList) ?> record(s)</span>
        </div>
        <table>
            <thead><tr>
                <th>Member</th><th>Email</th><th>Book</th><th>Due Date</th><th>Days Overdue</th><th>Fine</th>
            </tr></thead>
            <tbody>
            <?php foreach ($overdueList as $o): ?>
            <tr>
                <td><?= htmlspecialchars($o['full_name']) ?></td>
                <td style="font-size:.78rem;color:var(--muted)"><?= htmlspecialchars($o['email']) ?></td>
                <td><?= htmlspecialchars($o['title']) ?></td>
                <td style="color:var(--muted);font-size:.82rem"><?= date('d M Y', strtotime($o['due_date'])) ?></td>
                <td><span class="overdue-days"><?= $o['days_overdue'] ?> days</span></td>
                <td><span class="pill pill-red">₹<?= number_format($o['fine_amount'] ?? 0, 2) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- ═══ RECENT ACTIVITY ═══════════════════════════════════════════════════════ -->
    <div class="section-title"><i class="fas fa-clock" style="color:var(--primary)"></i> Recent Activity</div>
    <div class="table-card">
        <div class="table-head"><span class="table-head-title">Latest 15 Transactions</span></div>
        <?php if (empty($recentActivity)): ?>
        <div class="empty">No transactions yet.</div>
        <?php else: ?>
        <?php foreach ($recentActivity as $a):
            $isReturn = ($a['status'] === 'Returned');
        ?>
        <div class="feed-item">
            <div class="feed-icon <?= $isReturn ? 'return' : 'borrow' ?>">
                <i class="fas <?= $isReturn ? 'fa-undo' : 'fa-book-open' ?>"></i>
            </div>
            <div class="feed-body">
                <div class="feed-name"><?= htmlspecialchars($a['full_name']) ?></div>
                <div class="feed-sub"><?= $isReturn ? 'Returned' : 'Borrowed' ?> · <?= htmlspecialchars($a['title']) ?></div>
            </div>
            <div class="feed-time"><?= date('d M, g:i A', strtotime($a['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div style="text-align:center;padding:2rem 0;color:var(--muted);font-size:.78rem">
        Report generated on <?= date('d F Y, g:i A') ?> &nbsp;·&nbsp; LIBRITE Library Management System
    </div>

</div><!-- /content -->

<script>
// ── Chart defaults ─────────────────────────────────────────────────────────────
Chart.defaults.color = 'rgba(255,255,255,.5)';
Chart.defaults.font.family = 'Inter, sans-serif';
Chart.defaults.font.size   = 11;

// ── Borrow trend ───────────────────────────────────────────────────────────────
const borrowData  = <?= json_encode(array_column($borrowTrend, 'cnt')) ?>;
const borrowDays  = <?= json_encode(array_column($borrowTrend, 'day')) ?>;
new Chart(document.getElementById('borrowChart'), {
    type: 'line',
    data: {
        labels: borrowDays,
        datasets: [{
            label: 'Borrows',
            data: borrowData,
            borderColor: '#22d3ee',
            backgroundColor: 'rgba(34,211,238,.1)',
            fill: true,
            tension: .4,
            pointRadius: 3,
            pointBackgroundColor: '#22d3ee',
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: 'rgba(255,255,255,.05)' }, ticks: { maxTicksLimit: 8 } },
            y: { grid: { color: 'rgba(255,255,255,.05)' }, beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// ── New members trend ──────────────────────────────────────────────────────────
const memberData = <?= json_encode(array_column($newMembers, 'cnt')) ?>;
const memberDays = <?= json_encode(array_column($newMembers, 'day')) ?>;
new Chart(document.getElementById('memberChart'), {
    type: 'bar',
    data: {
        labels: memberDays,
        datasets: [{
            label: 'New Members',
            data: memberData,
            backgroundColor: 'rgba(167,139,250,.6)',
            borderColor: '#a78bfa',
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: 'rgba(255,255,255,.05)' }, ticks: { maxTicksLimit: 8 } },
            y: { grid: { color: 'rgba(255,255,255,.05)' }, beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// ── Status donut ───────────────────────────────────────────────────────────────
const statusLabels = <?= json_encode(array_keys($statusMap)) ?>;
const statusValues = <?= json_encode(array_values($statusMap)) ?>;
const statusColors = { Approved:'#22d3ee', Returned:'#34d399', Pending:'#fbbf24', Rejected:'#f87171', Cancelled:'#a78bfa' };
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{
            data: statusValues,
            backgroundColor: statusLabels.map(s => statusColors[s] || '#888'),
            borderColor: '#02040a',
            borderWidth: 2,
            hoverOffset: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed}` } }
        },
        cutout: '65%'
    }
});

// ── Category donut ─────────────────────────────────────────────────────────────
const catLabels = <?= json_encode(array_column($booksByCategory,'category')) ?>;
const catValues = <?= json_encode(array_column($booksByCategory,'cnt')) ?>;
const catPalette = ['#22d3ee','#a78bfa','#34d399','#fbbf24','#f87171','#60a5fa','#fb923c','#4ade80'];
new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: catLabels,
        datasets: [{
            data: catValues,
            backgroundColor: catPalette,
            borderColor: '#02040a',
            borderWidth: 2,
            hoverOffset: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed}` } }
        },
        cutout: '65%'
    }
});

// Build category legend dynamically
const legend = document.getElementById('catLegend');
catLabels.forEach((l,i) => {
    legend.innerHTML += `<div class="donut-legend-item">
        <div class="legend-dot" style="background:${catPalette[i]}"></div>${l} (${catValues[i]})
    </div>`;
});
</script>
</body>
</html>