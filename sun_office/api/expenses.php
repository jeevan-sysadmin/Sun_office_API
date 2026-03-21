<?php
/**
 * expenses.php - CRUD operations for expenses management
 * 
 * This API handles all expense-related operations:
 * - GET: Retrieve expenses (single, list, filtered, summary)
 * - POST: Create new expense
 * - PUT: Update existing expense
 * - DELETE: Remove expense
 */

// Set headers for REST API and CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'sun_office');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create database connection
function getConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $conn;
    } catch (PDOException $e) {
        sendResponse(500, false, "Database connection failed: " . $e->getMessage());
        exit();
    }
}

/**
 * Send JSON response
 */
function sendResponse($statusCode, $success, $message, $data = null, $meta = null) {
    http_response_code($statusCode);
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    if ($meta !== null) {
        $response['meta'] = $meta;
    }
    
    echo json_encode($response, JSON_NUMERIC_CHECK);
    exit();
}

/**
 * Validate date format
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Get staff list for dropdown
 */
function getStaffList($conn) {
    try {
        $query = "SELECT id, name, email, role FROM users WHERE is_active = 1 ORDER BY name";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get expense categories
 */
function getExpenseCategories($conn) {
    try {
        // Check if expense_categories table exists
        $stmt = $conn->prepare("SHOW TABLES LIKE 'expense_categories'");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $query = "SELECT id, category_name, description FROM expense_categories WHERE is_active = 1 ORDER BY category_name";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll();
        } else {
            // Return default categories if table doesn't exist
            return [
                ['id' => 1, 'category_name' => 'petrol', 'description' => 'Fuel expenses for vehicles'],
                ['id' => 2, 'category_name' => 'others', 'description' => 'Other miscellaneous expenses']
            ];
        }
    } catch (PDOException $e) {
        return [
            ['id' => 1, 'category_name' => 'petrol', 'description' => 'Fuel expenses for vehicles'],
            ['id' => 2, 'category_name' => 'others', 'description' => 'Other miscellaneous expenses']
        ];
    }
}

// Handle request based on method
$method = $_SERVER['REQUEST_METHOD'];
$conn = getConnection();

// Get request parameters
$params = $_GET;
$id = isset($params['id']) ? intval($params['id']) : null;
$staff_id = isset($params['staff_id']) ? intval($params['staff_id']) : null;
$expense_type = isset($params['type']) ? $params['type'] : null;
$from_date = isset($params['from']) ? $params['from'] : null;
$to_date = isset($params['to']) ? $params['to'] : null;
$month = isset($params['month']) ? $params['month'] : null;
$year = isset($params['year']) ? intval($params['year']) : null;
$action = isset($params['action']) ? $params['action'] : null;

switch ($method) {
    case 'GET':
        // Handle different GET actions
        if ($action === 'summary') {
            getExpenseSummary($conn, $from_date, $to_date, $month, $year);
        } else if ($action === 'staff-list') {
            getStaffForDropdown($conn);
        } else if ($action === 'categories') {
            getCategories($conn);
        } else if ($action === 'stats') {
            getExpenseStats($conn, $from_date, $to_date, $month, $year);
        } else if ($id) {
            getExpenseById($conn, $id);
        } else {
            getAllExpenses($conn, $staff_id, $expense_type, $from_date, $to_date, $month, $year);
        }
        break;
        
    case 'POST':
        createExpense($conn);
        break;
        
    case 'PUT':
        if (!$id) {
            sendResponse(400, false, "Expense ID is required for update");
        }
        updateExpense($conn, $id);
        break;
        
    case 'DELETE':
        if (!$id) {
            sendResponse(400, false, "Expense ID is required for deletion");
        }
        deleteExpense($conn, $id);
        break;
        
    default:
        sendResponse(405, false, "Method not allowed");
}

/**
 * GET: Get all expenses with optional filters
 */
function getAllExpenses($conn, $staff_id = null, $expense_type = null, $from_date = null, $to_date = null, $month = null, $year = null) {
    try {
        $query = "SELECT e.*, 
                         u.name as created_by_name,
                         u.email as created_by_email
                  FROM expenses e
                  LEFT JOIN users u ON e.created_by = u.id
                  WHERE 1=1";
        
        $params = [];
        
        // Apply filters
        if ($staff_id) {
            $query .= " AND e.staff_id = :staff_id";
            $params[':staff_id'] = $staff_id;
        }
        
        if ($expense_type) {
            $query .= " AND e.expense_type = :expense_type";
            $params[':expense_type'] = $expense_type;
        }
        
        if ($from_date && validateDate($from_date)) {
            $query .= " AND e.expense_date >= :from_date";
            $params[':from_date'] = $from_date;
        }
        
        if ($to_date && validateDate($to_date)) {
            $query .= " AND e.expense_date <= :to_date";
            $params[':to_date'] = $to_date;
        }
        
        if ($month) {
            $query .= " AND DATE_FORMAT(e.expense_date, '%Y-%m') = :month";
            $params[':month'] = $month;
        }
        
        if ($year) {
            $query .= " AND YEAR(e.expense_date) = :year";
            $params[':year'] = $year;
        }
        
        $query .= " ORDER BY e.expense_date DESC, e.created_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $expenses = $stmt->fetchAll();
        
        // Get summary statistics
        $stats = getExpenseStatsSimple($conn, $staff_id, $expense_type, $from_date, $to_date, $month, $year);
        
        $meta = [
            'total_count' => count($expenses),
            'filters' => [
                'staff_id' => $staff_id,
                'expense_type' => $expense_type,
                'from_date' => $from_date,
                'to_date' => $to_date,
                'month' => $month,
                'year' => $year
            ],
            'summary' => $stats
        ];
        
        sendResponse(200, true, "Expenses retrieved successfully", $expenses, $meta);
        
    } catch (PDOException $e) {
        sendResponse(500, false, "Error retrieving expenses: " . $e->getMessage());
    }
}

/**
 * GET: Get single expense by ID
 */
function getExpenseById($conn, $id) {
    try {
        $query = "SELECT e.*, 
                         u.name as created_by_name,
                         u.email as created_by_email,
                         creator.name as creator_name
                  FROM expenses e
                  LEFT JOIN users u ON e.staff_id = u.id
                  LEFT JOIN users creator ON e.created_by = creator.id
                  WHERE e.id = :id";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([':id' => $id]);
        $expense = $stmt->fetch();
        
        if (!$expense) {
            sendResponse(404, false, "Expense not found");
        }
        
        sendResponse(200, true, "Expense retrieved successfully", $expense);
        
    } catch (PDOException $e) {
        sendResponse(500, false, "Error retrieving expense: " . $e->getMessage());
    }
}

/**
 * GET: Get expense summary
 */
function getExpenseSummary($conn, $from_date = null, $to_date = null, $month = null, $year = null) {
    try {
        $query = "SELECT 
                    DATE_FORMAT(expense_date, '%Y-%m') as month,
                    expense_type,
                    COUNT(*) as expense_count,
                    SUM(amount) as total_amount,
                    AVG(amount) as average_amount,
                    MIN(amount) as min_amount,
                    MAX(amount) as max_amount,
                    SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END) as cash_total,
                    SUM(CASE WHEN payment_method = 'card' THEN amount ELSE 0 END) as card_total,
                    SUM(CASE WHEN payment_method = 'online' THEN amount ELSE 0 END) as online_total
                  FROM expenses
                  WHERE 1=1";
        
        $params = [];
        
        if ($from_date && validateDate($from_date)) {
            $query .= " AND expense_date >= :from_date";
            $params[':from_date'] = $from_date;
        }
        
        if ($to_date && validateDate($to_date)) {
            $query .= " AND expense_date <= :to_date";
            $params[':to_date'] = $to_date;
        }
        
        if ($month) {
            $query .= " AND DATE_FORMAT(expense_date, '%Y-%m') = :month";
            $params[':month'] = $month;
        }
        
        if ($year) {
            $query .= " AND YEAR(expense_date) = :year";
            $params[':year'] = $year;
        }
        
        $query .= " GROUP BY DATE_FORMAT(expense_date, '%Y-%m'), expense_type
                    ORDER BY month DESC, expense_type";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $summary = $stmt->fetchAll();
        
        // Get overall totals
        $totalQuery = "SELECT 
                          COUNT(*) as total_expenses,
                          SUM(amount) as grand_total,
                          SUM(CASE WHEN expense_type = 'petrol' THEN amount ELSE 0 END) as petrol_total,
                          SUM(CASE WHEN expense_type = 'others' THEN amount ELSE 0 END) as others_total,
                          COUNT(DISTINCT staff_id) as unique_staff
                       FROM expenses
                       WHERE 1=1";
        
        $totalStmt = $conn->prepare($totalQuery);
        $totalStmt->execute($params);
        $totals = $totalStmt->fetch();
        
        $meta = [
            'filters' => [
                'from_date' => $from_date,
                'to_date' => $to_date,
                'month' => $month,
                'year' => $year
            ],
            'totals' => $totals
        ];
        
        sendResponse(200, true, "Expense summary retrieved successfully", $summary, $meta);
        
    } catch (PDOException $e) {
        sendResponse(500, false, "Error retrieving expense summary: " . $e->getMessage());
    }
}

