<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
$host = "localhost";
$username = "root"; 
$password = ""; 
$database = "sun_powers";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit();
}

$conn->set_charset("utf8mb4");

$method = $_SERVER['REQUEST_METHOD'];
$request = $_SERVER['REQUEST_URI'];

// Debug logging
error_log("Method: $method, Request: $request");

// Check if we're getting a specific endpoint
$path = parse_url($request, PHP_URL_PATH);
$path_parts = explode('/', $path);
$endpoint = end($path_parts);

// Handle different endpoints
if ($endpoint === 'replacement_batteries.php') {
    $endpoint = 'index';
} elseif ($endpoint === 'replacement_battery.php') {
    $endpoint = 'single';
}

try {
    switch ($method) {
        case 'GET':
            handleGetRequest($conn, $endpoint);
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
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed. Supported methods: GET, POST, PUT, DELETE'
            ]);
            break;
    }
    
} catch (Exception $e) {
    error_log("Error in replacement_battery.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

// Close connection
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

// Function to handle GET requests
function handleGetRequest($conn, $endpoint) {
    // Get parameters
    $service_order_id = isset($_GET['service_order_id']) ? intval($_GET['service_order_id']) : 0;
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($service_order_id > 0) {
        // Get replacement battery by service order ID
        $query = "SELECT * FROM replacement_batteries WHERE service_order_id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $conn->error
            ]);
            return;
        }
        
        $stmt->bind_param("i", $service_order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            if ($result->num_rows > 0) {
                $replacement_battery = $result->fetch_assoc();
                echo json_encode([
                    'success' => true,
                    'replacement_battery' => $replacement_battery
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'replacement_battery' => null,
                    'message' => 'No replacement battery found for this service order'
                ]);
            }
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error fetching replacement battery: ' . $conn->error
            ]);
        }
        
        $stmt->close();
        
    } elseif ($id > 0) {
        // Get replacement battery by ID
        $query = "SELECT * FROM replacement_batteries WHERE id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $conn->error
            ]);
            return;
        }
        
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            if ($result->num_rows > 0) {
                $replacement_battery = $result->fetch_assoc();
                echo json_encode([
                    'success' => true,
                    'replacement_battery' => $replacement_battery
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Replacement battery not found'
                ]);
            }
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error fetching replacement battery: ' . $conn->error
            ]);
        }
        
        $stmt->close();
        
    } else {
        // Return all replacement batteries with service order and customer details
        $query = "SELECT 
                    rb.*, 
                    so.service_code, 
                    so.customer_id, 
                    so.status as service_status,
                    c.full_name as customer_name,
                    c.phone as customer_phone,
                    b.battery_model as original_battery_model,
                    b.battery_serial as original_battery_serial
                  FROM replacement_batteries rb
                  LEFT JOIN service_orders so ON rb.service_order_id = so.id
                  LEFT JOIN customers c ON so.customer_id = c.id
                  LEFT JOIN batteries b ON so.battery_id = b.id
                  ORDER BY rb.created_at DESC";
        
        $result = $conn->query($query);
        
        if ($result) {
            $replacement_batteries = [];
            while ($row = $result->fetch_assoc()) {
                $replacement_batteries[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'data' => $replacement_batteries,
                'count' => count($replacement_batteries)
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error fetching replacement batteries: ' . $conn->error
            ]);
        }
    }
}

