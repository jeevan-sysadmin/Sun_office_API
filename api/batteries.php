<?php
// C:\xampp\htdocs\sun_office\api\batteries.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration
require_once __DIR__ . '/config/database.php';

// Get database connection
function getDBConnection() {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    return $conn;
}

// Send JSON response
function sendResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

try {
    $conn = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetRequest($conn);
            break;
        case 'POST':
            handlePostRequest($conn);
            break;
        case 'PUT':
            handlePutRequest($conn);
            break;
        case 'DELETE':
            handleDeleteRequest($conn);
            break;
        default:
            sendResponse(false, 'Method not allowed', null, 405);
    }
} catch (Exception $e) {
    error_log("Batteries API Error: " . $e->getMessage());
    sendResponse(false, 'Server error: ' . $e->getMessage(), null, 500);
}

// GET Request Handler
function handleGetRequest($conn) {
    try {
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        $battery_code = isset($_GET['battery_code']) ? $_GET['battery_code'] : null;
        $serial = isset($_GET['serial']) ? $_GET['serial'] : null;
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        $category = isset($_GET['category']) ? $_GET['category'] : null;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $limit;
        
        $where = [];
        $params = [];
        
        if ($id) {
            $where[] = "id = ?";
            $params[] = $id;
        }
        if ($battery_code) {
            $where[] = "battery_code = ?";
            $params[] = $battery_code;
        }
        if ($serial) {
            $where[] = "battery_serial = ?";
            $params[] = $serial;
        }
        if ($status) {
            $where[] = "status = ?";
            $params[] = $status;
        }
        if ($category) {
            $where[] = "category = ?";
            $params[] = $category;
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM batteries";
        if (!empty($where)) {
            $countSql .= " WHERE " . implode(" AND ", $where);
        }
        $countStmt = $conn->prepare($countSql);
        foreach ($params as $i => $param) {
            $countStmt->bindValue($i + 1, $param);
        }
        $countStmt->execute();
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Main query
        $sql = "SELECT * FROM batteries";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY created_at DESC";
        
        if (!$id && !$battery_code && !$serial) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $i => $param) {
            $stmt->bindValue($i + 1, $param);
        }
        $stmt->execute();
        
        if ($id || $battery_code || $serial) {
            $battery = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$battery) {
                sendResponse(false, 'Battery not found', null, 404);
            }
            sendResponse(true, 'Battery retrieved successfully', $battery);
        } else {
            $batteries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = [
                'batteries' => $batteries,
                'count' => count($batteries),
                'total_count' => intval($totalCount),
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($totalCount / $limit)
            ];
            sendResponse(true, 'Batteries retrieved successfully', $response);
        }
    } catch (PDOException $e) {
        sendResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
    }
}

// POST Request Handler (Create) - NO REQUIRED FIELDS
function handlePostRequest($conn) {
    try {
        // Get input based on content type
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            // Handle JSON input
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                sendResponse(false, 'Invalid JSON: ' . json_last_error_msg(), null, 400);
            }
        } else {
            // Handle form-urlencoded input
            $data = $_POST;
        }
        
        // REMOVED: No required fields validation - all fields optional
        
        // Check if serial number exists (only if provided)
        if (isset($data['battery_serial']) && !empty(trim($data['battery_serial']))) {
            $checkStmt = $conn->prepare("SELECT id FROM batteries WHERE battery_serial = ?");
            $checkStmt->execute([trim($data['battery_serial'])]);
            if ($checkStmt->rowCount() > 0) {
                sendResponse(false, 'Battery serial number already exists', null, 409);
            }
        }
        
        // Prepare data - all fields optional
        $battery_model = isset($data['battery_model']) ? trim($data['battery_model']) : '';
        $battery_serial = isset($data['battery_serial']) ? trim($data['battery_serial']) : '';
        $brand = isset($data['brand']) ? trim($data['brand']) : '';
        $capacity = isset($data['capacity']) ? trim($data['capacity']) : '';
        $voltage = isset($data['voltage']) ? trim($data['voltage']) : '12V';
        $battery_type = isset($data['battery_type']) ? trim($data['battery_type']) : 'lead_acid';
        $category = isset($data['category']) ? trim($data['category']) : 'inverter';
        $specifications = isset($data['specifications']) ? trim($data['specifications']) : '';
        $warranty_period = isset($data['warranty_period']) ? trim($data['warranty_period']) : '1 year';
        $amc_period = isset($data['amc_period']) ? trim($data['amc_period']) : '0';
        $price = isset($data['price']) ? floatval($data['price']) : 0.00;
        $battery_condition = isset($data['battery_condition']) ? trim($data['battery_condition']) : 'good';
        $status = isset($data['status']) ? trim($data['status']) : 'active';
        $inverter = isset($data['inverter']) ? trim($data['inverter']) : '';
        
        // Handle dates
        $purchase_date = null;
        if (isset($data['purchase_date']) && !empty($data['purchase_date'])) {
            $purchase_date = $data['purchase_date'];
        }
        
        $installation_date = null;
        if (isset($data['installation_date']) && !empty($data['installation_date'])) {
            $installation_date = $data['installation_date'];
        }
        
        // Insert battery
        $sql = "INSERT INTO batteries (
            battery_model, battery_serial, brand, capacity, voltage, 
            battery_type, category, specifications, purchase_date, 
            warranty_period, amc_period, price, installation_date, 
            battery_condition, status, inverter, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $battery_model,
            $battery_serial,
            $brand,
            $capacity,
            $voltage,
            $battery_type,
            $category,
            $specifications,
            $purchase_date,
            $warranty_period,
            $amc_period,
            $price,
            $installation_date,
            $battery_condition,
            $status,
            $inverter
        ]);
        
        $battery_id = $conn->lastInsertId();
        
        // Get created battery
        $getStmt = $conn->prepare("SELECT * FROM batteries WHERE id = ?");
        $getStmt->execute([$battery_id]);
        $battery = $getStmt->fetch(PDO::FETCH_ASSOC);
        
        sendResponse(true, 'Battery created successfully', $battery, 201);
        
    } catch (PDOException $e) {
        sendResponse(false, 'Failed to create battery: ' . $e->getMessage(), null, 500);
    }
}

