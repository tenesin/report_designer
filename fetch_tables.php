<?php
session_start();
require 'db.php';

try {
    if (!isset($_POST['database'])) {
        throw new Exception('Database parameter missing.');
    }

    $database = $_POST['database'];

    // Validate database name to prevent SQL injection
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
        throw new Exception('Invalid database name.');
    }

    // Select the database
    $db->exec("USE `$database`");

    // Fetch tables
    $tablesQuery = $db->query("SHOW TABLES");
    $tables = $tablesQuery->fetchAll(PDO::FETCH_COLUMN);

    // Return JSON response
    echo json_encode($tables);

} catch (Exception $e) {
    // Log error to a file
    error_log($e->getMessage(), 3, 'errors.log');

    // Return error message
    echo json_encode(['error' => $e->getMessage()]);
}

