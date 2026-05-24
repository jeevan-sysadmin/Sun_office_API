<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $conn = connectDB();
    ensurePaymentsServiceStaffColumn($conn);

    switch ($method) {
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
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }

    $conn->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function handleGetPayments($conn) {
    if (isset($_GET['action']) && $_GET['action'] === 'staff_list') {
        $sql = "SELECT id, name, email, role FROM users WHERE is_active = 1 ORDER BY name ASC";
        $result = $conn->query($sql);

        $records = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $records[] = $row;
            }
        }

        echo json_encode(['success' => true, 'records' => $records]);
        return;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'staff_monthly_summary') {
        $month = (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) ? $_GET['month'] : date('Y-m');

        $summarySql = "SELECT p.service_staff_id,
                              COALESCE(u.name, 'Unassigned') AS service_staff_name,
                              COUNT(p.id) AS payment_count,
                              COALESCE(SUM(p.amount), 0) AS total_amount
                       FROM payments p
                       LEFT JOIN users u ON p.service_staff_id = u.id
                       WHERE DATE_FORMAT(p.payment_date, '%Y-%m') = ?
                       GROUP BY p.service_staff_id, u.name
                       ORDER BY total_amount DESC, payment_count DESC";
        $summaryStmt = $conn->prepare($summarySql);
        $summaryStmt->bind_param("s", $month);
        $summaryStmt->execute();
        $summaryResult = $summaryStmt->get_result();

        $summary = [];
        while ($row = $summaryResult->fetch_assoc()) {
            $summary[] = $row;
        }
        $summaryStmt->close();

        $listSql = "SELECT p.*, s.service_code, c.full_name AS customer_name, u.name AS service_staff_name
                    FROM payments p
                    LEFT JOIN service_orders s ON p.service_order_id = s.id
                    LEFT JOIN customers c ON s.customer_id = c.id
                    LEFT JOIN users u ON p.service_staff_id = u.id
                    WHERE DATE_FORMAT(p.payment_date, '%Y-%m') = ?
                    ORDER BY p.payment_date DESC, p.id DESC";
        $listStmt = $conn->prepare($listSql);
        $listStmt->bind_param("s", $month);
        $listStmt->execute();
        $listResult = $listStmt->get_result();

        $payments = [];
        while ($row = $listResult->fetch_assoc()) {
            $payments[] = $row;
        }
        $listStmt->close();

        echo json_encode([
            'success' => true,
            'month' => $month,
            'summary' => $summary,
            'payments' => $payments
        ]);
        return;
    }

    $sql = "SELECT p.*, s.service_code, c.full_name AS customer_name, u.name AS service_staff_name
            FROM payments p
            LEFT JOIN service_orders s ON p.service_order_id = s.id
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN users u ON p.service_staff_id = u.id
            WHERE 1=1";

    $params = [];
    $types = '';

    if (isset($_GET['service_order_id']) && $_GET['service_order_id'] !== '') {
        $sql .= " AND p.service_order_id = ?";
        $params[] = intval($_GET['service_order_id']);
        $types .= 'i';
    }

    if (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
        $sql .= " AND DATE_FORMAT(p.payment_date, '%Y-%m') = ?";
        $params[] = $_GET['month'];
        $types .= 's';
    }

    $sql .= " ORDER BY p.payment_date DESC, p.id DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $payments = [];
    while ($row = $result->fetch_assoc()) {
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

    $required_fields = ['service_order_id', 'amount', 'payment_type'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }

    $service_order_id = intval($input['service_order_id']);
    $amount = floatval($input['amount']);
    $payment_type = isset($input['payment_type']) ? trim($input['payment_type']) : '';
    $status = isset($input['status']) ? trim($input['status']) : 'pending';
    $payment_method = isset($input['payment_method']) ? trim($input['payment_method']) : 'cash';
    $transaction_id = isset($input['transaction_id']) && $input['transaction_id'] !== '' ? trim($input['transaction_id']) : null;
    $notes = isset($input['notes']) && $input['notes'] !== '' ? trim($input['notes']) : null;
    $service_staff_id = isset($input['service_staff_id']) && $input['service_staff_id'] !== '' ? intval($input['service_staff_id']) : null;

    $sql = "INSERT INTO payments
            (service_order_id, service_staff_id, amount, payment_type, status, payment_method, transaction_id, notes, payment_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }

    $stmt->bind_param("iidsssss", $service_order_id, $service_staff_id, $amount, $payment_type, $status, $payment_method, $transaction_id, $notes);

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

function handlePutPayment($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        parse_str(file_get_contents('php://input'), $input);
    }

    $id = isset($_GET['id']) ? intval($_GET['id']) : (isset($input['id']) ? intval($input['id']) : 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment ID is required']);
        return;
    }

    $checkStmt = $conn->prepare("SELECT * FROM payments WHERE id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $current = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$current) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        return;
    }

    $service_order_id = isset($input['service_order_id']) ? intval($input['service_order_id']) : intval($current['service_order_id']);
    $service_staff_id = array_key_exists('service_staff_id', $input)
        ? (($input['service_staff_id'] === '' || $input['service_staff_id'] === null) ? null : intval($input['service_staff_id']))
        : (isset($current['service_staff_id']) ? intval($current['service_staff_id']) : null);
    $amount = isset($input['amount']) ? floatval($input['amount']) : floatval($current['amount']);
    $payment_type = isset($input['payment_type']) ? trim($input['payment_type']) : $current['payment_type'];
    $status = isset($input['status']) ? trim($input['status']) : $current['status'];
    $payment_method = isset($input['payment_method']) ? trim($input['payment_method']) : $current['payment_method'];
    $transaction_id = array_key_exists('transaction_id', $input) ? ($input['transaction_id'] !== '' ? trim($input['transaction_id']) : null) : $current['transaction_id'];
    $notes = array_key_exists('notes', $input) ? ($input['notes'] !== '' ? trim($input['notes']) : null) : $current['notes'];

    $sql = "UPDATE payments
            SET service_order_id = ?, service_staff_id = ?, amount = ?, payment_type = ?, status = ?, payment_method = ?, transaction_id = ?, notes = ?
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }

    $stmt->bind_param("iidsssssi", $service_order_id, $service_staff_id, $amount, $payment_type, $status, $payment_method, $transaction_id, $notes, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Payment updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update payment: ' . $stmt->error]);
    }

    $stmt->close();
}

function handleDeletePayment($conn) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment ID is required']);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }

    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Payment deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete payment: ' . $stmt->error]);
    }
    $stmt->close();
}

function ensurePaymentsServiceStaffColumn($conn) {
    $conn->query("ALTER TABLE payments ADD COLUMN service_staff_id INT NULL AFTER service_order_id");
}

?>
