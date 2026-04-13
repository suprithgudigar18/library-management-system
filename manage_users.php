<?php
session_start();
include("db_connect.php");

// Admin guard
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

$toast = '';
$toastType = 'success';

// ── Handle POST actions (Add / Edit / Delete) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            $toast = "Member deleted successfully.";
            $toastType = 'error';
        }

    } elseif ($action === 'add') {
        $name     = trim($_POST['name']       ?? '');
        $username = trim($_POST['username']   ?? '');
        $phone    = trim($_POST['phone']      ?? '');
        $dept     = trim($_POST['dept']       ?? '');
        $type     = trim($_POST['type']       ?? 'Student');
        $year     = trim($_POST['year']       ?? 'N/A');
        $status   = trim($_POST['status']     ?? 'Active');
        $password = $_POST['password']        ?? 'changeme123';

        $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $chk->execute([$username]);
        if ($chk->fetch()) {
            $toast = "Username already exists.";
            $toastType = 'error';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (name, username, email, password, role, status, dept, member_type, year)
                    VALUES (?, ?, ?, ?, 'user', ?, ?, ?, ?)";
            $pdo->prepare($sql)->execute([$name, $username, $phone, $hashed, $status, $dept, $type, $year]);
            $toast = "Member added successfully.";
        }

    } elseif ($action === 'edit') {
        $id     = (int)($_POST['id']    ?? 0);
        $name   = trim($_POST['name']   ?? '');
        $dept   = trim($_POST['dept']   ?? '');
        $type   = trim($_POST['type']   ?? '');
        $year   = trim($_POST['year']   ?? '');
        $status = trim($_POST['status'] ?? '');

        if ($id > 0) {
            $sql = "UPDATE users SET name=?, dept=?, member_type=?, year=?, status=? WHERE id=?";
            $pdo->prepare($sql)->execute([$name, $dept, $type, $year, $status, $id]);
            $toast = "Member updated successfully.";
        }
    }
}

