<?php
// C:\xampp\htdocs\sun_office\api\inverters.php
// API for inverters table with serial number support - ALL FIELDS OPTIONAL, NULLS ALLOWED
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration
$host = '127.0.0.1';
$dbname = 'sun_office';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set collation for the connection - use utf8mb4_general_ci to match your tables
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Get request method and parameters
$method = $_SERVER['REQUEST_METHOD'];

// Parse ID from query string or path
$id = null;
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
} else {
    // Try to get ID from PATH_INFO
    $path_info = $_SERVER['PATH_INFO'] ?? '';
    $path_parts = explode('/', trim($path_info, '/'));
    if (!empty($path_parts) && is_numeric($path_parts[0])) {
        $id = (int)$path_parts[0];
    }
}

// Parse input data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input && ($method === 'POST' || $method === 'PUT')) {
    // Fallback to form data if JSON parsing fails
    $input = $_POST;
}

// Route based on HTTP method
switch ($method) {
    case 'GET':
        if ($id) {
            getInverter($pdo, $id);
        } else {
            getAllInverters($pdo);
        }
        break;
    
    case 'POST':
        createInverter($pdo, $input);
        break;
    
    case 'PUT':
        if ($id) {
            updateInverter($pdo, $id, $input);
        } else {
            // If no ID in URL, check if ID is in request body
            if (isset($input['id'])) {
                updateInverter($pdo, (int)$input['id'], $input);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'ID is required for PUT request']);
            }
        }
        break;
    
    case 'DELETE':
        if ($id) {
            deleteInverter($pdo, $id);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'ID is required for DELETE request']);
        }
        break;
    
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

/**
 * Get all inverters with optional filtering
 */
