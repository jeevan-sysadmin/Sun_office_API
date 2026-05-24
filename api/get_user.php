<?php
// C:\xampp\htdocs\sun_office\api\get_user.php

// Enable CORS for development
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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

class GetUserAPI {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        if (!$this->conn) {
            $this->sendError("Database connection failed", 500);
            exit();
        }
    }

    public function getUser() {
        try {
            // Get Authorization header
            $headers = getallheaders();
            $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
            
            if (!$authHeader || !preg_match('/Bearer\s+(.*)/', $authHeader, $matches)) {
                $this->sendError("No token provided", 401);
                return;
            }
            
            $token = $matches[1];
            
            // Decode JWT token
            $payload = JWT::decode($token);
            
            if (!$payload || !isset($payload->user_id)) {
                $this->sendError("Invalid token", 401);
                return;
            }
            
            $userId = $payload->user_id;
            
            // Get user from database
            $query = "SELECT id, name, email, role, is_active, 
                             department, position, phone, address, 
                             join_date, salary, last_login, created_at, updated_at 
                      FROM users WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $userId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    $this->sendSuccess($user);
                } else {
                    $this->sendError("User not found", 404);
                }
            } else {
                $this->sendError("Database query failed", 500);
            }
            
        } catch (Exception $e) {
            error_log("Get user error: " . $e->getMessage());
            $this->sendError("Server error occurred", 500);
        }
    }

    private function sendSuccess($user) {
        echo json_encode([
            "success" => true,
            "user" => $user
        ], JSON_PRETTY_PRINT);
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
    $api = new GetUserAPI();
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $api->getUser();
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