<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

date_default_timezone_set('Asia/Kolkata');

$backupDir = __DIR__ . '/backups';
$historyFile = $backupDir . '/backup_history.json';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

function jsonResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function getHistory($historyFile) {
    if (!file_exists($historyFile)) {
        return [];
    }
    $raw = file_get_contents($historyFile);
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function saveHistory($historyFile, $history) {
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));
}

function exportDatabaseToSql($outputFile) {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $dbName = 'sun_office';

    $conn = new mysqli($host, $user, $pass, $dbName);
    if ($conn->connect_error) {
        $dbName = 'sun_computers';
        $conn = new mysqli($host, $user, $pass, $dbName);
        if ($conn->connect_error) {
            throw new Exception('Database connection failed');
        }
    }

    $conn->set_charset('utf8mb4');
    $sql = "-- Sun Office Database Backup\n";
    $sql .= "-- Generated at: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database: {$dbName}\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tablesResult = $conn->query("SHOW TABLES");
    $tables = [];
    while ($row = $tablesResult->fetch_array()) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        $createResult = $conn->query("SHOW CREATE TABLE `{$table}`");
        $createRow = $createResult->fetch_assoc();
        $sql .= "-- Table structure for `{$table}`\n";
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $createRow['Create Table'] . ";\n\n";

        $rowsResult = $conn->query("SELECT * FROM `{$table}`");
        if ($rowsResult && $rowsResult->num_rows > 0) {
            $sql .= "-- Dumping data for `{$table}`\n";
            while ($row = $rowsResult->fetch_assoc()) {
                $columns = array_map(function($col) { return "`{$col}`"; }, array_keys($row));
                $values = array_map(function($value) use ($conn) {
                    if ($value === null) return "NULL";
                    return "'" . $conn->real_escape_string($value) . "'";
                }, array_values($row));
                $sql .= "INSERT INTO `{$table}` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n";
            }
            $sql .= "\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

    file_put_contents($outputFile, $sql);
    $conn->close();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $history = getHistory($historyFile);
        usort($history, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        jsonResponse(true, 'Backup history loaded', $history);
    }

    if ($action === 'take' && ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET')) {
        $fileName = 'sun_office.sql';
        $filePath = $backupDir . '/' . $fileName;

        exportDatabaseToSql($filePath);

        $history = getHistory($historyFile);
        $entry = [
            'id' => uniqid('backup_', true),
            'file_name' => $fileName,
            'created_at' => date('c'),
            'size' => filesize($filePath)
        ];
        array_unshift($history, $entry);
        saveHistory($historyFile, $history);

        jsonResponse(true, 'Backup created', [
            'file_name' => $fileName,
            'created_at' => $entry['created_at'],
            'download_url' => '/sun_office/api/backup.php?action=download&file=' . rawurlencode($fileName)
        ]);
    }

    if ($action === 'take_download' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $fileName = isset($_GET['file']) ? basename($_GET['file']) : 'sun_office.sql';
        $filePath = $backupDir . '/' . $fileName;

        exportDatabaseToSql($filePath);

        $history = getHistory($historyFile);
        $entry = [
            'id' => uniqid('backup_', true),
            'file_name' => $fileName,
            'created_at' => date('c'),
            'size' => filesize($filePath)
        ];
        array_unshift($history, $entry);
        saveHistory($historyFile, $history);

        header('Content-Description: File Transfer');
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    if ($action === 'download' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $fileName = isset($_GET['file']) ? basename($_GET['file']) : 'sun_office.sql';
        $filePath = $backupDir . '/' . $fileName;

        if (!file_exists($filePath)) {
            http_response_code(404);
            echo 'Backup file not found';
            exit;
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    jsonResponse(false, 'Invalid action or method', null, 400);
} catch (Exception $e) {
    jsonResponse(false, 'Backup operation failed: ' . $e->getMessage(), null, 500);
}
