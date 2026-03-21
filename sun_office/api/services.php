<?php
// services.php - Battery & Inverter Service Management API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$config = [
    'host' => '127.0.0.1',
    'dbname' => 'sun_office',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Set collation for the connection to handle mixed collations
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => $e->getMessage()
    ]);
    exit;
}

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Route the request
switch($method) {
    case 'GET':
        handleGetRequest($pdo);
        break;
    case 'POST':
        handlePostRequest($pdo);
        break;
    case 'PUT':
        handlePutRequest($pdo);
        break;
    case 'DELETE':
        handleDeleteRequest($pdo);
        break;
    default:
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
        break;
}

/**
 * Handle GET requests
 */
function handleGetRequest($pdo) {
    // Get query parameters
    $params = $_GET;
    $id = isset($params['id']) ? (int)$params['id'] : null;
    
    // If ID is provided, get single service order
    if ($id) {
        getServiceOrder($pdo, $id);
        return;
    }
    
    // Check for filters
    $whereConditions = [];
    $queryParams = [];
    
    // Customer filter
    if (isset($params['customer_id']) && !empty($params['customer_id'])) {
        $whereConditions[] = 'so.customer_id = ?';
        $queryParams[] = (int)$params['customer_id'];
    }
    
    // Staff filter
    if (isset($params['staff_id']) && !empty($params['staff_id'])) {
        $whereConditions[] = 'so.service_staff_id = ?';
        $queryParams[] = (int)$params['staff_id'];
    }
    
    // Battery filter - Handle null values properly
    if (isset($params['battery_id'])) {
        if ($params['battery_id'] === 'null' || $params['battery_id'] === '') {
            $whereConditions[] = 'so.battery_id IS NULL';
        } elseif (!empty($params['battery_id'])) {
            $whereConditions[] = 'so.battery_id = ?';
            $queryParams[] = (int)$params['battery_id'];
        }
    }
    
    // Inverter filter - Handle null values properly
    if (isset($params['inverter_id'])) {
        if ($params['inverter_id'] === 'null' || $params['inverter_id'] === '') {
            $whereConditions[] = 'so.inverter_id IS NULL';
        } elseif (!empty($params['inverter_id'])) {
            $whereConditions[] = 'so.inverter_id = ?';
            $queryParams[] = (int)$params['inverter_id'];
        }
    }
    
    // Warranty status filter
    if (isset($params['warranty_status']) && !empty($params['warranty_status'])) {
        $validWarrantyStatuses = ['in_warranty', 'extended_warranty', 'out_of_warranty'];
        if (in_array($params['warranty_status'], $validWarrantyStatuses)) {
            $whereConditions[] = 'so.warranty_status = ?';
            $queryParams[] = $params['warranty_status'];
        }
    }
    
    // AMC status filter
    if (isset($params['amc_status']) && !empty($params['amc_status'])) {
        $validAmcStatuses = ['active', 'expired', 'no_amc'];
        if (in_array($params['amc_status'], $validAmcStatuses)) {
            $whereConditions[] = 'so.amc_status = ?';
            $queryParams[] = $params['amc_status'];
        }
    }
    
    // Date range filter
    if (isset($params['start_date']) && !empty($params['start_date'])) {
        $whereConditions[] = 'DATE(so.created_at) >= ?';
        $queryParams[] = $params['start_date'];
    }
    
    if (isset($params['end_date']) && !empty($params['end_date'])) {
        $whereConditions[] = 'DATE(so.created_at) <= ?';
        $queryParams[] = $params['end_date'];
    }
    
    // Search filter - FIXED COLLATION ISSUE
    if (isset($params['search']) && !empty($params['search'])) {
        $searchTerm = '%' . $params['search'] . '%';
        
        // Use COLLATE to force consistent collation for all string comparisons
        $whereConditions[] = '(so.service_code COLLATE utf8mb4_unicode_ci LIKE ? OR ' .
                            'so.notes COLLATE utf8mb4_unicode_ci LIKE ? OR ' .
                            'IFNULL(b.battery_serial, "") COLLATE utf8mb4_unicode_ci LIKE ? OR ' .
                            'IFNULL(i.inverter_serial, "") COLLATE utf8mb4_unicode_ci LIKE ? OR ' .
                            'so.customer_phone COLLATE utf8mb4_unicode_ci LIKE ? OR ' .
                            'IFNULL(c.full_name, "") COLLATE utf8mb4_unicode_ci LIKE ?)';
        
        // Add search term for each field (6 times)
        for ($i = 0; $i < 6; $i++) {
            $queryParams[] = $searchTerm;
        }
    }
    
    // Build WHERE clause
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }
    
    // Pagination
    $limit = isset($params['limit']) ? (int)$params['limit'] : 50;
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $offset = ($page - 1) * $limit;
    
    // Get total count for pagination
    $countSql = "
        SELECT COUNT(*) as total 
        FROM service_orders so
        LEFT JOIN customers c ON so.customer_id = c.id
        LEFT JOIN batteries b ON so.battery_id = b.id
        LEFT JOIN inverters i ON so.inverter_id = i.id
        $whereClause
    ";
    
    try {
        $countStmt = $pdo->prepare($countSql);
        if (!empty($queryParams)) {
            $countStmt->execute($queryParams);
        } else {
            $countStmt->execute();
        }
        $totalCount = $countStmt->fetch()['total'];
        $totalPages = ceil($totalCount / $limit);
    } catch(PDOException $e) {
        error_log("Count query error: " . $e->getMessage());
        $totalCount = 0;
        $totalPages = 0;
    }
    
    // Main query to get service orders
    $sql = "
        SELECT so.id, 
               so.service_code, 
               so.customer_id, 
               so.customer_phone, 
               so.battery_id, 
               so.inverter_id,
               so.service_staff_id, 
               so.warranty_status, 
               so.amc_status, 
               so.notes, 
               so.created_at, 
               so.updated_at,
               
               -- Battery details
               b.battery_model, 
               b.battery_serial,
               b.brand as battery_brand,
               b.capacity as battery_capacity,
               b.voltage as battery_voltage,
               b.battery_type,
               b.purchase_date as battery_purchase_date,
               b.installation_date as battery_installation_date,
               b.warranty_period as battery_warranty,
               
               -- Inverter details
               i.inverter_model,
               i.inverter_serial,
               i.inverter_brand,
               i.power_rating,
               i.wave_type,
               i.battery_voltage as inverter_battery_voltage,
               i.purchase_date as inverter_purchase_date,
               i.installation_date as inverter_installation_date,
               i.warranty_period as inverter_warranty,
               
               -- Staff details
               u.name as staff_name,
               u.email as staff_email,
               
               -- Customer details
               c.full_name as customer_name,
               c.email as customer_email,
               c.phone as customer_phone_number,
               c.address as customer_address,
               c.city as customer_city
               
        FROM service_orders so
        LEFT JOIN batteries b ON so.battery_id = b.id
        LEFT JOIN inverters i ON so.inverter_id = i.id
        LEFT JOIN users u ON so.service_staff_id = u.id
        LEFT JOIN customers c ON so.customer_id = c.id
        $whereClause
        ORDER BY so.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    // Add pagination parameters
    $queryParamsWithPagination = array_merge($queryParams, [$limit, $offset]);
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($queryParamsWithPagination);
        $services = $stmt->fetchAll();
        
        // Format the response
        foreach ($services as &$service) {
            $service['has_battery'] = !is_null($service['battery_id']);
            $service['has_inverter'] = !is_null($service['inverter_id']);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $services,
            'pagination' => [
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages
            ],
            'filters' => [
                'warranty_statuses' => ['in_warranty', 'extended_warranty', 'out_of_warranty'],
                'amc_statuses' => ['active', 'expired', 'no_amc']
            ]
        ]);
        
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch service orders',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Get single service order
 */
