<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id'])) { header("Location: user_login.php"); exit(); }

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];

// ── Handle Profile Photo Upload ──────────────────────────────────────────────
$uploadMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $file    = $_FILES['profile_photo'];
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (in_array($file['type'], $allowed) && $file['size'] < 2 * 1024 * 1024) {
        $ext   = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fname = "user_{$user_id}_" . time() . ".$ext";
        $dest  = "uploads/profiles/$fname";
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $pdo->prepare("UPDATE users SET profile_photo=? WHERE id=?")->execute([$dest, $user_id]);
            $uploadMsg = 'photo_ok';
        }
    } else {
        $uploadMsg = 'photo_err';
    }
}

// ── Handle Profile Details Save ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $full_name  = trim($_POST['full_name']  ?? '');
    $reg_no     = trim($_POST['reg_no']     ?? '');
    $department = trim($_POST['department'] ?? '');
    $dob        = $_POST['dob']             ?? null;
    $address    = trim($_POST['address']    ?? '');
    $phone      = trim($_POST['phone']      ?? '');
    $pdo->prepare("UPDATE users SET full_name=?, reg_no=?, department=?, dob=?, address=?, phone=? WHERE id=?")
        ->execute([$full_name, $reg_no, $department, $dob ?: null, $address, $phone, $user_id]);
    $uploadMsg = 'profile_ok';
}

// ── Fetch current user data ───────────────────────────────────────────────────
$userData = $pdo->prepare("SELECT * FROM users WHERE id=?");
$userData->execute([$user_id]);
$userData = $userData->fetch();
$profilePhoto = $userData['profile_photo'] ?? '';

// ── Handle Book Request ───────────────────────────────────────────────────────
$requestMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_book_id'])) {
    $book_id = (int)$_POST['request_book_id'];
    $chk = $pdo->prepare("SELECT id FROM book_requests WHERE user_id=? AND book_id=? AND status IN ('Pending','Approved')");
    $chk->execute([$user_id, $book_id]);
    if ($chk->fetch()) {
        $requestMsg = 'already_requested';
    } else {
        $pdo->prepare("INSERT INTO book_requests (user_id, book_id, status) VALUES (?,?,'Pending')")
            ->execute([$user_id, $book_id]);
        $requestMsg = 'success';
    }
}

// ── Fetch books ───────────────────────────────────────────────────────────────
$books = $pdo->query("SELECT * FROM books ORDER BY title ASC")->fetchAll();