// Function to handle POST requests
function handlePostRequest($conn) {
    // Check if it's form data or JSON
    $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    
    if (strpos($content_type, 'application/json') !== false) {
        // Handle JSON data
        $input = file_get_contents("php://input");
        if (empty($input)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Request body is empty'
            ]);
            return;
        }
        
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid JSON data: ' . json_last_error_msg()
            ]);
            return;
        }
    } else {
        // Handle form data
        $data = $_POST;
    }
    
    error_log("POST Data: " . print_r($data, true));
    
    // Validate required fields
    $required_fields = ['service_order_id', 'battery_serial', 'battery_model'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Missing required field: $field"
            ]);
            return;
        }
    }
    
    // Sanitize and validate inputs
    $service_order_id = intval($data['service_order_id']);
    $battery_model = trim($conn->real_escape_string($data['battery_model']));
    $battery_serial = trim($conn->real_escape_string($data['battery_serial']));
    $brand = isset($data['brand']) ? trim($conn->real_escape_string($data['brand'])) : '';
    $capacity = isset($data['capacity']) ? trim($conn->real_escape_string($data['capacity'])) : '';
    $battery_type = isset($data['battery_type']) ? $conn->real_escape_string($data['battery_type']) : 'lead_acid';
    $voltage = isset($data['voltage']) ? $conn->real_escape_string($data['voltage']) : '12V';
    $price = isset($data['price']) ? floatval($data['price']) : 0.00;
    $warranty_period = isset($data['warranty_period']) ? trim($conn->real_escape_string($data['warranty_period'])) : '';
    $installation_date = isset($data['installation_date']) ? $conn->real_escape_string($data['installation_date']) : date('Y-m-d');
    $notes = isset($data['notes']) ? trim($conn->real_escape_string($data['notes'])) : '';
    
    // Validate service order exists
    $check_query = "SELECT id, status FROM service_orders WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $service_order_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if (!$check_result || $check_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Service order not found'
        ]);
        $check_stmt->close();
        return;
    }
    
    $check_row = $check_result->fetch_assoc();
    $service_status = $check_row['status'];
    $check_stmt->close();
    
    // Check if replacement already exists for this service order
    $existing_query = "SELECT id FROM replacement_batteries WHERE service_order_id = ?";
    $existing_stmt = $conn->prepare($existing_query);
    $existing_stmt->bind_param("i", $service_order_id);
    $existing_stmt->execute();
    $existing_result = $existing_stmt->get_result();
    
    if ($existing_result->num_rows > 0) {
        $existing_row = $existing_result->fetch_assoc();
        $existing_stmt->close();
        
        // Update existing record instead of creating new
        return updateReplacementBattery($conn, $existing_row['id'], $data, $service_order_id);
    }
    $existing_stmt->close();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert into replacement_batteries table
        $insert_query = "INSERT INTO replacement_batteries 
                        (service_order_id, battery_model, battery_serial, brand, capacity, battery_type, voltage, price, warranty_period, installation_date, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $insert_stmt = $conn->prepare($insert_query);
        if (!$insert_stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $insert_stmt->bind_param("issssssdsss", 
            $service_order_id, 
            $battery_model, 
            $battery_serial, 
            $brand, 
            $capacity, 
            $battery_type, 
            $voltage, 
            $price, 
            $warranty_period, 
            $installation_date, 
            $notes
        );
        
        if (!$insert_stmt->execute()) {
            throw new Exception('Error saving replacement battery: ' . $conn->error);
        }
        
        $replacement_id = $conn->insert_id;
        $insert_stmt->close();
        
        // Update service_orders table with replacement battery serial
        $update_service_query = "UPDATE service_orders 
                                SET replacement_battery_serial = ?,
                                    updated_at = NOW()
                                WHERE id = ?";
        
        $update_stmt = $conn->prepare($update_service_query);
        if (!$update_stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $update_stmt->bind_param("si", $battery_serial, $service_order_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception('Error updating service order: ' . $conn->error);
        }
        $update_stmt->close();
        
        // Update service status to completed if it's pending or in progress
        if (in_array($service_status, ['pending', 'in_progress', 'charging', 'testing', 'repair'])) {
            $update_status_query = "UPDATE service_orders 
                                   SET status = 'completed',
                                       updated_at = NOW()
                                   WHERE id = ?";
            
            $status_stmt = $conn->prepare($update_status_query);
            if ($status_stmt) {
                $status_stmt->bind_param("i", $service_order_id);
                $status_stmt->execute();
                $status_stmt->close();
            }
        }
        
        // Add activity log
        $activity_query = "INSERT INTO activities (user_id, activity, module, action) 
                          VALUES (1, 'Replacement battery added: $battery_model ($battery_serial) for service order #$service_order_id', 'replacement_batteries', 'create')";
        $conn->query($activity_query);
        
        // Commit transaction
        $conn->commit();
        
        // Get the inserted record
        $get_query = "SELECT * FROM replacement_batteries WHERE id = ?";
        $get_stmt = $conn->prepare($get_query);
        $get_stmt->bind_param("i", $replacement_id);
        $get_stmt->execute();
        $result = $get_stmt->get_result();
        $replacement_battery = $result->fetch_assoc();
        $get_stmt->close();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Replacement battery saved successfully',
            'replacement_id' => $replacement_id,
            'replacement_battery' => $replacement_battery
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Transaction failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

// Function to handle PUT requests
function handlePutRequest($conn) {
    $input = file_get_contents("php://input");
    if (empty($input)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Request body is empty'
        ]);
        return;
    }
    
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON data: ' . json_last_error_msg()
        ]);
        return;
    }
    
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Replacement battery ID is required'
        ]);
        return;
    }
    
    $id = intval($data['id']);
    
    // Update replacement battery
    return updateReplacementBattery($conn, $id, $data);
}

