<?php
// C:\xampp\htdocs\sun_office\api\water_services.php

// Required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database configuration
require_once 'config/database.php';

// Get database connection
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(array("message" => "Database connection failed."));
    exit();
}

ensureBatteryIdColumn($db);
ensureServiceStaffColumn($db);

// Get request method
$request_method = $_SERVER['REQUEST_METHOD'];

// Get ID parameter from query string if exists
$id = isset($_GET['id']) ? $_GET['id'] : null;

// Parse JSON input for POST, PUT methods
$data = json_decode(file_get_contents("php://input"));

// Route based on request method
switch($request_method) {
    case 'GET':
        handleGetRequest($db, $id);
        break;
        
    case 'POST':
        handlePostRequest($db, $data);
        break;
        
    case 'PUT':
        handlePutRequest($db, $id, $data);
        break;
        
    case 'DELETE':
        handleDeleteRequest($db, $id);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method not allowed."));
        break;
}

/**
 * Handle GET requests
 */
function handleGetRequest($db, $id) {
    // Check if it's a special action
    if(isset($_GET['action'])) {
        switch($_GET['action']) {
            case 'staff_list':
                $query = "SELECT id, name, email, role
                          FROM users
                          WHERE is_active = 1
                          ORDER BY name ASC";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode(array(
                    "success" => true,
                    "records" => $records
                ));
                break;

            case 'staff_monthly_summary':
                $month = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])
                    ? $_GET['month']
                    : date('Y-m');

                $detailsQuery = "SELECT ws.id, ws.service_id, ws.amount, ws.service_date, ws.notes, ws.created_at,
                                        ws.service_staff_id,
                                        u.name AS service_staff_name,
                                        so.service_code,
                                        c.full_name AS customer_name
                                 FROM water_services ws
                                 LEFT JOIN users u ON ws.service_staff_id = u.id
                                 LEFT JOIN service_orders so ON ws.service_id = so.id
                                 LEFT JOIN customers c ON ws.customer_id = c.id
                                 WHERE DATE_FORMAT(ws.service_date, '%Y-%m') = :month
                                 ORDER BY ws.service_date DESC, ws.id DESC";
                $detailsStmt = $db->prepare($detailsQuery);
                $detailsStmt->bindParam(':month', $month);
                $detailsStmt->execute();
                $payments = $detailsStmt->fetchAll(PDO::FETCH_ASSOC);

                $summaryQuery = "SELECT ws.service_staff_id,
                                        COALESCE(u.name, 'Unassigned') AS service_staff_name,
                                        COUNT(ws.id) AS service_count,
                                        COALESCE(SUM(ws.amount), 0) AS total_amount
                                 FROM water_services ws
                                 LEFT JOIN users u ON ws.service_staff_id = u.id
                                 WHERE DATE_FORMAT(ws.service_date, '%Y-%m') = :month
                                 GROUP BY ws.service_staff_id, u.name
                                 ORDER BY total_amount DESC, service_count DESC";
                $summaryStmt = $db->prepare($summaryQuery);
                $summaryStmt->bindParam(':month', $month);
                $summaryStmt->execute();
                $summary = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode(array(
                    "success" => true,
                    "month" => $month,
                    "summary" => $summary,
                    "payments" => $payments
                ));
                break;

            case 'total':
                // Get total amount by date range
                if(isset($_GET['start_date']) && isset($_GET['end_date'])) {
                    $start_date = $_GET['start_date'];
                    $end_date = $_GET['end_date'];
                    
                    $query = "SELECT SUM(amount) as total 
                              FROM water_services
                              WHERE service_date BETWEEN :start_date AND :end_date";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":start_date", $start_date);
                    $stmt->bindParam(":end_date", $end_date);
                    $stmt->execute();
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $total = $row['total'];
                    
                    http_response_code(200);
                    echo json_encode(array(
                        "start_date" => $start_date,
                        "end_date" => $end_date,
                        "total_amount" => $total ? floatval($total) : 0
                    ));
                } else {
                    http_response_code(400);
                    echo json_encode(array("message" => "Start date and end date are required."));
                }
                break;
                
            case 'by_service_id':
                // Get water services by service_id
                if(isset($_GET['service_id'])) {
                    $service_id = $_GET['service_id'];
                    
                    $query = "SELECT ws.*, c.full_name as customer_name, b.battery_model as battery_name
                              FROM water_services ws
                              LEFT JOIN customers c ON ws.customer_id = c.id
                              LEFT JOIN batteries b ON ws.battery_id = b.id
                              WHERE ws.service_id = :service_id
                              ORDER BY ws.service_date DESC";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":service_id", $service_id);
                    $stmt->execute();
                    
                    $water_services_arr = array();
                    $water_services_arr["records"] = array();
                    
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $water_service_item = array(
                            "id" => intval($row['id']),
                            "service_id" => intval($row['service_id']),
                            "customer_id" => $row['customer_id'] ? intval($row['customer_id']) : null,
                            "amount" => floatval($row['amount']),
                            "service_date" => $row['service_date'],
                            "notes" => $row['notes'],
                            "created_by" => intval($row['created_by']),
                            "created_at" => $row['created_at'],
                            "customer_name" => $row['customer_name'] ?? null
                            ,"battery_id" => isset($row['battery_id']) ? intval($row['battery_id']) : null
                            ,"battery_name" => $row['battery_name'] ?? null
                        );
                        
                        array_push($water_services_arr["records"], $water_service_item);
                    }
                    
                    http_response_code(200);
                    echo json_encode($water_services_arr);
                } else {
                    http_response_code(400);
                    echo json_encode(array("message" => "Service ID is required."));
                }
                break;
                
            default:
                http_response_code(400);
                echo json_encode(array("message" => "Invalid action."));
                break;
        }
        return;
    }
    
    // Regular GET requests
    if ($id) {
        // Get single water service with customer info
        $query = "SELECT ws.*, c.full_name as customer_name, b.battery_model as battery_name
                  FROM water_services ws
                  LEFT JOIN customers c ON ws.customer_id = c.id
                  LEFT JOIN batteries b ON ws.battery_id = b.id
                  WHERE ws.id = :id LIMIT 1";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $water_service_arr = array(
                "id" => intval($row['id']),
                "service_id" => intval($row['service_id']),
                "customer_id" => $row['customer_id'] ? intval($row['customer_id']) : null,
                "amount" => floatval($row['amount']),
                "service_date" => $row['service_date'],
                "notes" => $row['notes'],
                "created_by" => intval($row['created_by']),
                "created_at" => $row['created_at'],
                "customer_name" => $row['customer_name'] ?? null
                ,"battery_id" => isset($row['battery_id']) ? intval($row['battery_id']) : null
                ,"battery_name" => $row['battery_name'] ?? null
            );
            
            http_response_code(200);
            echo json_encode($water_service_arr);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "Water service not found."));
        }
    } else {
        // Get all water services with customer info
        if(isset($_GET['service_id']) && $_GET['service_id'] !== '') {
            $service_id = intval($_GET['service_id']);
            $query = "SELECT ws.*, c.full_name as customer_name, b.battery_model as battery_name, u.name as service_staff_name
                      FROM water_services ws
                      LEFT JOIN customers c ON ws.customer_id = c.id
                      LEFT JOIN batteries b ON ws.battery_id = b.id
                      LEFT JOIN users u ON ws.service_staff_id = u.id
                      WHERE ws.service_id = :service_id
                      ORDER BY ws.service_date DESC";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":service_id", $service_id);
        } else if(isset($_GET['search'])) {
            $keywords = $_GET['search'];
            $keywords = htmlspecialchars(strip_tags($keywords));
            $keywords = "%{$keywords}%";
            
            $query = "SELECT ws.*, c.full_name as customer_name, b.battery_model as battery_name, u.name as service_staff_name
                      FROM water_services ws
                      LEFT JOIN customers c ON ws.customer_id = c.id
                      LEFT JOIN batteries b ON ws.battery_id = b.id
                      LEFT JOIN users u ON ws.service_staff_id = u.id
                      WHERE ws.notes LIKE :keywords 
                         OR ws.amount LIKE :keywords 
                         OR ws.service_date LIKE :keywords
                         OR ws.service_id LIKE :keywords
                         OR c.full_name LIKE :keywords
                      ORDER BY ws.service_date DESC";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":keywords", $keywords);
        } else {
            $query = "SELECT ws.*, c.full_name as customer_name, b.battery_model as battery_name, u.name as service_staff_name
                      FROM water_services ws
                      LEFT JOIN customers c ON ws.customer_id = c.id
                      LEFT JOIN batteries b ON ws.battery_id = b.id
                      LEFT JOIN users u ON ws.service_staff_id = u.id";

            if (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
                $query .= " WHERE DATE_FORMAT(ws.service_date, '%Y-%m') = :month";
            }

            $query .= " ORDER BY ws.service_date DESC";
            
            $stmt = $db->prepare($query);
            if (isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
                $stmt->bindParam(":month", $_GET['month']);
            }
        }
        
        $stmt->execute();
        $num = $stmt->rowCount();
        
        if($num > 0) {
            $water_services_arr = array();
            $water_services_arr["records"] = array();
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $water_service_item = array(
                    "id" => intval($row['id']),
                    "service_id" => intval($row['service_id']),
                    "customer_id" => $row['customer_id'] ? intval($row['customer_id']) : null,
                    "amount" => floatval($row['amount']),
                    "service_date" => $row['service_date'],
                    "notes" => $row['notes'],
                    "created_by" => intval($row['created_by']),
                    "created_at" => $row['created_at'],
                    "customer_name" => $row['customer_name'] ?? null
                    ,"battery_id" => isset($row['battery_id']) ? intval($row['battery_id']) : null
                    ,"battery_name" => $row['battery_name'] ?? null
                    ,"service_staff_id" => isset($row['service_staff_id']) ? intval($row['service_staff_id']) : null
                    ,"service_staff_name" => $row['service_staff_name'] ?? null
                );
                
                array_push($water_services_arr["records"], $water_service_item);
            }
            
            http_response_code(200);
            echo json_encode($water_services_arr);
        } else {
            http_response_code(200);
            echo json_encode(array("records" => [], "message" => "No water services found."));
        }
    }
}

