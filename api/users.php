<?php
// C:\xampp\htdocs\sun_office\api\users.php
// Enhanced API endpoint with full CRUD operations and dynamic column checking

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = "127.0.0.1";
$dbname = "sun_office";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // First, check if users table exists and create if not
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        // Create users table with all columns
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `email` varchar(100) NOT NULL,
            `password` varchar(255) NOT NULL,
            `role` enum('admin','manager','technician','staff','sales') DEFAULT 'staff',
            `phone` varchar(20) DEFAULT NULL,
            `address` text DEFAULT NULL,
            `department` varchar(100) DEFAULT NULL,
            `position` varchar(100) DEFAULT NULL,
            `is_active` tinyint(1) DEFAULT 1,
            `last_login` datetime DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `email` (`email`),
            KEY `idx_user_email` (`email`),
            KEY `idx_user_role` (`role`),
            KEY `idx_user_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";
        
        $pdo->exec($createTableSQL);
        
        // Insert sample data
        $insertSQL = "
        INSERT INTO `users` (`name`, `email`, `password`, `role`, `phone`, `address`, `department`, `position`, `is_active`) VALUES
        ('Admin User', 'admin@sunoffice.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '9876543210', '123 Main Street, Tirunelveli', 'Management', 'Senior Administrator', 1),
        ('Staff User', 'staff@sunoffice.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', '9876543211', '456 Park Avenue, Tirunelveli', 'Operations', 'Customer Service', 1),
        ('Service Technician', 'tech@sunoffice.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'technician', '9876543212', '789 Service Road, Tirunelveli', 'Technical', 'Senior Technician', 1);
        ";
        
        $pdo->exec($insertSQL);
    }
    
    // Get existing columns in the users table
    $stmt = $pdo->query("DESCRIBE users");
    $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Define all possible columns we might want to use
    $allColumns = [
        'id', 'name', 'email', 'password', 'role', 'phone', 'address', 
        'department', 'position', 'is_active', 'last_login', 'created_at', 'updated_at'
    ];
    
    // Filter to only columns that exist
    $availableColumns = array_intersect($allColumns, $existingColumns);
    
    // Base columns that should always be selected (excluding sensitive data)
    $selectColumns = array_diff($availableColumns, ['password']);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($method) {
        case 'GET':
            // Get query parameters
            $id = isset($_GET['id']) ? intval($_GET['id']) : null;
            $role = isset($_GET['role']) ? $_GET['role'] : null;
            $is_active = isset($_GET['is_active']) ? $_GET['is_active'] : null;
            $search = isset($_GET['search']) ? $_GET['search'] : null;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : null;
            $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
            
            if ($id) {
                // Get single user - build dynamic select
                $selectList = implode(', ', $selectColumns);
                $stmt = $pdo->prepare("SELECT $selectList FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // Format boolean values
                    if (isset($user['is_active'])) {
                        $user['is_active'] = (bool)$user['is_active'];
                    }
                    echo json_encode(['success' => true, 'user' => $user]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                }
            } else {
                // Build dynamic query based on available columns
                $selectList = implode(', ', $selectColumns);
                $sql = "SELECT $selectList FROM users WHERE 1=1";
                $params = [];
                
                if ($role && in_array('role', $availableColumns)) {
                    $sql .= " AND role = ?";
                    $params[] = $role;
                }
                
                if ($is_active !== null && in_array('is_active', $availableColumns)) {
                    $sql .= " AND is_active = ?";
                    $params[] = (int)$is_active;
                }
                
                if ($search) {
                    $searchConditions = [];
                    $searchTerm = "%$search%";
                    
                    if (in_array('name', $availableColumns)) {
                        $searchConditions[] = "name LIKE ?";
                        $params[] = $searchTerm;
                    }
                    if (in_array('email', $availableColumns)) {
                        $searchConditions[] = "email LIKE ?";
                        $params[] = $searchTerm;
                    }
                    if (in_array('phone', $availableColumns)) {
                        $searchConditions[] = "phone LIKE ?";
                        $params[] = $searchTerm;
                    }
                    
                    if (!empty($searchConditions)) {
                        $sql .= " AND (" . implode(' OR ', $searchConditions) . ")";
                    }
                }
                
                $sql .= " ORDER BY id";
                
                if ($limit) {
                    $sql .= " LIMIT ? OFFSET ?";
                    $params[] = $limit;
                    $params[] = $offset;
                }
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Format boolean values
                foreach ($users as &$user) {
                    if (isset($user['is_active'])) {
                        $user['is_active'] = (bool)$user['is_active'];
                    }
                }
                
                // Get total count for pagination
                $countSql = "SELECT COUNT(*) as total FROM users WHERE 1=1";
                $countParams = [];
                
                if ($role && in_array('role', $availableColumns)) {
                    $countSql .= " AND role = ?";
                    $countParams[] = $role;
                }
                
                if ($is_active !== null && in_array('is_active', $availableColumns)) {
                    $countSql .= " AND is_active = ?";
                    $countParams[] = (int)$is_active;
                }
                
                if ($search) {
                    $searchConditions = [];
                    if (in_array('name', $availableColumns)) {
                        $searchConditions[] = "name LIKE ?";
                    }
                    if (in_array('email', $availableColumns)) {
                        $searchConditions[] = "email LIKE ?";
                    }
                    if (in_array('phone', $availableColumns)) {
                        $searchConditions[] = "phone LIKE ?";
                    }
                    
                    if (!empty($searchConditions)) {
                        $countSql .= " AND (" . implode(' OR ', $searchConditions) . ")";
                        $searchTerm = "%$search%";
                        for ($i = 0; $i < count($searchConditions); $i++) {
                            $countParams[] = $searchTerm;
                        }
                    }
                }
                
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($countParams);
                $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                echo json_encode([
                    'success' => true,
                    'users' => $users,
                    'count' => count($users),
                    'total' => (int)$total,
                    'available_columns' => array_values($availableColumns)
                ]);
            }
            break;
            
        case 'POST':
            // Create new user
            if (!$input) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid input data']);
                exit();
            }
            
            // Validate required fields
            if (empty($input['name']) || empty($input['email']) || empty($input['password'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Name, email and password are required']);
                exit();
            }
            
            // Check if email exists
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->execute([$input['email']]);
            if ($checkStmt->fetch()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Email already exists']);
                exit();
            }
            
            // Hash password
            $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
            
            // Build dynamic insert based on available columns
            $insertColumns = ['name', 'email', 'password'];
            $insertValues = [$input['name'], $input['email'], $hashedPassword];
            $placeholders = ['?', '?', '?'];
            
            // Add optional fields if they exist in table and are provided
            $optionalFields = ['role', 'phone', 'address', 'department', 'position', 'is_active'];
            foreach ($optionalFields as $field) {
                if (in_array($field, $availableColumns) && isset($input[$field])) {
                    $insertColumns[] = $field;
                    $insertValues[] = $field === 'is_active' ? (int)$input[$field] : $input[$field];
                    $placeholders[] = '?';
                }
            }
            
            $sql = "INSERT INTO users (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($insertValues);
            
            $userId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'User created successfully',
                'user_id' => $userId
            ]);
            break;
            
        case 'PUT':
            // Update user
            if (!$input || !isset($input['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID is required']);
                exit();
            }
            
            // Check if user exists
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $checkStmt->execute([$input['id']]);
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit();
            }
            
            // Check if email exists for other users
            if (isset($input['email']) && in_array('email', $availableColumns)) {
                $emailCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $emailCheck->execute([$input['email'], $input['id']]);
                if ($emailCheck->fetch()) {
                    http_response_code(409);
                    echo json_encode(['success' => false, 'message' => 'Email already exists for another user']);
                    exit();
                }
            }
            
            // Build update query dynamically based on available columns
            $updates = [];
            $params = [];
            
            $updatableFields = ['name', 'email', 'role', 'phone', 'address', 'department', 'position', 'is_active'];
            foreach ($updatableFields as $field) {
                if (in_array($field, $availableColumns) && isset($input[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $field === 'is_active' ? (int)$input[$field] : $input[$field];
                }
            }
            
            // Handle password update separately
            if (!empty($input['password'])) {
                if (in_array('password', $availableColumns)) {
                    $updates[] = "password = ?";
                    $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
                }
            }
            
            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
                exit();
            }
            
            $params[] = $input['id'];
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'User updated successfully'
            ]);
            break;
            
        case 'DELETE':
            // Delete user
            $id = isset($_GET['id']) ? intval($_GET['id']) : null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'User ID is required']);
                exit();
            }
            
            // Check if user exists
            $checkStmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
            $checkStmt->execute([$id]);
            $user = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit();
            }
            
            // Delete user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'User deleted successfully',
                'deleted_user' => $user['name']
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (PDOException $e) {
    $errorMessage = $e->getMessage();
    $errorCode = $e->getCode();
    
    // Provide more user-friendly error messages
    if (strpos($errorMessage, 'Column not found') !== false) {
        $response = [
            'success' => false,
            'message' => 'Database schema needs to be updated. Please run the database update script.',
            'error_details' => $errorMessage,
            'solution' => 'Run the SQL script to add missing columns: ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL, ADD COLUMN address TEXT DEFAULT NULL, ADD COLUMN department VARCHAR(100) DEFAULT NULL, ADD COLUMN position VARCHAR(100) DEFAULT NULL;'
        ];
    } else {
        $response = [
            'success' => false,
            'message' => 'Database error: ' . $errorMessage,
            'error_code' => $errorCode
        ];
    }
    
    http_response_code(500);
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>