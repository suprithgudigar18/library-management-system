<?php
// ================================================
// db_connect.php — Database Connection (PDO)
// Place this file in: C:\xampp\htdocs\librarysystem22\
// ================================================

$host     = "127.0.0.1";
$dbname   = "librite_db";   // Make sure this DB exists in phpMyAdmin
$username = "root";         // Default XAMPP username
$password = "";             // Default XAMPP password

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Error 1049 means 'Unknown database'
    if ($e->getCode() == 1049) {
        try {
            // Re-connect without database selected
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Auto-create database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            $pdo->exec("USE `$dbname`");
            
            // Auto-import the SQL file if available
            $sqlFile = __DIR__ . '/librite_db_07_2021.sql';
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                // Simple execution of the SQL dump
                $pdo->exec($sql);
            }
        } catch (PDOException $ex) {
            die("<div style='font-family:sans-serif;padding:30px;background:#1a0000;color:#ff6b6b;border:1px solid #ff4444;margin:20px;border-radius:8px;'>
                <strong>❌ Database Auto-Creation Failed:</strong> " . $ex->getMessage() . "
            </div>");
        }
    } else {
        die("<div style='font-family:sans-serif;padding:30px;background:#1a0000;color:#ff6b6b;border:1px solid #ff4444;margin:20px;border-radius:8px;'>
            <strong>❌ Database Connection Failed:</strong> " . $e->getMessage() . "
            <br><br><small>Check that XAMPP MySQL is running and the credentials are correct.</small>
        </div>");
    }
}

// Auto-create missing columns gracefully for existing structure mapping
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `book_reviews` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `book_id` int(11) NOT NULL,
        `rating` int(11) DEFAULT 0,
        `comment` text,
        `type` varchar(20) DEFAULT 'review',
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `book_id` (`book_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch(Exception $e){}

try { $pdo->exec("ALTER TABLE `book_requests` ADD COLUMN `extension_count` TINYINT(1) DEFAULT 0"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE `book_reviews` ADD COLUMN `type` VARCHAR(20) DEFAULT 'review'"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE `book_reviews` ADD COLUMN `updated_at` DATETIME DEFAULT NULL"); } catch(Exception $e){}
try { 
    $pdo->exec("CREATE TABLE IF NOT EXISTS `website_reports` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `issue_type` varchar(50) NOT NULL,
        `description` text NOT NULL,
        `status` varchar(20) DEFAULT 'Pending',
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"); 
} catch(Exception $e){}
?>