function getAllInverters($pdo) {
    try {
        // Build query with optional filters
        $sql = "SELECT * FROM inverters WHERE 1=1";
        $params = [];
        
        // Filter by status
        if (isset($_GET['status']) && $_GET['status'] !== '') {
            $sql .= " AND status = :status";
            $params[':status'] = $_GET['status'];
        }
        
        // Filter by brand
        if (isset($_GET['brand']) && $_GET['brand'] !== '') {
            $sql .= " AND inverter_brand LIKE :brand";
            $params[':brand'] = '%' . $_GET['brand'] . '%';
        }
        
        // Filter by condition
        if (isset($_GET['condition']) && $_GET['condition'] !== '') {
            $sql .= " AND inverter_condition = :condition";
            $params[':condition'] = $_GET['condition'];
        }
        
        // Filter by wave type
        if (isset($_GET['wave_type']) && $_GET['wave_type'] !== '') {
            $sql .= " AND wave_type = :wave_type";
            $params[':wave_type'] = $_GET['wave_type'];
        }
        
        // Search by code, model, or serial
        if (isset($_GET['search']) && $_GET['search'] !== '') {
            $sql .= " AND (inverter_code LIKE :search OR inverter_model LIKE :search OR inverter_serial LIKE :search)";
            $params[':search'] = '%' . $_GET['search'] . '%';
        }
        
        // Sorting
        $sort_field = $_GET['sort_by'] ?? 'id';
        $sort_order = $_GET['sort_order'] ?? 'DESC';
        $allowed_fields = ['id', 'inverter_code', 'inverter_model', 'inverter_serial', 'power_rating', 'price', 'created_at'];
        
        if (in_array($sort_field, $allowed_fields)) {
            $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';
            $sql .= " ORDER BY $sort_field $sort_order";
        } else {
            $sql .= " ORDER BY id DESC";
        }
        
        // Pagination
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
        $offset = ($page - 1) * $limit;
        
        // Get total count for pagination
        $count_sql = "SELECT COUNT(*) as total FROM inverters WHERE 1=1";
        $count_params = $params;
        
        // Add filter conditions to count query
        $count_stmt = $pdo->prepare($count_sql);
        foreach ($params as $key => $value) {
            $count_stmt->bindValue($key, $value);
        }
        $count_stmt->execute();
        $total = $count_stmt->fetch()['total'];
        
        // Add limit to main query
        $sql .= " LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind all parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $inverters = $stmt->fetchAll();
        
        // Return with pagination info
        echo json_encode([
            'success' => true,
            'data' => $inverters,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch inverters: ' . $e->getMessage()]);
    }
}

/**
 * Get single inverter by ID
 */
function getInverter($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM inverters WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $inverter = $stmt->fetch();
        
        if ($inverter) {
            echo json_encode(['success' => true, 'data' => $inverter]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Inverter not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch inverter: ' . $e->getMessage()]);
    }
}

/**
 * Helper function to convert empty strings to NULL
 */
function emptyToNull($value) {
    return ($value === '' || $value === null) ? null : $value;
}

/**
 * Create new inverter with serial number support - ALL FIELDS OPTIONAL
 */
function createInverter($pdo, $data) {
    try {
        // Process data: convert empty strings to NULL for unique fields
        $inverter_serial = isset($data['inverter_serial']) ? emptyToNull($data['inverter_serial']) : null;
        $inverter_code = isset($data['inverter_code']) ? emptyToNull($data['inverter_code']) : null;
        
        // Check for duplicate serial number only if provided and not NULL
        if ($inverter_serial !== null) {
            $check_serial = $pdo->prepare("SELECT id FROM inverters WHERE inverter_serial = :serial AND inverter_serial IS NOT NULL");
            $check_serial->execute([':serial' => $inverter_serial]);
            if ($check_serial->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Duplicate entry: Serial number already exists']);
                return;
            }
        }
        
        // Check for duplicate code only if provided and not NULL
        if ($inverter_code !== null) {
            $check_code = $pdo->prepare("SELECT id FROM inverters WHERE inverter_code = :code AND inverter_code IS NOT NULL");
            $check_code->execute([':code' => $inverter_code]);
            if ($check_code->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Duplicate entry: Inverter code already exists']);
                return;
            }
        }
        
        // Auto-generate inverter_code if not provided
        if ($inverter_code === null) {
            // Get the current max numeric value from inverter_code
            $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(inverter_code, 4) AS UNSIGNED)) as max_num FROM inverters WHERE inverter_code REGEXP '^INV[0-9]+$'");
            $result = $stmt->fetch();
            $next_num = ($result['max_num'] ?? 0) + 1;
            $inverter_code = 'INV' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
        }
        
        // Prepare insert query - all fields optional, convert empty strings to NULL
        $fields = [
            'inverter_code' => $inverter_code,
            'inverter_model' => isset($data['inverter_model']) ? $data['inverter_model'] : null,
            'inverter_serial' => $inverter_serial,
            'inverter_brand' => isset($data['inverter_brand']) ? $data['inverter_brand'] : null,
            'power_rating' => isset($data['power_rating']) ? $data['power_rating'] : null,
            'type' => isset($data['type']) && $data['type'] !== '' ? $data['type'] : 'inverter',
            'wave_type' => isset($data['wave_type']) && $data['wave_type'] !== '' ? $data['wave_type'] : 'modified_sine',
            'input_voltage' => isset($data['input_voltage']) ? emptyToNull($data['input_voltage']) : null,
            'output_voltage' => isset($data['output_voltage']) && $data['output_voltage'] !== '' ? $data['output_voltage'] : '230V',
            'efficiency' => isset($data['efficiency']) ? emptyToNull($data['efficiency']) : null,
            'battery_voltage' => isset($data['battery_voltage']) && $data['battery_voltage'] !== '' ? $data['battery_voltage'] : '12V',
            'specifications' => isset($data['specifications']) ? $data['specifications'] : null,
            'warranty_period' => isset($data['warranty_period']) ? $data['warranty_period'] : null,
            'price' => isset($data['price']) && $data['price'] !== '' ? floatval($data['price']) : 0.00,
            'status' => isset($data['status']) && $data['status'] !== '' ? $data['status'] : 'active',
            'purchase_date' => isset($data['purchase_date']) ? emptyToNull($data['purchase_date']) : null,
            'installation_date' => isset($data['installation_date']) ? emptyToNull($data['installation_date']) : null,
            'inverter_condition' => isset($data['inverter_condition']) && $data['inverter_condition'] !== '' ? $data['inverter_condition'] : 'good'
        ];
        
        // Build SQL
        $columns = implode(', ', array_keys($fields));
        $placeholders = ':' . implode(', :', array_keys($fields));
        
        $sql = "INSERT INTO inverters ($columns) VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        
        // Execute with parameters
        $stmt->execute($fields);
        
        // Get the inserted record
        $newId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM inverters WHERE id = :id");
        $stmt->execute([':id' => $newId]);
        $newInverter = $stmt->fetch();
        
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Inverter created successfully',
            'data' => $newInverter
        ]);
        
    } catch (PDOException $e) {
        // Handle duplicate entry errors
        if ($e->errorInfo[1] == 1062) {
            $error_message = $e->getMessage();
            
            if (strpos($error_message, 'inverter_serial') !== false) {
                http_response_code(409);
                echo json_encode(['error' => 'Duplicate entry: Serial number already exists']);
            } elseif (strpos($error_message, 'inverter_code') !== false) {
                http_response_code(409);
                echo json_encode(['error' => 'Duplicate entry: Inverter code already exists']);
            } elseif (strpos($error_message, 'PRIMARY') !== false) {
                http_response_code(409);
                echo json_encode(['error' => 'Duplicate entry: ID already exists']);
            } else {
                http_response_code(409);
                echo json_encode(['error' => 'Duplicate entry: ' . $error_message]);
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create inverter: ' . $e->getMessage()]);
        }
    }
}

