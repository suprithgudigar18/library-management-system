<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php"); exit();
}

$toast = ''; $toastType = 'success';

// ── Handle Approve / Reject ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $req_id = (int)($_POST['req_id'] ?? 0);

    if ($action === 'approve' && $req_id > 0) {
        $due = date('Y-m-d', strtotime('+4 days'));
        $pdo->prepare("UPDATE book_requests SET status='Approved', approved_at=NOW(), due_date=?, notified=0 WHERE id=?")
            ->execute([$due, $req_id]);
        // Decrement copies & set status to Borrowed if copies hit 0
        $pdo->prepare("UPDATE books b JOIN book_requests br ON br.book_id=b.id
                        SET b.copies = GREATEST(b.copies-1, 0),
                            b.status = IF(b.copies-1 <= 0, 'Borrowed', 'Available')
                        WHERE br.id=?")->execute([$req_id]);
        $toast = "Request approved. Due date set to $due.";
    } elseif ($action === 'reject' && $req_id > 0) {
        $pdo->prepare("UPDATE book_requests SET status='Rejected' WHERE id=?")->execute([$req_id]);
        $toast = "Request rejected."; $toastType = 'delete';
    } elseif ($action === 'return' && $req_id > 0) {
        $pdo->prepare("UPDATE book_requests SET status='Returned', returned_at=NOW() WHERE id=?")->execute([$req_id]);
        // Increment copies back
        $pdo->prepare("UPDATE books b JOIN book_requests br ON br.book_id=b.id
                        SET b.copies = b.copies+1, b.status='Available' WHERE br.id=?")->execute([$req_id]);
        $toast = "Book marked as returned.";
    }
    header("Location: book_requests.php?toast=".urlencode($toast)."&type=".urlencode($toastType)); exit();
}

if (isset($_GET['toast'])) { $toast = $_GET['toast']; $toastType = $_GET['type'] ?? 'success'; }

// ── Fetch all requests ────────────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'Pending';
$validFilters = ['Pending','Approved','Rejected','Returned','All'];
if (!in_array($filter, $validFilters)) $filter = 'Pending';

$sql = "SELECT br.*, b.title, b.author, u.username, u.name AS user_name
        FROM book_requests br
        JOIN books b ON b.id = br.book_id
        JOIN users u ON u.id = br.user_id";
if ($filter !== 'All') $sql .= " WHERE br.status = '$filter'";
$sql .= " ORDER BY br.requested_at DESC";

$requests = $pdo->query($sql)->fetchAll();

// Counts for badges
$counts = $pdo->query("SELECT status, COUNT(*) as c FROM book_requests GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

// ── Auto-calculate fines ─────────────────────────────────────────────────────
$today = new DateTime();
foreach ($requests as &$req) {
    if ($req['status'] === 'Approved' && $req['due_date'] && !$req['returned_at']) {
        $due = new DateTime($req['due_date']);
        if ($today > $due) {
            $days = (int)$today->diff($due)->days;
            $fine = 20 + (floor(($days - 1) / 2) * 10);
            $pdo->prepare("UPDATE book_requests SET fine_amount=? WHERE id=?")->execute([$fine, $req['id']]);
            $req['fine_amount'] = $fine;
        }
    }
}
unset($req);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Requests - LIBRITE Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .toast-anim { animation: slideIn .4s ease, fadeOut .5s ease 3.5s forwards; }
        @keyframes slideIn { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
        @keyframes fadeOut { to{opacity:0;pointer-events:none} }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">

<?php if ($toast): ?>
<div class="toast-anim fixed bottom-6 right-6 z-[9999] flex items-center gap-3 px-5 py-3 rounded-xl shadow-xl
    <?= $toastType === 'delete' ? 'bg-red-600' : 'bg-emerald-600' ?> text-white font-medium text-sm">
    <i data-lucide="<?= $toastType === 'delete' ? 'x-circle' : 'check-circle' ?>" class="w-5 h-5"></i>
    <?= htmlspecialchars($toast) ?>
</div>
<?php endif; ?>

<header class="bg-white border-b border-slate-200 sticky top-0 z-10 px-6 py-4 flex items-center justify-between shadow-sm">
    <a href="admin_dashboard.php" class="flex items-center gap-3 hover:opacity-80 no-underline">
        <div class="bg-blue-600 p-2 rounded-lg"><i data-lucide="clipboard-list" class="w-6 h-6 text-white"></i></div>
        <h1 class="text-xl font-bold text-slate-800">Librite Admin</h1>
    </a>
    <div class="flex items-center gap-4">
        <span class="text-sm text-slate-500 hidden sm:block">Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="admin_dashboard.php" class="text-xs text-red-500 hover:underline" style="font-weight: bold;">
    Back
</a>
    </div>
</header>

<main class="p-6 max-w-7xl mx-auto">
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-800">Book Requests</h2>
        <p class="text-slate-500">Manage borrow requests from library members.</p>
    </div>

    <!-- Filter tabs -->
    <div class="flex gap-2 mb-6 flex-wrap">
        <?php
        $tabs = ['Pending','Approved','Returned','Rejected','All'];
        $tabColors = ['Pending'=>'bg-blue-600','Approved'=>'bg-emerald-600','Returned'=>'bg-slate-600','Rejected'=>'bg-red-500','All'=>'bg-slate-800'];
        foreach ($tabs as $t):
            $active = $filter === $t;
            $cnt = $counts[$t] ?? 0;
        ?>
        <a href="?filter=<?= $t ?>"
            class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all
            <?= $active ? $tabColors[$t].' text-white shadow-md' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
            <?= $t ?>
            <?php if ($cnt > 0): ?>
            <span class="<?= $active ? 'bg-white/30' : 'bg-slate-200' ?> text-xs px-1.5 py-0.5 rounded-full font-bold <?= $active ? 'text-white' : 'text-slate-700' ?>">
                <?= $cnt ?>
            </span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-4">Member</th>
                        <th class="px-6 py-4">Book</th>
                        <th class="px-6 py-4">Requested</th>
                        <th class="px-6 py-4">Due Date</th>
                        <th class="px-6 py-4">Fine</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php if (empty($requests)): ?>
                    <tr><td colspan="7" class="text-center py-16 text-slate-400">
                        <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
                        <p>No <?= strtolower($filter) ?> requests.</p>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($requests as $req):
                        $isOverdue = $req['status']==='Approved' && $req['due_date'] && !$req['returned_at'] && strtotime($req['due_date']) < time();
                        $statusColors = ['Pending'=>'bg-blue-100 text-blue-700','Approved'=>'bg-emerald-100 text-emerald-700','Rejected'=>'bg-red-100 text-red-700','Returned'=>'bg-slate-100 text-slate-600'];
                        $sc = $statusColors[$req['status']] ?? 'bg-slate-100 text-slate-600';
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4">
                            <div class="font-semibold text-slate-800"><?= htmlspecialchars($req['user_name'] ?: $req['username']) ?></div>
                            <div class="text-xs text-slate-400">@<?= htmlspecialchars($req['username']) ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-800"><?= htmlspecialchars($req['title']) ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($req['author']) ?></div>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-500"><?= date('d M Y', strtotime($req['requested_at'])) ?></td>
                        <td class="px-6 py-4 text-sm <?= $isOverdue ? 'text-red-600 font-bold' : 'text-slate-500' ?>">
                            <?= $req['due_date'] ? date('d M Y', strtotime($req['due_date'])) : '—' ?>
                            <?php if ($isOverdue): ?><div class="text-xs text-red-400 mt-0.5">⚠️ OVERDUE</div><?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($req['fine_amount'] > 0): ?>
                                <span class="text-red-600 font-bold">₹<?= number_format($req['fine_amount'],2) ?></span>
                            <?php else: ?><span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $sc ?>"><?= $req['status'] ?></span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                            <?php if ($req['status'] === 'Pending'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                                    <button type="submit" class="flex items-center gap-1 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs rounded-lg font-medium">
                                        <i data-lucide="check" class="w-3 h-3"></i> Approve
                                    </button>
                                </form>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                                    <button type="submit" class="flex items-center gap-1 px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white text-xs rounded-lg font-medium">
                                        <i data-lucide="x" class="w-3 h-3"></i> Reject
                                    </button>
                                </form>
                            <?php elseif ($req['status'] === 'Approved'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="action" value="return">
                                    <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                                    <button type="submit" class="flex items-center gap-1 px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white text-xs rounded-lg font-medium">
                                        <i data-lucide="rotate-ccw" class="w-3 h-3"></i> Mark Returned
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">No action</span>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<script>lucide.createIcons();</script>
</body>
</html>