<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . '/config/database.php';

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Handle OPTIONS request
if ($method == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get database connection
$conn = connectDB();

// Main routing
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
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
}

// Handle GET requests
function handleGetRequest($conn) {
    try {
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $condition = isset($_GET['condition']) ? $_GET['condition'] : '';
        $type = isset($_GET['type']) ? $_GET['type'] : '';
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        
        if ($id) {
            // Get single spare battery
            getSpareBatteryById($conn, $id);
        } else {
            // Get all spare batteries
            getSpareBatteries($conn, $search, $condition, $type, $status);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

// Get spare battery by ID
function getSpareBatteryById($conn, $id) {
    // Check if it's from spare_batteries table
    $query = "SELECT * FROM spare_batteries WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if (is_numeric($id)) {
        $stmt->bind_param("i", $id);
    } else {
        // For string IDs (if you have them)
        $stmt->bind_param("s", $id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Calculate warranty status if needed
        if (!empty($row['purchase_date']) && !empty($row['warranty_months'])) {
            $warrantyExpiryDate = date('Y-m-d', strtotime($row['purchase_date'] . ' + ' . $row['warranty_months'] . ' months'));
            $row['warranty_expiry_date'] = $warrantyExpiryDate;
            
            $today = date('Y-m-d');
            if ($warrantyExpiryDate >= $today) {
                $row['warranty_status'] = 'active';
            } else {
                $row['warranty_status'] = 'expired';
            }
        } else {
            $row['warranty_status'] = 'unknown';
            $row['warranty_expiry_date'] = null;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $row
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Spare battery not found'
        ]);
    }
    
    $stmt->close();
}

// Get spare batteries with filters
function getSpareBatteries($conn, $search = '', $condition = '', $type = '', $status = '') {
    $query = "SELECT * FROM spare_batteries WHERE 1=1";
    $params = array();
    $types = '';
    
    // Add search filter
    if (!empty($search)) {
        $query .= " AND (battery_model LIKE ? 
                      OR battery_type LIKE ? 
                      OR manufacturer LIKE ? 
                      OR location LIKE ? 
                      OR notes LIKE ?)";
        $searchTerm = '%' . $search . '%';
        for ($i = 0; $i < 5; $i++) {
            $params[] = $searchTerm;
            $types .= 's';
        }
    }
    
    // Add condition filter
    if (!empty($condition) && $condition !== 'all') {
        $query .= " AND current_condition = ?";
        $params[] = $condition;
        $types .= 's';
    }
    
    // Add type filter
    if (!empty($type) && $type !== 'all') {
        $query .= " AND battery_type = ?";
        $params[] = $type;
        $types .= 's';
    }
    
    // Add status filter (for warranty status)
    if (!empty($status) && $status !== 'all') {
        if ($status === 'active') {
            $query .= " AND (warranty_months > 0 AND 
                      DATE_ADD(purchase_date, INTERVAL warranty_months MONTH) >= CURDATE())";
        } elseif ($status === 'expired') {
            $query .= " AND (warranty_months > 0 AND 
                      DATE_ADD(purchase_date, INTERVAL warranty_months MONTH) < CURDATE())";
        } elseif ($status === 'no_warranty') {
            $query .= " AND (warranty_months IS NULL OR warranty_months = 0 OR purchase_date IS NULL)";
        }
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $spareBatteries = array();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Generate battery code if not present
            if (empty($row['battery_code'])) {
                $row['battery_code'] = 'SPARE_' . $row['id'];
            }
            
            // Calculate warranty status and expiry date
            if (!empty($row['purchase_date']) && !empty($row['warranty_months'])) {
                $warrantyExpiryDate = date('Y-m-d', strtotime($row['purchase_date'] . ' + ' . $row['warranty_months'] . ' months'));
                $row['warranty_expiry_date'] = $warrantyExpiryDate;
                
                $today = date('Y-m-d');
                if ($warrantyExpiryDate >= $today) {
                    $row['warranty_status'] = 'active';
                } else {
                    $row['warranty_status'] = 'expired';
                }
            } else {
                $row['warranty_status'] = 'unknown';
                $row['warranty_expiry_date'] = null;
            }
            
            // Set is_spare to 1 since this is from spare_batteries table
            $row['is_spare'] = 1;
            
            // Add low quantity warning flag
            $quantity = intval($row['quantity']);
            $min_quantity = intval($row['min_quantity']);
            $row['is_low_quantity'] = ($quantity <= $min_quantity) ? 1 : 0;
            
            $spareBatteries[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $spareBatteries,
            'count' => count($spareBatteries),
            'total_units' => array_sum(array_column($spareBatteries, 'quantity'))
        ]);
    } else {
        throw new Exception("Database query error: " . $conn->error);
    }
    
    $stmt->close();
}

// Handle POST requests (Create new spare battery)
function handlePostRequest($conn) {
    try {
        // Get POST data
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data)) {
            $data = $_POST;
        }
        
        createSpareBattery($conn, $data);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

