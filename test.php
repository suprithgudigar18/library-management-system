<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch books
$books = $pdo->query("SELECT * FROM books ORDER BY title ASC")->fetchAll();

// Fetch requests
$myRequests = $pdo->prepare("SELECT * FROM book_requests WHERE user_id=?");
$myRequests->execute([$user_id]);
$myRequests = $myRequests->fetchAll();

// Requested book IDs
$requestedBookIds = array_column($myRequests, 'book_id');

// Handle request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_book_id'])) {
    $book_id = $_POST['request_book_id'];

    if (!in_array($book_id, $requestedBookIds)) {
        $pdo->prepare("INSERT INTO book_requests (user_id, book_id, status) VALUES (?,?,'Pending')")
            ->execute([$user_id, $book_id]);
    }

    header("Location: user_dashboard.php");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>LIBRITE Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>

<style>
body {
    background: #02040a;
    color: white;
    font-family: sans-serif;
}

/* HEADER */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

/* SEARCH */
.search-box {
    width: 100%;
    padding: 10px;
    border-radius: 8px;
    background: #0d1117;
    border: 1px solid #30363d;
    color: white;
}

/* GRID */
.book-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 20px;
}

/* CARD */
.book-card {
    background: #0d1117;
    border-radius: 14px;
    overflow: hidden;
    transition: 0.3s;
    border: 1px solid #30363d;
}

.book-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.6);
}

/* IMAGE */
.cover img {
    width: 100%;
    height: 240px;
    object-fit: cover;
}

/* CONTENT */
.content {
    padding: 12px;
}

.content h3 {
    font-size: 15px;
    font-weight: 600;
}

.author {
    font-size: 12px;
    color: #8b949e;
}

/* BADGE */
.badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
    display: inline-block;
    margin-top: 5px;
}

.available { background: #16a34a; }
.borrowed { background: #f59e0b; }

/* BUTTON */
.btn {
    margin-top: 10px;
    width: 100%;
    padding: 8px;
    border-radius: 8px;
    background: #238636;
    color: white;
    cursor: pointer;
}

.btn:hover {
    background: #2ea043;
}

.btn:disabled {
    background: #555;
    cursor: not-allowed;
}
</style>
</head>

<body>

<div class="max-w-6xl mx-auto p-6">

<!-- HEADER -->
<div class="header">
    <h1 class="text-2xl font-bold">📚 Browse Books</h1>
</div>

<!-- SEARCH -->
<input type="text" id="searchInput" class="search-box mb-6" placeholder="Search title, author...">

<!-- BOOK GRID -->
<div id="bookGrid" class="book-grid">

<?php foreach ($books as $b):

    $cover = "https://picsum.photos/300/400?random=" . $b['id'];
    $alreadyReq = in_array($b['id'], $requestedBookIds);
    $canRequest = $b['status'] == 'Available' && !$alreadyReq;
?>

<div class="book-card book-row"
     data-title="<?= strtolower($b['title']) ?>"
     data-author="<?= strtolower($b['author']) ?>">

    <div class="cover">
        <img src="<?= $cover ?>">
    </div>

    <div class="content">
        <h3><?= htmlspecialchars($b['title']) ?></h3>
        <p class="author"><?= htmlspecialchars($b['author']) ?></p>

        <span class="badge <?= strtolower($b['status']) ?>">
            <?= $b['status'] ?>
        </span>

        <p class="text-xs text-gray-400 mt-1">📍 <?= $b['shelf'] ?></p>

        <?php if ($alreadyReq): ?>
            <button class="btn" disabled>Requested</button>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="request_book_id" value="<?= $b['id'] ?>">
                <button class="btn" <?= !$canRequest ? 'disabled' : '' ?>>
                    <?= $canRequest ? 'Request' : 'Unavailable' ?>
                </button>
            </form>
        <?php endif; ?>
    </div>

</div>

<?php endforeach; ?>

</div>

</div>

<!-- SEARCH SCRIPT -->
<script>
document.getElementById("searchInput").addEventListener("keyup", function() {
    let value = this.value.toLowerCase();
    let books = document.querySelectorAll(".book-row");

    books.forEach(book => {
        let title = book.dataset.title;
        let author = book.dataset.author;

        if (title.includes(value) || author.includes(value)) {
            book.style.display = "block";
        } else {
            book.style.display = "none";
        }
    });
});
</script>

</body>
</html>