// ── Fetch all users, sorted alphabetically by name ─────────────────────────
$users = $pdo->query("SELECT id, name, username, email, dept, member_type, year, status
                       FROM users
                       WHERE role='user'
                       ORDER BY name ASC")->fetchAll();

// ── Split into Faculty vs Students/Staff ──────────────────────────────────
// Treat "Teacher" and "Faculty" as the same group
$facultyTypes = ['Faculty', 'Teacher'];
$faculty  = array_filter($users, fn($u) => in_array($u['member_type'] ?? '', $facultyTypes));
$students = array_filter($users, fn($u) => !in_array($u['member_type'] ?? '', $facultyTypes));

// Helper functions
function getInitials($name) {
    $parts = explode(' ', trim($name));
    if (count($parts) >= 2) return strtoupper($parts[0][0] . $parts[1][0]);
    return strtoupper(substr($name, 0, 2));
}
$avatarColors = [
    'bg-blue-100 text-blue-700', 'bg-purple-100 text-purple-700',
    'bg-amber-100 text-amber-700', 'bg-emerald-100 text-emerald-700',
    'bg-rose-100 text-rose-700',  'bg-indigo-100 text-indigo-700',
];
function getStatusBadgeClasses($status) {
    return $status === 'Active'
        ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
        : 'bg-red-100 text-red-700 border-red-200';
}

// Render a table section (Faculty or Students)
function renderSection($rows, $avatarColors, $sectionIndex = 0) {
    if (empty($rows)): ?>
    <tr>
        <td colspan="6" class="text-center py-10 text-slate-400">
            <p class="text-sm">No members in this category yet.</p>
        </td>
    </tr>
    <?php return; endif;

    foreach ($rows as $i => $u):
        $initials  = getInitials($u['name'] ?: $u['username']);
        $avatarCls = $avatarColors[($sectionIndex + $i) % count($avatarColors)];
        $dept      = $u['dept']        ?? '—';
        $mtype     = $u['member_type'] ?? 'Student';
        $year      = $u['year']        ?? 'N/A';
        $status    = $u['status']      ?? 'Active';
        $dataJson  = htmlspecialchars(json_encode([
            'id'     => $u['id'],
            'name'   => $u['name'],
            'dept'   => $dept,
            'type'   => $mtype,
            'year'   => $year,
            'status' => $status,
        ]), ENT_QUOTES, 'UTF-8');
    ?>
    <tr class="hover:bg-slate-50 transition-colors group user-row"
        data-name="<?= strtolower(htmlspecialchars($u['name'] . ' ' . $u['username'])) ?>"
        data-dept="<?= strtolower(htmlspecialchars($dept)) ?>"
        data-type="<?= htmlspecialchars($mtype) ?>"
        data-status="<?= htmlspecialchars($status) ?>">

        <td class="px-6 py-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full <?= $avatarCls ?> flex items-center justify-center font-bold text-sm flex-shrink-0 border">
                    <?= $initials ?>
                </div>
                <div class="flex flex-col">
                    <span class="font-semibold text-slate-800"><?= htmlspecialchars($u['name'] ?: $u['username']) ?></span>
                    <span class="text-xs text-slate-400 font-mono">@<?= htmlspecialchars($u['username']) ?></span>
                </div>
            </div>
        </td>
        <td class="px-6 py-4 text-slate-600 text-sm"><?= htmlspecialchars($u['email'] ?: '—') ?></td>
        <td class="px-6 py-4 text-slate-700"><?= htmlspecialchars($dept) ?></td>
        <td class="px-6 py-4">
            <div class="flex flex-col">
                <span class="text-slate-700"><?= htmlspecialchars($mtype) ?></span>
                <span class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($year) ?></span>
            </div>
        </td>
        <td class="px-6 py-4">
            <span class="px-2.5 py-1 rounded-full text-xs font-medium border <?= getStatusBadgeClasses($status) ?>">
                <?= htmlspecialchars($status) ?>
            </span>
        </td>
        <td class="px-6 py-4 text-right">
            <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                <button onclick='openModal(<?= $dataJson ?>)'
                    class="p-2 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">
                    <i data-lucide="edit-2" class="w-4 h-4"></i>
                </button>
                <button onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name'] ?: $u['username'], ENT_QUOTES) ?>')"
                    class="p-2 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
            </div>
        </td>
    </tr>
    <?php endforeach;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - LIBRITE Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .toast { animation: slideIn .4s ease, fadeOut .5s ease 3s forwards; }
        @keyframes slideIn { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }
        @keyframes fadeOut { to { opacity:0; pointer-events:none; } }

        /* Section divider header row */
        .section-header-row td {
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
            border-top: 2px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        .section-header-row:first-child td {
            border-top: none;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">

<!-- ── TOAST ─────────────────────────────────────────────────────────────── -->
<?php if ($toast): ?>
<div class="toast fixed bottom-6 right-6 z-[9999] flex items-center gap-3 px-5 py-3 rounded-xl shadow-xl
    <?= $toastType === 'success' ? 'bg-emerald-600' : 'bg-red-500' ?> text-white font-medium text-sm">
    <i data-lucide="<?= $toastType === 'success' ? 'check-circle' : 'trash-2' ?>" class="w-5 h-5"></i>
    <?= htmlspecialchars($toast) ?>
</div>
<?php endif; ?>

<!-- ── TOP NAV ────────────────────────────────────────────────────────────── -->
<header class="bg-white border-b border-slate-200 sticky top-0 z-10 px-6 py-4 flex items-center justify-between shadow-sm">
    <a href="admin_dashboard.php" class="flex items-center gap-3 hover:opacity-80 transition-opacity no-underline">
        <div class="bg-blue-600 p-2 rounded-lg">
            <i data-lucide="users" class="w-6 h-6 text-white"></i>
        </div>
        <h1 class="text-xl font-bold text-slate-800">Librite Admin</h1>
    </a>
    <div class="flex items-center gap-4">
        <span class="text-sm text-slate-500 hidden sm:block">
            Welcome, <?= htmlspecialchars($_SESSION['username']) ?>
        </span>
        <a href="admin_dashboard.php" class="text-xs text-red-500 hover:underline font-bold">Back</a>
        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold border border-blue-200 text-xs">
            AD
        </div>
    </div>
</header>

<main class="p-6 max-w-7xl mx-auto">

    <!-- Toolbar -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Manage Users</h2>
            <p class="text-slate-500">
                All registered library members, sorted alphabetically. Total:
                <strong><?= count($users) ?></strong>
                &nbsp;·&nbsp;
                <span class="text-indigo-600 font-semibold"><?= count($faculty) ?> Faculty</span>
                &nbsp;·&nbsp;
                <span class="text-blue-600 font-semibold"><?= count($students) ?> Students / Staff</span>
            </p>
        </div>
        <button onclick="openModal()" class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-medium transition-colors shadow-sm">
            <i data-lucide="user-plus" class="w-5 h-5"></i> Add New Member
        </button>
    </div>

    <!-- Filters -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6 flex flex-col md:flex-row gap-4">
        <div class="relative flex-1">
            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 w-5 h-5"></i>
            <input type="text" id="searchInput" onkeyup="filterUsers()"
                placeholder="Search by name, username, or department..."
                class="w-full pl-10 pr-4 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <select id="typeFilter" onchange="filterUsers()"
            class="px-4 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
            <option value="All">All Types</option>
            <option value="Student">Student</option>
            <option value="Faculty">Faculty</option>
            <option value="Staff">Staff</option>
        </select>
        <select id="statusFilter" onchange="filterUsers()"
            class="px-4 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
            <option value="All">All Status</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
        </select>
    </div>

    <!-- ═══ SINGLE TABLE with two visual sections ════════════════════════════ -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" id="usersTable">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-4 font-semibold">Member Details</th>
                        <th class="px-6 py-4 font-semibold">Contact / Phone</th>
                        <th class="px-6 py-4 font-semibold">Department</th>
                        <th class="px-6 py-4 font-semibold">Type &amp; Year</th>
                        <th class="px-6 py-4 font-semibold">Status</th>
                        <th class="px-6 py-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="usersTbody">

                    <!-- ══ FACULTY SECTION ══════════════════════════════════ -->
                    <tr class="section-header-row" id="section-faculty-header">
                        <td colspan="6" class="px-6 py-3">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-indigo-100 flex items-center justify-center">
                                        <i data-lucide="graduation-cap" class="w-3.5 h-3.5 text-indigo-600"></i>
                                    </div>
                                    <span class="text-sm font-bold text-indigo-700 tracking-wide uppercase">Faculty Members</span>
                                </div>
                                <span class="text-xs bg-indigo-100 text-indigo-600 font-semibold px-2.5 py-0.5 rounded-full border border-indigo-200">
                                    <?= count($faculty) ?> member<?= count($faculty) !== 1 ? 's' : '' ?>
                                </span>
                                <span class="text-xs text-slate-400 ml-auto italic">Sorted A → Z</span>
                            </div>
                        </td>
                    </tr>
                    <?php renderSection($faculty, $avatarColors, 0); ?>

                    <!-- ══ STUDENTS / STAFF SECTION ═════════════════════════ -->
                    <tr class="section-header-row" id="section-students-header">
                        <td colspan="6" class="px-6 py-3">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center">
                                        <i data-lucide="book-open" class="w-3.5 h-3.5 text-blue-600"></i>
                                    </div>
                                    <span class="text-sm font-bold text-blue-700 tracking-wide uppercase">Students &amp; Staff</span>
                                </div>
                                <span class="text-xs bg-blue-100 text-blue-600 font-semibold px-2.5 py-0.5 rounded-full border border-blue-200">
                                    <?= count($students) ?> member<?= count($students) !== 1 ? 's' : '' ?>
                                </span>
                                <span class="text-xs text-slate-400 ml-auto italic">Sorted A → Z</span>
                            </div>
                        </td>
                    </tr>
                    <?php renderSection($students, $avatarColors, count($faculty)); ?>

                </tbody>
            </table>
        </div>
        <div class="px-6 py-3 border-t border-slate-200 bg-slate-50 text-sm text-slate-500 flex items-center justify-between">
            <span id="recordCount">Showing <?= count($users) ?> members</span>
            <span class="text-xs text-slate-400">Listed alphabetically within each section</span>
        </div>
    </div>
</main>

<!-- ══════════════════════════════════════════════════════════════
     ADD / EDIT MODAL
═══════════════════════════════════════════════════════════════ -->
<div id="userModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">

        <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
            <h2 id="modalTitle" class="text-xl font-bold text-slate-800">Add New Member</h2>
            <button onclick="closeModal()" class="p-2 hover:bg-slate-200 rounded-full transition-colors text-slate-500">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="p-6 overflow-y-auto">
            <form id="userForm" method="POST" class="space-y-4">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id"     id="inp_id"     value="">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                        <input name="name" id="inp_name" required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                            placeholder="e.g. John Doe">
                    </div>
                    <div id="usernameField">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Username <span class="text-red-500">*</span></label>
                        <input name="username" id="inp_username"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                            placeholder="e.g. johndoe">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="addOnlyFields">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number</label>
                        <input name="phone" id="inp_phone" type="tel" maxlength="10"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                            placeholder="10-digit number">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Initial Password</label>
                        <input name="password" id="inp_password" type="text"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"
                            placeholder="Default: changeme123" value="changeme123">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Department</label>
                    <select name="dept" id="inp_dept"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                        <option value="">— Select —</option>
                        <option>Computer Science</option>
                        <option>Mechanical Eng.</option>
                        <option>Civil Eng.</option>
                        <option>English Literature</option>
                        <option>Mathematics</option>
                        <option>Physics</option>
                        <option>Administration</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Member Type</label>
                        <select name="type" id="inp_type"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                            <option>Student</option><option>Faculty</option><option>Staff</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Year / Level</label>
                        <select name="year" id="inp_year"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                            <option>1st Year</option><option>2nd Year</option>
                            <option>3rd Year</option><option>4th Year</option><option>N/A</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select name="status" id="inp_status"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                            <option>Active</option><option>Inactive</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-3">
            <button type="button" onclick="closeModal()"
                class="px-4 py-2 text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg font-medium">Cancel</button>
            <button type="submit" form="userForm"
                class="flex items-center gap-2 px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium shadow-sm">
                <i data-lucide="save" class="w-4 h-4"></i> Save Member
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     DELETE CONFIRM MODAL
═══════════════════════════════════════════════════════════════ -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">
        <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="trash-2" class="w-7 h-7 text-red-600"></i>
        </div>
        <h3 class="text-lg font-bold text-slate-800 mb-2">Delete Member?</h3>
        <p class="text-slate-500 mb-6">You are about to delete <strong id="deleteName"></strong>. This cannot be undone.</p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteId">
            <div class="flex justify-center gap-3">
                <button type="button" onclick="closeDeleteModal()"
                    class="px-5 py-2 border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50 font-medium">Cancel</button>
                <button type="submit"
                    class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
lucide.createIcons();

// ── Modal: Add / Edit ──────────────────────────────────────────────────────
function openModal(user = null) {
    const modal   = document.getElementById('userModal');
    const addOnly = document.getElementById('addOnlyFields');
    const uField  = document.getElementById('usernameField');
    modal.classList.remove('hidden');

    if (user) {
        document.getElementById('modalTitle').innerText = 'Edit Member';
        document.getElementById('formAction').value    = 'edit';
        document.getElementById('inp_id').value        = user.id;
        document.getElementById('inp_name').value      = user.name;
        document.getElementById('inp_dept').value      = user.dept;
        document.getElementById('inp_type').value      = user.type;
        document.getElementById('inp_year').value      = user.year;
        document.getElementById('inp_status').value    = user.status;
        addOnly.classList.add('hidden');
        uField.classList.add('hidden');
        document.getElementById('inp_username').removeAttribute('required');
    } else {
        document.getElementById('modalTitle').innerText = 'Add New Member';
        document.getElementById('formAction').value    = 'add';
        document.getElementById('inp_id').value        = '';
        document.getElementById('userForm').reset();
        addOnly.classList.remove('hidden');
        uField.classList.remove('hidden');
        document.getElementById('inp_username').setAttribute('required', 'required');
        document.getElementById('inp_password').value = 'changeme123';
    }
}

function closeModal() {
    document.getElementById('userModal').classList.add('hidden');
}

// ── Modal: Delete ──────────────────────────────────────────────────────────
function confirmDelete(id, name) {
    document.getElementById('deleteId').value      = id;
    document.getElementById('deleteName').innerText = name;
    document.getElementById('deleteModal').classList.remove('hidden');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

['userModal','deleteModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.classList.add('hidden');
    });
});

// ── Search & Filter ────────────────────────────────────────────────────────
function filterUsers() {
    const search  = document.getElementById('searchInput').value.toLowerCase();
    const status  = document.getElementById('statusFilter').value;
    const type    = document.getElementById('typeFilter').value;
    const rows    = document.querySelectorAll('.user-row');
    let visible   = 0;

    rows.forEach(row => {
        const matchSearch = row.dataset.name.includes(search) || row.dataset.dept.includes(search);
        const matchStatus = status === 'All' || row.dataset.status === status;
        const matchType   = type   === 'All' || row.dataset.type   === type;

        if (matchSearch && matchStatus && matchType) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    // Show/hide section headers smartly
    toggleSectionHeader('section-faculty-header',  'Faculty',  type, search, status);
    toggleSectionHeader('section-students-header', 'Students', type, search, status);

    document.getElementById('recordCount').innerText = `Showing ${visible} members`;
}

function toggleSectionHeader(headerId, sectionType, typeFilter, search, statusFilter) {
    const header = document.getElementById(headerId);
    if (!header) return;

    // Check if any visible rows belong to this section
    const rows = document.querySelectorAll('.user-row');
    let hasVisible = false;

    const facultyTypes = ['Faculty', 'Teacher'];
    rows.forEach(row => {
        const isFacultySection  = sectionType === 'Faculty'  && facultyTypes.includes(row.dataset.type);
        const isStudentSection  = sectionType === 'Students' && !facultyTypes.includes(row.dataset.type);
        const matchSearch  = row.dataset.name.includes(search) || row.dataset.dept.includes(search);
        const matchStatus  = statusFilter === 'All' || row.dataset.status === statusFilter;
        const matchType    = typeFilter   === 'All' || row.dataset.type   === typeFilter;

        if ((isFacultySection || isStudentSection) && matchSearch && matchStatus && matchType) {
            hasVisible = true;
        }
    });

    header.style.display = hasVisible ? '' : 'none';
}
</script>
</body>
</html>