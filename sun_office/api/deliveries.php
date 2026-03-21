<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once 'config/database.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $conn = connectDB();
    
    switch($method) {
        case 'GET':
            handleGetDeliveries($conn);
            break;
            
        case 'POST':
            handlePostDelivery($conn);
            break;
            
        case 'PUT':
            handlePutDelivery($conn);
            break;
            
        case 'DELETE':
            handleDeleteDelivery($conn);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

function handleGetDeliveries($conn) {
    // Check if we're getting a single delivery by ID
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $sql = "SELECT 
                    d.*,
                    s.service_code,
                    c.full_name as customer_name,
                    c.phone as customer_phone,
                    b.battery_model,
                    b.brand as battery_brand
                FROM deliveries d
                LEFT JOIN service_orders s ON d.service_id = s.id
                LEFT JOIN customers c ON d.customer_id = c.id
                LEFT JOIN batteries b ON s.battery_id = b.id
                WHERE d.id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $delivery = $result->fetch_assoc();
            
            // Format dates
            if ($delivery['scheduled_date']) {
                $delivery['scheduled_date_formatted'] = date('d M, Y', strtotime($delivery['scheduled_date']));
            }
            if ($delivery['delivered_date']) {
                $delivery['delivered_date_formatted'] = date('d M, Y H:i', strtotime($delivery['delivered_date']));
            }
            if ($delivery['scheduled_time']) {
                $delivery['scheduled_time_formatted'] = date('h:i A', strtotime($delivery['scheduled_time']));
            }
            
            echo json_encode([
                'success' => true,
                'delivery' => $delivery,
                'message' => 'Delivery retrieved successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Delivery not found'
            ]);
        }
        $stmt->close();
        return;
    }
    
    // Get all deliveries
    $sql = "SELECT 
                d.*,
                s.service_code,
                c.full_name as customer_name,
                c.phone as customer_phone,
                b.battery_model,
                b.brand as battery_brand
            FROM deliveries d
            LEFT JOIN service_orders s ON d.service_id = s.id
            LEFT JOIN customers c ON d.customer_id = c.id
            LEFT JOIN batteries b ON s.battery_id = b.id
            ORDER BY d.created_at DESC";
    
    $result = $conn->query($sql);
    $deliveries = array();
    
    while($row = $result->fetch_assoc()) {
        // Format dates
        if ($row['scheduled_date']) {
            $row['scheduled_date_formatted'] = date('d M, Y', strtotime($row['scheduled_date']));
        }
        if ($row['delivered_date']) {
            $row['delivered_date_formatted'] = date('d M, Y H:i', strtotime($row['delivered_date']));
        }
        if ($row['scheduled_time']) {
            $row['scheduled_time_formatted'] = date('h:i A', strtotime($row['scheduled_time']));
        }
        
        $deliveries[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'deliveries' => $deliveries,
        'count' => count($deliveries),
        'message' => 'Deliveries retrieved successfully'
    ]);
}

