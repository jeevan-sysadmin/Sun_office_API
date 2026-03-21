<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database configuration
require_once __DIR__ . '/config/database.php';

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Create database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Route based on request method
    switch($method) {
        case 'GET':
            handleGetCustomers($conn);
            break;
        case 'POST':
            handlePostCustomer($conn);
            break;
        case 'PUT':
            handlePutCustomer($conn);
            break;
        case 'DELETE':
            handleDeleteCustomer($conn);
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

/**
 * Handle GET requests - Retrieve customers
 */
function handleGetCustomers($conn) {
    // Check if single customer requested
    if (isset($_GET['id'])) {
        getSingleCustomer($conn, $_GET['id']);
    } else {
        getAllCustomers($conn);
    }
}

/**
 * Get single customer by ID
 */
function getSingleCustomer($conn, $id) {
    $id = intval($id);
    
    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM service_orders WHERE customer_id = c.id) as battery_service_count,
            (SELECT COUNT(*) FROM inverter_services WHERE customer_id = c.id) as inverter_service_count
            FROM customers c 
            WHERE c.id = :id";
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculate total services
            $customer['total_services'] = (int)$customer['battery_service_count'] + (int)$customer['inverter_service_count'];
            $customer['service_count'] = $customer['total_services'];
            
            // Remove individual counts
            unset($customer['battery_service_count']);
            unset($customer['inverter_service_count']);
            
            echo json_encode([
                'success' => true,
                'data' => $customer,
                'message' => 'Customer retrieved successfully'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Customer not found'
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get all customers
 */
function getAllCustomers($conn) {
    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM service_orders WHERE customer_id = c.id) as battery_service_count,
            (SELECT COUNT(*) FROM inverter_services WHERE customer_id = c.id) as inverter_service_count,
            (SELECT MAX(created_at) FROM service_orders WHERE customer_id = c.id) as last_battery_service,
            (SELECT MAX(created_at) FROM inverter_services WHERE customer_id = c.id) as last_inverter_service
            FROM customers c
            ORDER BY c.created_at DESC";
    
    try {
        $stmt = $conn->query($sql);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format customers for frontend
        $formattedCustomers = array_map(function($customer) {
            $batteryCount = (int)$customer['battery_service_count'];
            $inverterCount = (int)$customer['inverter_service_count'];
            $totalServices = $batteryCount + $inverterCount;
            
            // Get last service date
            $lastServiceDate = null;
            if ($customer['last_battery_service'] && $customer['last_inverter_service']) {
                $lastServiceDate = max($customer['last_battery_service'], $customer['last_inverter_service']);
            } else if ($customer['last_battery_service']) {
                $lastServiceDate = $customer['last_battery_service'];
            } else if ($customer['last_inverter_service']) {
                $lastServiceDate = $customer['last_inverter_service'];
            }
            
            return [
                'id' => (int)$customer['id'],
                'customer_code' => $customer['customer_code'],
                'full_name' => $customer['full_name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
                'alternate_phone' => $customer['alternate_phone'], // Added alternate_phone
                'address' => $customer['address'],
                'city' => $customer['city'],
                'state' => $customer['state'],
                'zip_code' => $customer['zip_code'],
                'notes' => $customer['notes'],
                'created_at' => $customer['created_at'],
                'updated_at' => $customer['updated_at'],
                'total_services' => $totalServices,
                'service_count' => $totalServices,
                'last_service_date' => $lastServiceDate
            ];
        }, $customers);
        
        echo json_encode([
            'success' => true,
            'customers' => $formattedCustomers,
            'count' => count($formattedCustomers),
            'message' => 'Customers retrieved successfully'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle POST requests - Create new customer
 */
function handlePostCustomer($conn) {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Log for debugging
    error_log("POST Customer Data: " . print_r($data, true));
    
    // Validate required fields
    if (empty($data['full_name']) || empty($data['phone'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Full name and phone number are required'
        ]);
        return;
    }
    
    // Sanitize input
    $full_name = trim($data['full_name']);
    $phone = trim($data['phone']);
    $alternate_phone = !empty($data['alternate_phone']) ? trim($data['alternate_phone']) : null; // Added alternate_phone
    $email = !empty($data['email']) ? trim($data['email']) : null;
    $address = !empty($data['address']) ? trim($data['address']) : null;
    $city = !empty($data['city']) ? trim($data['city']) : null;
    $state = !empty($data['state']) ? trim($data['state']) : null;
    $zip_code = !empty($data['zip_code']) ? trim($data['zip_code']) : null;
    $notes = !empty($data['notes']) ? trim($data['notes']) : null;
    
    try {
        // Check if phone already exists
        $checkSql = "SELECT id FROM customers WHERE phone = :phone";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bindParam(':phone', $phone);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Customer with this phone number already exists'
            ]);
            return;
        }
        
        // Generate customer code
        $customer_code = generateCustomerCode($conn);
        
        // Insert customer with alternate_phone
        $sql = "INSERT INTO customers (
                    customer_code, full_name, email, phone, alternate_phone, 
                    address, city, state, zip_code, notes,
                    created_at, updated_at
                ) VALUES (
                    :customer_code, :full_name, :email, :phone, :alternate_phone,
                    :address, :city, :state, :zip_code, :notes,
                    NOW(), NOW()
                )";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':customer_code', $customer_code);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':alternate_phone', $alternate_phone); // Added alternate_phone
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':city', $city);
        $stmt->bindParam(':state', $state);
        $stmt->bindParam(':zip_code', $zip_code);
        $stmt->bindParam(':notes', $notes);
        
        if ($stmt->execute()) {
            $customer_id = $conn->lastInsertId();
            
            // Get created customer
            $selectSql = "SELECT * FROM customers WHERE id = :id";
            $selectStmt = $conn->prepare($selectSql);
            $selectStmt->bindParam(':id', $customer_id);
            $selectStmt->execute();
            $customer = $selectStmt->fetch(PDO::FETCH_ASSOC);
            
            // Add service counts
            $customer['total_services'] = 0;
            $customer['service_count'] = 0;
            
            echo json_encode([
                'success' => true,
                'customer_id' => $customer_id,
                'customer_code' => $customer_code,
                'customer' => $customer,
                'message' => 'Customer created successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create customer'
            ]);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle PUT requests - Update customer
 */
function handlePutCustomer($conn) {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    error_log("PUT Customer Data: " . print_r($data, true));
    
    // Validate required fields
    if (empty($data['id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Customer ID is required'
        ]);
        return;
    }
    
    if (empty($data['full_name']) || empty($data['phone'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Full name and phone number are required'
        ]);
        return;
    }
    
    $id = intval($data['id']);
    $full_name = trim($data['full_name']);
    $phone = trim($data['phone']);
    $alternate_phone = !empty($data['alternate_phone']) ? trim($data['alternate_phone']) : null; // Added alternate_phone
    $email = !empty($data['email']) ? trim($data['email']) : null;
    $address = !empty($data['address']) ? trim($data['address']) : null;
    $city = !empty($data['city']) ? trim($data['city']) : null;
    $state = !empty($data['state']) ? trim($data['state']) : null;
    $zip_code = !empty($data['zip_code']) ? trim($data['zip_code']) : null;
    $notes = !empty($data['notes']) ? trim($data['notes']) : null;
    
    try {
        // Check if customer exists
        $checkSql = "SELECT * FROM customers WHERE id = :id";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Customer not found'
            ]);
            return;
        }
        
        $existingCustomer = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if phone already exists for another customer
        if ($phone !== $existingCustomer['phone']) {
            $phoneCheckSql = "SELECT id FROM customers WHERE phone = :phone AND id != :id";
            $phoneCheckStmt = $conn->prepare($phoneCheckSql);
            $phoneCheckStmt->bindParam(':phone', $phone);
            $phoneCheckStmt->bindParam(':id', $id);
            $phoneCheckStmt->execute();
            
            if ($phoneCheckStmt->rowCount() > 0) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Phone number already used by another customer'
                ]);
                return;
            }
        }
        
        // Update customer with alternate_phone
        $sql = "UPDATE customers SET 
                    full_name = :full_name,
                    email = :email,
                    phone = :phone,
                    alternate_phone = :alternate_phone,
                    address = :address,
                    city = :city,
                    state = :state,
                    zip_code = :zip_code,
                    notes = :notes,
                    updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':full_name', $full_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':alternate_phone', $alternate_phone); // Added alternate_phone
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':city', $city);
        $stmt->bindParam(':state', $state);
        $stmt->bindParam(':zip_code', $zip_code);
        $stmt->bindParam(':notes', $notes);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            // Get updated customer with service counts
            $selectSql = "SELECT c.*, 
                    (SELECT COUNT(*) FROM service_orders WHERE customer_id = c.id) as battery_count,
                    (SELECT COUNT(*) FROM inverter_services WHERE customer_id = c.id) as inverter_count
                    FROM customers c WHERE c.id = :id";
            
            $selectStmt = $conn->prepare($selectSql);
            $selectStmt->bindParam(':id', $id);
            $selectStmt->execute();
            $customer = $selectStmt->fetch(PDO::FETCH_ASSOC);
            
            $customer['total_services'] = (int)$customer['battery_count'] + (int)$customer['inverter_count'];
            $customer['service_count'] = $customer['total_services'];
            
            unset($customer['battery_count']);
            unset($customer['inverter_count']);
            
            echo json_encode([
                'success' => true,
                'customer' => $customer,
                'message' => 'Customer updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update customer'
            ]);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle DELETE requests - Delete customer
 */
