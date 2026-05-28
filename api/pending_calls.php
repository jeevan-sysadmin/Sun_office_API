<?php
/**
 * Pending Calls API - Get customers by city with active services and no water service this month
 * 
 * Endpoint: http://localhost/sun_office/api/pending_calls.php?city=Delhi
 * Method: GET
 * 
 * Steps:
 * 1. Get customers from specified city
 * 2. Filter customers with active service orders (amc_status = 'active')
 * 3. Check if they have water_services entry in last 30 days
 * 4. If no water_service in last 30 days, include them in results
 * 5. Sort by customers with most recent active services first
 */

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

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

// Include database configuration
require_once __DIR__ . '/config/database.php';

try {
    // Get database connection
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get city parameter
    $city = isset($_GET['city']) ? trim($_GET['city']) : '';
    
    if (empty($city)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'City parameter is required',
            'example' => 'pending_calls.php?city=Delhi'
        ]);
        exit();
    }
    
    // Rolling 30-day pending window
    $windowStartDate = date('Y-m-d', strtotime('-30 days'));
    $todayDate = date('Y-m-d');
    $currentMonthName = date('F Y');
    
    // Main query to get customers with PENDING PRODUCTS only:
    // - AMC active
    // - expand each service into battery products
    // - include product only if no water service entry in LAST 30 DAYS for that exact service_id + battery_id
    $sql = "
        SELECT 
            c.id,
            c.customer_code,
            c.full_name,
            c.email,
            c.phone,
            c.address,
            c.city,
            c.state,
            c.zip_code,
            c.notes,
            c.created_at,
            c.updated_at,
            -- Count of pending product rows
            COUNT(*) as active_services_count,
            -- Most recent pending product service date
            MAX(sp.created_at) as most_recent_service_date,
            -- Group all active services for display
            GROUP_CONCAT(DISTINCT CONCAT(
                sp.service_code, '|',
                COALESCE(
                    NULLIF(TRIM(
                        CONCAT_WS(' ',
                            COALESCE(b.battery_model, 'Unknown Battery'),
                            CONCAT('(', COALESCE(b.battery_serial, 'N/A'), ')')
                        )
                    ), ''),
                    'Unknown Product'
                ), '|',
                DATE_FORMAT(sp.created_at, '%Y-%m-%d')
            ) ORDER BY sp.created_at DESC SEPARATOR '||') as active_services_raw
        FROM 
            customers c
        INNER JOIN 
            (
                -- Products from service_order_batteries
                SELECT DISTINCT
                    s.id AS service_id,
                    s.customer_id,
                    s.service_code,
                    s.created_at,
                    sob.battery_id
                FROM service_orders s
                INNER JOIN service_order_batteries sob ON sob.service_order_id = s.id
                WHERE s.amc_status = 'active'

                UNION

                -- Fallback for older rows with single battery_id and no junction rows
                SELECT DISTINCT
                    s.id AS service_id,
                    s.customer_id,
                    s.service_code,
                    s.created_at,
                    s.battery_id
                FROM service_orders s
                WHERE s.amc_status = 'active'
                  AND s.battery_id IS NOT NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM service_order_batteries sob2 WHERE sob2.service_order_id = s.id
                  )
            ) sp ON c.id = sp.customer_id
        LEFT JOIN 
            batteries b ON b.id = sp.battery_id
        LEFT JOIN 
            water_services w ON c.id = w.customer_id
            AND w.service_id = sp.service_id
            AND (
                (w.battery_id IS NOT NULL AND sp.battery_id IS NOT NULL AND w.battery_id = sp.battery_id)
                OR (w.battery_id IS NULL AND sp.battery_id IS NULL)
            )
            AND w.service_date BETWEEN :window_start_date AND :today_date
        WHERE 
            LOWER(TRIM(c.city)) = LOWER(TRIM(:city))
            AND w.id IS NULL
        GROUP BY 
            c.id,
            c.customer_code,
            c.full_name,
            c.email,
            c.phone,
            c.address,
            c.city,
            c.state,
            c.zip_code,
            c.notes,
            c.created_at,
            c.updated_at
        ORDER BY 
            most_recent_service_date DESC,
            c.full_name ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':city', $city, PDO::PARAM_STR);
    $stmt->bindParam(':window_start_date', $windowStartDate, PDO::PARAM_STR);
    $stmt->bindParam(':today_date', $todayDate, PDO::PARAM_STR);
    $stmt->execute();
    
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response data
    $pendingCalls = [];
    
    foreach ($customers as $customer) {
        // Parse active services
        $activeServices = [];
        if (!empty($customer['active_services_raw'])) {
            $servicesArray = explode('||', $customer['active_services_raw']);
            foreach ($servicesArray as $service) {
                $parts = explode('|', $service);
                if (count($parts) >= 3) {
                    $activeServices[] = [
                        'service_code' => $parts[0],
                        'battery_model' => $parts[1],
                        'created_date' => $parts[2]
                    ];
                }
            }
        }
        
        // Get last water service date for this customer
        $lastWaterService = getLastWaterService($db, $customer['id']);
        
        // Calculate days since last water service
        $daysSinceLastService = null;
        if ($lastWaterService) {
            $lastDate = new DateTime($lastWaterService['service_date']);
            $today = new DateTime();
            $interval = $lastDate->diff($today);
            $daysSinceLastService = $interval->days;
        }
        
        // Build customer data
        $pendingCalls[] = [
            'id' => (int)$customer['id'],
            'customer_code' => $customer['customer_code'],
            'full_name' => $customer['full_name'],
            'email' => $customer['email'],
            'phone' => $customer['phone'],
            'address' => $customer['address'],
            'city' => $customer['city'],
            'state' => $customer['state'],
            'zip_code' => $customer['zip_code'],
            'notes' => $customer['notes'],
            'created_at' => $customer['created_at'],
            'updated_at' => $customer['updated_at'],
            'active_services' => [
                'count' => (int)$customer['active_services_count'],
                'list' => $activeServices
            ],
            'water_service_status' => [
                'has_service_this_month' => false,
                'current_month' => $currentMonthName,
                'last_service' => $lastWaterService,
                'days_since_last_service' => $daysSinceLastService,
                'pending_status' => $daysSinceLastService !== null
                    ? ($daysSinceLastService >= 30
                        ? "Due now ({$daysSinceLastService} days since last service)"
                        : "Not due yet ({$daysSinceLastService} days since last service)")
                    : "Never had water service"
            ],
            'priority' => calculatePriority(
                (int)$customer['active_services_count'],
                $daysSinceLastService,
                $activeServices
            )
        ];
    }
    
    // Sort by priority (High -> Medium -> Low)
    usort($pendingCalls, function($a, $b) {
        $priorityOrder = ['High' => 3, 'Medium' => 2, 'Low' => 1];
        return $priorityOrder[$b['priority']] - $priorityOrder[$a['priority']];
    });
    
    // Prepare response
    $response = [
        'success' => true,
        'city' => $city,
        'current_month' => $currentMonthName,
        'total_pending_calls' => count($pendingCalls),
        'pending_calls' => $pendingCalls,
            'message' => count($pendingCalls) > 0
            ? 'Pending product calls (30-day due) retrieved successfully'
            : 'No pending product calls (30-day due) found for this city'
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred',
        'error' => $e->getMessage()
    ]);
}

