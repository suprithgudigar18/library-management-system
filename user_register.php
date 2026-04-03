<?php
include "db_connect.php";

$message = "";
$is_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username     = trim($_POST['username']        ?? '');
    $phone        = trim($_POST['phone']           ?? '');
    $register_no  = trim($_POST['register_no']     ?? '');
    $password     = $_POST['password']             ?? '';
    $security_ans = trim($_POST['security_answer'] ?? '');

    try {
        // --- Validate register number: must be exactly 10 or 12 digits ---
        if (!preg_match("/^[a-zA-Z0-9]{10}$|^[a-zA-Z0-9]{12}$/", $register_no)) {
    $message = "Register number must be 10 or 12 characters (letters & numbers allowed).";
}
        // --- Validate phone: 10 digits ---
            elseif (!preg_match("/^[0-9]{10}$/", $phone)) {
            $message = "Phone number must be exactly 10 digits.";

        } else {
            // Auto-detect member type from register number length
            $member_type = (strlen($register_no) === 12) ? 'Student' : 'Teacher';

            // Check username uniqueness
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()) {
                $message = "Username already taken.";
            } else {
                // Check register_no uniqueness
                $chkReg = $pdo->prepare("SELECT id FROM users WHERE register_no = ?");
                $chkReg->execute([$register_no]);
                if ($chkReg->fetch()) {
                    $message = "This register number is already registered.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    $sql = "INSERT INTO users 
                                (name, username, email, password, security_answer, register_no, member_type, role)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'user')";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $username,
                        $username,
                        $phone,
                        $hashed_password,
                        $security_ans,
                        $register_no,
                        $member_type
                    ]);

                    $is_success = true;
                }
            }
        }
    } catch (PDOException $e) {
        $message = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - LIBRITE</title>
    <style>
        :root {
            --bg-dark: #111827;
            --card-dark: #1f2937;
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --success: #22c55e;
            --border: #374151;
            --accent: #22d3ee;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-main);
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .card {
            background-color: var(--card-dark);
            border: 1px solid var(--border);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            max-width: 420px;
            width: 100%;
        }

        .text-center { text-align: center; }
        h2 { margin-top: 0; margin-bottom: 0.3rem; font-size: 1.5rem; }
        .subtitle { color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem; }

        .form-group { margin-bottom: 1.2rem; }
        label { display: block; font-size: 0.875rem; margin-bottom: 0.3rem; color: var(--text-muted); }

        .input-wrapper { position: relative; display: flex; align-items: center; }

        input[type="text"],
        input[type="tel"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            background-color: #374151;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            color: white;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
            font-size: 0.95rem;
        }

        input:focus { border-color: var(--primary); }

        .pwd-input { padding-right: 2.8rem; }

        .toggle-btn {
            position: absolute; right: 0.75rem;
            background: none; border: none; cursor: pointer;
            color: var(--text-muted); display: flex; align-items: center;
            padding: 0; width: auto;
        }
        .toggle-btn:hover { color: white; }

        /* Register number hint badge */
        .reg-hint {
            display: flex;
            gap: 8px;
            margin-top: 6px;
        }
        .badge {
            font-size: 0.72rem;
            padding: 2px 10px;
            border-radius: 20px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .badge-student { background: rgba(34, 211, 238, 0.15); color: var(--accent); border: 1px solid rgba(34, 211, 238, 0.3); }
        .badge-teacher { background: rgba(167, 139, 250, 0.15); color: #a78bfa; border: 1px solid rgba(167, 139, 250, 0.3); }

        /* Live type indicator */
        .type-indicator {
            display: none;
            align-items: center;
            gap: 8px;
            margin-top: 7px;
            font-size: 0.82rem;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 20px;
            width: fit-content;
        }
        .type-indicator.student { display: flex; background: rgba(34, 211, 238, 0.12); color: var(--accent); border: 1px solid rgba(34, 211, 238, 0.25); }
        .type-indicator.teacher { display: flex; background: rgba(167, 139, 250, 0.12); color: #a78bfa; border: 1px solid rgba(167, 139, 250, 0.25); }
        .type-indicator.invalid  { display: flex; background: rgba(255,107,107,0.1); color: #ff6b6b; border: 1px solid rgba(255,107,107,0.25); }

        button[type="submit"] {
            width: 100%; padding: 0.75rem;
            background-color: var(--primary); color: white;
            border: none; border-radius: 0.5rem; font-weight: bold;
            cursor: pointer; transition: background-color 0.2s, transform 0.1s;
            margin-top: 0.5rem; font-size: 1rem;
        }
        button[type="submit"]:hover { background-color: var(--primary-hover); }
        button[type="submit"]:active { transform: scale(0.98); }
        button[type="submit"]:disabled { opacity: 0.7; cursor: not-allowed; }

        .success-icon {
            width: 60px; height: 60px;
            background: rgba(34, 197, 94, 0.2);
            border-radius: 50%; display: flex;
            align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .hidden { display: none; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate { animation: fadeInUp 0.5s ease-out forwards; }

        .error-msg {
            color: #ff6b6b;
            background: rgba(255, 107, 107, 0.1);
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            border: 1px solid rgba(255, 107, 107, 0.25);
            text-align: center;
        }

        .divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 1.2rem 0;
        }
    </style>
</head>
<body>

    <!-- SUCCESS VIEW -->
    <div id="successView" class="card <?= $is_success ? '' : 'hidden' ?> text-center animate">
        <div class="success-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#22c55e"
                 stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        <h2>Welcome Aboard!</h2>
        <p class="subtitle">Your account has been successfully created.</p>
        <button type="button" onclick="window.location.href='user_login.php'"
                style="background-color:var(--primary);color:white;border:none;padding:0.75rem;
                       border-radius:0.5rem;cursor:pointer;width:100%;font-weight:bold;font-size:1rem;">
            Login Now
        </button>
    </div>

    <!-- FORM VIEW -->
    <div id="formView" class="card <?= $is_success ? 'hidden' : '' ?>">
        <div class="text-center">
            <h2>Create Account</h2>
            <p class="subtitle">Enter your details to get started</p>
        </div>

        <?php if ($message): ?>
            <div class="error-msg"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form id="regForm" method="POST" action="user_register.php">

            <!-- USERNAME -->
            <div class="form-group">
                <label>Username</label>
                <input required type="text" name="username" placeholder="e.g. johndoe"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>

            <!-- REGISTER NUMBER  ← NEW FIELD -->
            <div class="form-group">
                <label>Register Number</label>
                <input required type="text" name="register_no" id="registerNo"
                       placeholder="10-digit  or 12-digit "
                       maxlength="12" minlength="10"
                       oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,''); detectType(this.value);"
                       value="<?= htmlspecialchars($_POST['register_no'] ?? '') ?>">
                <!-- Live type badge -->
                <div id="typeIndicator" class="type-indicator"></div>
                <!-- Static hint -->
                <div class="reg-hint">
                    <span class="badge badge-student">10
                    </span>
                    <span class="badge badge-teacher">12</span>
                </div>
            </div>

            <hr class="divider">

            <!-- PHONE -->
            <div class="form-group">
                <label>Phone Number</label>
                <input required type="tel" name="phone"
                       placeholder="10-digit phone number"
                       maxlength="10" pattern="[0-9]{10}"
                       oninput="this.value=this.value.replace(/[^0-9]/g,'');"
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>

            <!-- PASSWORD -->
            <div class="form-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <input required minlength="8" name="password" type="password"
                           id="passwordInput" class="pwd-input" placeholder="Min. 8 characters">
                    <button type="button" id="togglePassword" class="toggle-btn">
                        <span id="eyeIcon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </span>
                    </button>
                </div>
            </div>

            <!-- CONFIRM PASSWORD -->
            <div class="form-group">
                <label>Re-enter Password</label>
                <input required minlength="8" type="password"
                       id="confirmPasswordInput" placeholder="••••••••">
            </div>

            <!-- SECURITY ANSWER -->
            <div class="form-group">
                <label>Security Answer — Favourite Book?</label>
                <input required type="text" name="security_answer"
                       placeholder="e.g. Harry Potter"
                       value="<?= htmlspecialchars($_POST['security_answer'] ?? '') ?>">
            </div>

            <button type="submit" id="submitBtn">Create Account</button>
        </form>
    </div>

    <script>
        /* ── Live register-number type detector ── */
        function detectType(val) {
            const el = document.getElementById('typeIndicator');
            el.className = 'type-indicator';
            el.innerHTML = '';
            if (val.length === 0) return;
            if (val.length === 10) {
                el.classList.add('teacher');
                el.innerHTML = '👨‍🏫 Detected: <strong>Teacher</strong>';
            } else if (val.length === 12) {
                el.classList.add('student');
                el.innerHTML = '🎓 Detected: <strong>Student</strong>';
            } else if (val.length < 10) {
                el.classList.add('invalid');
                el.innerHTML = `⚠ Need ${10 - val.length} more digit${10 - val.length > 1 ? 's' : ''} (min 10)`;
            } else if (val.length === 11) {
                el.classList.add('invalid');
                el.innerHTML = '⚠ 11 digits not valid — use 10 or 12';
            }
        }

        /* ── Password match validation ── */
        document.getElementById('regForm').addEventListener('submit', function(e) {
            const pwd  = document.getElementById('passwordInput');
            const conf = document.getElementById('confirmPasswordInput');
            const reg  = document.getElementById('registerNo');

            // Extra register_no guard (browser may bypass minlength)
            const len = reg.value.length;
            if (len !== 10 && len !== 12) {
                e.preventDefault();
                reg.setCustomValidity("Register number must be exactly 10 or 12 digits.");
                reg.reportValidity();
                return;
            }
            reg.setCustomValidity('');

            if (pwd.value !== conf.value) {
                e.preventDefault();
                conf.setCustomValidity("Passwords do not match.");
                conf.reportValidity();
            } else {
                conf.setCustomValidity('');
                document.getElementById('submitBtn').innerText = "Processing...";
                document.getElementById('submitBtn').disabled = true;
            }
        });

        /* ── Toggle password visibility ── */
        document.getElementById('togglePassword').addEventListener('click', function() {
            const input = document.getElementById('passwordInput');
            const icon  = document.getElementById('eyeIcon');
            const show  = input.type === 'password';
            input.type  = show ? 'text' : 'password';
            icon.innerHTML = show
                ? `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                       fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                       stroke-linejoin="round">
                       <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                       <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                       <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                       <line x1="2" x2="22" y1="2" y2="22"/></svg>`
                : `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                       fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                       stroke-linejoin="round">
                       <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                       <circle cx="12" cy="12" r="3"/></svg>`;
        });
    </script>
</body>
</html>