<?php
/**
 * Revenue API - Get revenue data with profit/loss calculation including inverter services
 * 
 * Endpoint: http://localhost/sun_office/api/revenue.php
 * Method: GET
 * 
 * Query Parameters:
 * - date_range (optional): all, today, this_week, this_month, this_year, custom
 * - from_date (required if date_range=custom): Start date (YYYY-MM-DD)
 * - to_date (required if date_range=custom): End date (YYYY-MM-DD)
 * - year (optional): Filter by specific year (used with date_range=all or this_year)
 * - month (optional): Filter by specific month (1-12)
 * - customer_id (optional): Filter by specific customer
 * 
 * Examples:
 * - http://localhost/sun_office/api/revenue.php?date_range=this_month
 * - http://localhost/sun_office/api/revenue.php?date_range=custom&from_date=2026-01-01&to_date=2026-01-31
 * - http://localhost/sun_office/api/revenue.php?date_range=this_year&year=2026
 * - http://localhost/sun_office/api/revenue.php?date_range=all&year=2026&month=2
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET method.'
    ]);
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
        $response = array_merge($response, $data);
    }
    
    if ($meta !== null) {
        $response['meta'] = $meta;
    }
    
    echo json_encode($response, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

$conn = getConnection();

// Get filter parameters
$dateRange = isset($_GET['date_range']) ? $_GET['date_range'] : 'all';
$fromDate = isset($_GET['from_date']) ? $_GET['from_date'] : null;
$toDate = isset($_GET['to_date']) ? $_GET['to_date'] : null;
$year = isset($_GET['year']) ? intval($_GET['year']) : null;
$month = isset($_GET['month']) ? intval($_GET['month']) : null;
$customerId = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : null;

// Validate date range
$validDateRanges = ['all', 'today', 'this_week', 'this_month', 'this_year', 'custom'];
if (!in_array($dateRange, $validDateRanges)) {
    sendResponse(400, false, "Invalid date_range parameter. Must be one of: " . implode(', ', $validDateRanges));
}

// Validate custom date range
if ($dateRange === 'custom') {
    if (!$fromDate || !$toDate) {
        sendResponse(400, false, "from_date and to_date are required for custom date range");
    }
    
    // Validate date format
    $dateRegex = '/^\d{4}-\d{2}-\d{2}$/';
    if (!preg_match($dateRegex, $fromDate) || !preg_match($dateRegex, $toDate)) {
        sendResponse(400, false, "Invalid date format. Use YYYY-MM-DD");
    }
    
    // Validate date range
    if (strtotime($fromDate) > strtotime($toDate)) {
        sendResponse(400, false, "from_date must be less than or equal to to_date");
    }
}

// Validate year if provided
if ($year !== null && ($year < 2000 || $year > 2100)) {
    sendResponse(400, false, "Invalid year parameter. Year must be between 2000 and 2100.");
}

// Validate month if provided
if ($month !== null && ($month < 1 || $month > 12)) {
    sendResponse(400, false, "Invalid month parameter. Month must be between 1 and 12.");
}

/**
 * Build date conditions for water services (based on service_date)
 */
function buildWaterServiceDateConditions($dateRange, $fromDate = null, $toDate = null, $year = null, $month = null) {
    $conditions = [];
    $params = [];
    
    switch ($dateRange) {
        case 'today':
            $today = date('Y-m-d');
            $conditions[] = "DATE(service_date) = :date";
            $params[':date'] = $today;
            break;
            
        case 'this_week':
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            $conditions[] = "DATE(service_date) BETWEEN :from_date AND :to_date";
            $params[':from_date'] = $startOfWeek;
            $params[':to_date'] = $endOfWeek;
            break;
            
        case 'this_month':
            $startOfMonth = date('Y-m-01');
            $endOfMonth = date('Y-m-t');
            $conditions[] = "DATE(service_date) BETWEEN :from_date AND :to_date";
            $params[':from_date'] = $startOfMonth;
            $params[':to_date'] = $endOfMonth;
            break;
            
        case 'this_year':
            $currentYear = date('Y');
            $conditions[] = "YEAR(service_date) = :year";
            $params[':year'] = $year !== null ? $year : $currentYear;
            break;
            
        case 'custom':
            if ($fromDate && $toDate) {
                $conditions[] = "DATE(service_date) BETWEEN :from_date AND :to_date";
                $params[':from_date'] = $fromDate;
                $params[':to_date'] = $toDate;
            }
            break;
            
        case 'all':
        default:
            if ($year !== null) {
                $conditions[] = "YEAR(service_date) = :year";
                $params[':year'] = $year;
                
                if ($month !== null) {
                    $conditions[] = "MONTH(service_date) = :month";
                    $params[':month'] = $month;
                }
            }
            break;
    }
    
    return ['conditions' => $conditions, 'params' => $params];
}

/**
 * Build date conditions for inverter services (based on created_at)
 */
function buildInverterServiceDateConditions($dateRange, $fromDate = null, $toDate = null, $year = null, $month = null) {
    $conditions = [];
    $params = [];
    
    switch ($dateRange) {
        case 'today':
            $today = date('Y-m-d');
            $conditions[] = "DATE(created_at) = :date";
            $params[':date'] = $today;
            break;
            
        case 'this_week':
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            $conditions[] = "DATE(created_at) BETWEEN :from_date AND :to_date";
            $params[':from_date'] = $startOfWeek;
            $params[':to_date'] = $endOfWeek;
            break;
            
        case 'this_month':
            $startOfMonth = date('Y-m-01');
            $endOfMonth = date('Y-m-t');
            $conditions[] = "DATE(created_at) BETWEEN :from_date AND :to_date";
            $params[':from_date'] = $startOfMonth;
            $params[':to_date'] = $endOfMonth;
            break;
            
        case 'this_year':
            $currentYear = date('Y');
            $conditions[] = "YEAR(created_at) = :year";
            $params[':year'] = $year !== null ? $year : $currentYear;
            break;
            
        case 'custom':
            if ($fromDate && $toDate) {
                $conditions[] = "DATE(created_at) BETWEEN :from_date AND :to_date";
                $params[':from_date'] = $fromDate;
                $params[':to_date'] = $toDate;
            }
            break;
            
        case 'all':
        default:
            if ($year !== null) {
                $conditions[] = "YEAR(created_at) = :year";
                $params[':year'] = $year;
                
                if ($month !== null) {
                    $conditions[] = "MONTH(created_at) = :month";
                    $params[':month'] = $month;
                }
            }
            break;
    }
    
    return ['conditions' => $conditions, 'params' => $params];
}

/**
 * Build date conditions for expenses table
 */
