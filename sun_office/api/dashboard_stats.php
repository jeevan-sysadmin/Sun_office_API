<?php
/**
 * Dashboard Statistics API
 * Provides various statistics and data for the dashboard
 * 
 * API Endpoints:
 * - GET dashboard_stats.php?type=summary - Get overall summary statistics
 * - GET dashboard_stats.php?type=recent_activities - Get recent activities
 * - GET dashboard_stats.php?type=chart_data - Get chart data for visualizations
 * - GET dashboard_stats.php?type=staff_performance - Get staff performance metrics
 * - GET dashboard_stats.php?type=expense_analysis - Get expense analysis
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'config/database.php';
require_once 'middleware/Auth.php';

// Initialize response array
$response = [
    'success' => false,
    'data' => null,
    'message' => '',
    'timestamp' => date('Y-m-d H:i:s')
];

try {
    // Get database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Get request type
    $type = isset($_GET['type']) ? $_GET['type'] : 'summary';
    
    // Handle different request types
    switch ($type) {
        case 'summary':
            $response = getSummaryStats($db);
            break;
            
        case 'recent_activities':
            $response = getRecentActivities($db);
            break;
            
        case 'chart_data':
            $response = getChartData($db);
            break;
            
        case 'staff_performance':
            $response = getStaffPerformance($db);
            break;
            
        case 'expense_analysis':
            $response = getExpenseAnalysis($db);
            break;
            
        case 'battery_status':
            $response = getBatteryStatus($db);
            break;
            
        case 'service_trends':
            $response = getServiceTrends($db);
            break;
            
        default:
            $response['message'] = 'Invalid request type';
            http_response_code(400);
    }
    
} catch (Exception $e) {
    $response['message'] = 'Server error: ' . $e->getMessage();
    http_response_code(500);
}

echo json_encode($response, JSON_PRETTY_PRINT);

/**
 * Get overall summary statistics
 */
