<?php
require 'db.php';

$database = isset($_POST['database']) ? $_POST['database'] : '';
$table = isset($_POST['table']) ? $_POST['table'] : '';

if (empty($database) || empty($table)) {
    echo json_encode(['error' => 'Missing database or table name']);
    exit;
}

try {
    $db->exec("USE `$database`");
    $columnsQuery = $db->query("SHOW COLUMNS FROM `$table`");
    $columns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode($columns);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