/**
 * GET: Get expense statistics
 */
function getExpenseStats($conn, $from_date = null, $to_date = null, $month = null, $year = null) {
    try {
        // Daily stats
        $dailyQuery = "SELECT 
                          expense_date,
                          COUNT(*) as expense_count,
                          SUM(amount) as total_amount
                       FROM expenses
                       WHERE 1=1";
        
        $params = [];
        
        if ($from_date && validateDate($from_date)) {
            $dailyQuery .= " AND expense_date >= :from_date";
            $params[':from_date'] = $from_date;
        }
        
        if ($to_date && validateDate($to_date)) {
            $dailyQuery .= " AND expense_date <= :to_date";
            $params[':to_date'] = $to_date;
        }
        
        if ($month) {
            $dailyQuery .= " AND DATE_FORMAT(expense_date, '%Y-%m') = :month";
            $params[':month'] = $month;
        }
        
        if ($year) {
            $dailyQuery .= " AND YEAR(expense_date) = :year";
            $params[':year'] = $year;
        }
        
        $dailyQuery .= " GROUP BY expense_date ORDER BY expense_date DESC LIMIT 30";
        
        $dailyStmt = $conn->prepare($dailyQuery);
        $dailyStmt->execute($params);
        $dailyStats = $dailyStmt->fetchAll();
        
        // Staff-wise stats
        $staffQuery = "SELECT 
                          e.staff_id,
                          e.staff_name,
                          COUNT(*) as expense_count,
                          SUM(amount) as total_amount,
                          SUM(CASE WHEN e.expense_type = 'petrol' THEN e.amount ELSE 0 END) as petrol_total,
                          SUM(CASE WHEN e.expense_type = 'others' THEN e.amount ELSE 0 END) as others_total
                       FROM expenses e
                       WHERE 1=1";
        
        $staffStmt = $conn->prepare($staffQuery . " GROUP BY e.staff_id, e.staff_name ORDER BY total_amount DESC");
        $staffStmt->execute($params);
        $staffStats = $staffStmt->fetchAll();
        
        // Payment method stats
        $paymentQuery = "SELECT 
                           payment_method,
                           COUNT(*) as count,
                           SUM(amount) as total
                         FROM expenses
                         WHERE 1=1";
        
        $paymentStmt = $conn->prepare($paymentQuery . " GROUP BY payment_method");
        $paymentStmt->execute($params);
        $paymentStats = $paymentStmt->fetchAll();
        
        $stats = [
            'daily' => $dailyStats,
            'by_staff' => $staffStats,
            'by_payment' => $paymentStats
        ];
        
        sendResponse(200, true, "Expense statistics retrieved successfully", $stats);
        
    } catch (PDOException $e) {
        sendResponse(500, false, "Error retrieving expense statistics: " . $e->getMessage());
    }
}