function getServiceOrder($pdo, $id) {
    try {
        $sql = "
            SELECT so.id, 
                   so.service_code, 
                   so.customer_id, 
                   so.customer_phone, 
                   so.battery_id, 
                   so.inverter_id,
                   so.service_staff_id, 
                   so.warranty_status, 
                   so.amc_status, 
                   so.notes, 
                   so.created_at, 
                   so.updated_at,
                   
                   -- Battery details
                   b.battery_model, 
                   b.battery_serial,
                   b.brand as battery_brand,
                   b.capacity as battery_capacity,
                   b.voltage as battery_voltage,
                   b.battery_type,
                   b.purchase_date as battery_purchase_date,
                   b.installation_date as battery_installation_date,
                   b.warranty_period as battery_warranty,
                   
                   -- Inverter details
                   i.inverter_model,
                   i.inverter_serial,
                   i.inverter_brand,
                   i.power_rating,
                   i.wave_type,
                   i.battery_voltage as inverter_battery_voltage,
                   i.purchase_date as inverter_purchase_date,
                   i.installation_date as inverter_installation_date,
                   i.warranty_period as inverter_warranty,
                   
                   -- Staff details
                   u.name as staff_name,
                   u.email as staff_email,
                   
                   -- Customer details
                   c.full_name as customer_name,
                   c.email as customer_email,
                   c.phone as customer_phone_number,
                   c.address as customer_address,
                   c.city as customer_city
                   
            FROM service_orders so
            LEFT JOIN batteries b ON so.battery_id = b.id
            LEFT JOIN inverters i ON so.inverter_id = i.id
            LEFT JOIN users u ON so.service_staff_id = u.id
            LEFT JOIN customers c ON so.customer_id = c.id
            WHERE so.id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $service = $stmt->fetch();
        
        if (!$service) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Service order not found'
            ]);
            return;
        }
        
        // Add boolean flags
        $service['has_battery'] = !is_null($service['battery_id']);
        $service['has_inverter'] = !is_null($service['inverter_id']);
        
        echo json_encode([
            'success' => true,
            'data' => $service
        ]);
        
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch service order details',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Handle POST requests (create new service order)
 */