// Create new spare battery
function createSpareBattery($conn, $data) {
    // Required fields
    $requiredFields = ['battery_type', 'battery_model'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Set default values
    $battery_type = $data['battery_type'];
    $battery_model = $data['battery_model'];
    $capacity = isset($data['capacity']) ? $data['capacity'] : '';
    $voltage = isset($data['voltage']) ? $data['voltage'] : '';
    $manufacturer = isset($data['manufacturer']) ? $data['manufacturer'] : '';
    $purchase_date = isset($data['purchase_date']) ? $data['purchase_date'] : null;
    $warranty_months = isset($data['warranty_months']) ? intval($data['warranty_months']) : 0;
    $current_condition = isset($data['current_condition']) ? $data['current_condition'] : 'New';
    $quantity = isset($data['quantity']) ? intval($data['quantity']) : 1;
    $min_quantity = isset($data['min_quantity']) ? intval($data['min_quantity']) : 5;
    $location = isset($data['location']) ? $data['location'] : '';
    $notes = isset($data['notes']) ? $data['notes'] : '';
    
    // Insert into spare_batteries table
    $query = "INSERT INTO spare_batteries (
                battery_type, battery_model, capacity, voltage, manufacturer, 
                purchase_date, warranty_months, current_condition, quantity, 
                min_quantity, location, notes, created_at, updated_at
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param(
        "ssssssisiss", 
        $battery_type, $battery_model, $capacity, $voltage, $manufacturer,
        $purchase_date, $warranty_months, $current_condition, $quantity,
        $min_quantity, $location, $notes
    );
    
    if ($stmt->execute()) {
        $batteryId = $stmt->insert_id;
        
        echo json_encode([
            'success' => true,
            'message' => 'Spare battery created successfully',
            'id' => $batteryId
        ]);
    } else {
        throw new Exception("Failed to create spare battery: " . $conn->error);
    }
    
    $stmt->close();
}

// Handle PUT requests (Update spare battery)
function handlePutRequest($conn) {
    try {
        // Get PUT data
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data)) {
            parse_str(file_get_contents("php://input"), $data);
        }
        
        if (!isset($data['id'])) {
            throw new Exception("Battery ID is required");
        }
        
        $id = $data['id'];
        updateSpareBattery($conn, $id, $data);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

// Update spare battery
function updateSpareBattery($conn, $id, $data) {
    // Check if battery exists
    $checkQuery = "SELECT id FROM spare_batteries WHERE id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows == 0) {
        throw new Exception("Spare battery not found");
    }
    
    $checkStmt->close();
    
    // Build update query
    $allowedFields = [
        'battery_type', 'battery_model', 'capacity', 'voltage', 'manufacturer',
        'purchase_date', 'warranty_months', 'current_condition', 'quantity',
        'min_quantity', 'location', 'notes'
    ];
    
    $updates = [];
    $params = [];
    $types = '';
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
            
            // Determine parameter type
            if (in_array($field, ['warranty_months', 'quantity', 'min_quantity'])) {
                $types .= 'i'; // integer
            } else {
                $types .= 's'; // string
            }
        }
    }
    
    if (empty($updates)) {
        throw new Exception("No fields to update");
    }
    
    // Add updated_at
    $updates[] = "updated_at = NOW()";
    
    // Add ID to params
    $params[] = $id;
    $types .= 'i';
    
    $query = "UPDATE spare_batteries SET " . implode(', ', $updates) . " WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Spare battery updated successfully'
        ]);
    } else {
        throw new Exception("Failed to update spare battery: " . $conn->error);
    }
    
    $stmt->close();
}

// Handle DELETE requests
function handleDeleteRequest($conn) {
    try {
        // Get DELETE data
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data)) {
            // Try to get ID from URL parameters
            if (isset($_GET['id'])) {
                $data = ['id' => $_GET['id']];
            } else {
                throw new Exception("Battery ID is required");
            }
        }
        
        if (!isset($data['id'])) {
            throw new Exception("Battery ID is required");
        }
        
        $id = $data['id'];
        deleteSpareBattery($conn, $id);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

// Delete spare battery
function deleteSpareBattery($conn, $id) {
    // Check if battery exists
    $checkQuery = "SELECT id, quantity FROM spare_batteries WHERE id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows == 0) {
        throw new Exception("Spare battery not found");
    }
    
    $battery = $checkResult->fetch_assoc();
    
    // Optional: Check if battery has quantity > 0 before deleting
    // if ($battery['quantity'] > 0) {
    //     throw new Exception("Cannot delete spare battery with quantity > 0. Update quantity to 0 first.");
    // }
    
    $checkStmt->close();
    
    // Delete the battery
    $query = "DELETE FROM spare_batteries WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Spare battery deleted successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No spare battery was deleted'
            ]);
        }
    } else {
        throw new Exception("Failed to delete spare battery: " . $conn->error);
    }
    
    $stmt->close();
}

// Close database connection
$conn->close();
?>