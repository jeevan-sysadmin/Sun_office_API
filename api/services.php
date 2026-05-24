<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$config = [
    'host' => '127.0.0.1',
    'dbname' => 'sun_office',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    ensureServiceOrdersMultiIdColumns($pdo);
    ensureMappingTables($pdo);
    ensureServiceOrderCodeTrigger($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed', 'error' => $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        handleGetRequest($pdo);
        break;
    case 'POST':
        handlePostRequest($pdo);
        break;
    case 'PUT':
        handlePutRequest($pdo);
        break;
    case 'DELETE':
        handleDeleteRequest($pdo);
        break;
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function ensureServiceOrdersMultiIdColumns(PDO $pdo): void {
    try {
        $pdo->exec("
            ALTER TABLE service_orders
            ADD COLUMN battery_ids LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL
            CHECK (json_valid(battery_ids))
        ");
    } catch (PDOException $e) {
        // Column may already exist
    }

    try {
        $pdo->exec("
            ALTER TABLE service_orders
            ADD COLUMN inverter_ids LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL
            CHECK (json_valid(inverter_ids))
        ");
    } catch (PDOException $e) {
        // Column may already exist
    }
}

function ensureMappingTables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS service_order_batteries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_order_id INT NOT NULL,
            battery_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_service_battery (service_order_id, battery_id),
            KEY idx_sob_service (service_order_id),
            KEY idx_sob_battery (battery_id),
            CONSTRAINT fk_sob_service FOREIGN KEY (service_order_id) REFERENCES service_orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_sob_battery FOREIGN KEY (battery_id) REFERENCES batteries(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS service_order_inverters (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_order_id INT NOT NULL,
            inverter_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_service_inverter (service_order_id, inverter_id),
            KEY idx_soi_service (service_order_id),
            KEY idx_soi_inverter (inverter_id),
            CONSTRAINT fk_soi_service FOREIGN KEY (service_order_id) REFERENCES service_orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_soi_inverter FOREIGN KEY (inverter_id) REFERENCES inverters(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}


function ensureServiceOrderCodeTrigger(PDO $pdo): void {
    $pdo->exec("DROP TRIGGER IF EXISTS before_service_orders_insert");
    $pdo->exec("
        CREATE TRIGGER before_service_orders_insert
        BEFORE INSERT ON service_orders
        FOR EACH ROW
        BEGIN
            IF NEW.service_code IS NULL OR NEW.service_code = '' THEN
                SET NEW.service_code = CONCAT(
                    'SVC-',
                    DATE_FORMAT(NOW(), '%Y%m%d'),
                    '-',
                    LPAD(
                        (
                            SELECT COALESCE(MAX(CAST(SUBSTRING(service_code, 14) AS UNSIGNED)), 0) + 1
                            FROM service_orders
                            WHERE service_code LIKE CONCAT('SVC-', DATE_FORMAT(NOW(), '%Y%m%d'), '-%')
                        ),
                        4,
                        '0'
                    )
                );
            END IF;
        END
    ");
}
function getInputData(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($input)) ? $input : [];
    }
    return $_POST ?: $_GET;
}

function normalizeIdList($raw): array {
    if ($raw === null || $raw === '' || $raw === 'null') return [];
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
    foreach ($raw as $v) {
        if ($v === null || $v === '' || $v === 'null') continue;
        $id = (int)$v;
        if ($id > 0) {
            $result[$id] = $id;
        }
    }
    return array_values($result);
}

function normalizeStatusValue($value, array $allowed, string $default): string {
    $val = strtolower(trim((string)($value ?? '')));
    if ($val === '' || !in_array($val, $allowed, true)) {
        return $default;
    }
    return $val;
}

function extractSelectedIds(array $input, string $singleKey, string $multiKey): array {
    $ids = normalizeIdList($input[$multiKey] ?? null);
    if (empty($ids)) {
        // Backward-compatible alias support
        $aliasMulti = str_replace(['battery_ids', 'inverter_ids'], ['product_battery_ids', 'product_inverter_ids'], $multiKey);
        if (isset($input[$aliasMulti])) {
            $ids = normalizeIdList($input[$aliasMulti]);
        }
    }
    if (empty($ids)) {
        $aliasSingle = str_replace(['battery_id', 'inverter_id'], ['product_battery_id', 'product_inverter_id'], $singleKey);
        if (isset($input[$aliasSingle])) {
            $ids = normalizeIdList($input[$aliasSingle]);
        }
    }
    if (empty($ids)) {
        $ids = normalizeIdList($input[$singleKey] ?? null);
    }
    return $ids;
}

function toNullableInt($value): ?int {
    if ($value === null || $value === '' || $value === 'null') return null;
    $n = (int)$value;
    return $n > 0 ? $n : null;
}

function assertExists(PDO $pdo, string $table, int $id, string $label): void {
    $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        throw new RuntimeException("Invalid {$label}: {$id}");
    }
}

function assertAllExist(PDO $pdo, string $table, array $ids, string $label): void {
    if (empty($ids)) return;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE id IN ({$ph})");
    $stmt->execute($ids);
    $found = array_map('intval', array_column($stmt->fetchAll(), 'id'));
    $missing = array_values(array_diff($ids, $found));
    if (!empty($missing)) {
        throw new RuntimeException("Invalid {$label} id(s): " . implode(', ', $missing));
    }
}

function getNextServiceCode(PDO $pdo): string {
    $datePrefix = date('Ymd');
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(service_code, 14) AS UNSIGNED)), 0) + 1 AS next_no FROM service_orders WHERE service_code LIKE ?");
    $stmt->execute(["SVC-{$datePrefix}-%"]);
    $next = (int)($stmt->fetch()['next_no'] ?? 1);
    return 'SVC-' . $datePrefix . '-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function saveServiceMappings(PDO $pdo, int $serviceId, array $batteryIds, array $inverterIds): void {
    $pdo->prepare("DELETE FROM service_order_batteries WHERE service_order_id = ?")->execute([$serviceId]);
    $pdo->prepare("DELETE FROM service_order_inverters WHERE service_order_id = ?")->execute([$serviceId]);

    if (!empty($batteryIds)) {
        $stmt = $pdo->prepare("INSERT INTO service_order_batteries (service_order_id, battery_id) VALUES (?, ?)");
        foreach ($batteryIds as $bid) {
            $stmt->execute([$serviceId, $bid]);
        }
    }

    if (!empty($inverterIds)) {
        $stmt = $pdo->prepare("INSERT INTO service_order_inverters (service_order_id, inverter_id) VALUES (?, ?)");
        foreach ($inverterIds as $iid) {
            $stmt->execute([$serviceId, $iid]);
        }
    }
}

function formatServiceRow(PDO $pdo, array $service): array {
    $serviceId = (int)$service['id'];

    $bStmt = $pdo->prepare("
        SELECT b.id, b.battery_model, b.battery_serial, b.brand, b.capacity, b.voltage, b.battery_type
        FROM service_order_batteries sob
        JOIN batteries b ON b.id = sob.battery_id
        WHERE sob.service_order_id = ?
        ORDER BY sob.id ASC
    ");
    $bStmt->execute([$serviceId]);
    $batteries = $bStmt->fetchAll();

    $iStmt = $pdo->prepare("
        SELECT i.id, i.inverter_model, i.inverter_serial, i.inverter_brand, i.power_rating, i.wave_type, i.battery_voltage
        FROM service_order_inverters soi
        JOIN inverters i ON i.id = soi.inverter_id
        WHERE soi.service_order_id = ?
        ORDER BY soi.id ASC
    ");
    $iStmt->execute([$serviceId]);
    $inverters = $iStmt->fetchAll();

    $service['battery_ids'] = array_map(fn($x) => (int)$x['id'], $batteries);
    $service['inverter_ids'] = array_map(fn($x) => (int)$x['id'], $inverters);

    if (empty($service['battery_ids']) && !empty($service['battery_ids_json'])) {
        $decoded = json_decode((string)$service['battery_ids_json'], true);
        if (is_array($decoded)) {
            $service['battery_ids'] = normalizeIdList($decoded);
        }
    }
    if (empty($service['inverter_ids']) && !empty($service['inverter_ids_json'])) {
        $decoded = json_decode((string)$service['inverter_ids_json'], true);
        if (is_array($decoded)) {
            $service['inverter_ids'] = normalizeIdList($decoded);
        }
    }
    $service['batteries'] = $batteries;
    $service['inverters'] = $inverters;
    $service['has_battery'] = !empty($service['battery_ids']);
    $service['has_inverter'] = !empty($service['inverter_ids']);

    if (!$service['has_battery'] && !empty($service['battery_id'])) {
        $service['battery_ids'] = [(int)$service['battery_id']];
        $service['has_battery'] = true;
    }
    if (!$service['has_inverter'] && !empty($service['inverter_id'])) {
        $service['inverter_ids'] = [(int)$service['inverter_id']];
        $service['has_inverter'] = true;
    }

    unset($service['battery_ids_json'], $service['inverter_ids_json']);
    return $service;
}

function getServiceOrderBaseById(PDO $pdo, int $id): ?array {
    $sql = "
        SELECT so.id, so.service_code, so.customer_id, so.customer_phone, so.battery_id, so.inverter_id,
               so.battery_ids AS battery_ids_json, so.inverter_ids AS inverter_ids_json,
               so.service_staff_id, so.warranty_status, so.amc_status, so.notes, so.created_at, so.updated_at,
               u.name AS staff_name, u.email AS staff_email,
               c.full_name AS customer_name, c.email AS customer_email, c.phone AS customer_phone_number,
               c.address AS customer_address, c.city AS customer_city
        FROM service_orders so
        LEFT JOIN users u ON so.service_staff_id = u.id
        LEFT JOIN customers c ON so.customer_id = c.id
        WHERE so.id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function getServiceOrderById(PDO $pdo, int $id): ?array {
    $base = getServiceOrderBaseById($pdo, $id);
    return $base ? formatServiceRow($pdo, $base) : null;
}

function handleGetRequest(PDO $pdo): void {
    $params = $_GET;
    $id = isset($params['id']) ? (int)$params['id'] : null;
    if ($id) {
        $service = getServiceOrderById($pdo, $id);
        if (!$service) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Service order not found']);
            return;
        }
        echo json_encode(['success' => true, 'data' => $service]);
        return;
    }

    $where = [];
    $qp = [];

    if (!empty($params['customer_id'])) {
        $where[] = 'so.customer_id = ?';
        $qp[] = (int)$params['customer_id'];
    }
    if (!empty($params['staff_id'])) {
        $where[] = 'so.service_staff_id = ?';
        $qp[] = (int)$params['staff_id'];
    }
    if (isset($params['battery_id']) && $params['battery_id'] !== '' && $params['battery_id'] !== 'null') {
        $where[] = "EXISTS (SELECT 1 FROM service_order_batteries sob WHERE sob.service_order_id = so.id AND sob.battery_id = ?)";
        $qp[] = (int)$params['battery_id'];
    }
    if (isset($params['inverter_id']) && $params['inverter_id'] !== '' && $params['inverter_id'] !== 'null') {
        $where[] = "EXISTS (SELECT 1 FROM service_order_inverters soi WHERE soi.service_order_id = so.id AND soi.inverter_id = ?)";
        $qp[] = (int)$params['inverter_id'];
    }
    if (!empty($params['warranty_status'])) {
        $where[] = 'so.warranty_status = ?';
        $qp[] = $params['warranty_status'];
    }
    if (!empty($params['amc_status'])) {
        $where[] = 'so.amc_status = ?';
        $qp[] = $params['amc_status'];
    }
    if (!empty($params['start_date'])) {
        $where[] = 'DATE(so.created_at) >= ?';
        $qp[] = $params['start_date'];
    }
    if (!empty($params['end_date'])) {
        $where[] = 'DATE(so.created_at) <= ?';
        $qp[] = $params['end_date'];
    }
    if (!empty($params['search'])) {
        $like = '%' . $params['search'] . '%';
        $where[] = "(so.service_code LIKE ? OR so.notes LIKE ? OR c.full_name LIKE ? OR so.customer_phone LIKE ?)";
        $qp[] = $like;
        $qp[] = $like;
        $qp[] = $like;
        $qp[] = $like;
    }

    $whereSql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
    $limit = isset($params['limit']) ? max(1, (int)$params['limit']) : 50;
    $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
    $offset = ($page - 1) * $limit;

    $countStmt = $pdo->prepare("SELECT COUNT(*) total FROM service_orders so LEFT JOIN customers c ON c.id = so.customer_id $whereSql");
    $countStmt->execute($qp);
    $total = (int)$countStmt->fetch()['total'];

    $sql = "
        SELECT so.id, so.service_code, so.customer_id, so.customer_phone, so.battery_id, so.inverter_id,
               so.battery_ids AS battery_ids_json, so.inverter_ids AS inverter_ids_json,
               so.service_staff_id, so.warranty_status, so.amc_status, so.notes, so.created_at, so.updated_at,
               u.name AS staff_name, u.email AS staff_email,
               c.full_name AS customer_name, c.email AS customer_email, c.phone AS customer_phone_number,
               c.address AS customer_address, c.city AS customer_city
        FROM service_orders so
        LEFT JOIN users u ON so.service_staff_id = u.id
        LEFT JOIN customers c ON so.customer_id = c.id
        $whereSql
        ORDER BY so.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $pdo->prepare($sql);
    $exec = $qp;
    $exec[] = $limit;
    $exec[] = $offset;
    $stmt->execute($exec);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row = formatServiceRow($pdo, $row);
    }

    echo json_encode([
        'success' => true,
        'data' => $rows,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $limit > 0 ? (int)ceil($total / $limit) : 1
        ]
    ]);
}

function handlePostRequest(PDO $pdo): void {
    $input = getInputData();
    if (empty($input['customer_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'customer_id is required']);
        return;
    }

    $batteryIds = extractSelectedIds($input, 'battery_id', 'battery_ids');
    $inverterIds = extractSelectedIds($input, 'inverter_id', 'inverter_ids');
    $primaryBatteryId = $batteryIds[0] ?? null;
    $primaryInverterId = $inverterIds[0] ?? null;

    try {
        $pdo->beginTransaction();

        $customerId = (int)$input['customer_id'];
        assertExists($pdo, 'customers', $customerId, 'customer_id');

        $serviceStaffId = toNullableInt($input['service_staff_id'] ?? null);
        if ($serviceStaffId !== null) {
            assertExists($pdo, 'users', $serviceStaffId, 'service_staff_id');
        }

        assertAllExist($pdo, 'batteries', $batteryIds, 'battery');
        assertAllExist($pdo, 'inverters', $inverterIds, 'inverter');

        $customerPhone = $input['customer_phone'] ?? null;
        if (empty($customerPhone)) {
            $cstmt = $pdo->prepare("SELECT phone FROM customers WHERE id = ?");
            $cstmt->execute([$customerId]);
            $c = $cstmt->fetch();
            $customerPhone = $c['phone'] ?? null;
        }

        $serviceCode = getNextServiceCode($pdo);

        $stmt = $pdo->prepare("
            INSERT INTO service_orders (
                service_code, customer_id, customer_phone, battery_id, inverter_id, battery_ids, inverter_ids, service_staff_id,
                warranty_status, amc_status, notes, created_at, updated_at
            ) VALUES (
                :service_code, :customer_id, :customer_phone, :battery_id, :inverter_id, :battery_ids, :inverter_ids, :service_staff_id,
                :warranty_status, :amc_status, :notes, NOW(), NOW()
            )
        ");
        $stmt->execute([
            ':service_code' => $serviceCode,
            ':customer_id' => $customerId,
            ':customer_phone' => $customerPhone,
            ':battery_id' => $primaryBatteryId,
            ':inverter_id' => $primaryInverterId,
            ':battery_ids' => json_encode($batteryIds),
            ':inverter_ids' => json_encode($inverterIds),
            ':service_staff_id' => $serviceStaffId,
            ':warranty_status' => normalizeStatusValue(
                $input['warranty_status'] ?? null,
                ['in_warranty', 'extended_warranty', 'out_of_warranty'],
                'out_of_warranty'
            ),
            ':amc_status' => normalizeStatusValue(
                $input['amc_status'] ?? null,
                ['active', 'expired', 'no_amc'],
                'no_amc'
            ),
            ':notes' => isset($input['notes']) ? trim((string)$input['notes']) : null
        ]);

        $serviceId = (int)$pdo->lastInsertId();
        saveServiceMappings($pdo, $serviceId, $batteryIds, $inverterIds);
        $service = getServiceOrderById($pdo, $serviceId);

        $pdo->commit();
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Service order created successfully',
            'service_id' => $serviceId,
            'service_code' => $serviceCode,
            'data' => $service
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create service order', 'error' => $e->getMessage()]);
    }
}

function handlePutRequest(PDO $pdo): void {
    $input = getInputData();
    $id = !empty($input['id']) ? (int)$input['id'] : (!empty($_GET['id']) ? (int)$_GET['id'] : 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Service order ID is required']);
        return;
    }

    $existing = getServiceOrderById($pdo, $id);
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Service order not found']);
        return;
    }

    try {
        $pdo->beginTransaction();

        $batteryIds = array_key_exists('battery_ids', $input) || array_key_exists('battery_id', $input)
            ? extractSelectedIds($input, 'battery_id', 'battery_ids')
            : $existing['battery_ids'];
        $inverterIds = array_key_exists('inverter_ids', $input) || array_key_exists('inverter_id', $input)
            ? extractSelectedIds($input, 'inverter_id', 'inverter_ids')
            : $existing['inverter_ids'];

        if (array_key_exists('customer_id', $input) && toNullableInt($input['customer_id']) !== null) {
            assertExists($pdo, 'customers', (int)$input['customer_id'], 'customer_id');
        }

        if (array_key_exists('service_staff_id', $input)) {
            $staffId = toNullableInt($input['service_staff_id']);
            if ($staffId !== null) {
                assertExists($pdo, 'users', $staffId, 'service_staff_id');
            }
        }

        assertAllExist($pdo, 'batteries', $batteryIds, 'battery');
        assertAllExist($pdo, 'inverters', $inverterIds, 'inverter');

        $fields = [];
        $params = [':id' => $id];

        $updatable = ['customer_id', 'customer_phone', 'service_staff_id', 'warranty_status', 'amc_status', 'notes'];
        foreach ($updatable as $field) {
            if (array_key_exists($field, $input)) {
                $fields[] = "$field = :$field";
                if (in_array($field, ['customer_id', 'service_staff_id'], true)) {
                    $params[":$field"] = toNullableInt($input[$field]);
                } elseif ($field === 'warranty_status') {
                    $params[":$field"] = normalizeStatusValue(
                        $input[$field],
                        ['in_warranty', 'extended_warranty', 'out_of_warranty'],
                        'out_of_warranty'
                    );
                } elseif ($field === 'amc_status') {
                    $params[":$field"] = normalizeStatusValue(
                        $input[$field],
                        ['active', 'expired', 'no_amc'],
                        'no_amc'
                    );
                } else {
                    $params[":$field"] = ($input[$field] === '') ? null : $input[$field];
                }
            }
        }

        $fields[] = 'battery_id = :battery_id';
        $fields[] = 'inverter_id = :inverter_id';
        $fields[] = 'battery_ids = :battery_ids';
        $fields[] = 'inverter_ids = :inverter_ids';
        $fields[] = 'updated_at = NOW()';
        $params[':battery_id'] = $batteryIds[0] ?? null;
        $params[':inverter_id'] = $inverterIds[0] ?? null;
        $params[':battery_ids'] = json_encode($batteryIds);
        $params[':inverter_ids'] = json_encode($inverterIds);

        $sql = 'UPDATE service_orders SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        saveServiceMappings($pdo, $id, $batteryIds, $inverterIds);
        $updated = getServiceOrderById($pdo, $id);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Service order updated successfully', 'data' => $updated]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update service order', 'error' => $e->getMessage()]);
    }
}

function handleDeleteRequest(PDO $pdo): void {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        $input = getInputData();
        $id = isset($input['id']) ? (int)$input['id'] : 0;
    }
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valid service order ID is required']);
        return;
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM service_orders WHERE id = ?')->execute([$id]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Service order deleted successfully']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete service order', 'error' => $e->getMessage()]);
    }
}
?>


