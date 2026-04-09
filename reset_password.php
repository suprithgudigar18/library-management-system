<?php
session_start();
include("db_connect.php");

// Security Check: If they didn't come from the forgot_password page, kick them back to login
if (!isset($_SESSION['reset_user_id'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $user_id = $_SESSION['reset_user_id'];

            // Update the database
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);

            $message = "Success! Password updated. <a href='login.php' style='color:#22d3ee;'>Login here</a>";
            
            // Clear the session so the link expires
            unset($_SESSION['reset_user_id']);
        } catch (PDOException $e) {
            $error = "Database error. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - LIBRITE</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Inter:wght@300;400;600&display=swap');
        :root { --primary-accent: #22d3ee; --bg-deep: #0a192f; --bg-darker: #060d1a; --glass: rgba(255, 255, 255, 0.05); }
        body { background: radial-gradient(circle at center, var(--bg-deep), var(--bg-darker)); font-family: 'Inter', sans-serif; color: white; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .glass-card { background: var(--glass); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); padding: 3rem; border-radius: 24px; width: 400px; text-align: center; }
        h1 { font-family: 'Playfair Display', serif; margin-bottom: 1.5rem; }
        .input-group { margin-bottom: 1.2rem; text-align: left; }
        .input-field { width: 100%; padding: 12px; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: white; outline: none; }
        .btn { width: 100%; padding: 12px; border-radius: 50px; border: 1px solid var(--primary-accent); background: transparent; color: var(--primary-accent); cursor: pointer; font-weight: 600; }
        .btn:hover { background: var(--primary-accent); color: #0a192f; }
        .error { color: #ff6b6b; margin-bottom: 1rem; font-size: 0.9rem; }
        .success { color: #22d3ee; margin-bottom: 1rem; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="glass-card">
        <h1>New Password</h1>
        <?php if ($error) echo "<div class='error'>$error</div>"; ?>
        <?php if ($message) echo "<div class='success'>$message</div>"; ?>

        <?php if (!$message): ?>
        <form method="POST">
            <div class="input-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="input-field" required>
            </div>
            <div class="input-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="input-field" required>
            </div>
            <button type="submit" class="btn">Update Password</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>