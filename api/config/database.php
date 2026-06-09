<?php
// C:\xampp\htdocs\sun_office\api\config\database.php

class Database {
    private static $config = [
        'host' => 'localhost',
        'db_name' => 'sun_office',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4'
    ];
    private static $conn = null;

    public static function getConfig() {
        return self::$config;
    }

    public static function getDsn() {
        $config = self::getConfig();
        return "mysql:host={$config['host']};dbname={$config['db_name']};charset={$config['charset']}";
    }

    public function getConnection() {
        return self::getPdoConnection();
    }

    public static function getPdoConnection() {
        if (self::$conn instanceof PDO) {
            return self::$conn;
        }

        try {
            $config = self::getConfig();
            self::$conn = new PDO(
                self::getDsn(),
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_PERSISTENT => true,
                    PDO::ATTR_TIMEOUT => 3,
                ]
            );
            self::$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            return null;
        }

        return self::$conn;
    }

    public static function getMysqliConnection() {
        $config = self::getConfig();
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $conn = new mysqli(
                $config['host'],
                $config['username'],
                $config['password'],
                $config['db_name']
            );
            $conn->set_charset($config['charset']);
            return $conn;
        } catch (mysqli_sql_exception $exception) {
            error_log("MySQLi connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed: " . $exception->getMessage(), 0, $exception);
        }
    }
}

function getDatabaseConfig() {
    return Database::getConfig();
}

function connectDB() {
    return Database::getMysqliConnection();
}

// Test database connection
function testDatabaseConnection() {
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "Database connection successful!";
        
        // Check if database exists
        $stmt = $conn->query("SELECT DATABASE() as db_name");
        $result = $stmt->fetch();
        echo "\nConnected to database: " . $result['db_name'];
        
        // Check tables
        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "\nTables in database: " . (empty($tables) ? "No tables found" : implode(", ", $tables));
        
        $conn = null;
    } else {
        echo "Database connection failed!";
    }
}

// Uncomment to test database connection
// testDatabaseConnection();
?>