function handlePostRequest($pdo) {
    // Get input data
    $input = getInputData();
    
    // Debug log
    error_log("POST Request Input: " . json_encode($input));
    
    // Validate required fields - both battery_id and inverter_id are optional
    $requiredFields = ['customer_id'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields',
            'missing_fields' => $missingFields
        ]);
        return;
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Get customer phone if not provided
        $customerPhone = isset($input['customer_phone']) && !empty($input['customer_phone']) 
            ? $input['customer_phone'] 
            : null;
        
        // If customer phone is not provided, try to get it from customers table
        if (empty($customerPhone) && isset($input['customer_id']) && !empty($input['customer_id'])) {
            $customerSql = "SELECT phone FROM customers WHERE id = ?";
            $customerStmt = $pdo->prepare($customerSql);
            $customerStmt->execute([(int)$input['customer_id']]);
            $customer = $customerStmt->fetch();
            if ($customer && !empty($customer['phone'])) {
                $customerPhone = $customer['phone'];
            }
        }
        
        // Generate service code (format: SVC-YYYYMMDD-XXXX)
        $datePrefix = date('Ymd');
        
        // Check if there are any records today
        $codeSql = "SELECT COUNT(*) as count FROM service_orders WHERE DATE(created_at) = CURDATE()";
        $codeStmt = $pdo->query($codeSql);
        $codeCount = $codeStmt->fetch()['count'] + 1;
        $serviceCode = 'SVC-' . $datePrefix . '-' . str_pad($codeCount, 4, '0', STR_PAD_LEFT);
        
        // Determine warranty status (default if not provided)
        $warrantyStatus = isset($input['warranty_status']) && !empty($input['warranty_status']) 
            ? $input['warranty_status'] 
            : 'out_of_warranty';
        
        // Determine AMC status (default if not provided)
        $amcStatus = isset($input['amc_status']) && !empty($input['amc_status']) 
            ? $input['amc_status'] 
            : 'no_amc';
        
        // Handle battery_id - can be null
        $batteryId = null;
        if (isset($input['battery_id']) && !empty($input['battery_id']) && $input['battery_id'] !== 'null' && $input['battery_id'] !== '') {
            $batteryId = (int)$input['battery_id'];
        }
        
        // Handle inverter_id - can be null
        $inverterId = null;
        if (isset($input['inverter_id']) && !empty($input['inverter_id']) && $input['inverter_id'] !== 'null' && $input['inverter_id'] !== '') {
            $inverterId = (int)$input['inverter_id'];
        }
        
        // Handle service_staff_id - can be null
        $serviceStaffId = null;
        if (isset($input['service_staff_id']) && !empty($input['service_staff_id']) && $input['service_staff_id'] !== 'null' && $input['service_staff_id'] !== '') {
            $serviceStaffId = (int)$input['service_staff_id'];
        }
        
        // Handle notes
        $notes = isset($input['notes']) && !empty($input['notes']) ? trim($input['notes']) : null;
        
        // Insert service order
        $sql = "
            INSERT INTO service_orders (
                service_code,
                customer_id, 
                customer_phone, 
                battery_id,
                inverter_id,
                service_staff_id, 
                warranty_status, 
                amc_status,
                notes, 
                created_at, 
                updated_at
            ) VALUES (
                :service_code,
                :customer_id, 
                :customer_phone, 
                :battery_id,
                :inverter_id,
                :service_staff_id, 
                :warranty_status, 
                :amc_status,
                :notes, 
                NOW(), 
                NOW()
            )
        ";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':service_code' => $serviceCode,
            ':customer_id' => (int)$input['customer_id'],
            ':customer_phone' => $customerPhone,
            ':battery_id' => $batteryId,
            ':inverter_id' => $inverterId,
            ':service_staff_id' => $serviceStaffId,
            ':warranty_status' => $warrantyStatus,
            ':amc_status' => $amcStatus,
            ':notes' => $notes
        ]);
        
        if (!$result) {
            throw new Exception('Failed to insert service order');
        }
        
        $serviceId = $pdo->lastInsertId();
        
        // Get the created service order with all details
        $createdService = getServiceOrderById($pdo, $serviceId);
        
        // Commit transaction
        $pdo->commit();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Service order created successfully',
            'data' => $createdService,
            'service_id' => $serviceId,
            'service_code' => $serviceCode
        ]);
        
    } catch(Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create service order: ' . $e->getMessage(),
            'error' => $e->getMessage(),
            'debug' => [
                'input' => $input,
                'error_code' => $e->getCode()
            ]
        ]);
    }
}

