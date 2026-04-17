<?php
require 'db_connect.php';
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "TABLES FOUND: " . implode(', ', $tables) . "\n\n";

if (in_array('book_reviews', $tables)) {
    echo "book_reviews exists. Columns:\n";
    $cols = $pdo->query('DESCRIBE book_reviews')->fetchAll(PDO::FETCH_ASSOC);
    foreach($cols as $c) { echo $c['Field'] . " - " . $c['Type'] . "\n"; }
} else {
    echo "book_reviews DOES NOT EXIST.\n";
}

if (in_array('book_reports', $tables)) {
    echo "\nbook_reports exists. Columns:\n";
    $cols = $pdo->query('DESCRIBE book_reports')->fetchAll(PDO::FETCH_ASSOC);
    foreach($cols as $c) { echo $c['Field'] . " - " . $c['Type'] . "\n"; }
} else {
    echo "\nbook_reports DOES NOT EXIST.\n";
}
?>
