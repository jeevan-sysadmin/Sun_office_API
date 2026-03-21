<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require_once 'config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $conn = connectDB();
    
    switch($method) {
        case 'GET':
            handleGetPayments($conn);
            break;
            
        case 'POST':
            handlePostPayment($conn);
            break;
            
        case 'PUT':
            handlePutPayment($conn);
            break;
            
        case 'DELETE':
            handleDeletePayment($conn);
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

function handleGetPayments($conn) {
    $sql = "SELECT p.*, s.service_code, c.full_name as customer_name 
            FROM payments p 
            LEFT JOIN service_orders s ON p.service_order_id = s.id 
            LEFT JOIN customers c ON s.customer_id = c.id 
            WHERE 1=1";
    
    $params = [];
    $types = '';
    
    if (isset($_GET['service_order_id'])) {
        $sql .= " AND p.service_order_id = ?";
        $params[] = intval($_GET['service_order_id']);
        $types .= 'i';
    }
    
    $sql .= " ORDER BY p.payment_date DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    while($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'payments' => $payments,
        'count' => count($payments),
        'message' => 'Payments retrieved successfully'
    ]);
    
    $stmt->close();
}

function handlePostPayment($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    // Validate required fields
    $required_fields = ['service_order_id', 'amount', 'payment_type'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Missing required field: $field"
            ]);
            return;
        }
    }
    
    $service_order_id = intval($input['service_order_id']);
    $amount = floatval($input['amount']);
    $payment_type = $conn->real_escape_string($input['payment_type']);
    $status = isset($input['status']) ? $conn->real_escape_string($input['status']) : 'pending';
    $payment_method = isset($input['payment_method']) ? $conn->real_escape_string($input['payment_method']) : 'cash';
    $transaction_id = isset($input['transaction_id']) ? $conn->real_escape_string($input['transaction_id']) : NULL;
    $notes = isset($input['notes']) ? $conn->real_escape_string($input['notes']) : NULL;
    
    $sql = "INSERT INTO payments (service_order_id, amount, payment_type, status, payment_method, transaction_id, notes, payment_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $conn->error
        ]);
        return;
    }
    
    $stmt->bind_param("idsssss", $service_order_id, $amount, $payment_type, $status, $payment_method, $transaction_id, $notes);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'payment_id' => $stmt->insert_id,
            'message' => 'Payment created successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create payment: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
}
?>