/**
 * Simple expense summary for meta data
 */
function getExpenseStatsSimple($conn, $staff_id = null, $expense_type = null, $from_date = null, $to_date = null, $month = null, $year = null) {
    try {
        $query = "SELECT 
                    COUNT(*) as total_count,
                    SUM(amount) as total_amount,
                    SUM(CASE WHEN expense_type = 'petrol' THEN amount ELSE 0 END) as petrol_total,
                    SUM(CASE WHEN expense_type = 'others' THEN amount ELSE 0 END) as others_total,
                    AVG(amount) as average_amount
                  FROM expenses
                  WHERE 1=1";
        
        $params = [];
        
        if ($staff_id) {
            $query .= " AND staff_id = :staff_id";
            $params[':staff_id'] = $staff_id;
        }
        
        if ($expense_type) {
            $query .= " AND expense_type = :expense_type";
            $params[':expense_type'] = $expense_type;
        }
        
        if ($from_date && validateDate($from_date)) {
            $query .= " AND expense_date >= :from_date";
            $params[':from_date'] = $from_date;
        }
        
        if ($to_date && validateDate($to_date)) {
            $query .= " AND expense_date <= :to_date";
            $params[':to_date'] = $to_date;
        }
        
        if ($month) {
            $query .= " AND DATE_FORMAT(expense_date, '%Y-%m') = :month";
            $params[':month'] = $month;
        }
        
        if ($year) {
            $query .= " AND YEAR(expense_date) = :year";
            $params[':year'] = $year;
        }
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch();
        
    } catch (PDOException $e) {
        return [
            'total_count' => 0,
            'total_amount' => 0,
            'petrol_total' => 0,
            'others_total' => 0,
            'average_amount' => 0
        ];
    }
}