function buildExpenseDateConditions($dateRange, $fromDate = null, $toDate = null, $year = null, $month = null) {
    $conditions = [];
    $params = [];
    
    switch ($dateRange) {
        case 'today':
            $today = date('Y-m-d');
            $conditions[] = "DATE(expense_date) = :date";
            $params[':date'] = $today;
            break;
            
        case 'this_week':
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            $conditions[] = "DATE(expense_date) BETWEEN :from_date AND :to_date";
            $params[':from_date'] = $startOfWeek;
            $params[':to_date'] = $endOfWeek;
            break;
            
        case 'this_month':
            $startOfMonth = date('Y-m-01');
            $endOfMonth = date('Y-m-t');
            $conditions[] = "DATE(expense_date) BETWEEN :from_date AND :to_date";
            $params[':from_date'] = $startOfMonth;
            $params[':to_date'] = $endOfMonth;
            break;
            
        case 'this_year':
            $currentYear = date('Y');
            $conditions[] = "YEAR(expense_date) = :year";
            $params[':year'] = $year !== null ? $year : $currentYear;
            break;
            
        case 'custom':
            if ($fromDate && $toDate) {
                $conditions[] = "DATE(expense_date) BETWEEN :from_date AND :to_date";
                $params[':from_date'] = $fromDate;
                $params[':to_date'] = $toDate;
            }
            break;
            
        case 'all':
        default:
            if ($year !== null) {
                $conditions[] = "YEAR(expense_date) = :year";
                $params[':year'] = $year;
                
                if ($month !== null) {
                    $conditions[] = "MONTH(expense_date) = :month";
                    $params[':month'] = $month;
                }
            }
            break;
    }
    
    return ['conditions' => $conditions, 'params' => $params];
}

/**
 * Build date conditions for salary table
 */
function buildSalaryDateConditions($dateRange, $fromDate = null, $toDate = null, $year = null, $month = null) {
    $conditions = [];
    $params = [];
    
    switch ($dateRange) {
        case 'today':
            $today = date('Y-m-d');
            $conditions[] = "DATE(salary_date) = :date";
            $params[':date'] = $today;
            break;
            
        case 'this_week':
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            $conditions[] = "DATE(salary_date) BETWEEN :from_date AND :to_date";
            $params[':from_date'] = $startOfWeek;
            $params[':to_date'] = $endOfWeek;
            break;
            
        case 'this_month':
            $startOfMonth = date('Y-m-01');
            $endOfMonth = date('Y-m-t');
            $conditions[] = "DATE(salary_date) BETWEEN :from_date AND :to_date";
            $params[':from_date'] = $startOfMonth;
            $params[':to_date'] = $endOfMonth;
            break;
            
        case 'this_year':
            $currentYear = date('Y');
            $conditions[] = "YEAR(salary_date) = :year";
            $params[':year'] = $year !== null ? $year : $currentYear;
            break;
            
        case 'custom':
            if ($fromDate && $toDate) {
                $conditions[] = "DATE(salary_date) BETWEEN :from_date AND :to_date";
                $params[':from_date'] = $fromDate;
                $params[':to_date'] = $toDate;
            }
            break;
            
        case 'all':
        default:
            if ($year !== null) {
                $conditions[] = "YEAR(salary_date) = :year";
                $params[':year'] = $year;
                
                if ($month !== null) {
                    $conditions[] = "MONTH(salary_date) = :month";
                    $params[':month'] = $month;
                }
            }
            break;
    }
    
    return ['conditions' => $conditions, 'params' => $params];
}

/**
 * Get Water Services Income Data (based on service_date)
 */
