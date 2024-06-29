<?php
session_start();
if (!isset($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit;
}
require 'db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Generator</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Generate Report</h1>
    <form id="reportForm">
        <label for="database">Select Database:</label>
        <select name="database" id="database" onchange="fetchTables()">
            <option value="">Select Database</option>
            <?php
            $databasesQuery = $db->query("SHOW DATABASES");
            $databases = $databasesQuery->fetchAll(PDO::FETCH_COLUMN);
            foreach ($databases as $database): ?>
                <option value="<?= htmlspecialchars($database) ?>"><?= htmlspecialchars($database) ?></option>
            <?php endforeach; ?>
        </select>
        <br><br>
        
        <label for="table">Select Table:</label>
        <select name="table" id="table" onchange="fetchColumns()">
            <option value="">Select Table</option>
        </select>
        <br><br>
        
        <label for="reportType">Select Report Type:</label>
        <select name="reportType" id="reportType" onchange="toggleInputs()">
            <option value="">Select Report Type</option>
            <option value="group_by">Group By</option>
            <option value="case">Case</option>
            <option value="pivot">Pivot</option>
        </select>
        <br><br>

        <div id="groupByInputs" style="display: none;">
            <label for="groupByColumn">Select Column to Group By:</label>
            <select name="groupByColumn" id="groupByColumn">
                <option value="">Select Column</option>
            </select>
            <br><br>
        </div>

        <div id="caseInputs" style="display: none;">
            <label for="caseColumn">Select Column for Case:</label>
            <select name="caseColumn" id="caseColumn">
                <option value="">Select Column</option>
            </select>
            <br><br>

            <div id="conditionsContainer">
                <div class="condition">
                    <select name="operator[]">
                        <option value="=">=</option>
                        <option value="<>">&lt;&gt;</option>
                        <option value=">">&gt;</option>
                        <option value=">=">&gt;=</option>
                        <option value="<">&lt;</option>
                        <option value="<=">&lt;=</option>
                        <option value="LIKE">LIKE</option>
                    </select>
                    <input type="text" name="value[]" placeholder="Value">
                    <input type="text" name="result[]" placeholder="Result">
                    <button type="button" onclick="addCondition()">+</button>
                </div>
            </div>

            <label for="elseResult">Else Result:</label>
            <input type="text" name="elseResult" id="elseResult">
            <br><br>
        </div>

        <div id="pivotInputs" style="display: none;">
            <label for="pivotColumn">Select Column to Pivot:</label>
            <select name="pivotColumn" id="pivotColumn">
                <option value="">Select Column</option>
            </select>
            <br><br>

            <label for="valueColumns">Select Value Columns (comma separated):</label>
            <input type="text" name="valueColumns" id="valueColumns">
            <br><br>
        </div>

</div>




        <button type="submit">Generate Report</button>
    </form>

    <div id="reportResult"></div>
    <button type="button" id="exportButton" style="display: none;" onclick="exportToExcel()">Export to Excel</button>
<button type="button" id="unpivotButton" style="display: none;" onclick="unpivotReport()">Unpivot</button>

    <script>

function unpivotReport() {
    var database = $('#database').val();
    var table = $('#table').val();
    var pivotColumn = $('#pivotColumn').val();
    var valueColumns = $('#valueColumns').val();

    $.ajax({
        url: 'fetch_report.php',
        type: 'POST',
        data: {
            reportType: 'unpivot',
            database: database,
            table: table,
            pivotColumn: pivotColumn,
            valueColumns: valueColumns
        },
        success: function(response) {
            $('#reportResult').html(response);
            $('#unpivotButton').hide();
            $('#exportButton').show();
        },
        error: function(xhr, status, error) {
            $('#reportResult').html('Error: ' + error);
        }
    });
}
$('#reportForm').on('submit', function(e) {
    e.preventDefault();
    var formData = $(this).serialize();
    var reportType = $('#reportType').val();

    $.ajax({
        url: 'fetch_report.php',
        type: 'POST',
        data: formData,
        success: function(response) {
            $('#reportResult').html(response);
            $('#exportButton').show();
            if (reportType === 'pivot') {
                $('#unpivotButton').show();
            } else {
                $('#unpivotButton').hide();
            }
        },
        error: function(xhr, status, error) {
            $('#reportResult').html('Error: ' + error);
        }
    });
});

        $('#reportForm').on('submit', function(e) {
            e.preventDefault();
            var formData = $(this).serializeArray();
            var conditions = [];
            var operators = [];
            var values = [];
            var results = [];

            formData.forEach(function(item) {
                if (item.name === 'operator[]') operators.push(item.value);
                if (item.name === 'value[]') values.push(item.value);
                if (item.name === 'result[]') results.push(item.value);
            });

            for (var i = 0; i < operators.length; i++) {
                conditions.push({
                    operator: operators[i],
                    value: values[i],
                    result: results[i]
                });
            }

            formData.push({name: 'conditions', value: JSON.stringify(conditions)});

           
            $.ajax({
                url: 'fetch_report.php',
                type: 'POST',
                data: $.param(formData),
                success: function(response) {
                    $('#reportResult').html(response);
                    $('#exportButton').show();
                },
                error: function(xhr, status, error) {
                    $('#reportResult').html('Error: ' + error);
                }
            });
        });

        function fetchTables() {
            var database = $('#database').val();
            if (database) {
                $.post('fetch_tables.php', {database: database}, function(data) {
                    var tables = JSON.parse(data);
                    var tableSelect = $('#table');
                    tableSelect.empty();
                    tableSelect.append('<option value="">Select Table</option>');
                    tables.forEach(function(table) {
                        tableSelect.append('<option value="' + table + '">' + table + '</option>');
                    });
                    // Clear column dropdown when table changes
                    $('#column').empty().append('<option value="">Select Column</option>');
                });
            }
        }

        function fetchColumns() {
            var database = $('#database').val();
            var table = $('#table').val();
            if (database && table) {
                $.post('fetch_columns.php', {database: database, table: table}, function(data) {
                    try {
                        var columns = JSON.parse(data);
                        if (columns.error) {
                            console.error('Error fetching columns:', columns.error);
                            return;
                        }
                        
                        // Update all column dropdowns
                        var columnSelects = $('#groupByColumn, #caseColumn, #pivotColumn, #columnToUnpivot');
                        columnSelects.empty();
                        columnSelects.append('<option value="">Select Column</option>');
                        
                        columns.forEach(function(column) {
                            columnSelects.append('<option value="' + column + '">' + column + '</option>');
                        });
                        
                    } catch (e) {
                        console.error('Error parsing column data:', e);
                    }
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error:', textStatus, errorThrown);
                });
            }
        }

        function toggleInputs() {
            var reportType = $('#reportType').val();
            $('#groupByInputs, #caseInputs, #pivotInputs, #unpivotInputs').hide();
            if (reportType === 'group_by') {
                $('#groupByInputs').show();
            } else if (reportType === 'case') {
                $('#caseInputs').show();
            } else if (reportType === 'pivot') {
                $('#pivotInputs').show();
            } else if (reportType === 'unpivot') {
                $('#unpivotInputs').show();
            }
        }
        

        function addCondition() {
            var container = document.getElementById('conditionsContainer');
            var newCondition = document.createElement('div');
            newCondition.className = 'condition';
            newCondition.innerHTML = `
                <select name="operator[]">
                    <option value="=">=</option>
                    <option value="<>">&lt;&gt;</option>
                    <option value=">">&gt;</option>
                    <option value=">=">&gt;=</option>
                    <option value="<">&lt;</option>
                    <option value="<=">&lt;=</option>
                    <option value="LIKE">LIKE</option>
                </select>
                <input type="text" name="value[]" placeholder="Value">
                <input type="text" name="result[]" placeholder="Result">
                <button type="button" onclick="removeCondition(this)">-</button>
            `;
            container.appendChild(newCondition);
        }

        function removeCondition(button) {
            button.parentElement.remove();
        }


    function exportToExcel() {
        var table = document.querySelector('#reportResult table');
        if (!table) {
            alert('No report to export');
            return;
        }

        var tableHTML = table.outerHTML.replace(/ /g, '%20');

        var template = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="UTF-8">
                <!--[if gte mso 9]>
                <xml>
                    <x:ExcelWorkbook>
                        <x:ExcelWorksheets>
                            <x:ExcelWorksheet>
                                <x:Name>Sheet1</x:Name>
                                <x:WorksheetOptions>
                                    <x:DisplayGridlines/>
                                </x:WorksheetOptions>
                            </x:ExcelWorksheet>
                        </x:ExcelWorksheets>
                    </x:ExcelWorkbook>
                </xml>
                <![endif]-->
            </head>
            <body>
                <table>{table}</table>
            </body>
            </html>`;

        var ctx = { worksheet: 'Sheet1', table: tableHTML };
        var base64 = function(s) { return window.btoa(unescape(encodeURIComponent(s))); };
        var format = function(s, c) { return s.replace(/{(\w+)}/g, function(m, p) { return c[p]; }); };

        var link = document.createElement('a');
        link.href = 'data:application/vnd.ms-excel;base64,' + base64(format(template, ctx));
        link.download = 'report.xls';
        link.click();
    }

    $(document).ready(function() {
        $('#database').change(fetchTables);
        $('#table').change(fetchColumns);
        $('#reportType').change(toggleInputs);
        toggleInputs(); // Call this to set initial state

        $('#reportType').change(function() {
        var reportType = $(this).val();
        if (reportType === 'pivot') {
            $('#pivotInputs').show();
        } else {
            $('#pivotInputs').hide();
        }
        $('#unpivotButton').hide();
    });


        $('#reportForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: 'fetch_report.php',
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    $('#reportResult').html(response);
                    $('#exportButton').show(); // Show the export button when report is generated
                },
                error: function(xhr, status, error) {
                    $('#reportResult').html('Error: ' + error);
                }
            });
        });
    });


    </script>
</body>
</html>
