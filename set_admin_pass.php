<?php
// ============================================================
//  set_admin_pass.php  — ONE-TIME SETUP SCRIPT
//  Place in your project folder, open in browser ONCE,
//  then DELETE this file immediately.
// ============================================================
include "db_connect.php";

$username       = 'admin';
$password       = 'admin123';
$security_ans   = 'Harry Potter';
$hashed         = password_hash($password, PASSWORD_DEFAULT);

try {
    // Remove any old admin row, then insert fresh
    $pdo->prepare("DELETE FROM admin_creds WHERE username = ?")->execute([$username]);
    $pdo->prepare(
        "INSERT INTO admin_creds (username, password, security_answer) VALUES (?, ?, ?)"
    )->execute([$username, $hashed, $security_ans]);

    echo "<div style='font-family:monospace;background:#0a192f;color:#22d3ee;
                      min-height:100vh;display:flex;flex-direction:column;
                      justify-content:center;align-items:center;margin:0;padding:40px;'>
            <h2 style='color:#22d3ee;'>✅ Admin credentials set!</h2>
            <p>Username : <strong>admin</strong></p>
            <p>Password : <strong>admin123</strong></p>
            <br>
            <p style='color:#ff6b6b;font-weight:bold;'>
                ⚠ DELETE this file (set_admin_pass.php) now for security!
            </p>
            <br>
            <a href='admin_login.php' style='color:#22d3ee;'>→ Go to Login</a>
          </div>";
} catch (PDOException $e) {
    echo "<pre style='color:red'>Error: " . $e->getMessage() . "</pre>";
}
?>