function handlePostDelivery($conn) {
    // Check if it's an update
    if (isset($_POST['id'])) {
        handlePutDelivery($conn);
        return;
    }
    
    // Create new delivery
    $service_id = intval($_POST['service_id']);
    $customer_id = intval($_POST['customer_id']);
    $delivery_type = $conn->real_escape_string($_POST['delivery_type'] ?? 'home_delivery');
    $address = $conn->real_escape_string($_POST['address']);
    $contact_person = $conn->real_escape_string($_POST['contact_person']);
    $contact_phone = $conn->real_escape_string($_POST['contact_phone']);
    $scheduled_date = $conn->real_escape_string($_POST['scheduled_date']);
    $scheduled_time = $conn->real_escape_string($_POST['scheduled_time'] ?? '');
    $delivery_person = $conn->real_escape_string($_POST['delivery_person'] ?? '');
    $notes = $conn->real_escape_string($_POST['notes'] ?? '');
    $status = $conn->real_escape_string($_POST['status'] ?? 'pending');
    
    // Check if service exists
    $check_service_sql = "SELECT * FROM service_orders WHERE id = ?";
    $check_service_stmt = $conn->prepare($check_service_sql);
    $check_service_stmt->bind_param("i", $service_id);
    $check_service_stmt->execute();
    $check_service_result = $check_service_stmt->get_result();
    
    if ($check_service_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Service order not found'
        ]);
        $check_service_stmt->close();
        return;
    }
    $check_service_stmt->close();
    
    // Check if customer exists
    $check_customer_sql = "SELECT * FROM customers WHERE id = ?";
    $check_customer_stmt = $conn->prepare($check_customer_sql);
    $check_customer_stmt->bind_param("i", $customer_id);
    $check_customer_stmt->execute();
    $check_customer_result = $check_customer_stmt->get_result();
    
    if ($check_customer_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Customer not found'
        ]);
        $check_customer_stmt->close();
        return;
    }
    $check_customer_stmt->close();
    
    // Generate delivery code
    $delivery_code = generateDeliveryCode($conn);
    
    // Insert delivery
    $sql = "INSERT INTO deliveries (
                delivery_code, service_id, customer_id, delivery_type,
                address, contact_person, contact_phone, scheduled_date,
                scheduled_time, delivery_person, notes, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "siisssssssss",
        $delivery_code,
        $service_id,
        $customer_id,
        $delivery_type,
        $address,
        $contact_person,
        $contact_phone,
        $scheduled_date,
        $scheduled_time,
        $delivery_person,
        $notes,
        $status
    );
    
    if ($stmt->execute()) {
        $delivery_id = $stmt->insert_id;
        
        // Update service order status if needed
        if ($status === 'delivered') {
            $update_service_sql = "UPDATE service_orders SET status = 'delivered', actual_completion_date = CURDATE() WHERE id = ?";
            $update_stmt = $conn->prepare($update_service_sql);
            $update_stmt->bind_param("i", $service_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        // Log activity
        logActivity($conn, "New delivery scheduled: $delivery_code for service $service_id");
        
        echo json_encode([
            'success' => true,
            'delivery_id' => $delivery_id,
            'delivery_code' => $delivery_code,
            'message' => 'Delivery scheduled successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to schedule delivery: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
}

function handlePutDelivery($conn) {
    parse_str(file_get_contents("php://input"), $_PUT);
    
    $id = intval($_PUT['id']);
    $delivery_type = $conn->real_escape_string($_PUT['delivery_type'] ?? 'home_delivery');
    $address = $conn->real_escape_string($_PUT['address']);
    $contact_person = $conn->real_escape_string($_PUT['contact_person']);
    $contact_phone = $conn->real_escape_string($_PUT['contact_phone']);
    $scheduled_date = $conn->real_escape_string($_PUT['scheduled_date']);
    $scheduled_time = $conn->real_escape_string($_PUT['scheduled_time'] ?? '');
    $delivery_person = $conn->real_escape_string($_PUT['delivery_person'] ?? '');
    $notes = $conn->real_escape_string($_PUT['notes'] ?? '');
    $status = $conn->real_escape_string($_PUT['status'] ?? 'pending');
    
    // Check if delivery exists
    $check_sql = "SELECT * FROM deliveries WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Delivery not found'
        ]);
        $check_stmt->close();
        return;
    }
    
    $delivery = $check_result->fetch_assoc();
    $check_stmt->close();
    
    // Update delivery
    $sql = "UPDATE deliveries SET 
                delivery_type = ?,
                address = ?,
                contact_person = ?,
                contact_phone = ?,
                scheduled_date = ?,
                scheduled_time = ?,
                delivery_person = ?,
                notes = ?,
                status = ?,
                updated_at = NOW()";
    
    // Add delivered date if status changed to delivered
    if ($status === 'delivered' && $delivery['status'] !== 'delivered') {
        $sql .= ", delivered_date = NOW()";
    }
    
    $sql .= " WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if ($status === 'delivered' && $delivery['status'] !== 'delivered') {
        $stmt->bind_param(
            "sssssssssi",
            $delivery_type,
            $address,
            $contact_person,
            $contact_phone,
            $scheduled_date,
            $scheduled_time,
            $delivery_person,
            $notes,
            $status,
            $id
        );
    } else {
        $stmt->bind_param(
            "sssssssssi",
            $delivery_type,
            $address,
            $contact_person,
            $contact_phone,
            $scheduled_date,
            $scheduled_time,
            $delivery_person,
            $notes,
            $status,
            $id
        );
    }
    
    if ($stmt->execute()) {
        // Update service order status if delivery is marked as delivered
        if ($status === 'delivered' && $delivery['status'] !== 'delivered') {
            $update_service_sql = "UPDATE service_orders SET status = 'delivered', actual_completion_date = CURDATE() WHERE id = ?";
            $update_stmt = $conn->prepare($update_service_sql);
            $update_stmt->bind_param("i", $delivery['service_id']);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        // Log activity
        logActivity($conn, "Delivery updated: {$delivery['delivery_code']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Delivery updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update delivery: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
}

function handleDeleteDelivery($conn) {
    parse_str(file_get_contents("php://input"), $_DELETE);
    
    $id = intval($_DELETE['id']);
    
    // Check if delivery exists
    $check_sql = "SELECT * FROM deliveries WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Delivery not found'
        ]);
        $check_stmt->close();
        return;
    }
    
    $delivery = $check_result->fetch_assoc();
    $check_stmt->close();
    
    // Delete delivery
    $sql = "DELETE FROM deliveries WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        // Log activity
        logActivity($conn, "Delivery deleted: {$delivery['delivery_code']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'Delivery deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete delivery: ' . $stmt->error
        ]);
    }
    
    $stmt->close();
}

function generateDeliveryCode($conn) {
    $sql = "SELECT MAX(CAST(SUBSTRING(delivery_code, 4) AS UNSIGNED)) as max_num FROM deliveries";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $next_num = ($row['max_num'] ?: 0) + 1;
    return 'DEL' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
}

function logActivity($conn, $activity) {
    $user_id = 1; // Default admin user
    $module = 'deliveries';
    $action = 'create';
    
    if (strpos($activity, 'updated') !== false) {
        $action = 'update';
    } elseif (strpos($activity, 'deleted') !== false) {
        $action = 'delete';
    }
    
    $sql = "INSERT INTO activities (user_id, activity, module, action) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $user_id, $activity, $module, $action);
    $stmt->execute();
    $stmt->close();
}
?>