/**
 * Get last water service for a customer
 */
function getLastWaterService($db, $customerId) {
    try {
        $sql = "
            SELECT 
                w.id,
                w.service_id,
                w.amount,
                w.service_date,
                w.notes,
                s.service_code
            FROM 
                water_services w
            LEFT JOIN 
                service_orders s ON w.service_id = s.id
            WHERE 
                w.customer_id = :customer_id
            ORDER BY 
                w.service_date DESC,
                w.created_at DESC
            LIMIT 1
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return [
                'id' => (int)$result['id'],
                'service_id' => (int)$result['service_id'],
                'service_code' => $result['service_code'],
                'amount' => (float)$result['amount'],
                'service_date' => $result['service_date'],
                'notes' => $result['notes']
            ];
        }
        
        return null;
        
    } catch (PDOException $e) {
        error_log("Error in getLastWaterService: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate priority based on active services count and days since last service
 */
function calculatePriority($activeServicesCount, $daysSinceLastService, $activeServices) {
    $priority = 'Low';
    
    // Check for high priority conditions
    if ($activeServicesCount >= 3) {
        $priority = 'High';
    } elseif ($daysSinceLastService !== null && $daysSinceLastService > 45) {
        $priority = 'High';
    } elseif ($activeServicesCount >= 2 && $daysSinceLastService !== null && $daysSinceLastService > 30) {
        $priority = 'High';
    }
    // Check for medium priority
    elseif ($activeServicesCount >= 2) {
        $priority = 'Medium';
    } elseif ($daysSinceLastService !== null && $daysSinceLastService > 30) {
        $priority = 'Medium';
    } elseif ($activeServicesCount == 1 && $daysSinceLastService !== null && $daysSinceLastService > 20) {
        $priority = 'Medium';
    }
    
    return $priority;
}

/**
 * Alternative simplified version using direct query without extra function calls
 * Uncomment and use this if you want better performance
 */
/*
function getPendingCallsSimple($db, $city) {
    $currentMonth = date('m');
    $currentYear = date('Y');
    
    $sql = "
        SELECT 
            c.*,
            COUNT(DISTINCT s.id) as active_services_count,
            MAX(s.created_at) as last_service_date,
            (
                SELECT MAX(service_date) 
                FROM water_services 
                WHERE customer_id = c.id
            ) as last_water_service_date
        FROM 
            customers c
        INNER JOIN 
            service_orders s ON c.id = s.customer_id AND s.amc_status = 'active'
        LEFT JOIN 
            water_services w ON c.id = w.customer_id 
            AND MONTH(w.service_date) = :month 
            AND YEAR(w.service_date) = :year
        WHERE 
            LOWER(c.city) = LOWER(:city)
            AND w.id IS NULL
        GROUP BY 
            c.id
        ORDER BY 
            last_service_date DESC,
            c.full_name ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':city', $city);
    $stmt->bindParam(':month', $currentMonth);
    $stmt->bindParam(':year', $currentYear);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
*/

// Close database connection
$db = null;
?>
