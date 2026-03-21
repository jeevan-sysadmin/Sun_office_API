<?php
/**
 * salary.php - CRUD operations for staff salary management
 * 
 * This API handles all salary-related operations with service_type support
 * - GET: Retrieve salaries (single, list, filtered, summary)
 * - POST: Create new salary record
 * - PUT: Update existing salary record
 * - DELETE: Remove salary record
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
 * Validate month format (YYYY-MM)
 */
function validateMonth($month) {
    return preg_match('/^\d{4}-\d{2}$/', $month);
}

/**
 * Validate service type
 */
function validateServiceType($service_type) {
    return in_array($service_type, ['water', 'inverter']);
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
 * Calculate net amount
 */
function calculateNetAmount($amount, $bonus = 0, $deductions = 0) {
    return $amount + $bonus - $deductions;
}

// Handle request based on method
$method = $_SERVER['REQUEST_METHOD'];
$conn = getConnection();

// Get request parameters
$params = $_GET;
$id = isset($params['id']) ? intval($params['id']) : null;
$staff_id = isset($params['staff_id']) ? intval($params['staff_id']) : null;
$salary_month = isset($params['month']) ? $params['month'] : null;
$from_date = isset($params['from']) ? $params['from'] : null;
$to_date = isset($params['to']) ? $params['to'] : null;
$year = isset($params['year']) ? intval($params['year']) : null;
$service_type = isset($params['service_type']) ? $params['service_type'] : null;
$action = isset($params['action']) ? $params['action'] : null;

switch ($method) {
    case 'GET':
        // Handle different GET actions
        if ($action === 'summary') {
            getSalarySummary($conn, $year, $staff_id, $service_type);
        } else if ($action === 'staff-list') {
            getStaffForDropdown($conn);
        } else if ($action === 'stats') {
            getSalaryStats($conn, $year, $staff_id, $service_type);
        } else if ($action === 'pending') {
            getPendingSalaries($conn, $salary_month);
        } else if ($action === 'monthly-report') {
            getMonthlyReport($conn, $salary_month, $year, $service_type);
        } else if ($id) {
            getSalaryById($conn, $id);
        } else {
            getAllSalaries($conn, $staff_id, $salary_month, $from_date, $to_date, $year, $service_type);
        }
        break;
        
    case 'POST':
        createSalary($conn);
        break;
        
    case 'PUT':
        if (!$id) {
            sendResponse(400, false, "Salary ID is required for update");
        }
        updateSalary($conn, $id);
        break;
        
    case 'DELETE':
        if (!$id) {
            sendResponse(400, false, "Salary ID is required for deletion");
        }
        deleteSalary($conn, $id);
        break;
        
    default:
        sendResponse(405, false, "Method not allowed");
}

/**
 * GET: Get all salaries with optional filters
 */
function getAllSalaries($conn, $staff_id = null, $salary_month = null, $from_date = null, $to_date = null, $year = null, $service_type = null) {
    try {
        $query = "SELECT s.*, 
                         u.name as paid_by_name,
                         u.email as paid_by_email,
                         staff.name as staff_user_name,
                         staff.email as staff_email
                  FROM salary s
                  LEFT JOIN users u ON s.paid_by = u.id
                  LEFT JOIN users staff ON s.staff_id = staff.id
                  WHERE 1=1";
        
        $params = [];
        
        // Apply filters
        if ($staff_id) {
            $query .= " AND s.staff_id = :staff_id";
            $params[':staff_id'] = $staff_id;
        }
        
        if ($service_type && validateServiceType($service_type)) {
            $query .= " AND s.service_type = :service_type";
            $params[':service_type'] = $service_type;
        }
        
        if ($salary_month && validateMonth($salary_month)) {
            $query .= " AND s.salary_month = :salary_month";
            $params[':salary_month'] = $salary_month;
        }
        
        if ($from_date && validateDate($from_date)) {
            $query .= " AND s.salary_date >= :from_date";
            $params[':from_date'] = $from_date;
        }
        
        if ($to_date && validateDate($to_date)) {
            $query .= " AND s.salary_date <= :to_date";
            $params[':to_date'] = $to_date;
        }
        
        if ($year) {
            $query .= " AND YEAR(s.salary_date) = :year";
            $params[':year'] = $year;
        }
        
        $query .= " ORDER BY s.salary_month DESC, s.staff_name ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $salaries = $stmt->fetchAll();
        
        // Get summary statistics
        $stats = getSalaryStatsSimple($conn, $staff_id, $salary_month, $from_date, $to_date, $year, $service_type);
        
        $meta = [
            'total_count' => count($salaries),
            'filters' => [
                'staff_id' => $staff_id,
                'service_type' => $service_type,
                'salary_month' => $salary_month,
                'from_date' => $from_date,
                'to_date' => $to_date,
                'year' => $year
            ],
            'summary' => $stats
        ];
        
        sendResponse(200, true, "Salaries retrieved successfully", $salaries, $meta);
        
    } catch (PDOException $e) {
        sendResponse(500, false, "Error retrieving salaries: " . $e->getMessage());
    }
}

/**
 * GET: Get single salary by ID
 */
function getSalaryById($conn, $id) {
    try {
        $query = "SELECT s.*, 
                         u.name as paid_by_name,
                         u.email as paid_by_email,
                         staff.name as staff_user_name,
                         staff.email as staff_email,
                         staff.role as staff_role
                  FROM salary s
                  LEFT JOIN users u ON s.paid_by = u.id
                  LEFT JOIN users staff ON s.staff_id = staff.id
                  WHERE s.id = :id";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([':id' => $id]);
        $salary = $stmt->fetch();
        
        if (!$salary) {
            sendResponse(404, false, "Salary record not found");
        }
        
        sendResponse(200, true, "Salary record retrieved successfully", $salary);
        
    } catch (PDOException $e) {
        sendResponse(500, false, "Error retrieving salary: " . $e->getMessage());
    }
}

/**
 * GET: Get salary summary by month/year
 */
function getSalarySummary($conn, $year = null, $staff_id = null, $service_type = null) {
    try {
        $query = "SELECT 
                    salary_month,
                    service_type,
                    COUNT(*) as employee_count,
                    SUM(amount) as total_base_salary,
                    SUM(bonus) as total_bonus,
                    SUM(deductions) as total_deductions,
                    SUM(net_amount) as total_salary_paid,
                    AVG(net_amount) as average_salary,
                    MIN(net_amount) as min_salary,
                    MAX(net_amount) as max_salary
                  FROM salary
                  WHERE 1=1";
        
        $params = [];
        
        if ($year) {
            $query .= " AND salary_month LIKE :year_pattern";
            $params[':year_pattern'] = "$year%";
        }
        
        if ($staff_id) {
            $query .= " AND staff_id = :staff_id";
            $params[':staff_id'] = $staff_id;
        }
        
        if ($service_type && validateServiceType($service_type)) {
            $query .= " AND service_type = :service_type";
            $params[':service_type'] = $service_type;
        }
        
        $query .= " GROUP BY salary_month, service_type
                    ORDER BY salary_month DESC, service_type";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $summary = $stmt->fetchAll();
        
        // Get overall totals
        $totalQuery = "SELECT 
                          COUNT(*) as total_records,
                          COUNT(DISTINCT staff_id) as total_staff,
                          SUM(amount) as grand_total_base,
                          SUM(bonus) as grand_total_bonus,
                          SUM(deductions) as grand_total_deductions,
                          SUM(net_amount) as grand_total_paid
                       FROM salary
                       WHERE 1=1";
        
        $totalStmt = $conn->prepare($totalQuery);
        $totalStmt->execute($params);
        $totals = $totalStmt->fetch();
        
        $meta = [
            'filters' => [
                'year' => $year,
                'staff_id' => $staff_id,
                'service_type' => $service_type
            ],
            'totals' => $totals
        ];
        
        sendResponse(200, true, "Salary summary retrieved successfully", $summary, $meta);
        
    } catch (PDOException $e) {
        sendResponse(500, false, "Error retrieving salary summary: " . $e->getMessage());
    }
}

/**
 * GET: Get salary statistics
 */
function getSalaryStats($conn, $year = null, $staff_id = null, $service_type = null) {
    try {
        $params = [];
        $whereClause = "WHERE 1=1";
        
        if ($year) {
            $whereClause .= " AND salary_month LIKE :year_pattern";
            $params[':year_pattern'] = "$year%";
        }
        
        if ($staff_id) {
            $whereClause .= " AND staff_id = :staff_id";
            $params[':staff_id'] = $staff_id;
        }
        
        if ($service_type && validateServiceType($service_type)) {
            $whereClause .= " AND service_type = :service_type";
            $params[':service_type'] = $service_type;
        }
        
        // Monthly stats by service type
        $monthlyQuery = "SELECT 
                           salary_month,
                           service_type,
                           COUNT(*) as payments,
                           SUM(net_amount) as total
                         FROM salary
                         $whereClause
                         GROUP BY salary_month, service_type
                         ORDER BY salary_month DESC, service_type
                         LIMIT 12";
        
        $monthlyStmt = $conn->prepare($monthlyQuery);
        $monthlyStmt->execute($params);
        $monthlyStats = $monthlyStmt->fetchAll();
        
        // Staff-wise stats
        $staffQuery = "SELECT 
                          s.staff_id,
                          s.staff_name,
                          COUNT(*) as payment_count,
                          SUM(s.amount) as total_base,
                          SUM(s.bonus) as total_bonus,
                          SUM(s.deductions) as total_deductions,
                          SUM(s.net_amount) as total_net,
                          AVG(s.net_amount) as average_net,
                          MIN(s.salary_date) as first_payment,
                          MAX(s.salary_date) as last_payment
                       FROM salary s
                       $whereClause
                       GROUP BY s.staff_id, s.staff_name
                       ORDER BY total_net DESC";
        
        $staffStmt = $conn->prepare($staffQuery);
        $staffStmt->execute($params);
        $staffStats = $staffStmt->fetchAll();
        
        // Payment method stats
        $paymentQuery = "SELECT 
                           payment_method,
                           COUNT(*) as count,
                           SUM(net_amount) as total
                         FROM salary
                         $whereClause
                         GROUP BY payment_method";
        
        $paymentStmt = $conn->prepare($paymentQuery);
        $paymentStmt->execute($params);
        $paymentStats = $paymentStmt->fetchAll();
        
        // Service type stats
        $serviceQuery = "SELECT 
                           service_type,
                           COUNT(*) as count,
                           SUM(net_amount) as total
                         FROM salary
                         $whereClause
                         GROUP BY service_type";
        
        $serviceStmt = $conn->prepare($serviceQuery);
        $serviceStmt->execute($params);
        $serviceStats = $serviceStmt->fetchAll();
        
        $stats = [
            'monthly' => $monthlyStats,
            'by_staff' => $staffStats,
            'by_payment' => $paymentStats,
            'by_service' => $serviceStats
        ];
        
        sendResponse(200, true, "Salary statistics retrieved successfully", $stats);
        
    } catch (PDOException $e) {
        sendResponse(500, false, "Error retrieving salary statistics: " . $e->getMessage());
    }
}

/**
 * GET: Get pending salaries for a month
 */
function getPendingSalaries($conn, $salary_month = null) {
    try {
        if (!$salary_month) {
            // Default to current month
            $salary_month = date('Y-m');
        }
        
        if (!validateMonth($salary_month)) {
            sendResponse(400, false, "Invalid month format. Use YYYY-MM");
        }
        
        // Get all active staff
        $staffQuery = "SELECT id, name, email, role FROM users WHERE is_active = 1 ORDER BY name";
        $staffStmt = $conn->prepare($staffQuery);
        $staffStmt->execute();
        $allStaff = $staffStmt->fetchAll();
        
        // Get paid staff for the month (considering both service types)
        $paidQuery = "SELECT staff_id, service_type FROM salary WHERE salary_month = :salary_month";
        $paidStmt = $conn->prepare($paidQuery);
        $paidStmt->execute([':salary_month' => $salary_month]);
        $paidRecords = $paidStmt->fetchAll();
        
        // Create a map of paid staff and their service types
        $paidMap = [];
        foreach ($paidRecords as $record) {
            $paidMap[$record['staff_id']][$record['service_type']] = true;
        }
        
        // Filter pending staff (those missing either service type)
        $pendingStaff = [];
        foreach ($allStaff as $staff) {
            $staffId = $staff['id'];
            $pendingServices = [];
            
            if (!isset($paidMap[$staffId]['water'])) {
                $pendingServices[] = 'water';
            }
            if (!isset($paidMap[$staffId]['inverter'])) {
                $pendingServices[] = 'inverter';
            }
            
            if (!empty($pendingServices)) {
                $staff['pending_services'] = $pendingServices;
                $pendingStaff[] = $staff;
            }
        }
        
        // Get last salary info for pending staff
        foreach ($pendingStaff as &$staff) {
            $lastSalaryQuery = "SELECT salary_month, amount, net_amount, salary_date, service_type 
                               FROM salary 
                               WHERE staff_id = :staff_id 
                               ORDER BY salary_date DESC 
                               LIMIT 2"; // Get last 2 to see both service types
            $lastStmt = $conn->prepare($lastSalaryQuery);
            $lastStmt->execute([':staff_id' => $staff['id']]);
            $staff['last_salaries'] = $lastStmt->fetchAll();
        }
        
        $meta = [
            'salary_month' => $salary_month,
            'total_staff' => count($allStaff),
            'paid_records' => count($paidRecords),
            'pending_count' => count($pendingStaff)
        ];
        
        sendResponse(200, true, "Pending salaries retrieved successfully", $pendingStaff, $meta);
        
    } catch (PDOException $e) {
        sendResponse(500, false, "Error retrieving pending salaries: " . $e->getMessage());
    }
}

/**
 * GET: Get monthly salary report
 */
function getMonthlyReport($conn, $salary_month = null, $year = null, $service_type = null) {
    try {
        if ($salary_month && !validateMonth($salary_month)) {
            sendResponse(400, false, "Invalid month format. Use YYYY-MM");
        }
        
        $params = [];
        $whereClause = "WHERE 1=1";
        
        if ($salary_month) {
            $whereClause .= " AND salary_month = :salary_month";
            $params[':salary_month'] = $salary_month;
        } elseif ($year) {
            $whereClause .= " AND salary_month LIKE :year_pattern";
            $params[':year_pattern'] = "$year%";
        }
        
        if ($service_type && validateServiceType($service_type)) {
            $whereClause .= " AND service_type = :service_type";
            $params[':service_type'] = $service_type;
        }
        
        $query = "SELECT 
                    s.*,
                    u.name as paid_by_name
                  FROM salary s
                  LEFT JOIN users u ON s.paid_by = u.id
                  $whereClause
                  ORDER BY s.salary_month DESC, s.service_type, s.staff_name ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $salaries = $stmt->fetchAll();
        
        // Group by month and service type
        $report = [];
        foreach ($salaries as $salary) {
            $month = $salary['salary_month'];
            $service = $salary['service_type'];
            
            if (!isset($report[$month])) {
                $report[$month] = [
                    'month' => $month,
                    'total_base' => 0,
                    'total_bonus' => 0,
                    'total_deductions' => 0,
                    'total_net' => 0,
                    'employee_count' => 0,
                    'by_service' => [
                        'water' => ['count' => 0, 'total' => 0],
                        'inverter' => ['count' => 0, 'total' => 0]
                    ],
                    'employees' => []
                ];
            }
            
            $report[$month]['total_base'] += $salary['amount'];
            $report[$month]['total_bonus'] += $salary['bonus'];
            $report[$month]['total_deductions'] += $salary['deductions'];
            $report[$month]['total_net'] += $salary['net_amount'];
            $report[$month]['employee_count']++;
            $report[$month]['by_service'][$service]['count']++;
            $report[$month]['by_service'][$service]['total'] += $salary['net_amount'];
            $report[$month]['employees'][] = $salary;
        }
        
        sendResponse(200, true, "Monthly report retrieved successfully", array_values($report));
        
    } catch (PDOException $e) {
        sendResponse(500, false, "Error retrieving monthly report: " . $e->getMessage());
    }
}

/**
 * Simple salary summary for meta data
 */
function getSalaryStatsSimple($conn, $staff_id = null, $salary_month = null, $from_date = null, $to_date = null, $year = null, $service_type = null) {
    try {
        $query = "SELECT 
                    COUNT(*) as total_records,
                    COUNT(DISTINCT staff_id) as unique_staff,
                    SUM(amount) as total_base_salary,
                    SUM(bonus) as total_bonus,
                    SUM(deductions) as total_deductions,
                    SUM(net_amount) as total_net_paid,
                    AVG(net_amount) as average_net
                  FROM salary
                  WHERE 1=1";
        
        $params = [];
        
        if ($staff_id) {
            $query .= " AND staff_id = :staff_id";
            $params[':staff_id'] = $staff_id;
        }
        
        if ($service_type && validateServiceType($service_type)) {
            $query .= " AND service_type = :service_type";
            $params[':service_type'] = $service_type;
        }
        
        if ($salary_month && validateMonth($salary_month)) {
            $query .= " AND salary_month = :salary_month";
            $params[':salary_month'] = $salary_month;
        }
        
        if ($from_date && validateDate($from_date)) {
            $query .= " AND salary_date >= :from_date";
            $params[':from_date'] = $from_date;
        }
        
        if ($to_date && validateDate($to_date)) {
            $query .= " AND salary_date <= :to_date";
            $params[':to_date'] = $to_date;
        }
        
        if ($year) {
            $query .= " AND YEAR(salary_date) = :year";
            $params[':year'] = $year;
        }
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch();
        
    } catch (PDOException $e) {
        return [
            'total_records' => 0,
            'unique_staff' => 0,
            'total_base_salary' => 0,
            'total_bonus' => 0,
            'total_deductions' => 0,
            'total_net_paid' => 0,
            'average_net' => 0
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
 * POST: Create new salary record
 */
function createSalary($conn) {
    try {
        // Get POST data
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!$data) {
            sendResponse(400, false, "Invalid JSON data");
        }
        
        // Validate required fields
        $required_fields = ['staff_name', 'amount', 'salary_date', 'salary_month', 'service_type'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                sendResponse(400, false, "Missing required field: $field");
            }
        }
        
        // Validate service type
        if (!validateServiceType($data['service_type'])) {
            sendResponse(400, false, "Invalid service type. Must be 'water' or 'inverter'");
        }
        
        // Validate amount
        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            sendResponse(400, false, "Amount must be a positive number");
        }
        
        // Validate salary date
        if (!validateDate($data['salary_date'])) {
            sendResponse(400, false, "Invalid salary date format. Use YYYY-MM-DD");
        }
        
        // Validate salary month
        if (!validateMonth($data['salary_month'])) {
            sendResponse(400, false, "Invalid salary month format. Use YYYY-MM");
        }
        
        // Check for duplicate staff-month-service combination
        if (isset($data['staff_id']) && $data['staff_id']) {
            $checkQuery = "SELECT id FROM salary 
                          WHERE staff_id = :staff_id 
                          AND salary_month = :salary_month 
                          AND service_type = :service_type";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->execute([
                ':staff_id' => $data['staff_id'],
                ':salary_month' => $data['salary_month'],
                ':service_type' => $data['service_type']
            ]);
            
            if ($checkStmt->rowCount() > 0) {
                sendResponse(409, false, "Salary record already exists for this staff member with the same service type in the specified month");
            }
        }
        
        // Set defaults
        $staff_id = isset($data['staff_id']) && !empty($data['staff_id']) ? $data['staff_id'] : null;
        $payment_method = isset($data['payment_method']) ? $data['payment_method'] : 'bank_transfer';
        $transaction_id = isset($data['transaction_id']) ? $data['transaction_id'] : null;
        $bonus = isset($data['bonus']) ? floatval($data['bonus']) : 0;
        $deductions = isset($data['deductions']) ? floatval($data['deductions']) : 0;
        $notes = isset($data['notes']) ? $data['notes'] : null;
        $paid_by = isset($data['paid_by']) ? $data['paid_by'] : null;
        
        // Insert salary
        $query = "INSERT INTO salary (
                    staff_id, staff_name, service_type, amount, salary_date, salary_month,
                    payment_method, transaction_id, bonus, deductions, notes, paid_by
                  ) VALUES (
                    :staff_id, :staff_name, :service_type, :amount, :salary_date, :salary_month,
                    :payment_method, :transaction_id, :bonus, :deductions, :notes, :paid_by
                  )";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':staff_id' => $staff_id,
            ':staff_name' => $data['staff_name'],
            ':service_type' => $data['service_type'],
            ':amount' => $data['amount'],
            ':salary_date' => $data['salary_date'],
            ':salary_month' => $data['salary_month'],
            ':payment_method' => $payment_method,
            ':transaction_id' => $transaction_id,
            ':bonus' => $bonus,
            ':deductions' => $deductions,
            ':notes' => $notes,
            ':paid_by' => $paid_by
        ]);
        
        $salary_id = $conn->lastInsertId();
        
        // Get created salary record
        $query = "SELECT s.*, u.name as paid_by_name 
                  FROM salary s
                  LEFT JOIN users u ON s.paid_by = u.id
                  WHERE s.id = :id";
        $stmt = $conn->prepare($query);
        $stmt->execute([':id' => $salary_id]);
        $salary = $stmt->fetch();
        
        sendResponse(201, true, "Salary record created successfully", $salary);
        
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) { // Duplicate entry error
            sendResponse(409, false, "Salary record already exists for this staff member with the same service type in the specified month");
        }
        sendResponse(500, false, "Error creating salary record: " . $e->getMessage());
    }
}

