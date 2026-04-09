<?php
session_start();
include("db_connect.php");

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = trim($_POST['login_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_id) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, username, password, member_type, status
                   FROM users
                  WHERE username = ? OR register_no = ?"
            );
            $stmt->execute([$login_id, $login_id]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = "No account found with that Username or Register Number.";
            } elseif ($user['status'] !== 'Active') {
                $error = "Your account is inactive. Please contact the admin.";
            } elseif (!password_verify($password, $user['password'])) {
                $error = "Incorrect password.";
            } else {
                $_SESSION['user_id']     = $user['id'];
                $_SESSION['username']    = $user['username'];
                $_SESSION['member_type'] = $user['member_type'] ?? 'Student';
                $_SESSION['role']        = 'user';
                header("Location: user_dashboard.php");
                exit();
            }
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
    <title>LIBRITE — Login</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;600&display=swap');

        :root {
            --accent:       #22d3ee;
            --bg-deep:      #0a192f;
            --bg-darker:    #060d1a;
            --glass:        rgba(255,255,255,0.04);
            --glass-border: rgba(255,255,255,0.1);
            --red:          #ff6b6b;
        }
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

       body {
    background: #0a192f; /* solid dark blue */
}

        /* Ambient blob */
       
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
            12%  { opacity:0.55; }
            88%  { opacity:0.55; }
            100% { transform:translate(-115vw,var(--dy)) rotate(var(--rot)); opacity:0; }
        }

        /* Vignette */
        .vignette {
            position:fixed; inset:0; z-index:2; pointer-events:none;
            background:radial-gradient(circle at center,transparent 25%,rgba(0,0,0,0.72) 100%);
            backdrop-filter:blur(var(--vb,0px));
        }
        .grain {
            position:fixed; inset:0; z-index:3; pointer-events:none; opacity:0.03;
            background-image:url("data:image/svg+xml,%3Csvg viewBox='0%200%20200%20200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        /* Header */
        header {
            position:fixed; top:0; left:0; width:100%; z-index:50;
            display:flex; justify-content:space-between; align-items:center;
            padding:1.5rem 3rem;
        }
        .brand {
            font-family:'Playfair Display',serif; font-size:1.4rem; font-weight:700;
            color:white; text-decoration:none; display:flex; align-items:center; gap:12px;
        }
        .brand svg { height:46px; width:46px; }
        nav a {
            color:rgba(255,255,255,.55); text-decoration:none;
            margin-left:1.8rem; font-size:0.82rem;
            text-transform:uppercase; letter-spacing:.5px; transition:color .25s;
        }
        nav a:hover { color:var(--accent); }

        /* Stage */
        .stage {
            position:relative; z-index:10;
            width:100vw; height:100vh;
            display:flex; justify-content:center; align-items:center;
        }

        /* Card */
        .glass-card {
            
    background: rgba(255,255,255,0.03); /* softer glass */
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.08);
    padding: 2.6rem 2.8rem 2.2rem;
    border-radius: 22px;
    max-width: 470px;
    width: 93%;
    box-shadow: 0 20px 40px rgba(0,0,0,0.6); /* smoother shadow */
}
        
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(20px); }
            to   { opacity:1; transform:translateY(0); }
        }

        .card-title {
            font-family:'Playfair Display',serif; font-size:2.3rem;
            text-align:center; margin-bottom:0.25rem;
            background:linear-gradient(to bottom,#fff,#999);
            -webkit-background-clip:text; -webkit-text-fill-color:transparent;
        }
        .card-sub {
            text-align:center; font-size:0.8rem;
            color:rgba(255,255,255,.35); margin-bottom:1.8rem;
        }

        /* Toggle tabs */
        .toggle-tabs {
            display:flex; gap:6px;
            background:rgba(0,0,0,.3);
            border:1px solid var(--glass-border);
            border-radius:50px; padding:4px; margin-bottom:1.6rem;
        }
        .tab-btn {
            flex:1; padding:9px 6px; border-radius:50px; border:none;
            background:transparent; color:rgba(255,255,255,.4);
            font-family:'Inter',sans-serif; font-size:0.8rem;
            font-weight:600; text-transform:uppercase; letter-spacing:.5px;
            cursor:pointer; transition:all .25s;
        }
        .tab-btn.active {
            background:var(--accent); color:#000;
            box-shadow:0 0 14px rgba(34,211,238,.35);
        }

        /* Forms */
        .login-form { display:none; }
        .login-form.active { display:block; }

        .input-group { margin-bottom:1.2rem; }
        label {
            display:block; margin-bottom:0.4rem;
            font-size:0.76rem; color:rgba(255,255,255,.5);
            text-transform:uppercase; letter-spacing:.7px;
        }
        .req-note { color:rgba(255,255,255,.3); font-weight:400; font-size:0.7rem; }

        .input-field {
            width:100%; padding:12px 15px;
            background:rgba(15,23,42,.55);
            border:1px solid rgba(255,255,255,.09);
            border-radius:10px; color:white;
            font-family:'Inter',sans-serif; font-size:0.93rem;
            outline:none; transition:all .3s;
        }
        .input-field:focus {
            border-color:var(--accent);
            background:rgba(15,23,42,.8);
            box-shadow:0 0 16px rgba(34,211,238,.12);
        }
        .input-field::placeholder { color:rgba(255,255,255,.22); }

        /* Live type pill */
        .type-pill {
            display:inline-block; font-size:0.7rem; font-weight:700;
            padding:2px 11px; border-radius:20px; margin-top:5px;
        }
        .pill-student { background:rgba(34,211,238,.14); color:var(--accent);  border:1px solid rgba(34,211,238,.3); }
        .pill-teacher { background:rgba(167,139,250,.14); color:#a78bfa;       border:1px solid rgba(167,139,250,.3); }
        .pill-warn    { background:rgba(255,107,107,.12); color:var(--red);     border:1px solid rgba(255,107,107,.3); }

        .form-row { display:flex; gap:10px; }
        .form-row .input-group { flex:1; }

        .forgot-wrap { display:flex; justify-content:flex-end; margin:-0.4rem 0 1rem; }
        .forgot-link { font-size:0.76rem; color:var(--accent); text-decoration:none; opacity:.75; }
        .forgot-link:hover { opacity:1; text-decoration:underline; }

        /* Submit button */
        .btn-login {
            width:100%; padding:13px; border-radius:50px;
            font-weight:700; font-size:0.88rem;
            text-transform:uppercase; letter-spacing:1px;
            cursor:pointer; transition:all .3s;
            border:1px solid var(--accent);
            background:rgba(34,211,238,.04); color:var(--accent);
            display:flex; justify-content:center; align-items:center; gap:10px;
            font-family:'Inter',sans-serif;
        }
        .btn-login:hover {
    background: var(--accent);
    color: #000;
    box-shadow: 0 0 12px rgba(34,211,238,0.3);
}
        .btn-login:active { transform:translateY(0); }
        .btn-login:disabled { opacity:.55; cursor:not-allowed; transform:none; }

        .spinner {
            display:none; width:17px; height:17px;
            border:2px solid rgba(255,255,255,.3);
            border-top-color:#fff; border-radius:50%;
            animation:spin .8s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }

        /* Error */
        .error-box {
            background:rgba(255,107,107,.1); border:1px solid rgba(255,107,107,.25);
            color:var(--red); padding:10px 14px; border-radius:9px;
            margin-bottom:1.2rem; font-size:0.84rem; text-align:center;
        }

        /* Bottom */
        .bottom-links {
            text-align:center; margin-top:1.4rem;
            font-size:0.8rem; color:rgba(255,255,255,.38);
        }
        .bottom-links a { color:var(--accent); text-decoration:none; font-weight:600; }
        .bottom-links a:hover { text-decoration:underline; }

        .btn-back {
            display:block; width:100%; padding:10px; margin-top:1rem;
            border-radius:50px; text-align:center; text-decoration:none;
            color:rgba(255,255,255,.35); font-size:0.78rem; font-weight:600;
            border:1px solid rgba(255,255,255,.09);
            text-transform:uppercase; letter-spacing:.5px; transition:all .3s;
        }
        .btn-back:hover { color:white; border-color:rgba(255,255,255,.3); background:rgba(255,255,255,.04); }

        @keyframes shake {
            0%,100% { transform:translateX(0); }
            25%      { transform:translateX(-8px); }
            50%      { transform:translateX(8px); }
            75%      { transform:translateX(-5px); }
        }
        .shake { animation:shake .4s ease-in-out; }

        @media (max-width:560px) {
            header { padding:1.2rem 1.5rem; } nav { display:none; }
            .glass-card { padding:2rem 1.5rem; }
            .form-row { flex-direction:column; gap:0; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>



<header>
    <a href="index.php" class="brand">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
            <circle cx="50" cy="50" r="48" fill="#111" stroke="rgba(255,255,255,0.2)" stroke-width="2"/>
            <path d="M30 35 L30 75 L50 85 L70 75 L70 35 L50 45 Z"
                  fill="none" stroke="#22d3ee" stroke-width="3"
                  stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M50 45 L50 85" stroke="#22d3ee" stroke-width="3" stroke-linecap="round"/>
        </svg>
        LIBRITE
    </a>
    <nav>
        <a href="index.php">Home</a>
       
    </nav>
</header>

<div class="stage">
    <div class="glass-card" id="card">

        <h1 class="card-title">LIBRITE</h1>
        <p class="card-sub">Library Management System</p>

        <!-- Error box -->
        <?php if (!empty($error)): ?>
            <div class="error-box" id="errBox"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ══ USER FORM ══ -->
        <form id="userForm"
              class="login-form active"
              method="POST" action="user_login.php"
              onsubmit="go(event,'user')">

            <!-- Username or Register No -->
            <div class="input-group">
                <label>Username or Register No</label>
                <input type="text" id="loginRegNo" name="login_id" class="input-field"
                       placeholder="Enter Username or Register No" required
                       oninput="loginPill(this.value);"
                       value="<?= htmlspecialchars($_POST['login_id'] ?? '') ?>">
                <span id="loginPillEl"></span>
            </div>

            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" class="input-field"
                       placeholder="••••••••" required>
            </div>

            <div class="forgot-wrap">
                <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
            </div>

            <button type="submit" class="btn-login" id="userBtn">
                <span id="uTxt">Login</span>
                <div class="spinner" id="uSpin"></div>
            </button>
        </form>

        <div class="bottom-links">
            Not registered? <a href="user_register.php">Register here</a>
            <a href="index.php" class="btn-back">← Back to Home</a>
        </div>

    </div>
</div>

<script>
/* ── Live register-number pill (login page) ── */
function loginPill(val) {
    const el = document.getElementById('loginPillEl');
    el.className = 'type-pill';
    el.textContent = '';
    if (!val) return;
    
    // If it contains non-digits, treat it as a username
    if (/[^0-9]/.test(val)) {
        el.classList.add('pill-student');
        el.style.borderColor = 'rgba(255,255,255,0.3)';
        el.style.color = '#fff';
        el.textContent = '👤 Username Login';
        return;
    }
    
    if      (val.length === 10) { el.classList.add('pill-teacher'); el.textContent = '👨‍🏫 Teacher'; }
    else if (val.length === 12) { el.classList.add('pill-student'); el.textContent = '🎓 Student'; }
    else if (val.length > 12)   { el.classList.add('pill-warn');    el.textContent = '⚠ Too many digits'; }
    else                        { el.classList.add('pill-warn');    el.textContent = `${10-val.length} more needed`; }
}

// Restore pill on page reload
window.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('loginRegNo');
    if (el && el.value) loginPill(el.value);
});

/* ── Submit with spinner ── */
function go(e, type) {
    const txt = document.getElementById(type==='user'?'uTxt':'aTxt');
    const spin= document.getElementById(type==='user'?'uSpin':'aSpin');
    const btn = document.getElementById(type==='user'?'userBtn':'adminBtn');
    txt.style.display  = 'none';
    spin.style.display = 'block';
    btn.disabled       = true;
    // Form submits naturally to PHP
}



</script>
</body>
</html>