<?php
session_start();
include("db_connect.php");

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $answer   = trim($_POST['answer']   ?? '');

    if (empty($username) || empty($answer)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            // ── Check admin_creds table ──────────────────────────────────────
            $stmt = $pdo->prepare(
                "SELECT id, security_answer FROM admin_creds WHERE username = ?"
            );
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && strtolower(trim($answer)) === strtolower(trim($admin['security_answer']))) {
                // Store in session and go to reset page
                $_SESSION['reset_id']   = $admin['id'];
                $_SESSION['reset_type'] = 'admin';
                header("Location: admin_reset_password.php");
                exit();
            }

            // ── If not found in admin_creds, check users table ───────────────
            $stmt = $pdo->prepare(
                "SELECT id, security_answer FROM users WHERE username = ?"
            );
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $match = password_verify($answer, $user['security_answer'])
                      || strtolower(trim($answer)) === strtolower(trim($user['security_answer']));

                if ($match) {
                    $_SESSION['reset_id']   = $user['id'];
                    $_SESSION['reset_type'] = 'user';
                    header("Location: admin_reset_password.php");
                    exit();
                }
            }

            // Nothing matched
            $error = "Username not found or security answer is incorrect.";

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
    <title>Forgot Password - LIBRITE Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;600&display=swap');

        :root {
            --accent:       #22d3ee;
            --bg-deep:      #0a192f;
            --bg-darker:    #060d1a;
            --glass:        rgba(255,255,255,0.03);
            --glass-border: rgba(255,255,255,0.1);
            --red:          #ff6b6b;
        }
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        body, html {
            width:100%; height:100%; overflow:hidden;
            background:radial-gradient(circle at center, var(--bg-deep) 0%, var(--bg-darker) 100%);
            font-family:'Inter',sans-serif; color:white;
        }

        .stage {
            width:100vw; height:100vh;
            display:flex; justify-content:center; align-items:center;
        }

        .glass-card {
            background:var(--glass);
            backdrop-filter:blur(18px); -webkit-backdrop-filter:blur(18px);
            border:1px solid var(--glass-border);
            padding:3rem; border-radius:24px;
            max-width:450px; width:90%;
            box-shadow:0 25px 50px -12px rgba(0,0,0,0.6);
            text-align:center;
            animation:fadeUp .6s ease-out;
        }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(18px); }
            to   { opacity:1; transform:translateY(0); }
        }

        .icon-wrap {
            width:60px; height:60px; border-radius:50%;
            background:rgba(34,211,238,.1); border:1px solid rgba(34,211,238,.25);
            display:flex; align-items:center; justify-content:center;
            margin:0 auto 1.2rem; font-size:1.5rem; color:var(--accent);
        }

        h1 {
            font-family:'Playfair Display',serif; font-size:2rem;
            margin-bottom:0.4rem;
            background:linear-gradient(to bottom,#fff,#999);
            -webkit-background-clip:text; -webkit-text-fill-color:transparent;
        }
        .sub { font-size:0.8rem; color:rgba(255,255,255,.35); margin-bottom:2rem; line-height:1.5; }

        .input-group { margin-bottom:1.3rem; text-align:left; }
        label {
            display:block; margin-bottom:0.45rem;
            font-size:0.76rem; color:rgba(255,255,255,.55);
            text-transform:uppercase; letter-spacing:.8px;
        }
        .input-field {
            width:100%; padding:13px 15px;
            background:rgba(255,255,255,.05);
            border:1px solid rgba(255,255,255,.09);
            border-radius:10px; color:white;
            font-family:'Inter',sans-serif; font-size:0.95rem;
            outline:none; transition:all .3s;
        }
        .input-field:focus {
            border-color:var(--accent);
            background:rgba(255,255,255,.08);
            box-shadow:0 0 16px rgba(34,211,238,.13);
        }
        .input-field::placeholder { color:rgba(255,255,255,.22); }

        .btn-verify {
            display:block; width:100%; padding:13px;
            border-radius:50px; font-weight:700;
            text-transform:uppercase; letter-spacing:1px;
            cursor:pointer; transition:all .3s; margin-top:0.5rem;
            border:1px solid var(--accent);
            background:rgba(34,211,238,.05); color:var(--accent);
            font-family:'Inter',sans-serif; font-size:0.88rem;
        }
        .btn-verify:hover {
            background:var(--accent); color:#000;
            transform:translateY(-2px);
            box-shadow:0 10px 22px -10px var(--accent);
        }
        .btn-verify:active { transform:translateY(0); }

        .back-link {
            display:block; margin-top:1.5rem;
            font-size:0.82rem; color:rgba(255,255,255,.4);
            text-decoration:none; transition:color .25s;
        }
        .back-link:hover { color:white; }

        .error-box {
            background:rgba(255,107,107,.1);
            border:1px solid rgba(255,107,107,.25);
            color:var(--red); padding:10px 14px;
            border-radius:9px; margin-bottom:1.3rem;
            font-size:0.84rem;
        }

        .hint-box {
            background:rgba(34,211,238,.07);
            border:1px solid rgba(34,211,238,.2);
            color:rgba(255,255,255,.6); padding:10px 14px;
            border-radius:9px; margin-bottom:1.5rem;
            font-size:0.78rem; text-align:left; line-height:1.6;
        }
        .hint-box strong { color:var(--accent); }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="stage">
    <div class="glass-card">

        <div class="icon-wrap">
            <i class="fas fa-key"></i>
        </div>

        <h1>Reset Access</h1>
        <p class="sub">Enter your username and the security answer<br>you set during registration.</p>

        <?php if (!empty($error)): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="hint-box">
            <strong>Security Question:</strong> What is your favourite book?
        </div>

        <form method="POST" action="admin_forgot_password.php">

            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" class="input-field"
                       placeholder="Enter your username" required
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>

            <div class="input-group">
                <label>Security Answer</label>
                <input type="text" name="answer" class="input-field"
                       placeholder="Your favourite book" required>
            </div>

            <button type="submit" class="btn-verify">
                <i class="fas fa-check-circle"></i> &nbsp;Verify Identity
            </button>
        </form>

        <a href="admin_login.php" class="back-link">
            ← Return to Admin Login
        </a>

    </div>
</div>
</body>
</html>