/**
 * PUT: Update existing salary record
 */
function updateSalary($conn, $id) {
    try {
        // Check if salary record exists
        $checkQuery = "SELECT id FROM salary WHERE id = :id";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([':id' => $id]);
        
        if ($checkStmt->rowCount() === 0) {
            sendResponse(404, false, "Salary record not found");
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
            'staff_id', 'staff_name', 'service_type', 'amount', 'salary_date', 
            'salary_month', 'payment_method', 'transaction_id', 
            'bonus', 'deductions', 'notes', 'paid_by'
        ];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        // Validate service type if provided
        if (isset($data['service_type']) && !validateServiceType($data['service_type'])) {
            sendResponse(400, false, "Invalid service type. Must be 'water' or 'inverter'");
        }
        
        // Validate amount if provided
        if (isset($data['amount']) && (!is_numeric($data['amount']) || $data['amount'] <= 0)) {
            sendResponse(400, false, "Amount must be a positive number");
        }
        
        // Validate date if provided
        if (isset($data['salary_date']) && !validateDate($data['salary_date'])) {
            sendResponse(400, false, "Invalid salary date format. Use YYYY-MM-DD");
        }
        
        // Validate month if provided
        if (isset($data['salary_month']) && !validateMonth($data['salary_month'])) {
            sendResponse(400, false, "Invalid salary month format. Use YYYY-MM");
        }
        
        // Check for duplicate if staff_id, month, service_type are being updated
        if (isset($data['staff_id']) && isset($data['salary_month']) && isset($data['service_type'])) {
            $dupQuery = "SELECT id FROM salary 
                        WHERE staff_id = :staff_id 
                        AND salary_month = :salary_month 
                        AND service_type = :service_type 
                        AND id != :id";
            $dupStmt = $conn->prepare($dupQuery);
            $dupStmt->execute([
                ':staff_id' => $data['staff_id'],
                ':salary_month' => $data['salary_month'],
                ':service_type' => $data['service_type'],
                ':id' => $id
            ]);
            
            if ($dupStmt->rowCount() > 0) {
                sendResponse(409, false, "Salary record already exists for this staff member with the same service type in the specified month");
            }
        }
        
        if (empty($updateFields)) {
            sendResponse(400, false, "No fields to update");
        }
        
        $query = "UPDATE salary SET " . implode(", ", $updateFields) . " WHERE id = :id";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        
        // Get updated salary record
        $selectQuery = "SELECT s.*, u.name as paid_by_name 
                        FROM salary s
                        LEFT JOIN users u ON s.paid_by = u.id
                        WHERE s.id = :id";
        $selectStmt = $conn->prepare($selectQuery);
        $selectStmt->execute([':id' => $id]);
        $salary = $selectStmt->fetch();
        
        sendResponse(200, true, "Salary record updated successfully", $salary);
        
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) { // Duplicate entry error
            sendResponse(409, false, "Salary record already exists for this staff member with the same service type in the specified month");
        }
        sendResponse(500, false, "Error updating salary record: " . $e->getMessage());
    }
}

/**
 * DELETE: Delete salary record
 */
function deleteSalary($conn, $id) {
    try {
        // Check if salary record exists
        $checkQuery = "SELECT id, staff_name, amount, salary_month, service_type FROM salary WHERE id = :id";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([':id' => $id]);
        
        if ($checkStmt->rowCount() === 0) {
            sendResponse(404, false, "Salary record not found");
        }
        
        $salary = $checkStmt->fetch();
        
        // Delete salary record
        $deleteQuery = "DELETE FROM salary WHERE id = :id";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->execute([':id' => $id]);
        
        sendResponse(200, true, "Salary record deleted successfully", [
            'deleted_id' => $id,
            'staff_name' => $salary['staff_name'],
            'amount' => $salary['amount'],
            'salary_month' => $salary['salary_month'],
            'service_type' => $salary['service_type']
        ]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, "Error deleting salary record: " . $e->getMessage());
    }
}
?>