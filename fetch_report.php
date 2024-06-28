<?php
session_start();
if (!isset($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit;
}
require 'db.php';

$type = isset($_POST['reportType']) ? $_POST['reportType'] : '';
$database = isset($_POST['database']) ? $_POST['database'] : '';
$table = isset($_POST['table']) ? $_POST['table'] : '';
$column = isset($_POST['groupByColumn']) ? $_POST['groupByColumn'] : '';
$pivotColumn = isset($_POST['pivotColumn']) ? $_POST['pivotColumn'] : '';
$valueColumns = isset($_POST['valueColumns']) ? $_POST['valueColumns'] : '';
$caseColumn = isset($_POST['caseColumn']) ? $_POST['caseColumn'] : '';
$whenCondition = isset($_POST['whenCondition']) ? $_POST['whenCondition'] : '';
$thenResult = isset($_POST['thenResult']) ? $_POST['thenResult'] : '';
$elseResult = isset($_POST['elseResult']) ? $_POST['elseResult'] : '';

try {
    if (empty($type) || empty($database) || empty($table)) {
        throw new Exception('Invalid request: missing type, database, or table.');
    }

    // Validate database and table names to prevent SQL injection
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $database) || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        throw new Exception('Invalid database or table name.');
    }

    // Select the specified database
    $db->exec("USE `$database`");

    // Fetch primary key or use the first column if no primary key exists
    $columnsQuery = $db->query("SHOW COLUMNS FROM `$table`");
    $columns = $columnsQuery->fetchAll(PDO::FETCH_ASSOC);
    $primaryKey = '';
    foreach ($columns as $col) {
        if ($col['Key'] === 'PRI') {
            $primaryKey = $col['Field'];
            break;
        }
    }
    if (empty($primaryKey)) {
        $primaryKey = $columns[0]['Field'];
    }

    $data = '';

    switch ($type) {
        case 'group_by':
            if (empty($column)) {
                throw new Exception('Missing group by column.');
            }
            $query = $db->prepare("SELECT `$column`, COUNT(*) as count FROM `$table` GROUP BY `$column`");
            $query->execute();
            $results = $query->fetchAll(PDO::FETCH_ASSOC);

            $data .= "<h2>Group By Report</h2>";
            $data .= "<table border='1'><tr><th>$column</th><th>Count</th></tr>";
            foreach ($results as $row) {
                $data .= "<tr><td>" . htmlspecialchars($row[$column]) . "</td><td>" . $row['count'] . "</td></tr>";
            }
            $data .= "</table>";
            break;

        case 'case':
            if (empty($caseColumn)) {
                throw new Exception('Missing case column.');
            }
            
            // Parse the WHEN-THEN conditions
            $conditions = isset($_POST['conditions']) ? json_decode($_POST['conditions'], true) : [];
            $elseResult = isset($_POST['elseResult']) ? $_POST['elseResult'] : '';
        
            if (empty($conditions)) {
                throw new Exception('Missing WHEN-THEN conditions.');
            }
        
            // Construct the CASE statement
            $caseStatement = "CASE";
            $params = [];
            foreach ($conditions as $index => $condition) {
                $caseStatement .= " WHEN `$caseColumn` {$condition['operator']} ? THEN ?";
                $params[] = $condition['value'];
                $params[] = $condition['result'];
            }
            $caseStatement .= " ELSE ? END as case_result";
            $params[] = $elseResult;
        
            // Prepare and execute the query
            $query = $db->prepare("
                SELECT `$primaryKey`, `$caseColumn`,
                $caseStatement
                FROM `$table`
            ");
            $query->execute($params);
            $results = $query->fetchAll(PDO::FETCH_ASSOC);
        
            // Generate the HTML table
            $data .= "<h2>Case Report</h2>";
            $data .= "<table border='1'><tr><th>$primaryKey</th><th>$caseColumn</th><th>Case Result</th></tr>";
            foreach ($results as $row) {
                $data .= "<tr><td>" . htmlspecialchars($row[$primaryKey]) . "</td><td>" . htmlspecialchars($row[$caseColumn]) . "</td><td>" . htmlspecialchars($row['case_result']) . "</td></tr>";
            }
            $data .= "</table>";
            break;

            case 'pivot':
                if (empty($pivotColumn) || empty($valueColumns)) {
                    throw new Exception('Missing pivot parameters.');
                }
            
                // Parse value columns
                $valueColumnsArray = explode(',', $valueColumns);
                $valueColumnsArray = array_map('trim', $valueColumnsArray);
            
                // Get unique values for the pivot column
                $uniqueValuesQuery = $db->query("SELECT DISTINCT `$pivotColumn` FROM `$table` WHERE `$pivotColumn` IS NOT NULL AND `$pivotColumn` != '' ORDER BY `$pivotColumn`");
                $uniqueValues = $uniqueValuesQuery->fetchAll(PDO::FETCH_COLUMN);
            
                // Construct the dynamic pivot query
                $pivotClauses = [];
                foreach ($uniqueValues as $value) {
                    $escapedValue = $db->quote($value);
                    foreach ($valueColumnsArray as $valueColumn) {
                        // Use SUM for numeric values and GROUP_CONCAT for strings
                        $pivotClauses[] = "MAX(CASE WHEN `$pivotColumn` = $escapedValue THEN CAST(`$valueColumn` AS CHAR) END) AS `" . htmlspecialchars($valueColumn) . "($value)`";
                    }
                }
                $pivotClause = implode(", ", $pivotClauses);
            
                $pivotQuery = "
                    SELECT $pivotClause
                    FROM `$table`
                ";
            
                $query = $db->query($pivotQuery);
                $results = $query->fetchAll(PDO::FETCH_ASSOC);
            
                $data .= "<h2>Pivot Report</h2>";
                $data .= "<table border='1'><tr><th>Value Column</th>";
                foreach ($uniqueValues as $value) {
                    $data .= "<th>" . htmlspecialchars($value) . "</th>";
                }
                $data .= "</tr>";
            
                foreach ($valueColumnsArray as $valueColumn) {
                    $data .= "<tr>";
                    $data .= "<td>" . htmlspecialchars($valueColumn) . "</td>";
                    foreach ($uniqueValues as $value) {
                        $data .= "<td>" . htmlspecialchars($results[0][$valueColumn . "($value)"] ?? '') . "</td>";
                    }
                    $data .= "</tr>";
                }
                $data .= "</table>";
                break;
            
                case 'unpivot':
                    try {
                        // Fetch all columns from the table
                        $columnsQuery = $db->query("SHOW COLUMNS FROM `$table`");
                        $allColumns = $columnsQuery->fetchAll(PDO::FETCH_COLUMN);
                        
                        // Get the selected columns for unpivot from the request
                        $unpivotColumns = isset($_POST['unpivotColumns']) ? $_POST['unpivotColumns'] : '';
                        $unpivotColumnsArray = explode(',', $unpivotColumns);
                        $unpivotColumnsArray = array_map('trim', $unpivotColumnsArray);
                
                        // Remove the primary key and pivot column from the columns to unpivot
                        $columnsToUnpivot = array_diff($unpivotColumnsArray, [$primaryKey]);
                
                        if (empty($columnsToUnpivot)) {
                            throw new Exception('No columns to unpivot.');
                        }
                
                        // Construct the UNPIVOT query
                        $unpivotClauses = [];
                        foreach ($columnsToUnpivot as $column) {
                            $unpivotClauses[] = "SELECT `$primaryKey`, " . $db->quote($column) . " AS Employee, `$column` AS Orders FROM `$table` WHERE `$column` IS NOT NULL AND `$column` != ''";
                        }
                        $unpivotQuery = implode(" UNION ALL ", $unpivotClauses);
                
                        // Add ORDER BY clause
                        $unpivotQuery .= " ORDER BY `$primaryKey`, Employee";
                
                        // Execute the query
                        $query = $db->prepare($unpivotQuery);
                        $query->execute();
                
                        // Start output buffering
                        ob_start();
                
                        // Generate the HTML table
                        echo "<h2>Unpivot Report</h2>";
                        echo "<table border='1'><tr><th>$primaryKey</th><th>Employee</th><th>Orders</th></tr>";
                
                        // Fetch and output results in batches
                        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row[$primaryKey]) . "</td>";
                            echo "<td>" . htmlspecialchars($row['Employee']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['Orders']) . "</td>";
                            echo "</tr>";
                
                            // Flush the output buffer every 100 rows to prevent memory issues
                            if ($query->rowCount() % 100 == 0) {
                                ob_flush();
                                flush();
                            }
                        }
                        echo "</table>";
                
                        // End output buffering and return the data
                        $data = ob_get_clean();
                
                    } catch (PDOException $e) {
                        error_log("Database Error in unpivot: " . $e->getMessage());
                        throw new Exception("An error occurred while processing the unpivot operation.");
                    }
                    break;
                            

        default:
            throw new Exception('Invalid report type.');
    }

    echo $data;

} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}
?>