/**
 * GET: Get staff list for dropdown
 */
function getStaffForDropdown($conn) {
    $staff = getStaffList($conn);
    sendResponse(200, true, "Staff list retrieved successfully", $staff);
}

/**
 * GET: Get expense categories
 */
function getCategories($conn) {
    $categories = getExpenseCategories($conn);
    sendResponse(200, true, "Expense categories retrieved successfully", $categories);
}

/**
 * POST: Create new expense
 */
function createExpense($conn) {
    try {
        // Get POST data
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!$data) {
            sendResponse(400, false, "Invalid JSON data");
        }
        
        // Validate required fields
        $required_fields = ['staff_name', 'amount', 'expense_date'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                sendResponse(400, false, "Missing required field: $field");
            }
        }
        
        // Validate amount
        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            sendResponse(400, false, "Amount must be a positive number");
        }
        
        // Validate expense date
        if (!validateDate($data['expense_date'])) {
            sendResponse(400, false, "Invalid expense date format. Use YYYY-MM-DD");
        }
        
        // Set defaults
        $staff_id = isset($data['staff_id']) && !empty($data['staff_id']) ? $data['staff_id'] : null;
        $expense_type = isset($data['expense_type']) ? $data['expense_type'] : 'others';
        $payment_method = isset($data['payment_method']) ? $data['payment_method'] : 'cash';
        $receipt_number = isset($data['receipt_number']) ? $data['receipt_number'] : null;
        $description = isset($data['description']) ? $data['description'] : null;
        $notes = isset($data['notes']) ? $data['notes'] : null;
        $created_by = isset($data['created_by']) ? $data['created_by'] : null;
        
        // Insert expense
        $query = "INSERT INTO expenses (
                    staff_id, staff_name, expense_type, amount, description, 
                    expense_date, payment_method, receipt_number, notes, created_by
                  ) VALUES (
                    :staff_id, :staff_name, :expense_type, :amount, :description,
                    :expense_date, :payment_method, :receipt_number, :notes, :created_by
                  )";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':staff_id' => $staff_id,
            ':staff_name' => $data['staff_name'],
            ':expense_type' => $expense_type,
            ':amount' => $data['amount'],
            ':description' => $description,
            ':expense_date' => $data['expense_date'],
            ':payment_method' => $payment_method,
            ':receipt_number' => $receipt_number,
            ':notes' => $notes,
            ':created_by' => $created_by
        ]);
        
        $expense_id = $conn->lastInsertId();
        
        // Get created expense
        $query = "SELECT e.*, u.name as created_by_name 
                  FROM expenses e
                  LEFT JOIN users u ON e.created_by = u.id
                  WHERE e.id = :id";
        $stmt = $conn->prepare($query);
        $stmt->execute([':id' => $expense_id]);
        $expense = $stmt->fetch();
        
        sendResponse(201, true, "Expense created successfully", $expense);
        
    } catch (PDOException $e) {
        sendResponse(500, false, "Error creating expense: " . $e->getMessage());
    }
}