/**
 * Handle POST requests - Create new water service payment
 */
function handlePostRequest($db, $data) {
    // Validate required fields
    if(empty($data->service_id) || empty($data->amount) || empty($data->service_date)) {
        http_response_code(400);
        echo json_encode(array(
            "success" => false,
            "message" => "Unable to create water service. Data is incomplete.",
            "required_fields" => ["service_id", "amount", "service_date"],
            "received" => $data
        ));
        return;
    }
    
    // Validate amount is numeric and positive
    if(!is_numeric($data->amount) || floatval($data->amount) <= 0) {
        http_response_code(400);
        echo json_encode(array(
            "success" => false,
            "message" => "Amount must be a positive number."
        ));
        return;
    }
    
    // Validate service_date format
    $date = DateTime::createFromFormat('Y-m-d', $data->service_date);
    if (!$date || $date->format('Y-m-d') !== $data->service_date) {
        http_response_code(400);
        echo json_encode(array(
            "success" => false,
            "message" => "Invalid service_date format. Use YYYY-MM-DD"
        ));
        return;
    }
    
    // Check if service exists in service_orders table
    try {
        $check_service = "SELECT id FROM service_orders WHERE id = :service_id";
        $check_stmt = $db->prepare($check_service);
        $check_stmt->bindParam(":service_id", $data->service_id);
        $check_stmt->execute();
        
        if($check_stmt->rowCount() == 0) {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "Service ID does not exist."
            ));
            return;
        }
    } catch (PDOException $e) {
        // Service_orders table might not exist, continue anyway
    }
    
    // Validate customer_id if provided
    if(isset($data->customer_id) && !empty($data->customer_id)) {
        try {
            $check_customer = "SELECT id FROM customers WHERE id = :customer_id";
            $check_stmt = $db->prepare($check_customer);
            $check_stmt->bindParam(":customer_id", $data->customer_id);
            $check_stmt->execute();
            
            if($check_stmt->rowCount() == 0) {
                http_response_code(400);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Invalid customer ID."
                ));
                return;
            }
        } catch (PDOException $e) {
            // Customers table might not exist
        }
    }
    
    // Sanitize inputs
    $service_id = htmlspecialchars(strip_tags($data->service_id));
    $battery_id = isset($data->battery_id) && $data->battery_id !== '' ? intval($data->battery_id) : null;
    $customer_id = isset($data->customer_id) && $data->customer_id !== '' ? htmlspecialchars(strip_tags($data->customer_id)) : null;
    $amount = floatval($data->amount);
    $service_date = htmlspecialchars(strip_tags($data->service_date));
    $notes = isset($data->notes) ? htmlspecialchars(strip_tags($data->notes)) : null;
    $created_by = isset($data->created_by) ? htmlspecialchars(strip_tags($data->created_by)) : 1;
    $service_staff_id = isset($data->service_staff_id) && $data->service_staff_id !== '' ? intval($data->service_staff_id) : null;
    $created_at = date('Y-m-d H:i:s');
    
    try {
        $query = "INSERT INTO water_services
                  (service_id, customer_id, battery_id, amount, service_date, notes, created_by, service_staff_id, created_at)
                  VALUES
                  (:service_id, :customer_id, :battery_id, :amount, :service_date, :notes, :created_by, :service_staff_id, :created_at)";
        
        $stmt = $db->prepare($query);
        
        // Bind values
        $stmt->bindParam(":service_id", $service_id);
        $stmt->bindParam(":customer_id", $customer_id);
        $stmt->bindParam(":battery_id", $battery_id);
        $stmt->bindParam(":amount", $amount);
        $stmt->bindParam(":service_date", $service_date);
        $stmt->bindParam(":notes", $notes);
        $stmt->bindParam(":created_by", $created_by);
        $stmt->bindParam(":service_staff_id", $service_staff_id);
        $stmt->bindParam(":created_at", $created_at);
        
        if($stmt->execute()) {
            $new_id = $db->lastInsertId();
            
            // Get the created record
            $select_query = "SELECT ws.*, c.full_name as customer_name, b.battery_model as battery_name, u.name as service_staff_name
                            FROM water_services ws
                            LEFT JOIN customers c ON ws.customer_id = c.id
                            LEFT JOIN batteries b ON ws.battery_id = b.id
                            LEFT JOIN users u ON ws.service_staff_id = u.id
                            WHERE ws.id = :id";
            
            $select_stmt = $db->prepare($select_query);
            $select_stmt->bindParam(":id", $new_id);
            $select_stmt->execute();
            $new_record = $select_stmt->fetch(PDO::FETCH_ASSOC);
            
            $response_record = array(
                "id" => intval($new_record['id']),
                "service_id" => intval($new_record['service_id']),
                "customer_id" => $new_record['customer_id'] ? intval($new_record['customer_id']) : null,
                "amount" => floatval($new_record['amount']),
                "service_date" => $new_record['service_date'],
                "notes" => $new_record['notes'],
                "created_by" => intval($new_record['created_by']),
                "created_at" => $new_record['created_at'],
                "customer_name" => $new_record['customer_name'] ?? null
                ,"battery_id" => isset($new_record['battery_id']) ? intval($new_record['battery_id']) : null
                ,"battery_name" => $new_record['battery_name'] ?? null
                ,"service_staff_id" => isset($new_record['service_staff_id']) ? intval($new_record['service_staff_id']) : null
                ,"service_staff_name" => $new_record['service_staff_name'] ?? null
            );
            
            http_response_code(201);
            echo json_encode(array(
                "success" => true,
                "message" => "Payment created successfully.",
                "id" => intval($new_id),
                "payment" => $response_record
            ));
        } else {
            $errorInfo = $stmt->errorInfo();
            http_response_code(503);
            echo json_encode(array(
                "success" => false,
                "message" => "Unable to create payment.",
                "error" => $errorInfo[2]
            ));
        }
    } catch (PDOException $e) {
        http_response_code(503);
        echo json_encode(array(
            "success" => false,
            "message" => "Database error: " . $e->getMessage()
        ));
    }
}