function getWaterServicesIncome($conn, $dateRange, $fromDate = null, $toDate = null, $year = null, $month = null, $customerId = null) {
    try {
        $dateInfo = buildWaterServiceDateConditions($dateRange, $fromDate, $toDate, $year, $month);
        
        $whereConditions = $dateInfo['conditions'];
        $params = $dateInfo['params'];
        
        if ($customerId !== null) {
            $whereConditions[] = "customer_id = :customer_id";
            $params[':customer_id'] = $customerId;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "
            SELECT 
                COUNT(*) as transaction_count,
                COUNT(DISTINCT customer_id) as unique_customer_count,
                COALESCE(SUM(amount), 0) as total_income,
                COALESCE(AVG(amount), 0) as average_amount,
                COALESCE(MIN(amount), 0) as min_amount,
                COALESCE(MAX(amount), 0) as max_amount
            FROM 
                water_services
            $whereClause
        ";
        
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        
        return [
            'transaction_count' => intval($result['transaction_count'] ?? 0),
            'unique_customers' => intval($result['unique_customer_count'] ?? 0),
            'total' => floatval($result['total_income'] ?? 0),
            'average' => floatval($result['average_amount'] ?? 0),
            'min' => floatval($result['min_amount'] ?? 0),
            'max' => floatval($result['max_amount'] ?? 0)
        ];
        
    } catch (PDOException $e) {
        error_log("Error in getWaterServicesIncome: " . $e->getMessage());
        return [
            'transaction_count' => 0,
            'unique_customers' => 0,
            'total' => 0,
            'average' => 0,
            'min' => 0,
            'max' => 0
        ];
    }
}

/**
 * Get Inverter Services Income Data (only paid)
 */
function getInverterServicesIncome($conn, $dateRange, $fromDate = null, $toDate = null, $year = null, $month = null, $customerId = null) {
    try {
        $dateInfo = buildInverterServiceDateConditions($dateRange, $fromDate, $toDate, $year, $month);
        
        $whereConditions = $dateInfo['conditions'];
        $whereConditions[] = "payment_status = 'paid'";
        $params = $dateInfo['params'];
        
        if ($customerId !== null) {
            $whereConditions[] = "customer_id = :customer_id";
            $params[':customer_id'] = $customerId;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        $query = "
            SELECT 
                COUNT(*) as transaction_count,
                COUNT(DISTINCT customer_id) as unique_customer_count,
                COALESCE(SUM(final_cost), 0) as total_income,
                COALESCE(AVG(final_cost), 0) as average_amount,
                COALESCE(MIN(final_cost), 0) as min_amount,
                COALESCE(MAX(final_cost), 0) as max_amount
            FROM 
                inverter_services
            $whereClause
        ";
        
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        
        return [
            'transaction_count' => intval($result['transaction_count'] ?? 0),
            'unique_customers' => intval($result['unique_customer_count'] ?? 0),
            'total' => floatval($result['total_income'] ?? 0),
            'average' => floatval($result['average_amount'] ?? 0),
            'min' => floatval($result['min_amount'] ?? 0),
            'max' => floatval($result['max_amount'] ?? 0)
        ];
        
    } catch (PDOException $e) {
        error_log("Error in getInverterServicesIncome: " . $e->getMessage());
        return [
            'transaction_count' => 0,
            'unique_customers' => 0,
            'total' => 0,
            'average' => 0,
            'min' => 0,
            'max' => 0
        ];
    }
}

/**
 * Get Water Services Expenses Summary
 */
function getWaterServicesExpenses($conn, $dateRange, $fromDate = null, $toDate = null, $year = null, $month = null) {
    try {
        $dateInfo = buildExpenseDateConditions($dateRange, $fromDate, $toDate, $year, $month);
        
        $whereConditions = $dateInfo['conditions'];
        $whereConditions[] = "LOWER(COALESCE(service_type, '')) IN ('water', 'water_service', 'water_services')";
        $params = $dateInfo['params'];
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "SELECT 
                    COALESCE(SUM(amount), 0) as total_expenses,
                    COUNT(*) as expense_count,
                    SUM(CASE WHEN expense_type = 'petrol' THEN amount ELSE 0 END) as petrol_expenses,
                    SUM(CASE WHEN expense_type = 'others' THEN amount ELSE 0 END) as other_expenses,
                    COUNT(DISTINCT staff_id) as unique_staff_with_expenses
                  FROM expenses
                  $whereClause";
        
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        
        return [
            'total' => floatval($result['total_expenses'] ?? 0),
            'count' => intval($result['expense_count'] ?? 0),
            'by_type' => [
                'petrol' => floatval($result['petrol_expenses'] ?? 0),
                'others' => floatval($result['other_expenses'] ?? 0)
            ],
            'unique_staff' => intval($result['unique_staff_with_expenses'] ?? 0)
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting water expenses: " . $e->getMessage());
        return [
            'total' => 0,
            'count' => 0,
            'by_type' => ['petrol' => 0, 'others' => 0],
            'unique_staff' => 0
        ];
    }
}

/**
 * Get Inverter Services Expenses Summary
 */
function getInverterServicesExpenses($conn, $dateRange, $fromDate = null, $toDate = null, $year = null, $month = null) {
    try {
        $dateInfo = buildExpenseDateConditions($dateRange, $fromDate, $toDate, $year, $month);
        
        $whereConditions = $dateInfo['conditions'];
        $whereConditions[] = "LOWER(COALESCE(service_type, '')) IN ('inverter', 'inverter_service', 'inverter_services')";
        $params = $dateInfo['params'];
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "SELECT 
                    COALESCE(SUM(amount), 0) as total_expenses,
                    COUNT(*) as expense_count,
                    SUM(CASE WHEN expense_type = 'petrol' THEN amount ELSE 0 END) as petrol_expenses,
                    SUM(CASE WHEN expense_type = 'others' THEN amount ELSE 0 END) as other_expenses,
                    COUNT(DISTINCT staff_id) as unique_staff_with_expenses
                  FROM expenses
                  $whereClause";
        
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        
        return [
            'total' => floatval($result['total_expenses'] ?? 0),
            'count' => intval($result['expense_count'] ?? 0),
            'by_type' => [
                'petrol' => floatval($result['petrol_expenses'] ?? 0),
                'others' => floatval($result['other_expenses'] ?? 0)
            ],
            'unique_staff' => intval($result['unique_staff_with_expenses'] ?? 0)
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting inverter expenses: " . $e->getMessage());
        return [
            'total' => 0,
            'count' => 0,
            'by_type' => ['petrol' => 0, 'others' => 0],
            'unique_staff' => 0
        ];
    }
}

/**
 * Get Water Services Salaries Summary
 */
function getWaterServicesSalaries($conn, $dateRange, $fromDate = null, $toDate = null, $year = null, $month = null) {
    try {
        $dateInfo = buildSalaryDateConditions($dateRange, $fromDate, $toDate, $year, $month);
        
        $whereConditions = $dateInfo['conditions'];
        $whereConditions[] = "LOWER(COALESCE(service_type, '')) IN ('water', 'water_service', 'water_services')";
        $params = $dateInfo['params'];
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "SELECT 
                    COALESCE(SUM(net_amount), 0) as total_salaries,
                    COALESCE(SUM(amount), 0) as total_base_salary,
                    COALESCE(SUM(bonus), 0) as total_bonus,
                    COALESCE(SUM(deductions), 0) as total_deductions,
                    COUNT(*) as salary_count,
                    COUNT(DISTINCT staff_id) as unique_staff_paid
                  FROM salary
                  $whereClause";
        
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        
        return [
            'total' => floatval($result['total_salaries'] ?? 0),
            'base_salary' => floatval($result['total_base_salary'] ?? 0),
            'bonus' => floatval($result['total_bonus'] ?? 0),
            'deductions' => floatval($result['total_deductions'] ?? 0),
            'count' => intval($result['salary_count'] ?? 0),
            'unique_staff' => intval($result['unique_staff_paid'] ?? 0)
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting water salaries: " . $e->getMessage());
        return [
            'total' => 0,
            'base_salary' => 0,
            'bonus' => 0,
            'deductions' => 0,
            'count' => 0,
            'unique_staff' => 0
        ];
    }
}

/**
 * Get Inverter Services Salaries Summary
 */
function getInverterServicesSalaries($conn, $dateRange, $fromDate = null, $toDate = null, $year = null, $month = null) {
    try {
        $dateInfo = buildSalaryDateConditions($dateRange, $fromDate, $toDate, $year, $month);
        
        $whereConditions = $dateInfo['conditions'];
        $whereConditions[] = "LOWER(COALESCE(service_type, '')) IN ('inverter', 'inverter_service', 'inverter_services')";
        $params = $dateInfo['params'];
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "SELECT 
                    COALESCE(SUM(net_amount), 0) as total_salaries,
                    COALESCE(SUM(amount), 0) as total_base_salary,
                    COALESCE(SUM(bonus), 0) as total_bonus,
                    COALESCE(SUM(deductions), 0) as total_deductions,
                    COUNT(*) as salary_count,
                    COUNT(DISTINCT staff_id) as unique_staff_paid
                  FROM salary
                  $whereClause";
        
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        
        return [
            'total' => floatval($result['total_salaries'] ?? 0),
            'base_salary' => floatval($result['total_base_salary'] ?? 0),
            'bonus' => floatval($result['total_bonus'] ?? 0),
            'deductions' => floatval($result['total_deductions'] ?? 0),
            'count' => intval($result['salary_count'] ?? 0),
            'unique_staff' => intval($result['unique_staff_paid'] ?? 0)
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting inverter salaries: " . $e->getMessage());
        return [
            'total' => 0,
            'base_salary' => 0,
            'bonus' => 0,
            'deductions' => 0,
            'count' => 0,
            'unique_staff' => 0
        ];
    }
}

/**
 * Get Overall Expenses Summary (all service types + legacy records)
 */
function getOverallExpenses($conn, $dateRange, $fromDate = null, $toDate = null, $year = null, $month = null) {
    try {
        $dateInfo = buildExpenseDateConditions($dateRange, $fromDate, $toDate, $year, $month);
        $whereConditions = $dateInfo['conditions'];
        $params = $dateInfo['params'];
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $query = "SELECT COALESCE(SUM(amount), 0) as total_expenses FROM expenses $whereClause";
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        return floatval($result['total_expenses'] ?? 0);
    } catch (PDOException $e) {
        error_log("Error getting overall expenses: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get Overall Salaries Summary (all service types + legacy records)
 */
function getOverallSalaries($conn, $dateRange, $fromDate = null, $toDate = null, $year = null, $month = null) {
    try {
        $dateInfo = buildSalaryDateConditions($dateRange, $fromDate, $toDate, $year, $month);
        $whereConditions = $dateInfo['conditions'];
        $params = $dateInfo['params'];
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $query = "SELECT COALESCE(SUM(net_amount), 0) as total_salaries FROM salary $whereClause";
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        return floatval($result['total_salaries'] ?? 0);
    } catch (PDOException $e) {
        error_log("Error getting overall salaries: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get Monthly Water Services Income Data (based on service_date)
 */
function getMonthlyWaterServicesIncome($conn, $dateRange, $fromDate = null, $toDate = null, $year = null, $month = null, $customerId = null) {
    try {
        $dateInfo = buildWaterServiceDateConditions($dateRange, $fromDate, $toDate, $year, $month);
        
        $whereConditions = $dateInfo['conditions'];
        $params = $dateInfo['params'];
        
        if ($customerId !== null) {
            $whereConditions[] = "customer_id = :customer_id";
            $params[':customer_id'] = $customerId;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "
            SELECT 
                YEAR(service_date) as year_num,
                MONTH(service_date) as month_num,
                DATE_FORMAT(service_date, '%Y-%m') as year_month,
                DATE_FORMAT(service_date, '%M %Y') as month_name_full,
                COUNT(*) as transaction_count,
                COUNT(DISTINCT customer_id) as unique_customer_count,
                COALESCE(SUM(amount), 0) as total_income,
                COALESCE(AVG(amount), 0) as avg_amount,
                COALESCE(MIN(amount), 0) as min_amount,
                COALESCE(MAX(amount), 0) as max_amount
            FROM 
                water_services
            $whereClause
            GROUP BY 
                YEAR(service_date), 
                MONTH(service_date)
            ORDER BY 
                year_num DESC, 
                month_num DESC
        ";
        
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error in getMonthlyWaterServicesIncome: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Monthly Inverter Services Income Data (only paid)
 */
function getMonthlyInverterServicesIncome($conn, $dateRange, $fromDate = null, $toDate = null, $year = null, $month = null, $customerId = null) {
    try {
        $dateInfo = buildInverterServiceDateConditions($dateRange, $fromDate, $toDate, $year, $month);
        
        $whereConditions = $dateInfo['conditions'];
        $whereConditions[] = "payment_status = 'paid'";
        $params = $dateInfo['params'];
        
        if ($customerId !== null) {
            $whereConditions[] = "customer_id = :customer_id";
            $params[':customer_id'] = $customerId;
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        $query = "
            SELECT 
                YEAR(created_at) as year_num,
                MONTH(created_at) as month_num,
                DATE_FORMAT(created_at, '%Y-%m') as year_month,
                DATE_FORMAT(created_at, '%M %Y') as month_name_full,
                COUNT(*) as transaction_count,
                COUNT(DISTINCT customer_id) as unique_customer_count,
                COALESCE(SUM(final_cost), 0) as total_income,
                COALESCE(AVG(final_cost), 0) as avg_amount,
                COALESCE(MIN(final_cost), 0) as min_amount,
                COALESCE(MAX(final_cost), 0) as max_amount
            FROM 
                inverter_services
            $whereClause
            GROUP BY 
                YEAR(created_at), 
                MONTH(created_at)
            ORDER BY 
                year_num DESC, 
                month_num DESC
        ";
        
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error in getMonthlyInverterServicesIncome: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Monthly Water Services Expenses Data
 */
function getMonthlyWaterServicesExpenses($conn, $dateRange, $fromDate = null, $toDate = null, $year = null, $month = null) {
    try {
        $dateInfo = buildExpenseDateConditions($dateRange, $fromDate, $toDate, $year, $month);
        
        $whereConditions = $dateInfo['conditions'];
        $whereConditions[] = "LOWER(COALESCE(service_type, '')) IN ('water', 'water_service', 'water_services')";
        $params = $dateInfo['params'];
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "
            SELECT 
                YEAR(expense_date) as year_num,
                MONTH(expense_date) as month_num,
                DATE_FORMAT(expense_date, '%Y-%m') as year_month,
                DATE_FORMAT(expense_date, '%M %Y') as month_name_full,
                COUNT(*) as expense_count,
                COALESCE(SUM(amount), 0) as total_expenses,
                SUM(CASE WHEN expense_type = 'petrol' THEN amount ELSE 0 END) as petrol_expenses,
                SUM(CASE WHEN expense_type = 'others' THEN amount ELSE 0 END) as other_expenses,
                COUNT(DISTINCT staff_id) as unique_staff
            FROM 
                expenses
            $whereClause
            GROUP BY 
                YEAR(expense_date), 
                MONTH(expense_date)
            ORDER BY 
                year_num DESC, 
                month_num DESC
        ";
        
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error in getMonthlyWaterServicesExpenses: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Monthly Inverter Services Expenses Data
 */
function getMonthlyInverterServicesExpenses($conn, $dateRange, $fromDate = null, $toDate = null, $year = null, $month = null) {
    try {
        $dateInfo = buildExpenseDateConditions($dateRange, $fromDate, $toDate, $year, $month);
        
        $whereConditions = $dateInfo['conditions'];
        $whereConditions[] = "LOWER(COALESCE(service_type, '')) IN ('inverter', 'inverter_service', 'inverter_services')";
        $params = $dateInfo['params'];
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "
            SELECT 
                YEAR(expense_date) as year_num,
                MONTH(expense_date) as month_num,
                DATE_FORMAT(expense_date, '%Y-%m') as year_month,
                DATE_FORMAT(expense_date, '%M %Y') as month_name_full,
                COUNT(*) as expense_count,
                COALESCE(SUM(amount), 0) as total_expenses,
                SUM(CASE WHEN expense_type = 'petrol' THEN amount ELSE 0 END) as petrol_expenses,
                SUM(CASE WHEN expense_type = 'others' THEN amount ELSE 0 END) as other_expenses,
                COUNT(DISTINCT staff_id) as unique_staff
            FROM 
                expenses
            $whereClause
            GROUP BY 
                YEAR(expense_date), 
                MONTH(expense_date)
            ORDER BY 
                year_num DESC, 
                month_num DESC
        ";
        
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error in getMonthlyInverterServicesExpenses: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Monthly Water Services Salaries Data
 */
function getMonthlyWaterServicesSalaries($conn, $dateRange, $fromDate = null, $toDate = null, $year = null, $month = null) {
    try {
        $dateInfo = buildSalaryDateConditions($dateRange, $fromDate, $toDate, $year, $month);
        
        $whereConditions = $dateInfo['conditions'];
        $whereConditions[] = "LOWER(COALESCE(service_type, '')) IN ('water', 'water_service', 'water_services')";
        $params = $dateInfo['params'];
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "
            SELECT 
                YEAR(salary_date) as year_num,
                MONTH(salary_date) as month_num,
                DATE_FORMAT(salary_date, '%Y-%m') as year_month,
                DATE_FORMAT(salary_date, '%M %Y') as month_name_full,
                COUNT(*) as salary_count,
                COALESCE(SUM(amount), 0) as total_base_salary,
                COALESCE(SUM(bonus), 0) as total_bonus,
                COALESCE(SUM(deductions), 0) as total_deductions,
                COALESCE(SUM(net_amount), 0) as total_salaries,
                COUNT(DISTINCT staff_id) as unique_staff
            FROM 
                salary
            $whereClause
            GROUP BY 
                YEAR(salary_date), 
                MONTH(salary_date)
            ORDER BY 
                year_num DESC, 
                month_num DESC
        ";
        
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error in getMonthlyWaterServicesSalaries: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Monthly Inverter Services Salaries Data
 */
function getMonthlyInverterServicesSalaries($conn, $dateRange, $fromDate = null, $toDate = null, $year = null, $month = null) {
    try {
        $dateInfo = buildSalaryDateConditions($dateRange, $fromDate, $toDate, $year, $month);
        
        $whereConditions = $dateInfo['conditions'];
        $whereConditions[] = "LOWER(COALESCE(service_type, '')) IN ('inverter', 'inverter_service', 'inverter_services')";
        $params = $dateInfo['params'];
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $query = "
            SELECT 
                YEAR(salary_date) as year_num,
                MONTH(salary_date) as month_num,
                DATE_FORMAT(salary_date, '%Y-%m') as year_month,
                DATE_FORMAT(salary_date, '%M %Y') as month_name_full,
                COUNT(*) as salary_count,
                COALESCE(SUM(amount), 0) as total_base_salary,
                COALESCE(SUM(bonus), 0) as total_bonus,
                COALESCE(SUM(deductions), 0) as total_deductions,
                COALESCE(SUM(net_amount), 0) as total_salaries,
                COUNT(DISTINCT staff_id) as unique_staff
            FROM 
                salary
            $whereClause
            GROUP BY 
                YEAR(salary_date), 
                MONTH(salary_date)
            ORDER BY 
                year_num DESC, 
                month_num DESC
        ";
        
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error in getMonthlyInverterServicesSalaries: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Date Range
 */
function getDateRange($conn, $dateRange, $fromDate = null, $toDate = null, $year = null, $month = null, $customerId = null) {
    try {
        $query = "
            SELECT 
                MIN(date_value) as first_date,
                MAX(date_value) as last_date
            FROM (
                SELECT service_date as date_value FROM water_services WHERE 1=1
                UNION ALL
                SELECT created_at as date_value FROM inverter_services WHERE payment_status = 'paid'
            ) as all_dates
            WHERE 1=1
        ";
        
        $params = [];
        
        switch ($dateRange) {
            case 'today':
                $today = date('Y-m-d');
                $query .= " AND DATE(date_value) = :date";
                $params[':date'] = $today;
                break;
                
            case 'this_week':
                $startOfWeek = date('Y-m-d', strtotime('monday this week'));
                $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
                $query .= " AND DATE(date_value) BETWEEN :from_date AND :to_date";
                $params[':from_date'] = $startOfWeek;
                $params[':to_date'] = $endOfWeek;
                break;
                
            case 'this_month':
                $startOfMonth = date('Y-m-01');
                $endOfMonth = date('Y-m-t');
                $query .= " AND DATE(date_value) BETWEEN :from_date AND :to_date";
                $params[':from_date'] = $startOfMonth;
                $params[':to_date'] = $endOfMonth;
                break;
                
            case 'this_year':
                $currentYear = date('Y');
                $query .= " AND YEAR(date_value) = :year";
                $params[':year'] = $year !== null ? $year : $currentYear;
                break;
                
            case 'custom':
                if ($fromDate && $toDate) {
                    $query .= " AND DATE(date_value) BETWEEN :from_date AND :to_date";
                    $params[':from_date'] = $fromDate;
                    $params[':to_date'] = $toDate;
                }
                break;
                
            case 'all':
            default:
                if ($year !== null) {
                    $query .= " AND YEAR(date_value) = :year";
                    $params[':year'] = $year;
                    
                    if ($month !== null) {
                        $query .= " AND MONTH(date_value) = :month";
                        $params[':month'] = $month;
                    }
                }
                break;
        }
        
        if ($customerId !== null) {
            $query .= " AND customer_id = :customer_id";
            $params[':customer_id'] = $customerId;
        }
        
        $stmt = $conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetch();
        
        return [
            'from' => $result['first_date'] ?? null,
            'to' => $result['last_date'] ?? null
        ];
        
    } catch (PDOException $e) {
        error_log("Error in getDateRange: " . $e->getMessage());
        return ['from' => null, 'to' => null];
    }
}

/**
 * Get Paid Transactions Count
 */
function getPaidTransactionsCount($conn, $dateRange, $fromDate = null, $toDate = null, $year = null, $month = null, $customerId = null) {
    try {
        $waterDateInfo = buildWaterServiceDateConditions($dateRange, $fromDate, $toDate, $year, $month);
        $inverterDateInfo = buildInverterServiceDateConditions($dateRange, $fromDate, $toDate, $year, $month);
        
        $waterWhereConditions = $waterDateInfo['conditions'];
        $inverterWhereConditions = $inverterDateInfo['conditions'];
        $params = array_merge($waterDateInfo['params'], $inverterDateInfo['params']);
        
        $waterQuery = "SELECT COUNT(*) as paid_count FROM water_services";
        $inverterQuery = "SELECT COUNT(*) as paid_count FROM inverter_services WHERE payment_status = 'paid'";
        
        if (!empty($waterWhereConditions)) {
            $waterQuery .= " WHERE " . implode(' AND ', $waterWhereConditions);
        }
        
        if (!empty($inverterWhereConditions)) {
            $inverterQuery .= " AND " . implode(' AND ', $inverterWhereConditions);
        }
        
        if ($customerId !== null) {
            $customerCondition = "customer_id = :customer_id";
            if (strpos($waterQuery, 'WHERE') !== false) {
                $waterQuery .= " AND " . $customerCondition;
            } else {
                $waterQuery .= " WHERE " . $customerCondition;
            }
            
            if (strpos($inverterQuery, 'WHERE') !== false) {
                $inverterQuery .= " AND " . $customerCondition;
            } else {
                $inverterQuery .= " WHERE " . $customerCondition;
            }
            $params[':customer_id'] = $customerId;
        }
        
        $waterStmt = $conn->prepare($waterQuery);
        foreach ($waterDateInfo['params'] as $key => $value) {
            $waterStmt->bindValue($key, $value);
        }
        if ($customerId !== null) {
            $waterStmt->bindValue(':customer_id', $customerId);
        }
        $waterStmt->execute();
        $waterResult = $waterStmt->fetch();
        
        $inverterStmt = $conn->prepare($inverterQuery);
        foreach ($inverterDateInfo['params'] as $key => $value) {
            $inverterStmt->bindValue($key, $value);
        }
        if ($customerId !== null) {
            $inverterStmt->bindValue(':customer_id', $customerId);
        }
        $inverterStmt->execute();
        $inverterResult = $inverterStmt->fetch();
        
        return intval($waterResult['paid_count'] ?? 0) + intval($inverterResult['paid_count'] ?? 0);
        
    } catch (PDOException $e) {
        error_log("Error getting paid count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get Top Customers (combined from both tables)
 */
function getTopCustomers($conn, $dateRange, $fromDate = null, $toDate = null, $year = null, $month = null, $customerId = null) {
    try {
        $waterDateInfo = buildWaterServiceDateConditions($dateRange, $fromDate, $toDate, $year, $month);
        $inverterDateInfo = buildInverterServiceDateConditions($dateRange, $fromDate, $toDate, $year, $month);
        
        $waterWhereConditions = $waterDateInfo['conditions'];
        $inverterWhereConditions = $inverterDateInfo['conditions'];
        $params = array_merge($waterDateInfo['params'], $inverterDateInfo['params']);
        
        $query = "
            SELECT 
                customer_id,
                MAX(customer_name) as full_name,
                MAX(phone) as phone,
                MAX(email) as email,
                MAX(city) as city,
                COUNT(*) as service_count,
                COALESCE(SUM(amount), 0) as total_spent,
                COALESCE(AVG(amount), 0) as average_amount,
                MAX(service_date) as last_service_date
            FROM (
                SELECT 
                    ws.customer_id,
                    c.full_name as customer_name,
                    c.phone,
                    c.email,
                    c.city,
                    ws.amount,
                    ws.service_date as service_date
                FROM water_services ws
                INNER JOIN customers c ON ws.customer_id = c.id
                WHERE ws.customer_id IS NOT NULL
        ";
        
        if (!empty($waterWhereConditions)) {
            $query .= " AND " . implode(' AND ', $waterWhereConditions);
        }
        
        $query .= "
                
                UNION ALL
                
                SELECT 
                    invs.customer_id,
                    c.full_name as customer_name,
                    c.phone,
                    c.email,
                    c.city,
                    invs.final_cost as amount,
                    invs.created_at as service_date
                FROM inverter_services invs
                INNER JOIN customers c ON invs.customer_id = c.id
                WHERE invs.customer_id IS NOT NULL AND invs.payment_status = 'paid'
        ";
        
        if (!empty($inverterWhereConditions)) {
            $query .= " AND " . implode(' AND ', $inverterWhereConditions);
        }
        
        $query .= "
            ) as combined
            WHERE 1=1
        ";
        
        if ($customerId !== null) {
            $query .= " AND customer_id = :customer_id";
            $params[':customer_id'] = $customerId;
        }
        
        $query .= "
            GROUP BY customer_id
            ORDER BY total_spent DESC
            LIMIT 10
        ";
        
        $stmt = $conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error in getTopCustomers: " . $e->getMessage());
        return [];
    }
}

/**
 * Combine Monthly Data from all sources with service type separation
 */
function combineMonthlyData($waterIncomeData, $inverterIncomeData, 
                           $waterExpensesData, $inverterExpensesData,
                           $waterSalariesData, $inverterSalariesData) {
    $months = [];
    
    // Create a map of all months from all data sources
    $allMonths = [];
    
    foreach ($waterIncomeData as $data) {
        $allMonths[$data['year_month']] = true;
    }
    foreach ($inverterIncomeData as $data) {
        $allMonths[$data['year_month']] = true;
    }
    foreach ($waterExpensesData as $data) {
        $allMonths[$data['year_month']] = true;
    }
    foreach ($inverterExpensesData as $data) {
        $allMonths[$data['year_month']] = true;
    }
    foreach ($waterSalariesData as $data) {
        $allMonths[$data['year_month']] = true;
    }
    foreach ($inverterSalariesData as $data) {
        $allMonths[$data['year_month']] = true;
    }
    
    // Initialize months array
    foreach (array_keys($allMonths) as $yearMonth) {
        $months[$yearMonth] = [
            'year' => 0,
            'month' => 0,
            'year_month' => $yearMonth,
            'month_name' => '',
            'water_services' => [
                'income' => [
                    'transaction_count' => 0,
                    'unique_customers' => 0,
                    'total' => 0,
                    'average' => 0,
                    'min' => 0,
                    'max' => 0
                ],
                'expenses' => [
                    'total' => 0,
                    'count' => 0,
                    'by_type' => [
                        'petrol' => 0,
                        'others' => 0
                    ],
                    'unique_staff' => 0
                ],
                'salaries' => [
                    'total' => 0,
                    'base_salary' => 0,
                    'bonus' => 0,
                    'deductions' => 0,
                    'count' => 0,
                    'unique_staff' => 0
                ],
                'total_costs' => 0,
                'profit' => [
                    'net' => 0,
                    'margin' => 0,
                    'status' => 'neutral'
                ]
            ],
            'inverter_services' => [
                'income' => [
                    'transaction_count' => 0,
                    'unique_customers' => 0,
                    'total' => 0,
                    'average' => 0,
                    'min' => 0,
                    'max' => 0
                ],
                'expenses' => [
                    'total' => 0,
                    'count' => 0,
                    'by_type' => [
                        'petrol' => 0,
                        'others' => 0
                    ],
                    'unique_staff' => 0
                ],
                'salaries' => [
                    'total' => 0,
                    'base_salary' => 0,
                    'bonus' => 0,
                    'deductions' => 0,
                    'count' => 0,
                    'unique_staff' => 0
                ],
                'total_costs' => 0,
                'profit' => [
                    'net' => 0,
                    'margin' => 0,
                    'status' => 'neutral'
                ]
            ],
            'combined' => [
                'total_income' => 0,
                'total_expenses' => 0,
                'total_salaries' => 0,
                'total_costs' => 0,
                'profit' => [
                    'net' => 0,
                    'margin' => 0,
                    'status' => 'neutral'
                ]
            ]
        ];
    }
    
    // Process water income data
    foreach ($waterIncomeData as $data) {
        $key = $data['year_month'];
        $months[$key]['month_name'] = $data['month_name_full'];
        $months[$key]['year'] = intval($data['year_num']);
        $months[$key]['month'] = intval($data['month_num']);
        $months[$key]['water_services']['income'] = [
            'transaction_count' => intval($data['transaction_count']),
            'unique_customers' => intval($data['unique_customer_count']),
            'total' => floatval($data['total_income']),
            'average' => floatval($data['avg_amount']),
            'min' => floatval($data['min_amount']),
            'max' => floatval($data['max_amount'])
        ];
    }
    
    // Process inverter income data
    foreach ($inverterIncomeData as $data) {
        $key = $data['year_month'];
        $months[$key]['month_name'] = $data['month_name_full'];
        $months[$key]['year'] = intval($data['year_num']);
        $months[$key]['month'] = intval($data['month_num']);
        $months[$key]['inverter_services']['income'] = [
            'transaction_count' => intval($data['transaction_count']),
            'unique_customers' => intval($data['unique_customer_count']),
            'total' => floatval($data['total_income']),
            'average' => floatval($data['avg_amount']),
            'min' => floatval($data['min_amount']),
            'max' => floatval($data['max_amount'])
        ];
    }
    
    // Process water expenses data
    foreach ($waterExpensesData as $data) {
        $key = $data['year_month'];
        $months[$key]['month_name'] = $data['month_name_full'];
        $months[$key]['year'] = intval($data['year_num']);
        $months[$key]['month'] = intval($data['month_num']);
        $months[$key]['water_services']['expenses'] = [
            'total' => floatval($data['total_expenses']),
            'count' => intval($data['expense_count']),
            'by_type' => [
                'petrol' => floatval($data['petrol_expenses']),
                'others' => floatval($data['other_expenses'])
            ],
            'unique_staff' => intval($data['unique_staff'])
        ];
    }
    
    // Process inverter expenses data
    foreach ($inverterExpensesData as $data) {
        $key = $data['year_month'];
        $months[$key]['month_name'] = $data['month_name_full'];
        $months[$key]['year'] = intval($data['year_num']);
        $months[$key]['month'] = intval($data['month_num']);
        $months[$key]['inverter_services']['expenses'] = [
            'total' => floatval($data['total_expenses']),
            'count' => intval($data['expense_count']),
            'by_type' => [
                'petrol' => floatval($data['petrol_expenses']),
                'others' => floatval($data['other_expenses'])
            ],
            'unique_staff' => intval($data['unique_staff'])
        ];
    }
    
    // Process water salaries data
    foreach ($waterSalariesData as $data) {
        $key = $data['year_month'];
        $months[$key]['month_name'] = $data['month_name_full'];
        $months[$key]['year'] = intval($data['year_num']);
        $months[$key]['month'] = intval($data['month_num']);
        $months[$key]['water_services']['salaries'] = [
            'total' => floatval($data['total_salaries']),
            'base_salary' => floatval($data['total_base_salary']),
            'bonus' => floatval($data['total_bonus']),
            'deductions' => floatval($data['total_deductions']),
            'count' => intval($data['salary_count']),
            'unique_staff' => intval($data['unique_staff'])
        ];
    }
    
    // Process inverter salaries data
    foreach ($inverterSalariesData as $data) {
        $key = $data['year_month'];
        $months[$key]['month_name'] = $data['month_name_full'];
        $months[$key]['year'] = intval($data['year_num']);
        $months[$key]['month'] = intval($data['month_num']);
        $months[$key]['inverter_services']['salaries'] = [
            'total' => floatval($data['total_salaries']),
            'base_salary' => floatval($data['total_base_salary']),
            'bonus' => floatval($data['total_bonus']),
            'deductions' => floatval($data['total_deductions']),
            'count' => intval($data['salary_count']),
            'unique_staff' => intval($data['unique_staff'])
        ];
    }
    
    // Calculate totals and profit for each month and service type
    foreach ($months as $key => $month) {
        // Water services calculations
        $waterIncome = $month['water_services']['income']['total'];
        $waterExpenses = $month['water_services']['expenses']['total'];
        $waterSalaries = $month['water_services']['salaries']['total'];
        $waterTotalCosts = $waterExpenses + $waterSalaries;
        $waterNetProfit = $waterIncome - $waterTotalCosts;
        
        $months[$key]['water_services']['total_costs'] = $waterTotalCosts;
        $months[$key]['water_services']['profit']['net'] = $waterNetProfit;
        $months[$key]['water_services']['profit']['margin'] = $waterIncome > 0 ? ($waterNetProfit / $waterIncome) * 100 : 0;
        $months[$key]['water_services']['profit']['status'] = $waterNetProfit > 0 ? 'profit' : ($waterNetProfit < 0 ? 'loss' : 'break_even');
        
        // Inverter services calculations
        $inverterIncome = $month['inverter_services']['income']['total'];
        $inverterExpenses = $month['inverter_services']['expenses']['total'];
        $inverterSalaries = $month['inverter_services']['salaries']['total'];
        $inverterTotalCosts = $inverterExpenses + $inverterSalaries;
        $inverterNetProfit = $inverterIncome - $inverterTotalCosts;
        
        $months[$key]['inverter_services']['total_costs'] = $inverterTotalCosts;
        $months[$key]['inverter_services']['profit']['net'] = $inverterNetProfit;
        $months[$key]['inverter_services']['profit']['margin'] = $inverterIncome > 0 ? ($inverterNetProfit / $inverterIncome) * 100 : 0;
        $months[$key]['inverter_services']['profit']['status'] = $inverterNetProfit > 0 ? 'profit' : ($inverterNetProfit < 0 ? 'loss' : 'break_even');
        
        // Combined calculations
        $totalIncome = $waterIncome + $inverterIncome;
        $totalExpenses = $waterExpenses + $inverterExpenses;
        $totalSalaries = $waterSalaries + $inverterSalaries;
        $totalCosts = $totalExpenses + $totalSalaries;
        $netProfit = $totalIncome - $totalCosts;
        
        $months[$key]['combined']['total_income'] = $totalIncome;
        $months[$key]['combined']['total_expenses'] = $totalExpenses;
        $months[$key]['combined']['total_salaries'] = $totalSalaries;
        $months[$key]['combined']['total_costs'] = $totalCosts;
        $months[$key]['combined']['profit']['net'] = $netProfit;
        $months[$key]['combined']['profit']['margin'] = $totalIncome > 0 ? ($netProfit / $totalIncome) * 100 : 0;
        $months[$key]['combined']['profit']['status'] = $netProfit > 0 ? 'profit' : ($netProfit < 0 ? 'loss' : 'break_even');
    }
    
    // Sort by year and month descending
    usort($months, function($a, $b) {
        if ($a['year'] != $b['year']) {
            return $b['year'] - $a['year'];
        }
        return $b['month'] - $a['month'];
    });
    
    return $months;
}

// Get data with service type separation using date range filters
$waterIncome = getWaterServicesIncome($conn, $dateRange, $fromDate, $toDate, $year, $month, $customerId);
$inverterIncome = getInverterServicesIncome($conn, $dateRange, $fromDate, $toDate, $year, $month, $customerId);

// Get expenses by service type
$waterExpenses = getWaterServicesExpenses($conn, $dateRange, $fromDate, $toDate, $year, $month);
$inverterExpenses = getInverterServicesExpenses($conn, $dateRange, $fromDate, $toDate, $year, $month);

// Get salaries by service type
$waterSalaries = getWaterServicesSalaries($conn, $dateRange, $fromDate, $toDate, $year, $month);
$inverterSalaries = getInverterServicesSalaries($conn, $dateRange, $fromDate, $toDate, $year, $month);

// Get monthly data with service type separation
$monthlyWaterIncome = getMonthlyWaterServicesIncome($conn, $dateRange, $fromDate, $toDate, $year, $month, $customerId);
$monthlyInverterIncome = getMonthlyInverterServicesIncome($conn, $dateRange, $fromDate, $toDate, $year, $month, $customerId);
$monthlyWaterExpenses = getMonthlyWaterServicesExpenses($conn, $dateRange, $fromDate, $toDate, $year, $month);
$monthlyInverterExpenses = getMonthlyInverterServicesExpenses($conn, $dateRange, $fromDate, $toDate, $year, $month);
$monthlyWaterSalaries = getMonthlyWaterServicesSalaries($conn, $dateRange, $fromDate, $toDate, $year, $month);
$monthlyInverterSalaries = getMonthlyInverterServicesSalaries($conn, $dateRange, $fromDate, $toDate, $year, $month);

$dateRangeResult = getDateRange($conn, $dateRange, $fromDate, $toDate, $year, $month, $customerId);
$paidCount = getPaidTransactionsCount($conn, $dateRange, $fromDate, $toDate, $year, $month, $customerId);
$topCustomers = getTopCustomers($conn, $dateRange, $fromDate, $toDate, $year, $month, $customerId);

// Calculate totals with service type separation
$totalWaterIncome = $waterIncome['total'];
$totalInverterIncome = $inverterIncome['total'];
$totalIncome = $totalWaterIncome + $totalInverterIncome;

$totalWaterExpenses = $waterExpenses['total'];
$totalInverterExpenses = $inverterExpenses['total'];
$totalExpenses = getOverallExpenses($conn, $dateRange, $fromDate, $toDate, $year, $month);

$totalWaterSalaries = $waterSalaries['total'];
$totalInverterSalaries = $inverterSalaries['total'];
$totalSalaries = getOverallSalaries($conn, $dateRange, $fromDate, $toDate, $year, $month);

$totalWaterCosts = $totalWaterExpenses + $totalWaterSalaries;
$totalInverterCosts = $totalInverterExpenses + $totalInverterSalaries;
$totalCosts = $totalExpenses + $totalSalaries;

$netProfit = $totalIncome - $totalCosts;
$profitMargin = $totalIncome > 0 ? ($netProfit / $totalIncome) * 100 : 0;

// Combine monthly data
$months = combineMonthlyData(
    $monthlyWaterIncome, 
    $monthlyInverterIncome,
    $monthlyWaterExpenses, 
    $monthlyInverterExpenses,
    $monthlyWaterSalaries, 
    $monthlyInverterSalaries
);

// Format period name
$periodName = $dateRange;
switch ($dateRange) {
    case 'today':
        $periodName = 'Today (' . date('d M Y') . ')';
        break;
    case 'this_week':
        $startOfWeek = date('d M', strtotime('monday this week'));
        $endOfWeek = date('d M Y', strtotime('sunday this week'));
        $periodName = 'Week of ' . $startOfWeek . ' - ' . $endOfWeek;
        break;
    case 'this_month':
        $periodName = date('F Y');
        break;
    case 'this_year':
        $periodName = 'Year ' . ($year !== null ? $year : date('Y'));
        break;
    case 'custom':
        $periodName = date('d M Y', strtotime($fromDate)) . ' to ' . date('d M Y', strtotime($toDate));
        break;
    case 'all':
        if ($year !== null) {
            $periodName = 'Year ' . $year;
            if ($month !== null) {
                $timestamp = mktime(0, 0, 0, $month, 1, $year);
                $periodName = date('F Y', $timestamp);
            }
        } else {
            $periodName = 'All Dates';
        }
        break;
}

// Prepare response with separate income and expenses sections for each service type
$response = [
    'success' => true,
    'filters' => [
        'year' => $year,
        'month' => $month,
        'customer_id' => $customerId,
        'date_range' => $dateRange,
        'custom_from' => $fromDate,
        'custom_to' => $toDate
    ],
    'summary' => [
        'period' => $periodName,
        'date_range' => [
            'from' => $dateRangeResult['from'],
            'to' => $dateRangeResult['to']
        ],
        'overall' => [
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'total_salaries' => $totalSalaries,
            'total_costs' => $totalCosts,
            'net_profit' => $netProfit,
            'profit_margin' => $profitMargin,
            'profit_status' => $netProfit > 0 ? 'profit' : ($netProfit < 0 ? 'loss' : 'break_even'),
            'is_profitable' => $netProfit >= 0,
            'total_transactions' => $waterIncome['transaction_count'] + $inverterIncome['transaction_count'],
            'paid_transactions' => $paidCount,
            'unique_customers' => count($topCustomers)
        ],
        'water_services' => [
            'income' => $waterIncome,
            'expenses' => $waterExpenses,
            'salaries' => $waterSalaries,
            'total_costs' => $totalWaterCosts,
            'net_profit' => $totalWaterIncome - $totalWaterCosts,
            'profit_margin' => $totalWaterIncome > 0 ? (($totalWaterIncome - $totalWaterCosts) / $totalWaterIncome) * 100 : 0,
            'profit_status' => ($totalWaterIncome - $totalWaterCosts) > 0 ? 'profit' : (($totalWaterIncome - $totalWaterCosts) < 0 ? 'loss' : 'break_even')
        ],
        'inverter_services' => [
            'income' => $inverterIncome,
            'expenses' => $inverterExpenses,
            'salaries' => $inverterSalaries,
            'total_costs' => $totalInverterCosts,
            'net_profit' => $totalInverterIncome - $totalInverterCosts,
            'profit_margin' => $totalInverterIncome > 0 ? (($totalInverterIncome - $totalInverterCosts) / $totalInverterIncome) * 100 : 0,
            'profit_status' => ($totalInverterIncome - $totalInverterCosts) > 0 ? 'profit' : (($totalInverterIncome - $totalInverterCosts) < 0 ? 'loss' : 'break_even')
        ]
    ],
    'monthly_data' => $months,
    'top_customers' => array_map(function($customer) {
        return [
            'customer_id' => intval($customer['customer_id']),
            'full_name' => $customer['full_name'] ?? '',
            'phone' => $customer['phone'] ?? '',
            'email' => $customer['email'] ?? '',
            'city' => $customer['city'] ?? '',
            'transaction_count' => intval($customer['service_count'] ?? 0),
            'total_spent' => floatval($customer['total_spent'] ?? 0),
            'average_transaction' => floatval($customer['average_amount'] ?? 0),
            'last_service_date' => $customer['last_service_date'] ?? null
        ];
    }, $topCustomers),
    'profit_analysis' => [
        'revenue_vs_costs' => [
            'income_percentage' => $totalIncome > 0 ? 100 : 0,
            'expenses_percentage' => $totalIncome > 0 ? ($totalExpenses / $totalIncome) * 100 : 0,
            'salaries_percentage' => $totalIncome > 0 ? ($totalSalaries / $totalIncome) * 100 : 0,
            'profit_percentage' => $profitMargin
        ],
        'break_even_point' => [
            'needed_income' => $totalCosts,
            'current_income' => $totalIncome,
            'gap' => $totalCosts - $totalIncome,
            'is_profitable' => $totalIncome >= $totalCosts
        ],
        'service_type_breakdown' => [
            'water_services' => [
                'income_percentage' => $totalIncome > 0 ? ($totalWaterIncome / $totalIncome) * 100 : 0,
                'costs_percentage' => $totalCosts > 0 ? ($totalWaterCosts / $totalCosts) * 100 : 0,
                'profit_contribution' => ($totalWaterIncome - $totalWaterCosts)
            ],
            'inverter_services' => [
                'income_percentage' => $totalIncome > 0 ? ($totalInverterIncome / $totalIncome) * 100 : 0,
                'costs_percentage' => $totalCosts > 0 ? ($totalInverterCosts / $totalCosts) * 100 : 0,
                'profit_contribution' => ($totalInverterIncome - $totalInverterCosts)
            ]
        ]
    ],
    'message' => count($months) > 0 
        ? 'Revenue and profit data retrieved successfully' 
        : 'No data found for the specified period'
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