// ── Fetch user's requests ─────────────────────────────────────────────────────
$myRequests = $pdo->prepare("
    SELECT br.*, b.title, b.author, b.shelf
    FROM book_requests br
    JOIN books b ON b.id = br.book_id
    WHERE br.user_id = ?
    ORDER BY br.requested_at DESC
    LIMIT 10
");
$myRequests->execute([$user_id]);
$myRequests = $myRequests->fetchAll();

// ── Auto-calculate fines ──────────────────────────────────────────────────────
$today = new DateTime();
foreach ($myRequests as &$req) {
    if ($req['status'] === 'Approved' && $req['due_date'] && !$req['returned_at']) {
        $due = new DateTime($req['due_date']);
        if ($today > $due) {
            $overdueDays = (int)$today->diff($due)->days;
            $fine = 20 + (floor(($overdueDays - 1) / 2) * 10);
            $pdo->prepare("UPDATE book_requests SET fine_amount=? WHERE id=?")->execute([$fine, $req['id']]);
            $req['fine_amount'] = $fine;
        }
    }
}
unset($req);

// ── New approvals popup ───────────────────────────────────────────────────────
$newApprovals = $pdo->prepare("
    SELECT br.id, b.title, br.due_date
    FROM book_requests br
    JOIN books b ON b.id = br.book_id
    WHERE br.user_id = ? AND br.status = 'Approved' AND (br.notified IS NULL OR br.notified = 0)
");
$newApprovals->execute([$user_id]);
$newApprovals = $newApprovals->fetchAll();
foreach ($newApprovals as $na) {
    $pdo->prepare("UPDATE book_requests SET notified=1 WHERE id=?")->execute([$na['id']]);
}
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
        :root {
            --bg:#02040a; --card:#0d1117; --card2:#161b22; --border:#30363d;
            --accent:#238636; --muted:#8b949e; --main:#c9d1d9;
            --err:#f85149; --warn:#e3b341; --blue:#58a6ff;
        }
        * { box-sizing:border-box; }
        body { background:var(--bg); color:var(--main); font-family:'Space Grotesk',sans-serif; margin:0; }
        .card  { background:var(--card);  border:1px solid var(--border); border-radius:12px; }
        .dark-input { background:var(--card); border:1px solid var(--border); color:white; border-radius:6px; padding:8px 12px; outline:none; width:100%; }
        .dark-input:focus { border-color:var(--blue); }
        .badge { padding:2px 10px; border-radius:20px; font-size:.75rem; font-weight:600; display:inline-block; }
        .badge-available { background:rgba(35,134,54,.15);  color:#3fb950; border:1px solid rgba(63,185,80,.3); }
        .badge-borrowed  { background:rgba(210,153,34,.15); color:#d29922; border:1px solid rgba(210,153,34,.3); }
        .badge-lost      { background:rgba(248,81,73,.15);  color:#f85149; border:1px solid rgba(248,81,73,.3); }
        .badge-pending   { background:rgba(88,166,255,.15); color:#58a6ff; border:1px solid rgba(88,166,255,.3); }
        .badge-approved  { background:rgba(35,134,54,.15);  color:#3fb950; border:1px solid rgba(63,185,80,.3); }
        .badge-rejected  { background:rgba(248,81,73,.15);  color:#f85149; border:1px solid rgba(248,81,73,.3); }
        .btn-req  { background:transparent; border:1px solid var(--blue); color:var(--blue); padding:4px 14px; border-radius:6px; font-size:.8rem; font-weight:500; cursor:pointer; transition:all .2s; }
        .btn-req:hover { background:var(--blue); color:white; }
        .btn-req:disabled { opacity:.4; cursor:not-allowed; }
        th { background:#161b22; padding:12px 20px; color:var(--muted); font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; border-bottom:1px solid var(--border); }
        td { padding:14px 20px; border-bottom:1px solid var(--border); vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tr:hover { background:rgba(255,255,255,.02); }
        .modal-bg { background:rgba(0,0,0,.85); backdrop-filter:blur(6px); }
        .approval-popup { animation:popIn .4s cubic-bezier(0.175,0.885,0.32,1.275); }
        @keyframes popIn { from{transform:scale(.8) translateY(30px);opacity:0} to{transform:scale(1) translateY(0);opacity:1} }
        .tab-active   { border-bottom:2px solid var(--blue); color:var(--blue); }
        .tab-inactive { border-bottom:2px solid transparent; color:var(--muted); }

        /* ── Profile Slide Panel ── */
        #profilePanel {
            position:fixed; top:0; right:-440px; width:420px; height:100vh;
            background:var(--card); border-left:1px solid var(--border);
            z-index:9000; overflow-y:auto; transition:right .35s cubic-bezier(.4,0,.2,1);
            display:flex; flex-direction:column;
        }
        #profilePanel.open { right:0; box-shadow:-20px 0 60px rgba(0,0,0,.6); }
        #panelOverlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:8999; backdrop-filter:blur(2px); }
        #panelOverlay.open { display:block; }

        /* Avatar with ring */
        .avatar-ring {
            width:96px; height:96px; border-radius:50%;
            background:conic-gradient(#238636 0deg, #3fb950 120deg, #58a6ff 240deg, #238636 360deg);
            padding:3px; position:relative; cursor:pointer; flex-shrink:0;
        }
        .avatar-inner { width:100%; height:100%; border-radius:50%; overflow:hidden; background:#21262d; display:flex; align-items:center; justify-content:center; }
        .avatar-overlay {
            position:absolute; inset:0; border-radius:50%;
            background:rgba(0,0,0,.55); display:flex; align-items:center; justify-content:center;
            opacity:0; transition:opacity .2s;
        }
        .avatar-ring:hover .avatar-overlay { opacity:1; }

        .profile-hero {
            background:linear-gradient(135deg, #0d2818 0%, #0d1117 50%, #0d1526 100%);
            border-bottom:1px solid var(--border);
            padding:28px 24px 22px; position:relative; overflow:hidden;
        }
        .profile-hero::before {
            content:''; position:absolute; top:-40px; right:-40px;
            width:200px; height:200px; border-radius:50%;
            background:radial-gradient(circle, rgba(35,134,54,.12), transparent 70%);
            pointer-events:none;
        }

        .info-row { display:flex; align-items:flex-start; gap:12px; padding:11px 0; border-bottom:1px solid rgba(48,54,61,.5); }
        .info-row:last-child { border-bottom:none; }
        .info-label { color:var(--muted); font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; margin-bottom:2px; }
        .info-value { color:var(--main); font-size:.875rem; font-weight:500; }
        .info-icon { width:32px; height:32px; border-radius:8px; background:rgba(88,166,255,.1); border:1px solid rgba(88,166,255,.15); display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:2px; }

        .btn-save   { background:#238636; color:white; border:none; padding:9px 20px; border-radius:8px; font-weight:600; cursor:pointer; font-size:.875rem; transition:background .2s; }
        .btn-save:hover { background:#2ea043; }
        .btn-cancel { background:transparent; color:var(--muted); border:1px solid var(--border); padding:9px 16px; border-radius:8px; font-weight:500; cursor:pointer; font-size:.875rem; transition:all .2s; }
        .btn-cancel:hover { border-color:var(--main); color:var(--main); }

        .file-input-label { display:flex; align-items:center; gap:8px; padding:8px 14px; border:1px dashed var(--border); border-radius:8px; cursor:pointer; font-size:.82rem; color:var(--muted); transition:all .2s; flex:1; }
        .file-input-label:hover { border-color:var(--blue); color:var(--blue); }
        input[type=file] { display:none; }

        #toast { position:fixed; bottom:24px; left:50%; transform:translateX(-50%) translateY(60px); opacity:0; transition:all .3s; z-index:9999; white-space:nowrap; border-radius:999px; padding:10px 22px; font-size:.875rem; font-weight:600; }
        #toast.show { transform:translateX(-50%) translateY(0); opacity:1; }

        .header-avatar { width:36px; height:36px; border-radius:50%; overflow:hidden; display:flex; align-items:center; justify-content:center; cursor:pointer; border:2px solid var(--accent); font-weight:700; font-size:.82rem; background:#21262d; transition:border-color .2s, transform .2s; }
        .header-avatar:hover { border-color:#3fb950; transform:scale(1.08); }

        @media(max-width:480px){ #profilePanel { width:100vw; right:-100vw; } }
    </style>
</head>
<body>

<!-- TOAST -->
<div id="toast"></div>

<?php if ($uploadMsg === 'photo_ok'): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast('✅ Profile photo updated!','#238636'));</script>
<?php elseif ($uploadMsg === 'profile_ok'): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast('✅ Profile saved successfully!','#238636'));</script>
<?php elseif ($uploadMsg === 'photo_err'): ?>
<script>document.addEventListener('DOMContentLoaded',()=>showToast('❌ Use JPG/PNG/GIF under 2MB.','#f85149'));</script>
<?php endif; ?>

<!-- APPROVAL POPUP -->
<?php if (!empty($newApprovals)): ?>
<div id="approvalOverlay" class="fixed inset-0 modal-bg z-[9999] flex items-center justify-center p-4">
  <div class="approval-popup card max-w-md w-full p-6 text-center">
    <div class="w-16 h-16 rounded-full bg-emerald-900/40 border border-emerald-500/30 flex items-center justify-center mx-auto mb-4">
        <i data-lucide="check-circle-2" class="w-8 h-8 text-emerald-400"></i>
    </div>
    <h2 class="text-xl font-bold text-white mb-2">🎉 Request Approved!</h2>
    <?php foreach ($newApprovals as $na): ?>
    <p class="text-[var(--muted)] mb-1">
        <strong class="text-white"><?= htmlspecialchars($na['title']) ?></strong> has been approved.
        <?php if ($na['due_date']): ?>
        <br><span class="text-yellow-400 text-sm">Return by: <strong><?= date('d M Y', strtotime($na['due_date'])) ?></strong></span>
        <?php endif; ?>
    </p>
    <?php endforeach; ?>
    <p class="text-xs text-[var(--muted)] mt-3 mb-5">Please collect your book from the library counter. Return within 4 days to avoid fines.</p>
    <button onclick="document.getElementById('approvalOverlay').remove()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-8 py-2 rounded-lg font-bold">Got it!</button>
  </div>
</div>
<?php endif; ?>

<!-- PANEL OVERLAY -->
<div id="panelOverlay" onclick="closeProfile()"></div>

<!-- ══════════════════════ PROFILE SLIDE PANEL ══════════════════════════════ -->
<div id="profilePanel">

    <!-- Hero -->
    <div class="profile-hero">
        <div class="flex items-start justify-between mb-5">
            <span class="text-xs font-semibold uppercase tracking-widest" style="color:var(--muted)">My Profile</span>
            <button onclick="closeProfile()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/10 transition-colors">
                <i data-lucide="x" class="w-4 h-4" style="color:var(--muted)"></i>
            </button>
        </div>

        <div class="flex items-end gap-4">
            <!-- Avatar -->
            <div class="avatar-ring" onclick="switchProfileTab('edit');document.getElementById('photoFileInput').click()" title="Change photo">
                <div class="avatar-inner">
                    <?php if ($profilePhoto && file_exists($profilePhoto)): ?>
                        <img src="<?= htmlspecialchars($profilePhoto) ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <span class="text-2xl font-bold text-white"><?= strtoupper(substr($userData['full_name'] ?: $username, 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="avatar-overlay">
                    <i data-lucide="camera" class="w-5 h-5 text-white"></i>
                </div>
            </div>

            <div class="flex-1 min-w-0">
                <h3 class="text-white font-bold text-lg leading-tight truncate">
                    <?= htmlspecialchars($userData['full_name'] ?: $username) ?>
                </h3>
                <?php if (!empty($userData['reg_no'])): ?>
                <p class="text-xs font-mono mt-0.5" style="color:var(--muted)"><?= htmlspecialchars($userData['reg_no']) ?></p>
                <?php endif; ?>
                <?php if (!empty($userData['department'])): ?>
                <span class="text-xs px-2 py-0.5 rounded-full mt-1.5 inline-block" style="background:rgba(35,134,54,.2);color:#3fb950;border:1px solid rgba(35,134,54,.3)">
                    <?= htmlspecialchars($userData['department']) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mini stats -->
        <?php
        $totalReqs   = count($myRequests);
        $activeBooks = count(array_filter($myRequests, fn($r) => $r['status'] === 'Approved' && !($r['returned_at'] ?? null)));
        $totalFine   = array_sum(array_column(array_filter($myRequests, fn($r) => ($r['fine_amount'] ?? 0) > 0), 'fine_amount'));
        ?>
        <div class="grid grid-cols-3 gap-2 mt-5">
            <div class="text-center p-2.5 rounded-lg" style="background:rgba(255,255,255,.04);border:1px solid var(--border)">
                <div class="text-lg font-bold text-white"><?= $totalReqs ?></div>
                <div class="text-[10px]" style="color:var(--muted)">Total Req.</div>
            </div>
            <div class="text-center p-2.5 rounded-lg" style="background:rgba(255,255,255,.04);border:1px solid var(--border)">
                <div class="text-lg font-bold text-emerald-400"><?= $activeBooks ?></div>
                <div class="text-[10px]" style="color:var(--muted)">Holding</div>
            </div>
            <div class="text-center p-2.5 rounded-lg" style="background:rgba(255,255,255,.04);border:1px solid var(--border)">
                <div class="text-lg font-bold <?= $totalFine > 0 ? 'text-red-400' : 'text-white' ?>">
                    ₹<?= $totalFine ?>
                </div>
                <div class="text-[10px]" style="color:var(--muted)">Fine Due</div>
            </div>
        </div>
    </div>

    <!-- Profile sub-tabs -->
    <div class="flex border-b" style="border-color:var(--border)">
        <button onclick="switchProfileTab('view')" id="ptab-view" class="flex-1 py-3 text-sm font-medium tab-active transition-colors">
            <i data-lucide="user" class="inline w-3.5 h-3.5 mr-1"></i>Details
        </button>
        <button onclick="switchProfileTab('edit')" id="ptab-edit" class="flex-1 py-3 text-sm font-medium tab-inactive transition-colors">
            <i data-lucide="pencil" class="inline w-3.5 h-3.5 mr-1"></i>Edit Profile
        </button>
    </div>

    <!-- VIEW pane -->
    <div id="ppane-view" class="p-5">
        <?php
        $fields = [
            ['icon'=>'user',          'label'=>'Full Name',       'val'=>$userData['full_name']  ?? '—'],
            ['icon'=>'hash',          'label'=>'Register No.',    'val'=>$userData['reg_no']     ?? '—'],
            ['icon'=>'graduation-cap','label'=>'Department',      'val'=>$userData['department'] ?? '—'],
            ['icon'=>'calendar',      'label'=>'Date of Birth',   'val'=>!empty($userData['dob']) ? date('d M Y', strtotime($userData['dob'])) : '—'],
            ['icon'=>'phone',         'label'=>'Phone',           'val'=>$userData['phone']      ?? '—'],
            ['icon'=>'mail',          'label'=>'Username / Email','val'=>$username],
            ['icon'=>'map-pin',       'label'=>'Address',         'val'=>$userData['address']    ?? '—'],
        ];
        foreach ($fields as $f): ?>
        <div class="info-row">
            <div class="info-icon">
                <i data-lucide="<?= $f['icon'] ?>" class="w-3.5 h-3.5" style="color:#58a6ff"></i>
            </div>
            <div>
                <div class="info-label"><?= $f['label'] ?></div>
                <div class="info-value"><?= htmlspecialchars($f['val']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="mt-6 pt-5" style="border-top:1px solid var(--border)">
            <a href="index.php" class="flex items-center gap-2 text-sm font-medium text-red-400 hover:text-red-300 transition-colors">
                <i data-lucide="log-out" class="w-4 h-4"></i> Logout
            </a>
        </div>
    </div>

    <!-- EDIT pane -->
    <div id="ppane-edit" class="p-5 hidden">

        <!-- Photo upload -->
        <form method="POST" enctype="multipart/form-data" class="mb-5 pb-5" style="border-bottom:1px solid var(--border)">
            <p class="text-xs font-semibold uppercase tracking-wider mb-2.5" style="color:var(--muted)">Profile Photo</p>
            <div class="flex items-center gap-3">
                <label class="file-input-label" for="photoFileInput">
                    <i data-lucide="upload" class="w-4 h-4"></i>
                    <span id="fileLabel">Choose image (max 2MB)</span>
                </label>
                <input type="file" id="photoFileInput" name="profile_photo" accept="image/*" onchange="previewPhoto(this)">
                <button type="submit" class="btn-save whitespace-nowrap">Upload</button>
            </div>
            <div id="photoPreviewWrap" class="hidden mt-3 flex items-center gap-3">
                <img id="photoPreviewImg" src="" class="w-12 h-12 rounded-full object-cover border border-gray-600">
                <span class="text-xs" style="color:var(--muted)">Preview — click Upload to save</span>
            </div>
        </form>

        <!-- Details form -->
        <form method="POST">
            <input type="hidden" name="save_profile" value="1">
            <div class="space-y-4">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Full Name</label>
                        <input type="text" name="full_name" class="dark-input" placeholder="Your full name"
                            value="<?= htmlspecialchars($userData['full_name'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Register No.</label>
                        <input type="text" name="reg_no" class="dark-input" placeholder="e.g. 22CS045"
                            value="<?= htmlspecialchars($userData['reg_no'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Department</label>
                    <select name="department" class="dark-input">
                        <option value="">— Select Department —</option>
                        <?php
                        $depts = ['BCA','Information Technology','BCom',
                                  'Electrical Engineering','Mechanical Engineering','Civil Engineering',
                                  'BBA','Biotechnology','Physics','Mathematics','MBA','MCA','Other'];
                        foreach ($depts as $d): ?>
                        <option value="<?= $d ?>" <?= ($userData['department'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Date of Birth</label>
                        <input type="date" name="dob" class="dark-input"
                            value="<?= htmlspecialchars($userData['dob'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Phone</label>
                        <input type="text" name="phone" class="dark-input" placeholder="+91 9876543210"
                            value="<?= htmlspecialchars($userData['phone'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color:var(--muted)">Address</label>
                    <textarea name="address" rows="3" class="dark-input resize-none" placeholder="Your residential address"><?= htmlspecialchars($userData['address'] ?? '') ?></textarea>
                </div>

            </div>

            <div class="flex gap-3 mt-5">
                <button type="submit" class="btn-save flex-1">
                    <i data-lucide="save" class="inline w-4 h-4 mr-1.5"></i>Save Changes
                </button>
                <button type="button" class="btn-cancel" onclick="switchProfileTab('view')">Cancel</button>
            </div>
        </form>
    </div>

</div><!-- /profilePanel -->


<!-- ══ HEADER ════════════════════════════════════════════════════════════════ -->
<header style="background:var(--card);border-bottom:1px solid var(--border)" class="sticky top-0 z-50 px-6 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div style="background:#238636" class="p-2 rounded-lg"><i data-lucide="book-open" class="w-5 h-5 text-white"></i></div>
        <span class="font-bold text-white text-lg">LIBRITE</span>
    </div>
    <div class="flex items-center gap-4">
        <?php $pendingCount = count(array_filter($myRequests, fn($r) => $r['status']==='Pending')); ?>
        <?php if ($pendingCount > 0): ?>
        <span class="flex items-center gap-1 text-xs bg-yellow-500/20 text-yellow-400 border border-yellow-500/30 px-2 py-1 rounded-full">
            <i data-lucide="clock" class="w-3 h-3"></i> <?= $pendingCount ?> pending
        </span>
        <?php endif; ?>
        <!-- Header avatar — opens profile panel -->
        <div class="header-avatar" onclick="openProfile()" title="My Profile">
            <?php if ($profilePhoto && file_exists($profilePhoto)): ?>
                <img src="<?= htmlspecialchars($profilePhoto) ?>" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
                <?= strtoupper(substr($userData['full_name'] ?: $username, 0, 1)) ?>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- ══ MAIN ══════════════════════════════════════════════════════════════════ -->
<div class="max-w-6xl mx-auto px-4 py-8">

<div class="flex flex-col md:flex-row justify-between items-start gap-4 mb-8 pb-6" style="border-bottom:1px solid var(--border)">
    <div>
        <h1 class="text-2xl font-bold text-white">Welcome back, <?= htmlspecialchars($userData['name'] ?: $username) ?> 👋</h1>
        <p class="italic mt-1 text-sm" style="color:var(--muted)">"A reader lives a thousand lives before he dies."</p>
        <div class="flex gap-3 mt-3 flex-wrap">
            <div class="text-xs px-3 py-1.5 rounded" style="background:rgba(227,179,65,.1);border-left:3px solid var(--warn)">📅 Loan limit: <strong>4 days</strong></div>
            <div class="text-xs px-3 py-1.5 rounded" style="background:rgba(248,81,73,.1);border-left:3px solid var(--err)">💰 Fine: ₹20 after due date, +₹10 per 2 extra days</div>
        </div>
    </div>
</div>

<!-- TABS -->
<div class="flex gap-6 mb-6 border-b" style="border-color:var(--border)">
    <button onclick="switchTab('browse')"   id="tab-browse"   class="tab-btn pb-3 text-sm font-medium tab-active">📚 Browse Books</button>
    <button onclick="switchTab('requests')" id="tab-requests" class="tab-btn pb-3 text-sm font-medium tab-inactive">🗂 My Requests (<?= count($myRequests) ?>)</button>
</div>

<!-- BROWSE pane -->
<div id="pane-browse">
    <?php if ($requestMsg === 'success'): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium" style="background:rgba(35,134,54,.15);border:1px solid rgba(63,185,80,.3);color:#3fb950">
        ✅ Request submitted! The admin will review it shortly.
    </div>
    <?php elseif ($requestMsg === 'already_requested'): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium" style="background:rgba(248,81,73,.1);border:1px solid rgba(248,81,73,.3);color:#f85149">
        ⚠️ You already have an active request for that book.
    </div>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row gap-3 mb-5">
        <div class="relative flex-1">
            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color:var(--muted)"></i>
            <input type="text" id="searchInput" onkeyup="filterBooks()" placeholder="Search title, author, ISBN..." class="dark-input pl-9 py-2.5">
        </div>
        <select id="statusFilter" onchange="filterBooks()" class="dark-input" style="width:auto">
            <option value="All">All Status</option><option>Available</option><option>Borrowed</option><option>Lost</option>
        </select>
        <select id="catFilter" onchange="filterBooks()" class="dark-input" style="width:auto">
            <option value="All">All Categories</option>
            <option>Fiction</option><option>Non-Fiction</option><option>Reference</option><option>Academic</option>
        </select>
    </div>

    <?php if (empty($books)): ?>
    <div class="card p-12 text-center" style="color:var(--muted)">
        <i data-lucide="book" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
        <p>No books in the library yet. Check back soon!</p>
    </div>
    <?php else:
    $requestedBookIds = array_column(
        array_filter($myRequests, fn($r) => in_array($r['status'], ['Pending','Approved'])),
        'book_id'
    );
    ?>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr>
                    <th class="text-left">Book Details</th>
                    <th class="text-left">Category</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Location</th>
                    <th class="text-left">Action</th>
                </tr></thead>
                <tbody id="booksBody">
                <?php foreach ($books as $b):
                    $alreadyReq = in_array($b['id'], $requestedBookIds);
                    $canRequest = $b['status'] === 'Available' && !$alreadyReq;
                ?>
                <tr class="book-row"
                    data-title="<?= strtolower(htmlspecialchars($b['title'])) ?>"
                    data-author="<?= strtolower(htmlspecialchars($b['author'])) ?>"
                    data-isbn="<?= strtolower(htmlspecialchars($b['isbn'] ?? '')) ?>"
                    data-status="<?= htmlspecialchars($b['status']) ?>"
                    data-cat="<?= htmlspecialchars($b['category'] ?? '') ?>">
                    <td>
                        <div class="font-bold text-white"><?= htmlspecialchars($b['title']) ?></div>
                        <div class="text-xs" style="color:var(--muted)"><?= htmlspecialchars($b['author']) ?></div>
                        <div class="text-[10px] font-mono mt-1" style="color:#484f58">ISBN: <?= htmlspecialchars($b['isbn'] ?? '—') ?></div>
                    </td>
                    <td>
                        <div class="text-sm" style="color:var(--muted)"><?= htmlspecialchars($b['category'] ?? '—') ?></div>
                        <div class="text-[10px] px-1.5 py-0.5 rounded w-fit mt-1" style="background:#21262d;color:var(--muted)"><?= htmlspecialchars($b['genre'] ?? '') ?></div>
                    </td>
                    <td><span class="badge badge-<?= strtolower($b['status']) ?>"><?= $b['status'] ?></span></td>
                    <td class="text-sm" style="color:var(--muted)">
                        <i data-lucide="map-pin" class="inline w-3 h-3 mr-1 opacity-50"></i>
                        <?= htmlspecialchars($b['shelf'] ?? '—') ?>
                    </td>
                    <td>
                        <?php if ($alreadyReq): ?>
                            <span class="badge badge-pending">Requested</span>
                        <?php else: ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="request_book_id" value="<?= $b['id'] ?>">
                                <button type="submit" class="btn-req" <?= !$canRequest ? 'disabled' : '' ?>>
                                    <?= $canRequest ? 'Request' : 'Unavailable' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3 text-sm flex justify-between items-center" style="border-top:1px solid var(--border);color:var(--muted)">
            <span id="recordCount">Showing <?= count($books) ?> books</span>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- MY REQUESTS pane -->
<div id="pane-requests" class="hidden">
    <?php if (empty($myRequests)): ?>
    <div class="card p-12 text-center" style="color:var(--muted)">
        <i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
        <p>No requests yet. Browse books and request one!</p>
    </div>
    <?php else: ?>
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead><tr>
                    <th class="text-left">Book</th>
                    <th class="text-left">Requested</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Due Date</th>
                    <th class="text-left">Fine</th>
                </tr></thead>
                <tbody>
                <?php foreach ($myRequests as $req):
                    $isOverdue = $req['status']==='Approved' && $req['due_date'] && !$req['returned_at'] && strtotime($req['due_date']) < time();
                ?>
                <tr>
                    <td>
                        <div class="font-semibold text-white"><?= htmlspecialchars($req['title']) ?></div>
                        <div class="text-xs" style="color:var(--muted)"><?= htmlspecialchars($req['author']) ?></div>
                    </td>
                    <td class="text-sm" style="color:var(--muted)"><?= date('d M Y', strtotime($req['requested_at'])) ?></td>
                    <td><span class="badge badge-<?= strtolower($req['status']) ?>"><?= $req['status'] ?></span></td>
                    <td class="text-sm <?= $isOverdue ? 'text-red-400 font-bold' : '' ?>" style="<?= !$isOverdue ? 'color:var(--muted)' : '' ?>">
                        <?= $req['due_date'] ? date('d M Y', strtotime($req['due_date'])) : '—' ?>
                        <?php if ($isOverdue): ?><span class="ml-1 text-xs">⚠️ Overdue</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if (($req['fine_amount'] ?? 0) > 0): ?>
                            <span class="text-red-400 font-bold">₹<?= number_format($req['fine_amount'], 2) ?></span>
                        <?php else: ?>
                            <span style="color:var(--muted)">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

</div><!-- /container -->

<script>
lucide.createIcons();

// ── Profile panel ──────────────────────────────────────────────────────────
function openProfile() {
    document.getElementById('profilePanel').classList.add('open');
    document.getElementById('panelOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
    lucide.createIcons();
}
function closeProfile() {
    document.getElementById('profilePanel').classList.remove('open');
    document.getElementById('panelOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

function switchProfileTab(t) {
    ['view','edit'].forEach(name => {
        document.getElementById('ppane-'+name).classList.toggle('hidden', name !== t);
        const btn = document.getElementById('ptab-'+name);
        btn.classList.toggle('tab-active',   name === t);
        btn.classList.toggle('tab-inactive', name !== t);
    });
    lucide.createIcons();
}

function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('photoPreviewImg').src = e.target.result;
            document.getElementById('photoPreviewWrap').classList.remove('hidden');
        };
        reader.readAsDataURL(input.files[0]);
        document.getElementById('fileLabel').textContent = input.files[0].name;
    }
}

// ── Main tabs ──────────────────────────────────────────────────────────────
function switchTab(t) {
    ['browse','requests'].forEach(name => {
        document.getElementById('pane-'+name).classList.toggle('hidden', name !== t);
        const btn = document.getElementById('tab-'+name);
        btn.classList.toggle('tab-active',   name === t);
        btn.classList.toggle('tab-inactive', name !== t);
    });
}

// ── Filter ─────────────────────────────────────────────────────────────────
function filterBooks() {
    const s   = document.getElementById('searchInput').value.toLowerCase();
    const st  = document.getElementById('statusFilter').value;
    const cat = document.getElementById('catFilter').value;
    let n = 0;
    document.querySelectorAll('.book-row').forEach(row => {
        const ok = (row.dataset.title.includes(s)||row.dataset.author.includes(s)||row.dataset.isbn.includes(s))
                && (st==='All'||row.dataset.status===st)
                && (cat==='All'||row.dataset.cat===cat);
        row.style.display = ok ? '' : 'none';
        if(ok) n++;
    });
    document.getElementById('recordCount').innerText = `Showing ${n} books`;
}

// ── Toast ──────────────────────────────────────────────────────────────────
function showToast(msg, color='#238636') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.background = color;
    t.style.color = 'white';
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3500);
}

// Auto-open Edit tab if profile is empty
<?php if (empty($userData['full_name'])): ?>
setTimeout(() => { openProfile(); switchProfileTab('edit'); }, 700);
<?php endif; ?>
</script>
</body>
</html>