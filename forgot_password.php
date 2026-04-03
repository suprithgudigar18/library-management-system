<?php
session_start();
include("db_connect.php");

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $answer = trim($_POST['answer'] ?? '');

    try {
        // 1. Check Admin Table
        $stmt = $pdo->prepare("SELECT id, security_answer FROM admin_creds WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && strtolower($answer) === strtolower($admin['security_answer'])) {
            $_SESSION['reset_user_id'] = $admin['id'];
            $_SESSION['reset_type'] = 'admin'; 
            header("Location: reset_password.php");
            exit();
        }

        // 2. Check User Table
        $stmt = $pdo->prepare("SELECT id, security_answer FROM users WHERE username = ? OR name = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user) {
            if (password_verify($answer, $user['security_answer']) || strtolower($answer) === strtolower($user['security_answer'])) {
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_type'] = 'user'; 
                header("Location: reset_password.php");
                exit();
            }
        }
        $error = "Invalid username or security answer.";
    } catch (PDOException $e) {
        $error = "Database error.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - LIBRITE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Inter:wght@300;400;600&display=swap');
        
        :root {
            --primary-accent: #22d3ee; 
            --bg-deep: #0a192f; /* Consistent Static Dark Blue */
            --bg-darker: #060d1a;
            --glass: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body, html {
            width: 100%; height: 100%; overflow: hidden;
            background: radial-gradient(circle at center, var(--bg-deep) 0%, var(--bg-darker) 100%);
            font-family: 'Inter', sans-serif; color: white;
        }

        .stage {
            width: 100vw; height: 100vh; position: relative; overflow: hidden;
            display: flex; justify-content: center; align-items: center;
            perspective: 2000px;
        }

        /* --- ANIMATION ELEMENTS --- */
        .books-container { position: absolute; inset: 0; z-index: 2; pointer-events: none; }
        .falling-book {
            position: absolute; right: -150px;
            color: var(--primary-accent);
            text-shadow: 0 0 10px var(--primary-accent);
            filter: blur(var(--b-blur, 2px));
            opacity: 0;
            animation: bookDrift var(--b-speed) linear infinite;
        }
        @keyframes bookDrift {
            0% { transform: translate(0,0) rotate(0deg); opacity: 0; }
            15% { opacity: 0.6; }
            85% { opacity: 0.6; }
            100% { transform: translate(-120vw, var(--b-y-drift)) rotate(var(--b-rot)); opacity: 0; }
        }

        .ui-overlay { z-index: 10; width: 100%; display: flex; justify-content: center; }

        /* --- CARD STYLES --- */
        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            padding: 3rem; border-radius: 24px;
            max-width: 450px; width: 90%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        h1 {
            font-family: 'Playfair Display', serif; font-size: 2.2rem;
            margin-bottom: 2rem; text-align: center;
            background: linear-gradient(to bottom, #fff, #999);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        .input-group { margin-bottom: 1.5rem; text-align: left; }

        label {
            display: block; margin-bottom: 0.5rem; font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .input-field {
            width: 100%; padding: 12px 15px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px; color: white;
            font-family: 'Inter', sans-serif; outline: none; transition: 0.3s;
        }

        .input-field:focus {
            border-color: var(--primary-accent);
            box-shadow: 0 0 15px rgba(34, 211, 238, 0.15);
        }

        .btn {
            width: 100%; padding: 12px; border-radius: 50px;
            font-weight: 600; text-transform: uppercase; cursor: pointer; transition: 0.3s;
            border: 1px solid var(--primary-accent); 
            background: rgba(255, 255, 255, 0.03); color: var(--primary-accent);
            margin-top: 1rem;
        }

        .btn:hover {
            background: var(--primary-accent); color: #000;
            transform: translateY(-3px);
        }

        .msg.error {
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid #ff6b6b; color: #ff6b6b;
            padding: 0.8rem; border-radius: 8px; margin-bottom: 1.5rem;
            font-size: 0.9rem; text-align: center;
        }

        .back-link {
            display: block; margin-top: 1.5rem; font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.5); text-decoration: none;
            text-align: center;
        }
        .back-link:hover { color: white; }
    </style>
</head>
<body>
    <div class="stage">
        <div class="books-container" id="books"></div>

        <div class="ui-overlay">
            <div class="glass-card">
                <h1>Reset Access</h1>
                
                <?php if ($error): ?>
                    <div class="msg error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="input-group">
                        <label for="username">Username / ID</label>
                        <input type="text" id="username" name="username" class="input-field" placeholder="Enter your username" required>
                    </div>
                    <div class="input-group">
                        <label for="answer">Security Answer: Favorite Book?</label>
                        <input type="text" id="answer" name="answer" class="input-field" placeholder="Your Answer" required>
                    </div>
                    <button type="submit" class="btn">Verify Identity</button>
                </form>

                <a href="user_login.php" class="back-link">← Return to Login</a>
            </div>
        </div>
    </div>

    <script>
        const booksContainer = document.getElementById('books');
        const bookIcons = ['fa-book', 'fa-book-open', 'fa-journal-whills', 'fa-scroll'];

        function createBook() {
            const book = document.createElement('i');
            book.className = `fas ${bookIcons[Math.floor(Math.random()*bookIcons.length)]} falling-book`;
            const speed = Math.random() * (15 - 8) + 8;
            
            book.style.fontSize = `${Math.random() * 1.2 + 0.8}rem`;
            book.style.top = `${Math.random() * 80 + 10}%`;

            book.style.setProperty('--b-speed', `${speed}s`);
            book.style.setProperty('--b-blur', `${Math.random()*3}px`);
            book.style.setProperty('--b-y-drift', `${(Math.random()-0.5)*300}px`);
            book.style.setProperty('--b-rot', `${(Math.random()-0.5)*360}deg`);

            booksContainer.appendChild(book);
            setTimeout(() => book.remove(), (speed + 2) * 1000);
        }
        
        for(let i=0; i<10; i++) createBook();
        setInterval(createBook, 1500);
    </script>
</body>
</html>