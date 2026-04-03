<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['reset_id']) || !isset($_SESSION['reset_type'])) {
    header("Location: admin_forgot_password.php");
    exit();
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_pass     = $_POST['new']     ?? '';
    $confirm_pass = $_POST['confirm'] ?? '';

    if (strlen($new_pass) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "Passwords do not match.";
    } else {
        try {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $id     = $_SESSION['reset_id'];
            $type   = $_SESSION['reset_type'];

            if ($type === 'admin') {
                $pdo->prepare("UPDATE admin_creds SET password = ? WHERE id = ?")
                    ->execute([$hashed, $id]);
            } else {
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                    ->execute([$hashed, $id]);
            }

            unset($_SESSION['reset_id'], $_SESSION['reset_type']);
            $success = true;

            // Auto-redirect to admin dashboard after 2 seconds
            header("Refresh: 2; url=admin_dashboard.php");

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
    <title>Reset Password - LIBRITE Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;600&display=swap');
        :root {
            --accent:#22d3ee; --bg-deep:#0a192f; --bg-darker:#060d1a;
            --glass:rgba(255,255,255,0.03); --glass-border:rgba(255,255,255,0.1);
            --red:#ff6b6b; --green:#34d399;
        }
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
        body,html{width:100%;height:100%;overflow:hidden;
            background:radial-gradient(circle at center,var(--bg-deep) 0%,var(--bg-darker) 100%);
            font-family:'Inter',sans-serif;color:white;}
        .stage{width:100vw;height:100vh;display:flex;justify-content:center;align-items:center;}
        .glass-card{background:var(--glass);backdrop-filter:blur(18px);
            border:1px solid var(--glass-border);padding:3rem;border-radius:24px;
            max-width:450px;width:90%;box-shadow:0 25px 50px -12px rgba(0,0,0,0.6);
            text-align:center;animation:fadeUp .6s ease-out;}
        @keyframes fadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}
        .icon-wrap{width:60px;height:60px;border-radius:50%;display:flex;
            align-items:center;justify-content:center;margin:0 auto 1.2rem;font-size:1.5rem;}
        .icon-lock {background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.25);color:var(--accent);}
        .icon-check{background:rgba(52,211,153,.15);border:1px solid rgba(52,211,153,.3);color:var(--green);}
        h1{font-family:'Playfair Display',serif;font-size:2rem;margin-bottom:0.4rem;
            background:linear-gradient(to bottom,#fff,#999);
            -webkit-background-clip:text;-webkit-text-fill-color:transparent;}
        .sub{font-size:0.8rem;color:rgba(255,255,255,.35);margin-bottom:2rem;}
        .input-group{margin-bottom:1.3rem;text-align:left;}
        label{display:block;margin-bottom:0.45rem;font-size:0.76rem;
            color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.8px;}
        .input-field{width:100%;padding:13px 15px;background:rgba(255,255,255,.05);
            border:1px solid rgba(255,255,255,.09);border-radius:10px;color:white;
            font-family:'Inter',sans-serif;font-size:0.95rem;outline:none;transition:all .3s;}
        .input-field:focus{border-color:var(--accent);background:rgba(255,255,255,.08);
            box-shadow:0 0 16px rgba(34,211,238,.13);}
        .input-field::placeholder{color:rgba(255,255,255,.22);}
        .btn{display:block;width:100%;padding:13px;border-radius:50px;font-weight:700;
            text-transform:uppercase;letter-spacing:1px;cursor:pointer;transition:all .3s;
            margin-top:0.5rem;border:1px solid var(--accent);
            background:rgba(34,211,238,.05);color:var(--accent);
            font-family:'Inter',sans-serif;font-size:0.88rem;text-decoration:none;text-align:center;}
        .btn:hover{background:var(--accent);color:#000;transform:translateY(-2px);}
        .error-box{background:rgba(255,107,107,.1);border:1px solid rgba(255,107,107,.25);
            color:var(--red);padding:10px 14px;border-radius:9px;
            margin-bottom:1.3rem;font-size:0.84rem;}
        .success-box{background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.3);
            color:var(--green);padding:14px;border-radius:12px;
            margin-bottom:1.5rem;font-size:0.9rem;line-height:1.6;}
        .redirect-bar{width:100%;height:4px;background:rgba(255,255,255,.1);
            border-radius:2px;overflow:hidden;margin-top:1.2rem;}
        .redirect-fill{height:100%;background:var(--green);border-radius:2px;
            width:100%;animation:drain 2s linear forwards;}
        @keyframes drain{from{width:100%}to{width:0%}}
        .redirect-txt{font-size:0.75rem;color:rgba(255,255,255,.35);margin-top:8px;}
        .strength-bar{height:3px;border-radius:2px;margin-top:6px;
            background:rgba(255,255,255,.08);overflow:hidden;}
        .strength-fill{height:100%;border-radius:2px;transition:width .3s,background .3s;width:0;}
        .strength-label{font-size:0.7rem;margin-top:3px;}
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="stage">
    <div class="glass-card">

    <?php if ($success): ?>
        <!-- SUCCESS -->
        <div class="icon-wrap icon-check">
            <i class="fas fa-check"></i>
        </div>
        <h1>Password Updated!</h1>
        <p class="sub">Your password has been changed successfully.</p>

        <div class="success-box">
            <i class="fas fa-check-circle"></i>
            Password updated successfully.<br>
            <small>Redirecting to dashboard...</small>
        </div>

        <div class="redirect-bar"><div class="redirect-fill"></div></div>
        <p class="redirect-txt">Redirecting to Admin Dashboard in 2 seconds...</p>

        <a href="admin_dashboard.php" class="btn" style="margin-top:1.5rem;">
            <i class="fas fa-tachometer-alt"></i> &nbsp;Go to Dashboard Now
        </a>

    <?php else: ?>
        <!-- FORM -->
        <div class="icon-wrap icon-lock">
            <i class="fas fa-lock-open"></i>
        </div>
        <h1>New Password</h1>
        <p class="sub">Choose a strong new password.</p>

        <?php if (!empty($error)): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin_reset_password.php" onsubmit="return checkMatch()">
            <div class="input-group">
                <label>New Password</label>
                <input type="password" id="newPwd" name="new"
                       class="input-field" placeholder="Min. 8 characters"
                       required minlength="8"
                       oninput="strengthCheck(this.value)">
                <div class="strength-bar">
                    <div class="strength-fill" id="sFill"></div>
                </div>
                <div class="strength-label" id="sLabel" style="color:rgba(255,255,255,.35)"></div>
            </div>

            <div class="input-group">
                <label>Confirm Password</label>
                <input type="password" id="conPwd" name="confirm"
                       class="input-field" placeholder="••••••••"
                       required minlength="8">
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-save"></i> &nbsp;Update Password
            </button>
        </form>

        <a href="admin_forgot_password.php"
           style="display:block;margin-top:1.2rem;font-size:0.8rem;
                  color:rgba(255,255,255,.35);text-decoration:none;">
            ← Back
        </a>

    <?php endif; ?>

    </div>
</div>
<script>
function strengthCheck(val) {
    const fill = document.getElementById('sFill');
    const lbl  = document.getElementById('sLabel');
    let s = 0;
    if (val.length >= 8)          s++;
    if (/[A-Z]/.test(val))        s++;
    if (/[0-9]/.test(val))        s++;
    if (/[^A-Za-z0-9]/.test(val)) s++;
    const m = [
        {w:'0%',  c:'transparent', t:''},
        {w:'25%', c:'#ff6b6b',     t:'Weak'},
        {w:'50%', c:'#fbbf24',     t:'Fair'},
        {w:'75%', c:'#34d399',     t:'Good'},
        {w:'100%',c:'#22d3ee',     t:'Strong'},
    ];
    fill.style.width      = m[s].w;
    fill.style.background = m[s].c;
    lbl.textContent       = m[s].t;
    lbl.style.color       = m[s].c;
}
function checkMatch() {
    const n = document.getElementById('newPwd').value;
    const c = document.getElementById('conPwd');
    if (n !== c.value) {
        c.setCustomValidity('Passwords do not match.');
        c.reportValidity();
        return false;
    }
    c.setCustomValidity('');
    return true;
}
</script>
</body>
</html>