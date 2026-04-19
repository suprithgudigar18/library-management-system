<?php
session_start();
include("db_connect.php");

// Admin guard
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: admin_login.php"); exit();
}

$toast = ''; $toastType = 'success';

// ── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM books WHERE id = ?")->execute([$id]);
            $toast = "Book deleted from library and database."; $toastType = 'delete';
        }
    } elseif ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $title       = trim($_POST['title']       ?? '');
        $author      = trim($_POST['author']      ?? '');
        $isbn        = trim($_POST['isbn']        ?? '');
        $publisher   = trim($_POST['publisher']   ?? '');
        $year        = trim($_POST['year']        ?? '');
        $category    = trim($_POST['category']    ?? 'Fiction');
        $genre       = trim($_POST['genre']       ?? '');
        $status      = trim($_POST['status']      ?? 'Available');
        $copies      = (int)($_POST['copies']     ?? 1);
        $shelf       = trim($_POST['shelf']       ?? '');
        $description = trim($_POST['description'] ?? '');
        $description = trim($_POST['description'] ?? '');

        $cover_image = '';
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === 0) {
            $ext = pathinfo($_FILES['cover_image']['name'], PATHINFO_EXTENSION);
            $fn = "cover_" . time() . "_" . rand(100, 999) . "." . $ext;
            @mkdir("uploads/books", 0755, true);
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], "uploads/books/$fn")) {
                $cover_image = "uploads/books/$fn";
            }
        }

        if ($id > 0) {
            if ($cover_image) {
                $sql = "UPDATE books SET title=?,author=?,isbn=?,publisher=?,year=?,category=?,
                        genre=?,status=?,copies=?,shelf=?,description=?,cover_image=? WHERE id=?";
                $pdo->prepare($sql)->execute([$title,$author,$isbn,$publisher,$year,$category,
                                              $genre,$status,$copies,$shelf,$description,$cover_image,$id]);
            } else {
                $sql = "UPDATE books SET title=?,author=?,isbn=?,publisher=?,year=?,category=?,
                        genre=?,status=?,copies=?,shelf=?,description=? WHERE id=?";
                $pdo->prepare($sql)->execute([$title,$author,$isbn,$publisher,$year,$category,
                                              $genre,$status,$copies,$shelf,$description,$id]);
            }
            $toast = "Book updated successfully.";
        } else {
            $sql = "INSERT INTO books (title,author,isbn,publisher,year,category,genre,status,copies,shelf,description".($cover_image?',cover_image':'').")
                    VALUES (?,?,?,?,?,?,?,?,?,?,?".($cover_image?',?':'').")";
            $params = [$title,$author,$isbn,$publisher,$year,$category,$genre,$status,$copies,$shelf,$description];
            if ($cover_image) $params[] = $cover_image;
            $pdo->prepare($sql)->execute($params);
            $toast = "Book added to library and database.";
        }
    }
    header("Location: manage_books.php?toast=".urlencode($toast)."&type=".urlencode($toastType)); exit();
}