function getSummaryStats($db) {
    $stats = [];
    
    // Get total customers
    $query = "SELECT COUNT(*) as total FROM customers WHERE 1";
    $stmt = $db->query($query);
    $stats['total_customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total batteries
    $query = "SELECT COUNT(*) as total FROM batteries WHERE 1";
    $stmt = $db->query($query);
    $stats['total_batteries'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get active batteries
    $query = "SELECT COUNT(*) as total FROM batteries WHERE status = 'active'";
    $stmt = $db->query($query);
    $stats['active_batteries'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total service orders
    $query = "SELECT COUNT(*) as total FROM service_orders WHERE 1";
    $stmt = $db->query($query);
    $stats['total_services'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get pending services (services created in last 7 days without water service)
    $query = "SELECT COUNT(DISTINCT so.id) as total 
              FROM service_orders so
              LEFT JOIN water_services ws ON so.id = ws.service_id
              WHERE so.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              AND ws.id IS NULL";
    $stmt = $db->query($query);
    $stats['pending_services'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total users (staff)
    $query = "SELECT COUNT(*) as total FROM users WHERE is_active = 1";
    $stmt = $db->query($query);
    $stats['total_staff'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total water services amount (current month)
    $query = "SELECT COALESCE(SUM(amount), 0) as total 
              FROM water_services 
              WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
              AND YEAR(created_at) = YEAR(CURRENT_DATE())";
    $stmt = $db->query($query);
    $stats['monthly_revenue'] = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total expenses (current month)
    $query = "SELECT COALESCE(SUM(amount), 0) as total 
              FROM expenses 
              WHERE MONTH(expense_date) = MONTH(CURRENT_DATE())
              AND YEAR(expense_date) = YEAR(CURRENT_DATE())";
    $stmt = $db->query($query);
    $stats['monthly_expenses'] = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get total salary paid (current month)
    $query = "SELECT COALESCE(SUM(net_amount), 0) as total 
              FROM salary 
              WHERE salary_month = DATE_FORMAT(CURRENT_DATE(), '%Y-%m')";
    $stmt = $db->query($query);
    $stats['monthly_salary'] = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calculate net profit
    $stats['monthly_profit'] = $stats['monthly_revenue'] - $stats['monthly_expenses'] - $stats['monthly_salary'];
    
    // Get battery condition distribution
    $query = "SELECT 
                battery_condition,
                COUNT(*) as count
              FROM batteries
              WHERE battery_condition IS NOT NULL
              GROUP BY battery_condition";
    $stmt = $db->query($query);
    $stats['battery_conditions'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['battery_conditions'][$row['battery_condition']] = (int)$row['count'];
    }
    
    // Get warranty status distribution
    $query = "SELECT 
                warranty_status,
                COUNT(*) as count
              FROM service_orders
              WHERE warranty_status IS NOT NULL
              GROUP BY warranty_status";
    $stmt = $db->query($query);
    $stats['warranty_status'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['warranty_status'][$row['warranty_status']] = (int)$row['count'];
    }
    
    return [
        'success' => true,
        'data' => $stats,
        'message' => 'Summary statistics retrieved successfully'
    ];
}

/**
 * Get recent activities
 */
function getRecentActivities($db) {
    $activities = [];
    
    // Get recent service orders
    $query = "SELECT 
                so.id,
                so.service_code,
                so.created_at,
                c.full_name as customer_name,
                b.battery_model,
                'service' as activity_type,
                CONCAT('New service order created for ', c.full_name) as description
              FROM service_orders so
              JOIN customers c ON so.customer_id = c.id
              JOIN batteries b ON so.battery_id = b.id
              ORDER BY so.created_at DESC
              LIMIT 10";
    
    $stmt = $db->query($query);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['created_at'] = date('Y-m-d H:i:s', strtotime($row['created_at']));
        $activities[] = $row;
    }
    
    // Get recent water services (payments)
    $query = "SELECT 
                ws.id,
                so.service_code,
                ws.created_at,
                c.full_name as customer_name,
                'payment' as activity_type,
                CONCAT('Payment of ₹', ws.amount, ' received for service ', so.service_code) as description,
                ws.amount
              FROM water_services ws
              JOIN service_orders so ON ws.service_id = so.id
              JOIN customers c ON ws.customer_id = c.id
              ORDER BY ws.created_at DESC
              LIMIT 10";
    
    $stmt = $db->query($query);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['created_at'] = date('Y-m-d H:i:s', strtotime($row['created_at']));
        $activities[] = $row;
    }
    
    // Get recent expenses
    $query = "SELECT 
                e.id,
                e.expense_type,
                e.created_at,
                u.name as staff_name,
                'expense' as activity_type,
                CONCAT('Expense of ₹', e.amount, ' for ', e.description) as description,
                e.amount
              FROM expenses e
              LEFT JOIN users u ON e.staff_id = u.id
              ORDER BY e.created_at DESC
              LIMIT 10";
    
    $stmt = $db->query($query);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['created_at'] = date('Y-m-d H:i:s', strtotime($row['created_at']));
        $activities[] = $row;
    }
    
    // Sort all activities by created_at descending
    usort($activities, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Return top 20 activities
    $activities = array_slice($activities, 0, 20);
    
    return [
        'success' => true,
        'data' => $activities,
        'message' => 'Recent activities retrieved successfully'
    ];
}

/**
 * Get chart data for visualizations
 */
function getChartData($db) {
    $chartData = [];
    
    // Monthly service trends (last 6 months)
    $query = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as service_count,
                COUNT(DISTINCT customer_id) as unique_customers
              FROM service_orders
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
              GROUP BY DATE_FORMAT(created_at, '%Y-%m')
              ORDER BY month ASC";
    
    $stmt = $db->query($query);
    $chartData['monthly_services'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $chartData['monthly_services'][] = [
            'month' => $row['month'],
            'services' => (int)$row['service_count'],
            'customers' => (int)$row['unique_customers']
        ];
    }
    
    // Monthly revenue trends (last 6 months)
    $query = "SELECT 
                DATE_FORMAT(ws.created_at, '%Y-%m') as month,
                COALESCE(SUM(ws.amount), 0) as total_revenue,
                COUNT(DISTINCT ws.customer_id) as paying_customers
              FROM water_services ws
              WHERE ws.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
              GROUP BY DATE_FORMAT(ws.created_at, '%Y-%m')
              ORDER BY month ASC";
    
    $stmt = $db->query($query);
    $chartData['monthly_revenue'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $chartData['monthly_revenue'][] = [
            'month' => $row['month'],
            'revenue' => (float)$row['total_revenue'],
            'customers' => (int)$row['paying_customers']
        ];
    }
    
    // Expense breakdown by type (current month)
    $query = "SELECT 
                expense_type,
                COALESCE(SUM(amount), 0) as total
              FROM expenses
              WHERE MONTH(expense_date) = MONTH(CURRENT_DATE())
              AND YEAR(expense_date) = YEAR(CURRENT_DATE())
              GROUP BY expense_type";
    
    $stmt = $db->query($query);
    $chartData['expense_breakdown'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $chartData['expense_breakdown'][$row['expense_type']] = (float)$row['total'];
    }
    
    // Battery categories distribution
    $query = "SELECT 
                category,
                COUNT(*) as count
              FROM batteries
              WHERE category IS NOT NULL
              GROUP BY category";
    
    $stmt = $db->query($query);
    $chartData['battery_categories'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $chartData['battery_categories'][$row['category']] = (int)$row['count'];
    }
    
    // Battery types distribution
    $query = "SELECT 
                battery_type,
                COUNT(*) as count
              FROM batteries
              WHERE battery_type IS NOT NULL
              GROUP BY battery_type";
    
    $stmt = $db->query($query);
    $chartData['battery_types'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $chartData['battery_types'][$row['battery_type']] = (int)$row['count'];
    }
    
    return [
        'success' => true,
        'data' => $chartData,
        'message' => 'Chart data retrieved successfully'
    ];
}

/**
 * Get staff performance metrics
 */
function getStaffPerformance($db) {
    $performance = [];
    
    // Get staff service counts
    $query = "SELECT 
                u.id,
                u.name,
                u.role,
                COUNT(DISTINCT so.id) as total_services,
                COUNT(DISTINCT so.customer_id) as unique_customers,
                COUNT(DISTINCT CASE 
                    WHEN so.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                    THEN so.id 
                END) as monthly_services,
                COUNT(DISTINCT ws.id) as payments_collected,
                COALESCE(SUM(ws.amount), 0) as total_collected
              FROM users u
              LEFT JOIN service_orders so ON u.id = so.service_staff_id
              LEFT JOIN water_services ws ON so.id = ws.service_id
              WHERE u.is_active = 1
              GROUP BY u.id, u.name, u.role
              ORDER BY total_services DESC";
    
    $stmt = $db->query($query);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $performance[] = [
            'staff_id' => (int)$row['id'],
            'name' => $row['name'],
            'role' => $row['role'],
            'total_services' => (int)$row['total_services'],
            'unique_customers' => (int)$row['unique_customers'],
            'monthly_services' => (int)$row['monthly_services'],
            'payments_collected' => (int)$row['payments_collected'],
            'total_collected' => (float)$row['total_collected']
        ];
    }
    
    return [
        'success' => true,
        'data' => $performance,
        'message' => 'Staff performance data retrieved successfully'
    ];
}

/**
 * Get expense analysis
 */
function getExpenseAnalysis($db) {
    $analysis = [];
    
    // Monthly expense summary (last 6 months)
    $query = "SELECT 
                DATE_FORMAT(expense_date, '%Y-%m') as month,
                COUNT(*) as total_transactions,
                SUM(amount) as total_amount,
                SUM(CASE WHEN expense_type = 'petrol' THEN amount ELSE 0 END) as petrol_expenses,
                SUM(CASE WHEN expense_type = 'others' THEN amount ELSE 0 END) as other_expenses
              FROM expenses
              WHERE expense_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
              GROUP BY DATE_FORMAT(expense_date, '%Y-%m')
              ORDER BY month ASC";
    
    $stmt = $db->query($query);
    $analysis['monthly_expenses'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $analysis['monthly_expenses'][] = [
            'month' => $row['month'],
            'transactions' => (int)$row['total_transactions'],
            'total' => (float)$row['total_amount'],
            'petrol' => (float)$row['petrol_expenses'],
            'others' => (float)$row['other_expenses']
        ];
    }
    
    // Top expense categories
    $query = "SELECT 
                expense_type,
                COUNT(*) as count,
                SUM(amount) as total
              FROM expenses
              WHERE expense_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
              GROUP BY expense_type
              ORDER BY total DESC";
    
    $stmt = $db->query($query);
    $analysis['top_categories'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $analysis['top_categories'][] = [
            'type' => $row['expense_type'],
            'count' => (int)$row['count'],
            'total' => (float)$row['total']
        ];
    }
    
    // Expense by staff
    $query = "SELECT 
                staff_name,
                COUNT(*) as count,
                SUM(amount) as total
              FROM expenses
              WHERE expense_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
              GROUP BY staff_name
              ORDER BY total DESC";
    
    $stmt = $db->query($query);
    $analysis['by_staff'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $analysis['by_staff'][] = [
            'staff' => $row['staff_name'],
            'count' => (int)$row['count'],
            'total' => (float)$row['total']
        ];
    }
    
    return [
        'success' => true,
        'data' => $analysis,
        'message' => 'Expense analysis retrieved successfully'
    ];
}

/**
 * Get battery status overview
 */
function getBatteryStatus($db) {
    $status = [];
    
    // Battery condition summary
    $query = "SELECT 
                battery_condition,
                COUNT(*) as count
              FROM batteries
              WHERE battery_condition IS NOT NULL
              GROUP BY battery_condition";
    
    $stmt = $db->query($query);
    $status['condition_summary'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status['condition_summary'][$row['battery_condition']] = (int)$row['count'];
    }
    
    // Top battery brands
    $query = "SELECT 
                brand,
                COUNT(*) as count,
                AVG(CASE 
                    WHEN battery_condition = 'excellent' OR battery_condition = 'good' 
                    THEN 1 ELSE 0 END) as good_condition_rate
              FROM batteries
              WHERE brand IS NOT NULL
              GROUP BY brand
              ORDER BY count DESC
              LIMIT 5";
    
    $stmt = $db->query($query);
    $status['top_brands'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status['top_brands'][] = [
            'brand' => $row['brand'],
            'count' => (int)$row['count'],
            'good_rate' => round((float)$row['good_condition_rate'] * 100, 2) . '%'
        ];
    }
    
    // Batteries needing attention (fair/poor condition)
    $query = "SELECT 
                b.battery_code,
                b.battery_model,
                b.brand,
                b.battery_condition,
                b.installation_date,
                DATEDIFF(NOW(), b.installation_date) as days_installed,
                COUNT(so.id) as service_count
              FROM batteries b
              LEFT JOIN service_orders so ON b.id = so.battery_id
              WHERE b.battery_condition IN ('fair', 'poor')
              GROUP BY b.id
              ORDER BY b.battery_condition DESC
              LIMIT 10";
    
    $stmt = $db->query($query);
    $status['needs_attention'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status['needs_attention'][] = [
            'code' => $row['battery_code'],
            'model' => $row['battery_model'],
            'brand' => $row['brand'],
            'condition' => $row['battery_condition'],
            'days_installed' => (int)$row['days_installed'],
            'service_count' => (int)$row['service_count']
        ];
    }
    
    return [
        'success' => true,
        'data' => $status,
        'message' => 'Battery status retrieved successfully'
    ];
}

/**
 * Get service trends analysis
 */
function getServiceTrends($db) {
    $trends = [];
    
    // Services by day of week
    $query = "SELECT 
                DAYNAME(created_at) as day_name,
                DAYOFWEEK(created_at) as day_num,
                COUNT(*) as service_count
              FROM service_orders
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
              GROUP BY DAYNAME(created_at), DAYOFWEEK(created_at)
              ORDER BY day_num";
    
    $stmt = $db->query($query);
    $trends['by_day'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $trends['by_day'][$row['day_name']] = (int)$row['service_count'];
    }
    
    // Top serviced batteries
    $query = "SELECT 
                b.battery_model,
                b.brand,
                COUNT(so.id) as service_count,
                COUNT(DISTINCT so.customer_id) as unique_customers
              FROM batteries b
              JOIN service_orders so ON b.id = so.battery_id
              GROUP BY b.id
              ORDER BY service_count DESC
              LIMIT 5";
    
    $stmt = $db->query($query);
    $trends['top_batteries'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $trends['top_batteries'][] = [
            'model' => $row['battery_model'],
            'brand' => $row['brand'],
            'services' => (int)$row['service_count'],
            'customers' => (int)$row['unique_customers']
        ];
    }
    
    // Average service time (days between service and payment)
    $query = "SELECT 
                AVG(DATEDIFF(ws.created_at, so.created_at)) as avg_days_to_payment,
                MIN(DATEDIFF(ws.created_at, so.created_at)) as min_days,
                MAX(DATEDIFF(ws.created_at, so.created_at)) as max_days
              FROM service_orders so
              JOIN water_services ws ON so.id = ws.service_id
              WHERE ws.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
    
    $stmt = $db->query($query);
    $avgData = $stmt->fetch(PDO::FETCH_ASSOC);
    $trends['payment_timeline'] = [
        'avg_days' => round($avgData['avg_days_to_payment'] ?? 0, 1),
        'min_days' => (int)($avgData['min_days'] ?? 0),
        'max_days' => (int)($avgData['max_days'] ?? 0)
    ];
    
    return [
        'success' => true,
        'data' => $trends,
        'message' => 'Service trends retrieved successfully'
    ];
}
?>