/**
 * Handle PUT requests (update service order)
 */
function handlePutRequest($pdo) {
    // Get input data
    $input = getInputData();
    
    // Debug: Log the received input
    error_log("PUT Request Input: " . json_encode($input));
    
    // Check for ID
    $id = null;
    
    if (isset($input['id']) && !empty($input['id'])) {
        $id = (int)$input['id'];
    } elseif (isset($input['service_id']) && !empty($input['service_id'])) {
        $id = (int)$input['service_id'];
    } elseif (isset($_GET['id']) && !empty($_GET['id'])) {
        $id = (int)$_GET['id'];
    }
    
    if (!$id || $id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Service order ID is required',
            'debug' => $input
        ]);
        return;
    }
    
    // Check if service order exists
    $existingService = getServiceOrderById($pdo, $id);
    if (!$existingService) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Service order not found',
            'service_id' => $id
        ]);
        return;
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Build dynamic update query based on provided fields
        $updateFields = [];
        $params = [':id' => $id];
        
        // Fields that exist in the table
        $updatableFields = [
            'customer_id', 
            'customer_phone', 
            'battery_id', 
            'inverter_id',
            'service_staff_id',
            'warranty_status', 
            'amc_status', 
            'notes'
        ];
        
        foreach ($updatableFields as $field) {
            if (array_key_exists($field, $input)) {
                if (in_array($field, ['customer_id', 'service_staff_id'])) {
                    $updateFields[] = "$field = :$field";
                    if (isset($input[$field]) && !empty($input[$field]) && $input[$field] !== 'null' && $input[$field] !== '') {
                        $params[":$field"] = (int)$input[$field];
                    } else {
                        $params[":$field"] = null;
                    }
                } elseif (in_array($field, ['battery_id', 'inverter_id'])) {
                    // Handle battery_id and inverter_id - can be explicitly set to null
                    $updateFields[] = "$field = :$field";
                    if (isset($input[$field]) && !empty($input[$field]) && $input[$field] !== '' && $input[$field] !== 'null') {
                        $params[":$field"] = (int)$input[$field];
                    } else {
                        $params[":$field"] = null;
                    }
                } else {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = (isset($input[$field]) && $input[$field] !== '') ? $input[$field] : null;
                }
            }
        }
        
        // Always update the updated_at timestamp
        $updateFields[] = "updated_at = NOW()";
        
        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No fields to update'
            ]);
            return;
        }
        
        // Update service order
        $sql = "UPDATE service_orders SET " . implode(', ', $updateFields) . " WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Get updated service order with all details
        $updatedService = getServiceOrderById($pdo, $id);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Service order updated successfully',
            'data' => $updatedService
        ]);
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update service order: ' . $e->getMessage(),
            'error' => $e->getMessage(),
            'debug' => [
                'input' => $input,
                'error_code' => $e->getCode()
            ]
        ]);
    }
}

/**
 * Handle DELETE requests
 */
