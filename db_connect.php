<?php
// ================================================
// db_connect.php — Database Connection (PDO)
// Place this file in: C:\xampp\htdocs\librarysystem22\
// ================================================

$host     = "localhost";
$dbname   = "librite_db";   // Make sure this DB exists in phpMyAdmin
$username = "root";         // Default XAMPP username
$password = "";             // Default XAMPP password

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);

    // Set error mode
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Auto-create missing columns gracefully
    try { $pdo->exec("ALTER TABLE `book_requests` ADD COLUMN `extension_count` TINYINT(1) DEFAULT 0"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE `book_reviews` ADD COLUMN `type` VARCHAR(20) DEFAULT 'review'"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE `book_reviews` ADD COLUMN `updated_at` DATETIME DEFAULT NULL"); } catch(Exception $e){}

} catch (PDOException $e) {
    die("<div style='font-family:sans-serif;padding:30px;background:#1a0000;color:#ff6b6b;border:1px solid #ff4444;margin:20px;border-radius:8px;'>
        <strong>❌ Database Connection Failed:</strong> " . $e->getMessage() . "
        <br><br><small>Check that XAMPP MySQL is running and the database name is correct.</small>
    </div>");
}
?>