if (isset($_GET['toast'])) { $toast = $_GET['toast']; $toastType = $_GET['type'] ?? 'success'; }
$books = $pdo->query("SELECT * FROM books ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books - LIBRITE Admin</title>
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
    <i data-lucide="<?= $toastType === 'delete' ? 'trash-2' : 'check-circle' ?>" class="w-5 h-5"></i>
    <?= htmlspecialchars($toast) ?>
</div>
<?php endif; ?>

<header class="bg-white border-b border-slate-200 sticky top-0 z-10 px-6 py-4 flex items-center justify-between shadow-sm">
    <a href="admin_dashboard.php" class="flex items-center gap-3 hover:opacity-80 no-underline">
        <div class="bg-blue-600 p-2 rounded-lg"><i data-lucide="book" class="w-6 h-6 text-white"></i></div>
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
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-800">Manage Books</h2>
            <p class="text-slate-500">Total in database: <strong><?= count($books) ?></strong> books</p>
        </div>
        <button onclick="openModal()" class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-medium shadow-sm">
            <i data-lucide="plus" class="w-5 h-5"></i> Add New Book
        </button>
        <a href="import_books.php" 
   class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
   📂 Bulk Upload Books
</a>
    </div>

    <!-- Filters -->
    <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm mb-6 flex flex-col md:flex-row gap-4">
        <div class="relative flex-1">
            <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 w-5 h-5"></i>
            <input type="text" id="searchInput" onkeyup="filterBooks()" placeholder="Search title, author, ISBN..."
                class="w-full pl-10 pr-4 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <select id="catFilter" onchange="filterBooks()" class="px-4 py-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 bg-white">
            <option value="All">All Categories</option>
            <option>Fiction</option><option>Non-Fiction</option><option>Reference</option><option>Academic</option><option>horror</option><option>magic</option>
        </select>
        <select id="statusFilter" onchange="filterBooks()" class="px-4 py-2 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 bg-white">
            <option value="All">All Status</option>
            <option>Available</option><option>Borrowed</option><option>Lost</option><option>Maintenance</option>
        </select>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-4">Book Details</th>
                        <th class="px-6 py-4">Category / Genre</th>
                        <th class="px-6 py-4">Copies</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Shelf</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php if (empty($books)): ?>
                    <tr><td colspan="6" class="text-center py-16 text-slate-400">
                        <i data-lucide="book-open" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
                        <p>No books yet. Add your first book!</p>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($books as $b):
                        $statusColors = ['Available'=>'bg-emerald-100 text-emerald-700','Borrowed'=>'bg-amber-100 text-amber-700','Lost'=>'bg-red-100 text-red-700','Maintenance'=>'bg-slate-100 text-slate-600'];
                        $sc = $statusColors[$b['status']] ?? 'bg-slate-100 text-slate-600';
                        $dataJson = htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr class="hover:bg-slate-50 group book-row"
                        data-title="<?= strtolower(htmlspecialchars($b['title'])) ?>"
                        data-author="<?= strtolower(htmlspecialchars($b['author'])) ?>"
                        data-isbn="<?= strtolower(htmlspecialchars($b['isbn'] ?? '')) ?>"
                        data-cat="<?= htmlspecialchars($b['category'] ?? '') ?>"
                        data-status="<?= htmlspecialchars($b['status']) ?>">
                        <td class="px-6 py-4">
                            <div class="flex flex-col">
                                <span class="font-semibold text-slate-800"><?= htmlspecialchars($b['title']) ?></span>
                                <span class="text-xs text-slate-500"><?= htmlspecialchars($b['author']) ?></span>
                                <span class="text-[10px] text-slate-400 font-mono mt-1">ISBN: <?= htmlspecialchars($b['isbn'] ?? '—') ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm text-slate-700"><?= htmlspecialchars($b['category'] ?? '—') ?></div>
                            <div class="text-xs bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded w-fit mt-1"><?= htmlspecialchars($b['genre'] ?? '') ?></div>
                        </td>
                        <td class="px-6 py-4 font-bold text-slate-700"><?= (int)$b['copies'] ?></td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $sc ?>"><?= htmlspecialchars($b['status']) ?></span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-500"><?= htmlspecialchars($b['shelf'] ?? '—') ?></td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button onclick='openModal(<?= $dataJson ?>)'
                                    class="p-2 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg" title="Edit">
                                    <i data-lucide="edit-2" class="w-4 h-4"></i>
                                </button>
                                <button onclick="confirmDelete(<?= $b['id'] ?>, '<?= htmlspecialchars($b['title'], ENT_QUOTES) ?>')"
                                    class="p-2 text-slate-500 hover:text-red-600 hover:bg-red-50 rounded-lg" title="Delete">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="px-6 py-3 border-t bg-slate-50 text-sm text-slate-500">
            <span id="recordCount">Showing <?= count($books) ?> books</span>
        </div>
    </div>
</main>

<!-- ═══ ADD / EDIT MODAL ════════════════════════════════════════════════════ -->
<div id="bookModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col max-h-[90vh]">
    <div class="px-6 py-4 border-b flex justify-between items-center bg-slate-50 rounded-t-2xl">
        <h2 id="modalTitle" class="text-xl font-bold text-slate-800">Add New Book</h2>
        <button onclick="closeModal()" class="p-2 hover:bg-slate-200 rounded-full text-slate-500">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>
    <div class="p-6 overflow-y-auto">
        <!-- Tabs -->
        <div class="flex border-b border-slate-200 mb-6">
            <button id="tab-core"      onclick="switchTab('core')"      class="tab-btn px-4 py-2 text-sm font-medium border-b-2 border-blue-600 text-blue-600">Core Info</button>
            <button id="tab-details"   onclick="switchTab('details')"   class="tab-btn px-4 py-2 text-sm font-medium border-b-2 border-transparent text-slate-500">Category</button>
            <button id="tab-inventory" onclick="switchTab('inventory')" class="tab-btn px-4 py-2 text-sm font-medium border-b-2 border-transparent text-slate-500">Inventory</button>
        </div>
        <form id="bookForm" method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id"     id="inp_id">

            <!-- Core Info -->
            <div id="content-core" class="tab-content space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Title <span class="text-red-500">*</span></label>
                        <input required name="title" id="inp_title" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Book Title"></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Author <span class="text-red-500">*</span></label>
                        <input required name="author" id="inp_author" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Author Name"></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">ISBN</label>
                        <input name="isbn" id="inp_isbn" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="978-..."></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Publisher</label>
                        <input name="publisher" id="inp_publisher" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Publication Year</label>
                        <input type="number" name="year" id="inp_year" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Cover Image (Optional)</label>
                        <input type="file" name="cover_image" accept="image/*" class="w-full px-2 py-1.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-white"></div>
                </div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea name="description" id="inp_description" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"></textarea></div>
            </div>

            <!-- Category -->
            <div id="content-details" class="tab-content hidden space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                        <select name="category" id="inp_category" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                            <option>Fiction</option><option>Non-Fiction</option><option>Reference</option><option>Academic</option><option>Periodical</option>
                        </select></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Genre</label>
                        <input name="genre" id="inp_genre" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="e.g. Sci-Fi, Classic"></div>
                </div>
            </div>

            <!-- Inventory -->
            <div id="content-inventory" class="tab-content hidden space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select name="status" id="inp_status" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                            <option>Available</option><option>Borrowed</option><option>Lost</option><option>Maintenance</option><option>Archived</option>
                        </select></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Total Copies</label>
                        <input type="number" name="copies" id="inp_copies" min="0" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none"></div>
                </div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Shelf / Rack</label>
                    <input name="shelf" id="inp_shelf" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" placeholder="e.g. A-12"></div>
            </div>
        </form>
    </div>
    <div class="px-6 py-4 bg-slate-50 border-t flex justify-end gap-3 rounded-b-2xl">
        <button onclick="closeModal()" class="px-4 py-2 text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg font-medium">Cancel</button>
        <button type="submit" form="bookForm" class="flex items-center gap-2 px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium shadow-sm">
            <i data-lucide="save" class="w-4 h-4"></i> Save Book
        </button>
    </div>
  </div>
</div>

<!-- ═══ DELETE CONFIRM ═══════════════════════════════════════════════════════ -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 text-center">
    <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
        <i data-lucide="trash-2" class="w-7 h-7 text-red-600"></i>
    </div>
    <h3 class="text-lg font-bold text-slate-800 mb-2">Delete Book?</h3>
    <p class="text-slate-500 mb-6">Remove <strong id="deleteBookName"></strong> from the library database. This cannot be undone.</p>
    <form method="POST" id="deleteForm">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteBookId">
        <div class="flex justify-center gap-3">
            <button type="button" onclick="closeDeleteModal()" class="px-5 py-2 border border-slate-300 rounded-lg text-slate-600 hover:bg-slate-50 font-medium">Cancel</button>
            <button type="submit" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium">Delete</button>
        </div>
    </form>
  </div>
</div>

<script>
lucide.createIcons();

function openModal(book = null) {
    document.getElementById('bookModal').classList.remove('hidden');
    if (book) {
        document.getElementById('modalTitle').innerText = 'Edit Book';
        document.getElementById('inp_id').value          = book.id;
        document.getElementById('inp_title').value       = book.title;
        document.getElementById('inp_author').value      = book.author;
        document.getElementById('inp_isbn').value        = book.isbn       || '';
        document.getElementById('inp_publisher').value   = book.publisher  || '';
        document.getElementById('inp_year').value        = book.year       || '';
        document.getElementById('inp_description').value = book.description || '';
        document.getElementById('inp_category').value    = book.category   || 'Fiction';
        document.getElementById('inp_genre').value       = book.genre      || '';
        document.getElementById('inp_status').value      = book.status     || 'Available';
        document.getElementById('inp_copies').value      = book.copies     || 1;
        document.getElementById('inp_shelf').value       = book.shelf      || '';
    } else {
        document.getElementById('modalTitle').innerText = 'Add New Book';
        document.getElementById('bookForm').reset();
        document.getElementById('inp_id').value = '';
        document.getElementById('inp_copies').value = 1;
    }
    switchTab('core');
}
function closeModal() { document.getElementById('bookModal').classList.add('hidden'); }

function confirmDelete(id, name) {
    document.getElementById('deleteBookId').value     = id;
    document.getElementById('deleteBookName').innerText = name;
    document.getElementById('deleteModal').classList.remove('hidden');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.add('hidden'); }

function switchTab(t) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.getElementById('content-'+t).classList.remove('hidden');
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('text-blue-600','border-blue-600');
        btn.classList.add('border-transparent','text-slate-500');
    });
    const active = document.getElementById('tab-'+t);
    active.classList.remove('border-transparent','text-slate-500');
    active.classList.add('text-blue-600','border-blue-600');
}

function filterBooks() {
    const s = document.getElementById('searchInput').value.toLowerCase();
    const st = document.getElementById('statusFilter').value;
    const cat = document.getElementById('catFilter').value;
    let n = 0;
    document.querySelectorAll('.book-row').forEach(row => {
        const ok = (row.dataset.title.includes(s)||row.dataset.author.includes(s)||row.dataset.isbn.includes(s))
                && (st==='All'||row.dataset.status===st)
                && (cat==='All'||row.dataset.cat===cat);
        row.style.display = ok ? '' : 'none';
        if (ok) n++;
    });
    document.getElementById('recordCount').innerText = `Showing ${n} books`;
}

['bookModal','deleteModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e){ if(e.target===this) this.classList.add('hidden'); });
});
</script>
</body>
</html>