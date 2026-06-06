<?php
require 'includes/db.php';
$stmt = $pdo->query('SHOW COLUMNS FROM products');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($columns as $col) {
    echo $col['Field'] . "\n";
}
