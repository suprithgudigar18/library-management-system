<?php
// ================================================
// db_connection.php — Database Connection File
// Place this file in: C:\xampp\htdocs\librarysystem22\
// ================================================

$host     = "localhost";
$user     = "root";         // Default XAMPP username
$password = "";             // Default XAMPP password (empty)
$database = "librarysystem22"; // Your database name

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("<div style='font-family:sans-serif;padding:30px;background:#1a0000;color:#ff6b6b;border:1px solid #ff4444;margin:20px;border-radius:8px;'>
        <strong>❌ Database Connection Failed:</strong> " . $conn->connect_error . "
        <br><br><small>Check that XAMPP MySQL is running and the database name is correct.</small>
    </div>");
}

// Set charset for proper encoding
$conn->set_charset("utf8mb4");
?>