// PUT Request Handler (Update) - NO REQUIRED FIELDS
function handlePutRequest($conn) {
    try {
        // Get input based on content type
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $data = [];
        
        if (strpos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                sendResponse(false, 'Invalid JSON: ' . json_last_error_msg(), null, 400);
            }
        } else {
            parse_str(file_get_contents('php://input'), $data);
        }
        
        if (!isset($data['id']) || empty($data['id'])) {
            sendResponse(false, 'Battery ID is required', null, 400);
        }
        
        $id = intval($data['id']);
        
        // Check if battery exists
        $checkStmt = $conn->prepare("SELECT * FROM batteries WHERE id = ?");
        $checkStmt->execute([$id]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            sendResponse(false, 'Battery not found', null, 404);
        }
        
        // Check serial number conflict (only if provided)
        if (isset($data['battery_serial']) && !empty($data['battery_serial']) && $data['battery_serial'] !== $existing['battery_serial']) {
            $serialStmt = $conn->prepare("SELECT id FROM batteries WHERE battery_serial = ? AND id != ?");
            $serialStmt->execute([trim($data['battery_serial']), $id]);
            if ($serialStmt->rowCount() > 0) {
                sendResponse(false, 'Battery serial number already used by another battery', null, 409);
            }
        }
        
        // Build update query - all fields optional
        $updates = [];
        $params = [];
        
        $fields = [
            'battery_model', 'battery_serial', 'brand', 'capacity', 'voltage',
            'battery_type', 'category', 'specifications', 'warranty_period',
            'amc_period', 'price', 'status', 'battery_condition', 'inverter'
        ];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = trim($data[$field]);
            }
        }
        
        // Handle dates
        if (isset($data['purchase_date'])) {
            $updates[] = "purchase_date = ?";
            $params[] = !empty($data['purchase_date']) ? $data['purchase_date'] : null;
        }
        
        if (isset($data['installation_date'])) {
            $updates[] = "installation_date = ?";
            $params[] = !empty($data['installation_date']) ? $data['installation_date'] : null;
        }
        
        if (empty($updates)) {
            sendResponse(true, 'No changes detected', $existing);
        }
        
        $updates[] = "updated_at = NOW()";
        $params[] = $id;
        
        $sql = "UPDATE batteries SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
        // Get updated battery
        $getStmt = $conn->prepare("SELECT * FROM batteries WHERE id = ?");
        $getStmt->execute([$id]);
        $battery = $getStmt->fetch(PDO::FETCH_ASSOC);
        
        sendResponse(true, 'Battery updated successfully', $battery);
        
    } catch (PDOException $e) {
        sendResponse(false, 'Failed to update battery: ' . $e->getMessage(), null, 500);
    }
}

// DELETE Request Handler
function handleDeleteRequest($conn) {
    try {
        $id = null;
        
        // Get ID from query string
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
        } else {
            // Get ID from request body
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            
            if (strpos($contentType, 'application/json') !== false) {
                $input = file_get_contents('php://input');
                $data = json_decode($input, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($data['id'])) {
                    $id = intval($data['id']);
                }
            } else {
                parse_str(file_get_contents('php://input'), $data);
                if (isset($data['id'])) {
                    $id = intval($data['id']);
                }
            }
        }
        
        if (!$id) {
            sendResponse(false, 'Battery ID is required', null, 400);
        }
        
        // Check if battery exists
        $checkStmt = $conn->prepare("SELECT * FROM batteries WHERE id = ?");
        $checkStmt->execute([$id]);
        $battery = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$battery) {
            sendResponse(false, 'Battery not found', null, 404);
        }
        
        // Check if battery has service orders
        $serviceStmt = $conn->prepare("SELECT COUNT(*) as count FROM service_orders WHERE battery_id = ?");
        $serviceStmt->execute([$id]);
        $serviceCount = $serviceStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($serviceCount > 0) {
            // Mark as inactive
            $updateStmt = $conn->prepare("UPDATE batteries SET status = 'inactive', updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$id]);
            sendResponse(true, 'Battery marked as inactive (has service orders)', ['id' => $id, 'status' => 'inactive']);
            return;
        }
        
        // Delete the battery
        $deleteStmt = $conn->prepare("DELETE FROM batteries WHERE id = ?");
        $deleteStmt->execute([$id]);
        
        sendResponse(true, 'Battery deleted successfully', ['id' => $id]);
        
    } catch (PDOException $e) {
        sendResponse(false, 'Failed to delete battery: ' . $e->getMessage(), null, 500);
    }
}
?>