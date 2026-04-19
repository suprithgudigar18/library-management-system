<?php
session_start();

$error = "";

require 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']      ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $pdo->prepare("SELECT id, password FROM admin_creds WHERE username=?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']  = $admin['id'];
            $_SESSION['user_id']   = $admin['id'];
            $_SESSION['username']  = $username;
            $_SESSION['role']      = 'admin';
            header("Location: admin_dashboard.php");
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - LIBRITE</title>
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

body {
    background: #0a192f; /* solid dark blue */
}

        /* Ambient glow */
        body::before {
            content:''; position:fixed;
            width:350px; height:350px;
            background:var(--accent); filter:blur(160px); opacity:0.08;
            top:10%; right:15%; border-radius:50%; z-index:0;
            animation:blob 12s infinite alternate ease-in-out;
        }
        @keyframes blob {
            from { transform:translate(0,0); }
            to   { transform:translate(-60px,40px); }
        }

        /* Floating books */
        .books-bg { position:fixed; inset:0; z-index:1; pointer-events:none; }
        .falling-book {
            position:absolute; right:-120px; opacity:0;
            color:var(--accent); text-shadow:0 0 8px var(--accent);
            filter:blur(var(--blur,2px));
            animation:drift var(--spd) linear infinite;
        }
        @keyframes drift {
            0%   { transform:translate(0,0) rotate(0deg); opacity:0; }
            12%  { opacity:0.5; }
            88%  { opacity:0.5; }
            100% { transform:translate(-115vw,var(--dy)) rotate(var(--rot)); opacity:0; }
        }

        /* Vignette */
        .vignette {
            position:fixed; inset:0; z-index:2; pointer-events:none;
            background:radial-gradient(circle at center,transparent 25%,rgba(0,0,0,0.75) 100%);
        }
        .grain {
            position:fixed; inset:0; z-index:3; pointer-events:none; opacity:0.03;
            background-image:url("data:image/svg+xml,%3Csvg viewBox='0%200%20200%20200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        /* Stage */
        .stage {
            position:relative; z-index:10;
            width:100vw; height:100vh;
            display:flex; justify-content:center; align-items:center;
        }

        /* Card */
        .glass-card {
            background:var(--glass);
            backdrop-filter:blur(18px); -webkit-backdrop-filter:blur(18px);
            border:1px solid var(--glass-border);
            padding:3rem; border-radius:24px;
            max-width:450px; width:90%;
            box-shadow:0 25px 50px -12px rgba(0,0,0,0.6);
            text-align:center;
            animation:fadeUp .65s ease-out;
        }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(20px); }
            to   { opacity:1; transform:translateY(0); }
        }

        /* Admin badge */
        .admin-badge {
            display:inline-flex; align-items:center; gap:6px;
            background:rgba(34,211,238,.1); border:1px solid rgba(34,211,238,.25);
            color:var(--accent); font-size:0.72rem; font-weight:700;
            padding:4px 14px; border-radius:20px;
            text-transform:uppercase; letter-spacing:1px;
            margin-bottom:1rem;
        }

        h1 {
            font-family:'Playfair Display',serif; font-size:2.4rem;
            margin-bottom:0.4rem;
            background:linear-gradient(to bottom,#fff,#888);
            -webkit-background-clip:text; -webkit-text-fill-color:transparent;
        }
        .sub {
            font-size:0.8rem; color:rgba(255,255,255,.35);
            margin-bottom:2rem;
        }

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

        /* Forgot link */
        .forgot-wrap { display:flex; justify-content:flex-end; margin-top:5px; }
        .forgot-link {
            font-size:0.78rem; color:var(--accent);
            text-decoration:none; opacity:.7; transition:.2s;
        }
        .forgot-link:hover { opacity:1; text-decoration:underline; }

        /* Buttons */
        .btn-login {
            display:block; width:100%; padding:14px;
            border-radius:50px; font-weight:700;
            text-transform:uppercase; letter-spacing:1px;
            cursor:pointer; transition:all .3s; margin-top:1.2rem;
            border:1px solid var(--accent);
            background:rgba(34,211,238,.05); color:var(--accent);
            font-family:'Inter',sans-serif; font-size:0.9rem;
        }
        .btn-login:hover {
            background:var(--accent); color:#000;
            transform:translateY(-2px);
            box-shadow:0 10px 25px -10px var(--accent);
        }
        .btn-login:active { transform:translateY(0); }

        .btn-back {
            display:block; width:100%; padding:11px;
            border-radius:50px; text-align:center;
            text-decoration:none; margin-top:1rem;
            font-size:0.82rem; font-weight:600;
            color:rgba(255,255,255,.38);
            border:1px solid rgba(255,255,255,.09);
            text-transform:uppercase; letter-spacing:.5px;
            transition:all .3s;
        }
        .btn-back:hover {
            color:white; border-color:rgba(255,255,255,.3);
            background:rgba(255,255,255,.04);
        }

        /* Error */
        .error-box {
            background:rgba(255,107,107,.1);
            border:1px solid rgba(255,107,107,.25);
            color:var(--red); padding:10px 14px;
            border-radius:9px; margin-bottom:1.3rem;
            font-size:0.85rem; text-align:center;
        }

        @keyframes shake {
            0%,100% { transform:translateX(0); }
            25%      { transform:translateX(-8px); }
            50%      { transform:translateX(8px); }
            75%      { transform:translateX(-5px); }
        }
        .shake { animation:shake .4s ease-in-out; }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<div class="books-bg" id="booksBg"></div>
<div class="vignette"></div>
<div class="grain"></div>

<div class="stage">
    <div class="glass-card" id="card">

        <div class="admin-badge">
            <i class="fas fa-shield-alt"></i> Admin Portal
        </div>

        <h1>LIBRITE</h1>
        <p class="sub">Restricted — Authorised Access Only</p>

        <?php if (!empty($error)): ?>
            <div class="error-box" id="errBox">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin_login.php" onsubmit="showSpin()">

            <div class="input-group">
                <label>Admin Username</label>
                <input type="text" name="username" class="input-field"
                       placeholder="Enter admin username" required
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>

            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" class="input-field"
                       placeholder="••••••••" required>
                <div class="forgot-wrap">
                    <a href="admin_forgot_password.php" class="forgot-link">
                        Forgot password? <span style="color:var(--accent);">Reset here</span>
                    </a>
                </div>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                <span id="btnTxt"><i class="fas fa-sign-in-alt"></i> &nbsp;Login as Admin</span>
                <span id="btnSpin" style="display:none;">
                    <i class="fas fa-circle-notch fa-spin"></i> &nbsp;Verifying...
                </span>
            </button>
        </form>

        <a href="index.php" class="btn-back">← Back to Home</a>

    </div>
</div>

<script>
    function showSpin() {
        document.getElementById('btnTxt').style.display  = 'none';
        document.getElementById('btnSpin').style.display = 'inline';
        document.getElementById('loginBtn').disabled     = true;
    }

    // Shake card on error
    <?php if (!empty($error)): ?>
        document.getElementById('card').classList.add('shake');
    <?php endif; ?>

    
    for (let i=0; i<12; i++) mkBook();
    setInterval(mkBook, 1200);
</script>
</body>
</html>