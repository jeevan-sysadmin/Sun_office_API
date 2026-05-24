<?php
// Enable CORS for development
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Error reporting for debugging (turn off in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Set base path - adjust this based on your installation
$base_dir = __DIR__;
$base_path = '/sun_powers/api'; // Change this to match your actual URL path

// Get request method and URI
$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];

// Remove base path from request URI
$path = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace($base_path, '', $path);
$path = trim($path, '/');

// Log request for debugging
error_log("API Request: $method $request_uri -> $path");

// Split into segments
$segments = $path ? explode('/', $path) : [];

// Handle different endpoints
$endpoint = $segments[0] ?? '';

// Check if endpoint file exists before including
$endpoint_file = __DIR__ . '/' . $endpoint . '.php';
$is_valid_endpoint = in_array($endpoint, ['login', 'dashboard', 'payments', 'notifications', 'test', 'health']);

try {
    switch ($endpoint) {
        case 'login':
            if (file_exists($endpoint_file)) {
                require_once $endpoint_file;
            } else {
                throw new Exception("Login endpoint not found");
            }
            break;
            
        case 'dashboard':
            // Pass remaining path segments to dashboard.php
            $remaining_path = implode('/', array_slice($segments, 1));
            $_GET['action'] = $remaining_path ?: 'overview';
            
            $dashboard_file = __DIR__ . '/dashboard.php';
            if (file_exists($dashboard_file)) {
                require_once $dashboard_file;
            } else {
                throw new Exception("Dashboard endpoint not found");
            }
            break;
            
        case 'payments':
        case 'notifications':
            if (file_exists($endpoint_file)) {
                require_once $endpoint_file;
            } else {
                echo json_encode([
                    "success" => false,
                    "message" => "Endpoint not implemented: $endpoint"
                ]);
            }
            break;
            
        case 'test':
            // Test endpoint
            echo json_encode([
                "success" => true,
                "message" => "Sun Powers API is working",
                "method" => $method,
                "endpoint" => $endpoint,
                "full_path" => $path,
                "segments" => $segments,
                "base_path" => $base_path,
                "timestamp" => date('Y-m-d H:i:s'),
                "server" => [
                    "php_version" => phpversion(),
                    "document_root" => $_SERVER['DOCUMENT_ROOT'],
                    "script_name" => $_SERVER['SCRIPT_NAME']
                ]
            ]);
            break;
            
        case 'health':
            // Health check endpoint
            try {
                require_once __DIR__ . '/config/database.php';
                $database = new Database();
                $conn = $database->getConnection();
                
                $status = "healthy";
                $http_code = 200;
                $db_status = "connected";
                
                if (!$conn) {
                    $status = "unhealthy";
                    $http_code = 503;
                    $db_status = "disconnected";
                }
                
                http_response_code($http_code);
                echo json_encode([
                    "status" => $status,
                    "timestamp" => date('c'),
                    "database" => $db_status,
                    "service" => "Sun Powers API",
                    "version" => "1.0",
                    "environment" => "development"
                ]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    "status" => "error",
                    "message" => $e->getMessage(),
                    "timestamp" => date('c')
                ]);
            }
            break;
            
        default:
            if (empty($endpoint)) {
                // API home - show available endpoints
                echo json_encode([
                    "success" => true,
                    "message" => "Sun Powers API v1.0",
                    "timestamp" => date('Y-m-d H:i:s'),
                    "base_url" => "http://" . $_SERVER['HTTP_HOST'] . $base_path,
                    "endpoints" => [
                        "POST /login" => "User authentication",
                        "GET /dashboard" => "Dashboard overview",
                        "GET /dashboard/overview" => "Dashboard overview data",
                        "GET /dashboard/stats" => "Detailed statistics",
                        "GET /dashboard/orders" => "Recent orders",
                        "GET /dashboard/deliveries" => "Delivery management",
                        "POST /dashboard/deliveries" => "Create delivery",
                        "GET /payments" => "Payment management",
                        "GET /notifications" => "User notifications",
                        "GET /test" => "Test connection",
                        "GET /health" => "System health check"
                    ],
                    "authentication" => "Bearer token required for protected endpoints",
                    "note" => "Check browser console for errors if experiencing white screen"
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    "success" => false,
                    "message" => "Endpoint not found: /$endpoint",
                    "available_endpoints" => [
                        "/login",
                        "/dashboard",
                        "/payments", 
                        "/notifications",
                        "/test",
                        "/health"
                    ],
                    "request_details" => [
                        "method" => $method,
                        "path" => $path,
                        "segments" => $segments
                    ]
                ]);
            }
            break;
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server error",
        "error" => $e->getMessage(),
        "error_details" => [
            "file" => $e->getFile(),
            "line" => $e->getLine(),
            "trace" => $e->getTraceAsString()
        ]
    ]);
}
?>