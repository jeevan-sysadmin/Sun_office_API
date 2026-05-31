<?php
// C:\xampp\htdocs\sun_office\api\config\database.php

class Database {
    private $host = "localhost";
    private $db_name = "sun_office";
    private $username = "root";
    private $password = "";
    private static $conn = null;

    public function getConnection() {
        if (self::$conn instanceof PDO) {
            return self::$conn;
        }

        try {
            self::$conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
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