function handleDeleteCustomer($conn) {
    // Get ID from query string or request body
    $id = null;
    
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
    } else {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $id = isset($data['id']) ? intval($data['id']) : null;
    }
    
    if (!$id) {
        echo json_encode([
            'success' => false,
            'message' => 'Customer ID is required'
        ]);
        return;
    }
    
    try {
        // Check if customer exists
        $checkSql = "SELECT id FROM customers WHERE id = :id";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Customer not found'
            ]);
            return;
        }
        
        // Check for related service orders
        $serviceSql = "SELECT COUNT(*) as count FROM service_orders WHERE customer_id = :id";
        $serviceStmt = $conn->prepare($serviceSql);
        $serviceStmt->bindParam(':id', $id);
        $serviceStmt->execute();
        $serviceCount = $serviceStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $invSql = "SELECT COUNT(*) as count FROM inverter_services WHERE customer_id = :id";
        $invStmt = $conn->prepare($invSql);
        $invStmt->bindParam(':id', $id);
        $invStmt->execute();
        $invCount = $invStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($serviceCount > 0 || $invCount > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Cannot delete customer with existing service orders',
                'service_count' => $serviceCount + $invCount
            ]);
            return;
        }
        
        // Delete customer
        $deleteSql = "DELETE FROM customers WHERE id = :id";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bindParam(':id', $id);
        
        if ($deleteStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Customer deleted successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete customer'
            ]);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Generate unique customer code
 */
function generateCustomerCode($conn) {
    try {
        // Try to use the database trigger first
        $sql = "SELECT MAX(CAST(SUBSTRING(customer_code, 5) AS UNSIGNED)) as max_num 
                FROM customers WHERE customer_code LIKE 'CUST%'";
        $stmt = $conn->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row && $row['max_num']) {
            $next_num = intval($row['max_num']) + 1;
        } else {
            $next_num = 1;
        }
        
        return 'CUST' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
        
    } catch (PDOException $e) {
        // Fallback: generate based on timestamp
        return 'CUST' . date('Ymd') . rand(100, 999);
    }
}
?>