<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once 'config/database.php';

try {
    $conn = connectDB();
    
    // Get recent activities
    $sql = "SELECT 
                a.*,
                u.name as user_name
            FROM activities a
            LEFT JOIN users u ON a.user_id = u.id
            ORDER BY a.created_at DESC
            LIMIT 50";
    
    $result = $conn->query($sql);
    $activities = array();
    
    while($row = $result->fetch_assoc()) {
        // Format timestamp
        $timestamp = strtotime($row['created_at']);
        $current_time = time();
        $time_diff = $current_time - $timestamp;
        
        if ($time_diff < 60) {
            $time_ago = 'just now';
        } elseif ($time_diff < 3600) {
            $minutes = floor($time_diff / 60);
            $time_ago = $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($time_diff < 86400) {
            $hours = floor($time_diff / 3600);
            $time_ago = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($time_diff < 604800) {
            $days = floor($time_diff / 86400);
            $time_ago = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            $time_ago = date('M j, Y', $timestamp);
        }
        
        $activities[] = [
            'activity' => $row['activity'],
            'timestamp' => $time_ago,
            'module' => $row['module'],
            'action' => $row['action'],
            'user' => $row['user_name'] ?: 'System',
            'created_at' => $row['created_at']
        ];
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'activities' => $activities,
        'count' => count($activities),
        'message' => 'Activities retrieved successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>