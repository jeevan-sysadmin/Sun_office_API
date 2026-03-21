<?php
// C:\xampp\htdocs\sun_office\api\login.php

// Enable CORS for development
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/jwt_helper.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

class LoginAPI {
    private $conn;
    private $data;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        if (!$this->conn) {
            $this->sendError("Database connection failed", 500);
            exit();
        }
        
        // Get input data
        $input = file_get_contents("php://input");
        $this->data = json_decode($input);
        
        if (!$this->data && json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError("Invalid JSON input", 400);
            exit();
        }
    }

    public function login() {
        try {
            // Validate input
            if (!isset($this->data->email) || empty($this->data->email)) {
                $this->sendError("Email is required", 400);
                return;
            }

            if (!isset($this->data->password) || empty($this->data->password)) {
                $this->sendError("Password is required", 400);
                return;
            }

            $email = filter_var(trim($this->data->email), FILTER_SANITIZE_EMAIL);
            $password = $this->data->password;

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->sendError("Invalid email format", 400);
                return;
            }

            // Check if users table exists, create if not
            $this->createUsersTableIfNotExists();

            // Prepare SQL query
            $query = "SELECT id, name, email, password, role, is_active FROM users WHERE email = :email";
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                $this->sendError("Database query preparation failed", 500);
                return;
            }

            $stmt->bindParam(":email", $email, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Check if account is active
                    if ($row['is_active'] != 1) {
                        $this->sendError("Account is deactivated. Please contact administrator.", 401);
                        return;
                    }
                    
                    // Verify password
                    if (password_verify($password, $row['password'])) {
                        // Update last login
                        $this->updateLastLogin($row['id']);
                        
                        // Remove password from response
                        unset($row['password']);
                        
                        // Generate JWT token
                        $tokenPayload = [
                            'user_id' => $row['id'],
                            'email' => $row['email'],
                            'name' => $row['name'],
                            'role' => $row['role'],
                            'iat' => time(),
                            'exp' => time() + (24 * 60 * 60) // 24 hours
                        ];
                        $token = JWT::encode($tokenPayload);
                        
                        // ALL USERS REDIRECT TO DASHBOARD - regardless of role
                        $redirectUrl = "/dashboard";
                        
                        // Send success response
                        $this->sendSuccess([
                            "user" => $row,
                            "token" => $token,
                            "role" => $row['role'],
                            "redirect_to" => $redirectUrl,
                            "dashboard_url" => $redirectUrl,
                            "message" => "Login successful. Redirecting to dashboard..."
                        ]);
                    } else {
                        $this->sendError("Invalid email or password", 401);
                    }
                } else {
                    $this->sendError("Invalid email or password", 401);
                }
            } else {
                $this->sendError("Database query failed", 500);
            }
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $this->sendError("Server error occurred", 500);
        }
    }

    private function createUsersTableIfNotExists() {
        try {
            // Create users table if it doesn't exist
            $createTable = "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('admin', 'staff') DEFAULT 'staff',
                is_active TINYINT(1) DEFAULT 1,
                last_login TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->conn->exec($createTable);
            
            // Check if admin exists
            $checkAdmin = "SELECT id FROM users WHERE email = 'admin@sunoffice.com'";
            $result = $this->conn->query($checkAdmin);
            
            if ($result->rowCount() == 0) {
                // Create default admin
                $hashedPassword = password_hash('admin123', PASSWORD_BCRYPT);
                $insertAdmin = "INSERT INTO users (name, email, password, role) 
                               VALUES ('Admin User', 'admin@sunoffice.com', :password, 'admin')";
                $stmt = $this->conn->prepare($insertAdmin);
                $stmt->bindParam(':password', $hashedPassword);
                $stmt->execute();
                
                // Create default staff
                $hashedPassword2 = password_hash('staff123', PASSWORD_BCRYPT);
                $insertStaff = "INSERT INTO users (name, email, password, role) 
                              VALUES ('Staff User', 'staff@sunoffice.com', :password, 'staff')";
                $stmt2 = $this->conn->prepare($insertStaff);
                $stmt2->bindParam(':password', $hashedPassword2);
                $stmt2->execute();
            }
        } catch (Exception $e) {
            error_log("Table creation error: " . $e->getMessage());
        }
    }

    private function updateLastLogin($user_id) {
        try {
            $query = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Failed to update last login: " . $e->getMessage());
        }
    }

    private function sendSuccess($data = []) {
        $response = array_merge([
            "success" => true,
            "message" => "Login successful"
        ], $data);
        
        echo json_encode($response, JSON_PRETTY_PRINT);
    }

    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            "success" => false,
            "message" => $message
        ], JSON_PRETTY_PRINT);
    }

    public function __destruct() {
        if ($this->conn) {
            $this->conn = null;
        }
    }
}

// Handle the request
try {
    $api = new LoginAPI();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        $api->login();
    } elseif ($method === 'GET') {
        // For testing - check if API is reachable
        echo json_encode([
            "success" => true,
            "message" => "SUN Office Login API is running",
            "version" => "1.0",
            "method" => "POST",
            "required_fields" => ["email", "password"],
            "test_accounts" => [
                [
                    "email" => "admin@sunoffice.com",
                    "password" => "admin123",
                    "role" => "admin",
                    "redirects_to" => "/dashboard"  // Changed from /admin-dashboard to /dashboard
                ],
                [
                    "email" => "staff@sunoffice.com",
                    "password" => "staff123",
                    "role" => "staff",
                    "redirects_to" => "/dashboard"
                ]
            ],
            "note" => "All users are redirected to the dashboard after login",
            "timestamp" => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(405);
        echo json_encode([
            "success" => false,
            "message" => "Method not allowed"
        ], JSON_PRETTY_PRINT);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server error",
        "error" => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>