/**
 * PUT: Update existing expense
 */
function updateExpense($conn, $id) {
    try {
        // Check if expense exists
        $checkQuery = "SELECT id FROM expenses WHERE id = :id";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([':id' => $id]);
        
        if ($checkStmt->rowCount() === 0) {
            sendResponse(404, false, "Expense not found");
        }
        
        // Get PUT data
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!$data) {
            sendResponse(400, false, "Invalid JSON data");
        }
        
        // Build update query dynamically
        $updateFields = [];
        $params = [':id' => $id];
        
        $allowed_fields = [
            'staff_id', 'staff_name', 'expense_type', 'amount', 
            'description', 'expense_date', 'payment_method', 
            'receipt_number', 'notes'
        ];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        // Validate amount if provided
        if (isset($data['amount']) && (!is_numeric($data['amount']) || $data['amount'] <= 0)) {
            sendResponse(400, false, "Amount must be a positive number");
        }
        
        // Validate date if provided
        if (isset($data['expense_date']) && !validateDate($data['expense_date'])) {
            sendResponse(400, false, "Invalid expense date format. Use YYYY-MM-DD");
        }
        
        if (empty($updateFields)) {
            sendResponse(400, false, "No fields to update");
        }
        
        $query = "UPDATE expenses SET " . implode(", ", $updateFields) . " WHERE id = :id";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        
        // Get updated expense
        $selectQuery = "SELECT e.*, u.name as created_by_name 
                        FROM expenses e
                        LEFT JOIN users u ON e.created_by = u.id
                        WHERE e.id = :id";
        $selectStmt = $conn->prepare($selectQuery);
        $selectStmt->execute([':id' => $id]);
        $expense = $selectStmt->fetch();
        
        sendResponse(200, true, "Expense updated successfully", $expense);
        
    } catch (PDOException $e) {
        sendResponse(500, false, "Error updating expense: " . $e->getMessage());
    }
}

/**
 * DELETE: Delete expense
 */
function deleteExpense($conn, $id) {
    try {
        // Check if expense exists
        $checkQuery = "SELECT id, staff_name, amount, expense_date FROM expenses WHERE id = :id";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([':id' => $id]);
        
        if ($checkStmt->rowCount() === 0) {
            sendResponse(404, false, "Expense not found");
        }
        
        $expense = $checkStmt->fetch();
        
        // Delete expense
        $deleteQuery = "DELETE FROM expenses WHERE id = :id";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->execute([':id' => $id]);
        
        sendResponse(200, true, "Expense deleted successfully", [
            'deleted_id' => $id,
            'staff_name' => $expense['staff_name'],
            'amount' => $expense['amount'],
            'expense_date' => $expense['expense_date']
        ]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, "Error deleting expense: " . $e->getMessage());
    }
}
?>