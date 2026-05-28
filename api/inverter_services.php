<?php
// C:\xampp\htdocs\sun_office\api\inverter_services.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config/database.php';

class InverterServiceAPI {
    private $conn;
    private $table_name = "inverter_services";
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        // Check connection
        if (!$this->conn) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Database connection failed"
            ]);
            exit();
        }

        $this->ensureInverterIdsColumn();
    }
    
    /**
     * Helper function to convert empty strings to NULL
     */
    private function emptyToNull($value) {
        if ($value === '' || $value === 'null' || $value === null) {
            return null;
        }
        return $value;
    }
    
    /**
     * Helper function to validate and format date
     */
    private function formatDate($date) {
        if (empty($date) || $date === '' || $date === 'null' || $date === null) {
            return null;
        }
        
        // Remove any whitespace
        $date = trim($date);
        
        // Check if it's a valid date format (YYYY-MM-DD)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        
        // Try to parse and format the date
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        // If invalid date, return null
        return null;
    }

    private function ensureInverterIdsColumn() {
        try {
            $sql = "ALTER TABLE " . $this->table_name . " 
                    ADD COLUMN inverter_ids LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL";
            $this->conn->exec($sql);
        } catch (PDOException $e) {
            // Column likely already exists.
        }
    }

    private function normalizeIdList($raw) {
        if ($raw === null || $raw === '' || $raw === 'null') {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $raw = $decoded;
            }
        }

        if (!is_array($raw)) {
            $raw = [$raw];
        }

        $result = [];
        foreach ($raw as $value) {
            if ($value === null || $value === '' || $value === 'null') {
                continue;
            }
            $id = (int)$value;
            if ($id > 0) {
                $result[$id] = $id;
            }
        }

        return array_values($result);
    }

    private function getSelectedInverterIds($data) {
        if (!is_array($data)) {
            return [];
        }

        $ids = $this->normalizeIdList($data['inverter_ids'] ?? null);
        if (empty($ids)) {
            $ids = $this->normalizeIdList($data['inverter_id'] ?? null);
        }
        return $ids;
    }

    private function getInvertersByIds($ids) {
        $ids = $this->normalizeIdList($ids);
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = "SELECT id, inverter_model, inverter_brand, inverter_serial, power_rating, type, wave_type
                  FROM inverters
                  WHERE id IN ($placeholders)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byId = [];
        foreach ($rows as $row) {
            $byId[(int)$row['id']] = $row;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }
        return $ordered;
    }

    private function hydrateServiceRow($service) {
        if (!$service) {
            return $service;
        }

        $decodedIds = [];
        if (!empty($service['inverter_ids'])) {
            $decodedIds = $this->normalizeIdList($service['inverter_ids']);
        }

        if (empty($decodedIds) && !empty($service['inverter_id'])) {
            $decodedIds = [(int)$service['inverter_id']];
        }

        $service['inverter_ids'] = $decodedIds;
        $service['inverters'] = $this->getInvertersByIds($decodedIds);
        return $service;
    }
    
    // Generate unique service code
    private function generateServiceCode() {
        $prefix = 'INV';
        $year = date('Y');
        $month = date('m');
        
        // Get the latest service code for this month
        $query = "SELECT service_code FROM " . $this->table_name . " 
                  WHERE service_code LIKE :pattern 
                  ORDER BY id DESC LIMIT 1";
        
        $pattern = $prefix . $year . $month . '%';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':pattern', $pattern);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $lastCode = $row['service_code'];
            $lastNumber = intval(substr($lastCode, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        // Format: INV2025020001 (INV + Year + Month + 4-digit sequence)
        return $prefix . $year . $month . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
    
    // Get customer details by ID
    private function getCustomerDetails($customer_id) {
        try {
            $query = "SELECT id, full_name, email, address, city, state, zip_code, phone 
                      FROM customers WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $customer_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getCustomerDetails: " . $e->getMessage());
            return null;
        }
    }
    
    // Get inverter details by ID
    private function getInverterDetails($inverter_id) {
        try {
            if (empty($inverter_id)) {
                return null;
            }
            
            $query = "SELECT id, inverter_model, inverter_brand, inverter_serial, 
                             power_rating, type, wave_type 
                      FROM inverters WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $inverter_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getInverterDetails: " . $e->getMessage());
            return null;
        }
    }
    
    // Get staff details by ID
    private function getStaffDetails($staff_id) {
        try {
            if (empty($staff_id)) {
                return null;
            }
            
            $query = "SELECT id, name, email FROM users WHERE id = :id AND is_active = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $staff_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getStaffDetails: " . $e->getMessage());
            return null;
        }
    }
    
    // Get all inverter services
    public function getInverterServices() {
        try {
            $search = isset($_GET['search']) ? $_GET['search'] : '';
            $status = isset($_GET['status']) ? $_GET['status'] : '';
            $customer_id = isset($_GET['customer_id']) ? $_GET['customer_id'] : '';
            $from_date = isset($_GET['from_date']) ? $_GET['from_date'] : '';
            $to_date = isset($_GET['to_date']) ? $_GET['to_date'] : '';
            
            $query = "SELECT 
                        s.*,
                        c.full_name as customer_name,
                        c.email as customer_email,
                        c.phone as customer_phone,
                        c.address as customer_address,
                        i.inverter_model,
                        i.inverter_brand,
                        i.inverter_serial,
                        u.name as staff_name,
                        u.email as staff_email
                      FROM " . $this->table_name . " s
                      LEFT JOIN customers c ON s.customer_id = c.id
                      LEFT JOIN inverters i ON s.inverter_id = i.id
                      LEFT JOIN users u ON s.service_staff_id = u.id
                      WHERE 1=1";
            
            $params = [];
            
            if (!empty($search)) {
                $query .= " AND (s.service_code LIKE :search 
                            OR c.full_name LIKE :search 
                            OR c.phone LIKE :search
                            OR i.inverter_model LIKE :search
                            OR i.inverter_brand LIKE :search
                            OR i.inverter_serial LIKE :search
                            OR s.issue_description LIKE :search)";
                $params[':search'] = "%$search%";
            }
            
            if (!empty($status) && $status !== 'all') {
                $query .= " AND s.status = :status";
                $params[':status'] = $status;
            }
            
            if (!empty($customer_id)) {
                $query .= " AND s.customer_id = :customer_id";
                $params[':customer_id'] = $customer_id;
            }
            
            if (!empty($from_date)) {
                $query .= " AND DATE(s.created_at) >= :from_date";
                $params[':from_date'] = $from_date;
            }
            
            if (!empty($to_date)) {
                $query .= " AND DATE(s.created_at) <= :to_date";
                $params[':to_date'] = $to_date;
            }
            
            $query .= " ORDER BY s.created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val);
            }
            
            $stmt->execute();
            $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($services as &$service) {
                $service = $this->hydrateServiceRow($service);
            }
            unset($service);
            
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "data" => $services
            ]);
            
        } catch (PDOException $e) {
            error_log("Error in getInverterServices: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error fetching inverter services: " . $e->getMessage()
            ]);
        }
    }
    
    // Get single inverter service by ID
    public function getInverterService($id) {
        try {
            $query = "SELECT 
                        s.*,
                        c.full_name as customer_name,
                        c.email as customer_email,
                        c.phone as customer_phone,
                        c.address as customer_address,
                        i.inverter_model,
                        i.inverter_brand,
                        i.inverter_serial,
                        u.name as staff_name,
                        u.email as staff_email
                      FROM " . $this->table_name . " s
                      LEFT JOIN customers c ON s.customer_id = c.id
                      LEFT JOIN inverters i ON s.inverter_id = i.id
                      LEFT JOIN users u ON s.service_staff_id = u.id
                      WHERE s.id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $service = $stmt->fetch(PDO::FETCH_ASSOC);
            $service = $this->hydrateServiceRow($service);
            
            if ($service) {
                http_response_code(200);
                echo json_encode([
                    "success" => true,
                    "data" => $service
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    "success" => false,
                    "message" => "Inverter service not found"
                ]);
            }
            
        } catch (PDOException $e) {
            error_log("Error in getInverterService: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error fetching inverter service: " . $e->getMessage()
            ]);
        }
    }
    
    // Get reference data for forms
    public function getReferenceData() {
        try {
            // Get all customers
            $customers_query = "SELECT id, full_name, phone FROM customers ORDER BY full_name";
            $customers_stmt = $this->conn->prepare($customers_query);
            $customers_stmt->execute();
            $customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get all inverters
            $inverters_query = "SELECT id, inverter_model, inverter_brand, inverter_serial 
                               FROM inverters ORDER BY inverter_brand, inverter_model";
            $inverters_stmt = $this->conn->prepare($inverters_query);
            $inverters_stmt->execute();
            $inverters = $inverters_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get all staff
            $staff_query = "SELECT id, name, email FROM users WHERE is_active = 1 ORDER BY name";
            $staff_stmt = $this->conn->prepare($staff_query);
            $staff_stmt->execute();
            $staff = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "data" => [
                    "customers" => $customers,
                    "inverters" => $inverters,
                    "staff" => $staff,
                    "status_options" => [
                        "pending", "in_progress", "diagnostic", "repairing", 
                        "testing", "completed", "delivered", "cancelled"
                    ],
                    "warranty_options" => [
                        "in_warranty", "extended_warranty", "out_of_warranty"
                    ],
                    "payment_options" => [
                        "pending", "paid", "refunded"
                    ]
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Error in getReferenceData: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error fetching reference data: " . $e->getMessage()
            ]);
        }
    }
    
    // Create new inverter service - FIXED: Properly handles empty date values
    public function createInverterService() {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "No data provided or invalid JSON format"
                ]);
                return;
            }
            
            // Log received data for debugging
            error_log("Creating inverter service with data: " . json_encode($data));
            
            // Validate required fields - ONLY customer_id is required now
            $required_fields = ['customer_id'];
            $missing = [];
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || trim($data[$field]) === '') {
                    $missing[] = $field;
                }
            }
            
            if (!empty($missing)) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "Missing required fields: " . implode(", ", $missing)
                ]);
                return;
            }
            
            // Verify customer exists
            $customer = $this->getCustomerDetails($data['customer_id']);
            if (!$customer) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "Invalid customer ID"
                ]);
                return;
            }
            
            $inverterIds = $this->getSelectedInverterIds($data);
            foreach ($inverterIds as $selectedId) {
                $inverter = $this->getInverterDetails($selectedId);
                if (!$inverter) {
                    http_response_code(400);
                    echo json_encode([
                        "success" => false,
                        "message" => "Invalid inverter ID"
                    ]);
                    return;
                }
            }
            $inverterId = !empty($inverterIds) ? (int)$inverterIds[0] : null;
            
            // Generate unique service code
            $service_code = $this->generateServiceCode();
            
            // Prepare insert query - inverter_id can be NULL
            $query = "INSERT INTO " . $this->table_name . "
                      SET
                        service_code = :service_code,
                        customer_id = :customer_id,
                        customer_phone = :customer_phone,
                        inverter_id = :inverter_id,
                        inverter_ids = :inverter_ids,
                        service_staff_id = :service_staff_id,
                        issue_description = :issue_description,
                        warranty_status = :warranty_status,
                        status = :status,
                        payment_status = :payment_status,
                        final_cost = :final_cost,
                        estimated_completion_date = :estimated_completion_date,
                        notes = :notes,
                        created_at = NOW(),
                        updated_at = NOW()";
            
            $stmt = $this->conn->prepare($query);
            
            // Set values with proper defaults
            $customer_phone = $customer['phone'] ?? ($data['customer_phone'] ?? '');
            $issue_description = isset($data['issue_description']) ? trim($data['issue_description']) : '';
            $warranty_status = $data['warranty_status'] ?? 'out_of_warranty';
            $status = $data['status'] ?? 'pending';
            $payment_status = $data['payment_status'] ?? 'pending';
            $final_cost = isset($data['final_cost']) && $data['final_cost'] !== '' ? floatval($data['final_cost']) : 0;
            
            // FIXED: Properly handle estimated_completion_date - convert empty to NULL
            $estimated_completion_date = null;
            if (isset($data['estimated_completion_date']) && !empty($data['estimated_completion_date']) && $data['estimated_completion_date'] !== 'null') {
                $estimated_completion_date = $this->formatDate($data['estimated_completion_date']);
            }
            
            $notes = $data['notes'] ?? '';
            $service_staff_id = isset($data['service_staff_id']) && !empty($data['service_staff_id']) && $data['service_staff_id'] !== 'null' ? (int)$data['service_staff_id'] : null;
            
            // Bind parameters
            $stmt->bindParam(':service_code', $service_code);
            $stmt->bindParam(':customer_id', $data['customer_id']);
            $stmt->bindParam(':customer_phone', $customer_phone);
            $stmt->bindParam(':inverter_id', $inverterId, $inverterId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':inverter_ids', json_encode($inverterIds), PDO::PARAM_STR);
            $stmt->bindParam(':service_staff_id', $service_staff_id, $service_staff_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':issue_description', $issue_description);
            $stmt->bindParam(':warranty_status', $warranty_status);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':payment_status', $payment_status);
            $stmt->bindParam(':final_cost', $final_cost);
            
            // FIXED: Bind estimated_completion_date with proper handling
            if ($estimated_completion_date === null) {
                $stmt->bindValue(':estimated_completion_date', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindParam(':estimated_completion_date', $estimated_completion_date);
            }
            
            $stmt->bindParam(':notes', $notes);
            
            if ($stmt->execute()) {
                $last_id = $this->conn->lastInsertId();
                
                // Fetch the created service
                $this->getInverterService($last_id);
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("SQL Error in createInverterService: " . json_encode($errorInfo));
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Unable to create inverter service",
                    "error" => $errorInfo[2] ?? 'Unknown error'
                ]);
            }
            
        } catch (PDOException $e) {
            error_log("PDO Exception in createInverterService: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error creating inverter service: " . $e->getMessage()
            ]);
        } catch (Exception $e) {
            error_log("General Exception in createInverterService: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error creating inverter service: " . $e->getMessage()
            ]);
        }
    }
    
    // Update inverter service - FIXED: Now handles empty date values properly
    public function updateInverterService($id) {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "No data provided or invalid JSON format"
                ]);
                return;
            }
            
            // Log received data for debugging
            error_log("Updating inverter service ID: $id with data: " . json_encode($data));
            
            // Check if service exists
            $check_query = "SELECT id FROM " . $this->table_name . " WHERE id = :id";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() == 0) {
                http_response_code(404);
                echo json_encode([
                    "success" => false,
                    "message" => "Inverter service not found"
                ]);
                return;
            }
            
            $hasInverterSelection = array_key_exists('inverter_ids', $data) || array_key_exists('inverter_id', $data);
            $inverterIds = $hasInverterSelection ? $this->getSelectedInverterIds($data) : null;
            if (is_array($inverterIds)) {
                foreach ($inverterIds as $selectedId) {
                    $inverter = $this->getInverterDetails($selectedId);
                    if (!$inverter) {
                        http_response_code(400);
                        echo json_encode([
                            "success" => false,
                            "message" => "Invalid inverter ID"
                        ]);
                        return;
                    }
                }
            }

            // Backward compatibility check for inverter_id-only updates
            if (!$hasInverterSelection && isset($data['inverter_id']) && !empty($data['inverter_id']) && $data['inverter_id'] !== 'null' && $data['inverter_id'] !== '') {
                $inverter = $this->getInverterDetails($data['inverter_id']);
                if (!$inverter) {
                    http_response_code(400);
                    echo json_encode([
                        "success" => false,
                        "message" => "Invalid inverter ID"
                    ]);
                    return;
                }
            }
            
            // If customer_id is provided, verify it exists
            if (isset($data['customer_id']) && !empty($data['customer_id'])) {
                $customer = $this->getCustomerDetails($data['customer_id']);
                if (!$customer) {
                    http_response_code(400);
                    echo json_encode([
                        "success" => false,
                        "message" => "Invalid customer ID"
                    ]);
                    return;
                }
            }
            
            // Build update query dynamically
            $update_fields = [];
            $params = [':id' => $id];
            
            $allowed_fields = [
                'customer_id', 'customer_phone', 'inverter_id', 'service_staff_id',
                'issue_description', 'warranty_status', 'status',
                'payment_status', 'final_cost', 'estimated_completion_date', 'notes'
            ];
            
            foreach ($allowed_fields as $field) {
                if (array_key_exists($field, $data)) {
                    if ($field === 'inverter_id' || $field === 'service_staff_id') {
                        // Handle ID fields - can be NULL
                        if (isset($data[$field]) && !empty($data[$field]) && $data[$field] !== 'null' && $data[$field] !== '') {
                            $update_fields[] = "$field = :$field";
                            $params[":$field"] = (int)$data[$field];
                        } else {
                            $update_fields[] = "$field = NULL";
                        }
                    } elseif ($field === 'final_cost') {
                        $update_fields[] = "$field = :$field";
                        $params[":$field"] = $data[$field] !== '' ? floatval($data[$field]) : 0;
                    } elseif ($field === 'estimated_completion_date') {
                        // FIXED: Handle date field properly
                        if (isset($data[$field]) && !empty($data[$field]) && $data[$field] !== 'null' && $data[$field] !== '') {
                            $formatted_date = $this->formatDate($data[$field]);
                            if ($formatted_date !== null) {
                                $update_fields[] = "$field = :$field";
                                $params[":$field"] = $formatted_date;
                            } else {
                                $update_fields[] = "$field = NULL";
                            }
                        } else {
                            $update_fields[] = "$field = NULL";
                        }
                    } else {
                        $update_fields[] = "$field = :$field";
                        $params[":$field"] = trim($data[$field]);
                    }
                }
            }

            if ($hasInverterSelection) {
                $update_fields[] = "inverter_id = :inverter_id";
                $update_fields[] = "inverter_ids = :inverter_ids";
                $params[':inverter_id'] = !empty($inverterIds) ? (int)$inverterIds[0] : null;
                $params[':inverter_ids'] = json_encode($inverterIds ?? []);
            }
            
            // Always update the updated_at timestamp
            $update_fields[] = "updated_at = NOW()";
            
            if (empty($update_fields)) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "No fields to update"
                ]);
                return;
            }
            
            $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $update_fields) . " WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                if ($key === ':final_cost') {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                } elseif ($key === ':inverter_ids') {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                } elseif ($key === ':id' || strpos($key, '_id') !== false) {
                    if ($value === null) {
                        $stmt->bindValue($key, null, PDO::PARAM_NULL);
                    } else {
                        $stmt->bindValue($key, $value, PDO::PARAM_INT);
                    }
                } elseif ($key === ':estimated_completion_date') {
                    if ($value === null) {
                        $stmt->bindValue($key, null, PDO::PARAM_NULL);
                    } else {
                        $stmt->bindValue($key, $value, PDO::PARAM_STR);
                    }
                } else {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                }
            }
            
            if ($stmt->execute()) {
                // Fetch the updated service
                $this->getInverterService($id);
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("SQL Error in updateInverterService: " . json_encode($errorInfo));
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Unable to update inverter service",
                    "error" => $errorInfo[2] ?? 'Unknown error'
                ]);
            }
            
        } catch (PDOException $e) {
            error_log("PDO Exception in updateInverterService: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error updating inverter service: " . $e->getMessage()
            ]);
        } catch (Exception $e) {
            error_log("General Exception in updateInverterService: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error updating inverter service: " . $e->getMessage()
            ]);
        }
    }
    
    // Delete inverter service
    public function deleteInverterService($id) {
        try {
            // Check if service exists
            $check_query = "SELECT id FROM " . $this->table_name . " WHERE id = :id";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() == 0) {
                http_response_code(404);
                echo json_encode([
                    "success" => false,
                    "message" => "Inverter service not found"
                ]);
                return;
            }
            
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode([
                    "success" => true,
                    "message" => "Inverter service deleted successfully"
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Unable to delete inverter service"
                ]);
            }
            
        } catch (PDOException $e) {
            error_log("Error in deleteInverterService: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error deleting inverter service: " . $e->getMessage()
            ]);
        }
    }
    
    // Get service statistics
    public function getStats() {
        try {
            $query = "SELECT 
                        COUNT(*) as total_services,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                        SUM(CASE WHEN warranty_status = 'in_warranty' THEN 1 ELSE 0 END) as in_warranty,
                        SUM(CASE WHEN warranty_status = 'out_of_warranty' THEN 1 ELSE 0 END) as out_of_warranty,
                        SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid,
                        SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as payment_pending,
                        COALESCE(SUM(final_cost), 0) as total_revenue,
                        COUNT(DISTINCT customer_id) as unique_customers,
                        COUNT(DISTINCT CASE WHEN inverter_id IS NOT NULL THEN inverter_id END) as inverters_serviced,
                        SUM(CASE WHEN inverter_id IS NULL THEN 1 ELSE 0 END) as services_without_inverter
                      FROM " . $this->table_name;
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "data" => $stats
            ]);
            
        } catch (PDOException $e) {
            error_log("Error in getStats: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error fetching statistics: " . $e->getMessage()
            ]);
        }
    }
}

// Route the request
$api = new InverterServiceAPI();
$request_method = $_SERVER['REQUEST_METHOD'];

// Get ID from URL if present
$id = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
}

// Route based on request method
try {
    switch ($request_method) {
        case 'GET':
            if (isset($_GET['stats'])) {
                $api->getStats();
            } elseif (isset($_GET['reference'])) {
                $api->getReferenceData();
            } elseif ($id) {
                $api->getInverterService($id);
            } else {
                $api->getInverterServices();
            }
            break;
            
        case 'POST':
            $api->createInverterService();
            break;
            
        case 'PUT':
            if ($id) {
                $api->updateInverterService($id);
            } else {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "ID is required for update"
                ]);
            }
            break;
            
        case 'DELETE':
            if ($id) {
                $api->deleteInverterService($id);
            } else {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "ID is required for delete"
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                "success" => false,
                "message" => "Method not allowed"
            ]);
            break;
    }
} catch (Exception $e) {
    error_log("Unhandled exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Internal server error: " . $e->getMessage()
    ]);
}

?>