/**
 * Handle PUT requests - Update existing water service payment
 */
function handlePutRequest($db, $id, $data) {
    if(!$id) {
        http_response_code(400);
        echo json_encode(array(
            "success" => false,
            "message" => "Payment ID is required."
        ));
        return;
    }
    
    // Check if water service exists
    $check_query = "SELECT id FROM water_services WHERE id = :id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":id", $id);
    $check_stmt->execute();
    
    if($check_stmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(array(
            "success" => false,
            "message" => "Payment not found."
        ));
        return;
    }
    
    // Validate amount if provided
    if(isset($data->amount) && (!is_numeric($data->amount) || floatval($data->amount) <= 0)) {
        http_response_code(400);
        echo json_encode(array(
            "success" => false,
            "message" => "Amount must be a positive number."
        ));
        return;
    }
    
    // Validate service_date if provided
    if(isset($data->service_date)) {
        $date = DateTime::createFromFormat('Y-m-d', $data->service_date);
        if (!$date || $date->format('Y-m-d') !== $data->service_date) {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "Invalid service_date format. Use YYYY-MM-DD"
            ));
            return;
        }
    }
    
    // Validate customer_id if provided
    if(isset($data->customer_id) && !empty($data->customer_id)) {
        try {
            $check_customer = "SELECT id FROM customers WHERE id = :customer_id";
            $check_stmt = $db->prepare($check_customer);
            $check_stmt->bindParam(":customer_id", $data->customer_id);
            $check_stmt->execute();
            
            if($check_stmt->rowCount() == 0) {
                http_response_code(400);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Invalid customer ID."
                ));
                return;
            }
        } catch (PDOException $e) {
            // Customers table might not exist
        }
    }
    
    // Get current data
    $current_query = "SELECT * FROM water_services WHERE id = :id";
    $current_stmt = $db->prepare($current_query);
    $current_stmt->bindParam(":id", $id);
    $current_stmt->execute();
    $current = $current_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Prepare update data
    $service_id = isset($data->service_id) ? htmlspecialchars(strip_tags($data->service_id)) : $current['service_id'];
    $customer_id = isset($data->customer_id) ? htmlspecialchars(strip_tags($data->customer_id)) : $current['customer_id'];
    $battery_id = isset($data->battery_id) ? intval($data->battery_id) : (isset($current['battery_id']) ? intval($current['battery_id']) : null);
    $amount = isset($data->amount) ? floatval($data->amount) : $current['amount'];
    $service_date = isset($data->service_date) ? htmlspecialchars(strip_tags($data->service_date)) : $current['service_date'];
    $notes = isset($data->notes) ? htmlspecialchars(strip_tags($data->notes)) : $current['notes'];
    $service_staff_id = isset($data->service_staff_id) ? intval($data->service_staff_id) : (isset($current['service_staff_id']) ? intval($current['service_staff_id']) : null);
    
    try {
        $query = "UPDATE water_services
                  SET
                    service_id = :service_id,
                    customer_id = :customer_id,
                    battery_id = :battery_id,
                    amount = :amount,
                    service_date = :service_date,
                    notes = :notes,
                    service_staff_id = :service_staff_id
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        
        // Bind values
        $stmt->bindParam(":service_id", $service_id);
        $stmt->bindParam(":customer_id", $customer_id);
        $stmt->bindParam(":battery_id", $battery_id);
        $stmt->bindParam(":amount", $amount);
        $stmt->bindParam(":service_date", $service_date);
        $stmt->bindParam(":notes", $notes);
        $stmt->bindParam(":service_staff_id", $service_staff_id);
        $stmt->bindParam(":id", $id);
        
        if($stmt->execute()) {
            // Get the updated record
            $select_query = "SELECT ws.*, c.full_name as customer_name, b.battery_model as battery_name, u.name as service_staff_name
                            FROM water_services ws
                            LEFT JOIN customers c ON ws.customer_id = c.id
                            LEFT JOIN batteries b ON ws.battery_id = b.id
                            LEFT JOIN users u ON ws.service_staff_id = u.id
                            WHERE ws.id = :id";
            
            $select_stmt = $db->prepare($select_query);
            $select_stmt->bindParam(":id", $id);
            $select_stmt->execute();
            $updated_record = $select_stmt->fetch(PDO::FETCH_ASSOC);
            
            $response_record = array(
                "id" => intval($updated_record['id']),
                "service_id" => intval($updated_record['service_id']),
                "customer_id" => $updated_record['customer_id'] ? intval($updated_record['customer_id']) : null,
                "amount" => floatval($updated_record['amount']),
                "service_date" => $updated_record['service_date'],
                "notes" => $updated_record['notes'],
                "created_by" => intval($updated_record['created_by']),
                "created_at" => $updated_record['created_at'],
                "customer_name" => $updated_record['customer_name'] ?? null
                ,"battery_id" => isset($updated_record['battery_id']) ? intval($updated_record['battery_id']) : null
                ,"battery_name" => $updated_record['battery_name'] ?? null
                ,"service_staff_id" => isset($updated_record['service_staff_id']) ? intval($updated_record['service_staff_id']) : null
                ,"service_staff_name" => $updated_record['service_staff_name'] ?? null
            );
            
            http_response_code(200);
            echo json_encode(array(
                "success" => true,
                "message" => "Payment updated successfully.",
                "payment" => $response_record
            ));
        } else {
            $errorInfo = $stmt->errorInfo();
            http_response_code(503);
            echo json_encode(array(
                "success" => false,
                "message" => "Unable to update payment.",
                "error" => $errorInfo[2]
            ));
        }
    } catch (PDOException $e) {
        http_response_code(503);
        echo json_encode(array(
            "success" => false,
            "message" => "Database error: " . $e->getMessage()
        ));
    }
}