/**
 * Update existing inverter with serial number support - ALL FIELDS OPTIONAL
 */
function updateInverter($pdo, $id, $data) {
    try {
        // Check if inverter exists
        $check = $pdo->prepare("SELECT id, inverter_serial, inverter_code FROM inverters WHERE id = :id");
        $check->execute([':id' => $id]);
        $existing = $check->fetch();
        
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['error' => 'Inverter not found']);
            return;
        }
        
        // Process data: convert empty strings to NULL for unique fields
        $inverter_serial = isset($data['inverter_serial']) ? emptyToNull($data['inverter_serial']) : null;
        $inverter_code = isset($data['inverter_code']) ? emptyToNull($data['inverter_code']) : null;
        
        // Check for duplicate serial only if provided and changed
        if ($inverter_serial !== null && $inverter_serial !== $existing['inverter_serial']) {
            $check_serial = $pdo->prepare("SELECT id FROM inverters WHERE inverter_serial = :serial AND inverter_serial IS NOT NULL AND id != :id");
            $check_serial->execute([
                ':serial' => $inverter_serial,
                ':id' => $id
            ]);
            if ($check_serial->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Duplicate entry: Serial number already exists']);
                return;
            }
        }
        
        // Check for duplicate code only if provided and changed
        if ($inverter_code !== null && $inverter_code !== $existing['inverter_code']) {
            $check_code = $pdo->prepare("SELECT id FROM inverters WHERE inverter_code = :code AND inverter_code IS NOT NULL AND id != :id");
            $check_code->execute([
                ':code' => $inverter_code,
                ':id' => $id
            ]);
            if ($check_code->fetch()) {
                http_response_code(409);
                echo json_encode(['error' => 'Duplicate entry: Inverter code already exists']);
                return;
            }
        }
        
        // Build update query dynamically based on provided data
        $updates = [];
        $params = [':id' => $id];
        
        $field_mappings = [
            'inverter_code' => $inverter_code,
            'inverter_model' => isset($data['inverter_model']) ? $data['inverter_model'] : null,
            'inverter_serial' => $inverter_serial,
            'inverter_brand' => isset($data['inverter_brand']) ? $data['inverter_brand'] : null,
            'power_rating' => isset($data['power_rating']) ? $data['power_rating'] : null,
            'type' => isset($data['type']) && $data['type'] !== '' ? $data['type'] : null,
            'wave_type' => isset($data['wave_type']) && $data['wave_type'] !== '' ? $data['wave_type'] : null,
            'input_voltage' => isset($data['input_voltage']) ? emptyToNull($data['input_voltage']) : null,
            'output_voltage' => isset($data['output_voltage']) && $data['output_voltage'] !== '' ? $data['output_voltage'] : null,
            'efficiency' => isset($data['efficiency']) ? emptyToNull($data['efficiency']) : null,
            'battery_voltage' => isset($data['battery_voltage']) && $data['battery_voltage'] !== '' ? $data['battery_voltage'] : null,
            'specifications' => isset($data['specifications']) ? $data['specifications'] : null,
            'warranty_period' => isset($data['warranty_period']) ? $data['warranty_period'] : null,
            'price' => isset($data['price']) && $data['price'] !== '' ? floatval($data['price']) : null,
            'status' => isset($data['status']) && $data['status'] !== '' ? $data['status'] : null,
            'purchase_date' => isset($data['purchase_date']) ? emptyToNull($data['purchase_date']) : null,
            'installation_date' => isset($data['installation_date']) ? emptyToNull($data['installation_date']) : null,
            'inverter_condition' => isset($data['inverter_condition']) && $data['inverter_condition'] !== '' ? $data['inverter_condition'] : null
        ];
        
        foreach ($field_mappings as $field => $value) {
            if (array_key_exists($field, $data)) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $value;
            }
        }
        
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            return;
        }
        
        // Execute update
        $sql = "UPDATE inverters SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Get updated record
        $stmt = $pdo->prepare("SELECT * FROM inverters WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $updatedInverter = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'Inverter updated successfully',
            'data' => $updatedInverter
        ]);
        
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            $error_message = $e->getMessage();
            
            if (strpos($error_message, 'inverter_serial') !== false) {
                http_response_code(409);
                echo json_encode(['error' => 'Duplicate entry: Serial number already exists']);
            } elseif (strpos($error_message, 'inverter_code') !== false) {
                http_response_code(409);
                echo json_encode(['error' => 'Duplicate entry: Inverter code already exists']);
            } else {
                http_response_code(409);
                echo json_encode(['error' => 'Duplicate entry: ' . $error_message]);
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update inverter: ' . $e->getMessage()]);
        }
    }
}

/**
 * Delete inverter
 */
function deleteInverter($pdo, $id) {
    try {
        // Check if inverter exists
        $check = $pdo->prepare("SELECT id, inverter_code, inverter_model, inverter_serial FROM inverters WHERE id = :id");
        $check->execute([':id' => $id]);
        $inverter = $check->fetch();
        
        if (!$inverter) {
            http_response_code(404);
            echo json_encode(['error' => 'Inverter not found']);
            return;
        }
        
        // Check if inverter is used in inverter_services
        $check_services = $pdo->prepare("SELECT COUNT(*) as count FROM inverter_services WHERE inverter_id = :id");
        $check_services->execute([':id' => $id]);
        $service_count = $check_services->fetch()['count'];
        
        if ($service_count > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Cannot delete inverter: It is referenced in ' . $service_count . ' service record(s)']);
            return;
        }
        
        // Delete the record
        $stmt = $pdo->prepare("DELETE FROM inverters WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Inverter deleted successfully',
            'data' => [
                'id' => $id,
                'inverter_code' => $inverter['inverter_code'],
                'inverter_model' => $inverter['inverter_model'],
                'inverter_serial' => $inverter['inverter_serial']
            ]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete inverter: ' . $e->getMessage()]);
    }
}
?>