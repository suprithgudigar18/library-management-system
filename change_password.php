<?php
session_start();

// DB CONFIG
$host = 'localhost';
$db   = 'librite_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

// LOGIN CHECK
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $current = $_POST['current'] ?? '';
    $new     = $_POST['new'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!$current || !$new || !$confirm) {
        $error = "All fields are required.";
    } elseif ($new !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($new) < 8) {
        $error = "Minimum 8 characters required.";
    } elseif (!preg_match('/[A-Z]/', $new) || !preg_match('/[0-9]/', $new)) {
        $error = "Must include uppercase & number.";
    } else {

        $stmt = $pdo->prepare("SELECT password FROM admin_creds WHERE id=?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($current, $admin['password'])) {
            $error = "Incorrect current password.";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);

            $update = $pdo->prepare("UPDATE admin_creds SET password=? WHERE id=?");
            $update->execute([$hash, $_SESSION['admin_id']]);

            $success = "Password updated successfully!";
            session_destroy();
            header("Refresh:2; url=admin_login.php");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Change Password</title>

<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display&family=Inter&display=swap');

body {
    margin: 0;
    background: #02040a;
    color: white;
    font-family: 'Inter', sans-serif;
}

/* Layout */
.dashboard {
    display: flex;
}

/* Sidebar */
.sidebar {
    width: 250px;
    height: 100vh;
    background: rgba(0,0,0,0.6);
    padding: 2rem 1rem;
}

.logo {
    font-family: 'Playfair Display';
    text-align: center;
    margin-bottom: 2rem;
}

.nav-item {
    display: block;
    padding: 10px;
    margin: 5px 0;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    border-radius: 6px;
}

.nav-item:hover {
    background: rgba(255,255,255,0.1);
    color: cyan;
}

.active {
    background: rgba(255,255,255,0.1);
    color: cyan;
}

/* MAIN CENTER FIX */
.main-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;  /* CENTER MAGIC */
    padding: 2rem;
}

/* Header */
.header {
    width: 100%;
    max-width: 600px;
    margin-bottom: 1.5rem;
}

.page-title {
    font-family: 'Playfair Display';
    font-size: 2rem;
}

/* Card */
.glass-card {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    padding: 2rem;
    border-radius: 20px;
    width: 100%;
    max-width: 600px;
}

/* Inputs */
.input-group {
    margin-bottom: 1.2rem;
}

input {
    width: 100%;
    padding: 12px;
    margin-top: 5px;
    background: rgba(255,255,255,0.08);
    border: none;
    border-radius: 8px;
    color: white;
}

/* Button */
button {
    width: 100%;
    padding: 12px;
    background: transparent;
    border: 1px solid cyan;
    color: cyan;
    border-radius: 30px;
    cursor: pointer;
    transition: 0.3s;
}

button:hover {
    background: cyan;
    color: black;
}

/* Messages */
.error {
    color: #ff6b6b;
    margin-bottom: 10px;
}

.success {
    color: #34d399;
    margin-bottom: 10px;
}

/* Back link */
.back {
    display: inline-block;
    margin-top: 15px;
    color: cyan;
    text-decoration: none;
}
</style>
</head>

<body>

<div class="dashboard">

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo">LIBRITE ADMIN</div>

    <a href="admin_dashboard.php" class="nav-item">Dashboard</a>
    <a href="manage_books.php" class="nav-item">Manage Books</a>
    <a href="manage_users.php" class="nav-item">Manage Users</a>
    <a href="change_password.php" class="nav-item active">Change Password</a>
    <a href="admin_login.php" class="nav-item" style="color:red;">Logout</a>
</div>

<!-- MAIN -->
<div class="main-content">

    <div class="header">
        <h1 class="page-title">Change Password</h1>
    </div>

    <div class="glass-card">

        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php else: ?>

        <form method="POST">

            <div class="input-group">
                <label>Current Password</label>
                <input type="password" name="current" required>
            </div>

            <div class="input-group">
                <label>New Password</label>
                <input type="password" name="new" required>
            </div>

            <div class="input-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm" required>
            </div>

            <button type="submit">UPDATE PASSWORD</button>

        </form>

        <?php endif; ?>

        <a href="admin_dashboard.php" class="back">← Back to Dashboard</a>

    </div>

</div>

</div>

</body>
</html>