/**
 * Handle DELETE requests - Delete water service payment
 */
function handleDeleteRequest($db, $id) {
    if(!$id) {
        http_response_code(400);
        echo json_encode(array(
            "success" => false,
            "message" => "Payment ID is required."
        ));
        return;
    }
    
    // Check if water service exists
    $check_query = "SELECT id FROM water_services WHERE id = :id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(":id", $id);
    $check_stmt->execute();
    
    if($check_stmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(array(
            "success" => false,
            "message" => "Payment not found."
        ));
        return;
    }
    
    try {
        $query = "DELETE FROM water_services WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $id);
        
        if($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array(
                "success" => true,
                "message" => "Payment deleted successfully."
            ));
        } else {
            $errorInfo = $stmt->errorInfo();
            http_response_code(503);
            echo json_encode(array(
                "success" => false,
                "message" => "Unable to delete payment.",
                "error" => $errorInfo[2]
            ));
        }
    } catch (PDOException $e) {
        http_response_code(503);
        echo json_encode(array(
            "success" => false,
            "message" => "Database error: " . $e->getMessage()
        ));
    }
}

function ensureBatteryIdColumn($db) {
    try {
        $db->exec("ALTER TABLE water_services ADD COLUMN battery_id INT NULL AFTER customer_id");
    } catch (PDOException $e) {
        // already exists
    }
}

function ensureServiceStaffColumn($db) {
    try {
        $db->exec("ALTER TABLE water_services ADD COLUMN service_staff_id INT NULL AFTER created_by");
    } catch (PDOException $e) {
        // already exists
    }
}
?>