function handleDeleteRequest($pdo) {
    // Get ID from query parameters or input
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$id) {
        $input = getInputData();
        $id = isset($input['id']) ? (int)$input['id'] : null;
    }
    
    if (!$id || $id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Valid service order ID is required'
        ]);
        return;
    }
    
    // Check if service order exists
    $existingService = getServiceOrderById($pdo, $id);
    if (!$existingService) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Service order not found'
        ]);
        return;
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete the service order
        $sql = "DELETE FROM service_orders WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        
        // Check if delete was successful
        if ($stmt->rowCount() > 0) {
            // Commit transaction
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Service order deleted successfully'
            ]);
        } else {
            throw new Exception('Failed to delete service order');
        }
        
    } catch(Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete service order: ' . $e->getMessage(),
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Helper Functions
 */

/**
 * Get input data from request
 */
function getInputData() {
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        // Handle JSON parsing errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        return $input ?: [];
    }
    
    // For form data or query string
    return $_POST ?: $_GET;
}

/**
 * Get service order by ID
 */
function getServiceOrderById($pdo, $id) {
    $sql = "
        SELECT so.id, 
               so.service_code, 
               so.customer_id, 
               so.customer_phone, 
               so.battery_id, 
               so.inverter_id,
               so.service_staff_id, 
               so.warranty_status, 
               so.amc_status, 
               so.notes, 
               so.created_at, 
               so.updated_at,
               
               -- Battery details
               b.battery_model, 
               b.battery_serial,
               b.brand as battery_brand,
               b.capacity as battery_capacity,
               b.voltage as battery_voltage,
               b.battery_type,
               b.purchase_date as battery_purchase_date,
               b.installation_date as battery_installation_date,
               b.warranty_period as battery_warranty,
               
               -- Inverter details
               i.inverter_model,
               i.inverter_serial,
               i.inverter_brand,
               i.power_rating,
               i.wave_type,
               i.battery_voltage as inverter_battery_voltage,
               i.purchase_date as inverter_purchase_date,
               i.installation_date as inverter_installation_date,
               i.warranty_period as inverter_warranty,
               
               -- Staff details
               u.name as staff_name,
               u.email as staff_email,
               
               -- Customer details
               c.full_name as customer_name,
               c.email as customer_email,
               c.phone as customer_phone_number,
               c.address as customer_address,
               c.city as customer_city
               
        FROM service_orders so
        LEFT JOIN batteries b ON so.battery_id = b.id
        LEFT JOIN inverters i ON so.inverter_id = i.id
        LEFT JOIN users u ON so.service_staff_id = u.id
        LEFT JOIN customers c ON so.customer_id = c.id
        WHERE so.id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $service = $stmt->fetch();
    
    if ($service) {
        $service['has_battery'] = !is_null($service['battery_id']);
        $service['has_inverter'] = !is_null($service['inverter_id']);
    }
    
    return $service;
}

/**
 * Get service statistics
 */
function getServiceStatistics($pdo) {
    $stats = [];
    
    try {
        // Total service orders
        $sql = "SELECT COUNT(*) as total FROM service_orders";
        $stmt = $pdo->query($sql);
        $stats['total'] = $stmt->fetch()['total'];
        
        // By warranty status
        $sql = "SELECT warranty_status, COUNT(*) as count FROM service_orders GROUP BY warranty_status";
        $stmt = $pdo->query($sql);
        $stats['by_warranty'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // By AMC status
        $sql = "SELECT amc_status, COUNT(*) as count FROM service_orders GROUP BY amc_status";
        $stmt = $pdo->query($sql);
        $stats['by_amc'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Equipment usage statistics (including nulls)
        $sql = "SELECT 
                CASE 
                    WHEN battery_id IS NOT NULL AND inverter_id IS NOT NULL THEN 'Both'
                    WHEN battery_id IS NOT NULL THEN 'Battery Only'
                    WHEN inverter_id IS NOT NULL THEN 'Inverter Only'
                    ELSE 'No Equipment'
                END as equipment_type,
                COUNT(*) as count 
                FROM service_orders 
                GROUP BY equipment_type";
        $stmt = $pdo->query($sql);
        $stats['equipment_usage'] = $stmt->fetchAll();
        
        // Recent activity - last 7 days
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM service_orders 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC";
        $stmt = $pdo->query($sql);
        $stats['recent'] = $stmt->fetchAll();
        
    } catch(PDOException $e) {
        error_log("Statistics error: " . $e->getMessage());
        $stats = [
            'total' => 0,
            'by_warranty' => [],
            'by_amc' => [],
            'equipment_usage' => [],
            'recent' => []
        ];
    }
    
    return $stats;
}
?>