// Helper function to update replacement battery
function updateReplacementBattery($conn, $id, $data, $service_order_id = null) {
    // Check if replacement battery exists
    $check_query = "SELECT id, service_order_id FROM replacement_batteries WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if (!$check_result || $check_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Replacement battery not found'
        ]);
        $check_stmt->close();
        return;
    }
    
    $check_row = $check_result->fetch_assoc();
    if ($service_order_id === null) {
        $service_order_id = $check_row['service_order_id'];
    }
    $check_stmt->close();
    
    // Build update query dynamically
    $updates = [];
    $params = [];
    $types = "";
    
    $field_mapping = [
        'service_order_id' => 'i',
        'battery_model' => 's',
        'battery_serial' => 's',
        'brand' => 's',
        'capacity' => 's',
        'battery_type' => 's',
        'voltage' => 's',
        'price' => 'd',
        'warranty_period' => 's',
        'installation_date' => 's',
        'notes' => 's'
    ];
    
    foreach ($field_mapping as $field => $type) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
            $types .= $type;
        }
    }
    
    if (empty($updates)) {
        echo json_encode([
            'success' => false,
            'message' => 'No fields to update'
        ]);
        return;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update replacement_batteries table
        $query = "UPDATE replacement_batteries SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
        $params[] = $id;
        $types .= 'i';
        
        $update_stmt = $conn->prepare($query);
        if (!$update_stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $update_stmt->bind_param($types, ...$params);
        
        if (!$update_stmt->execute()) {
            throw new Exception('Error updating replacement battery: ' . $conn->error);
        }
        $update_stmt->close();
        
        // If battery_serial was updated, also update service_orders table
        if (isset($data['battery_serial'])) {
            $update_service_query = "UPDATE service_orders 
                                    SET replacement_battery_serial = ?,
                                        updated_at = NOW()
                                    WHERE id = ?";
            
            $service_stmt = $conn->prepare($update_service_query);
            if (!$service_stmt) {
                throw new Exception('Prepare failed: ' . $conn->error);
            }
            
            $service_stmt->bind_param("si", $data['battery_serial'], $service_order_id);
            
            if (!$service_stmt->execute()) {
                throw new Exception('Error updating service order: ' . $conn->error);
            }
            $service_stmt->close();
        }
        
        // Add activity log
        $activity_query = "INSERT INTO activities (user_id, activity, module, action) 
                          VALUES (1, 'Replacement battery updated: ID $id', 'replacement_batteries', 'update')";
        $conn->query($activity_query);
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Replacement battery updated successfully',
            'updated_fields' => array_keys($data)
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Transaction failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

// Function to handle DELETE requests
function handleDeleteRequest($conn) {
    // Check if ID is in URL parameters or request body
    $id = 0;
    
    // Try to get ID from URL parameters first
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
    }
    
    // If not in URL, check request body
    if ($id === 0) {
        $input = file_get_contents("php://input");
        if (!empty($input)) {
            $data = json_decode($input, true);
            if (isset($data['id'])) {
                $id = intval($data['id']);
            }
        }
    }
    
    if ($id === 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Replacement battery ID is required'
        ]);
        return;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get service_order_id before deleting
        $get_query = "SELECT service_order_id, battery_model, battery_serial FROM replacement_batteries WHERE id = ?";
        $get_stmt = $conn->prepare($get_query);
        $get_stmt->bind_param("i", $id);
        $get_stmt->execute();
        $result = $get_stmt->get_result();
        
        if (!$result || $result->num_rows === 0) {
            throw new Exception('Replacement battery not found');
        }
        
        $row = $result->fetch_assoc();
        $service_order_id = $row['service_order_id'];
        $battery_model = $row['battery_model'];
        $battery_serial = $row['battery_serial'];
        $get_stmt->close();
        
        // Delete the replacement battery
        $delete_query = "DELETE FROM replacement_batteries WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $id);
        
        if (!$delete_stmt->execute()) {
            throw new Exception('Error deleting replacement battery: ' . $conn->error);
        }
        $delete_stmt->close();
        
        // Remove replacement_battery_serial from service_orders
        $update_query = "UPDATE service_orders 
                        SET replacement_battery_serial = NULL,
                            updated_at = NOW()
                        WHERE id = ?";
        
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $service_order_id);
        
        if (!$update_stmt->execute()) {
            throw new Exception('Error updating service order: ' . $conn->error);
        }
        $update_stmt->close();
        
        // Add activity log
        $activity_query = "INSERT INTO activities (user_id, activity, module, action) 
                          VALUES (1, 'Replacement battery deleted: $battery_model ($battery_serial)', 'replacement_batteries', 'delete')";
        $conn->query($activity_query);
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Replacement battery deleted successfully'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Transaction failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>