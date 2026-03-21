<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$page_title = 'Service Cards';

// Fetch all services with customer, battery, and staff details
$query = "SELECT s.*, 
          c.name as customer_name, c.phone as customer_phone, c.email as customer_email, c.address as customer_address,
          b.model as battery_model, b.serial_number as battery_serial, b.brand as battery_brand, 
          b.capacity as battery_capacity, b.voltage as battery_voltage, b.type as battery_type,
          b.purchase_date, b.installation_date, b.warranty as battery_warranty,
          u.name as staff_name, u.email as staff_email
          FROM services s
          LEFT JOIN customers c ON s.customer_id = c.id
          LEFT JOIN batteries b ON s.battery_id = b.id
          LEFT JOIN users u ON s.service_staff_id = u.id
          ORDER BY s.created_at DESC";

$result = mysqli_query($conn, $query);

// Handle water service submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_water_service'])) {
    $service_id = mysqli_real_escape_string($conn, $_POST['service_id']);
    $water_service_amount = mysqli_real_escape_string($conn, $_POST['water_service_amount']);
    $water_service_date = mysqli_real_escape_string($conn, $_POST['water_service_date']);
    $water_notes = mysqli_real_escape_string($conn, $_POST['water_notes']);
    
    // Insert water service record
    $insert_query = "INSERT INTO water_services (service_id, amount, service_date, notes, created_by, created_at) 
                     VALUES ('$service_id', '$water_service_amount', '$water_service_date', '$water_notes', '{$_SESSION['user_id']}', NOW())";
    
    if (mysqli_query($conn, $insert_query)) {
        $success_message = "Water service added successfully!";
    } else {
        $error_message = "Error adding water service: " . mysqli_error($conn);
    }
}

// Handle delete water service
if (isset($_GET['delete_water_service'])) {
    $water_service_id = mysqli_real_escape_string($conn, $_GET['delete_water_service']);
    $delete_query = "DELETE FROM water_services WHERE id = '$water_service_id'";
    
    if (mysqli_query($conn, $delete_query)) {
        $success_message = "Water service deleted successfully!";
    } else {
        $error_message = "Error deleting water service: " . mysqli_error($conn);
    }
}

// Fetch water services for all services
$water_services_query = "SELECT ws.*, s.service_code, c.name as customer_name 
                        FROM water_services ws
                        JOIN services s ON ws.service_id = s.id
                        JOIN customers c ON s.customer_id = c.id
                        ORDER BY ws.service_date DESC";
$water_services_result = mysqli_query($conn, $water_services_query);
$water_services = [];
while ($row = mysqli_fetch_assoc($water_services_result)) {
    $water_services[$row['service_id']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Sun Office</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .service-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .service-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            transform: translateY(-2px);
        }
        
        .service-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        
        .service-body {
            padding: 20px;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        }
        
        .info-value {
            color: #212529;
            margin-bottom: 10px;
        }
        
        .badge-warranty {
            background-color: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
        }
        
        .badge-amc {
            background-color: #17a2b8;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
        }
        
        .water-service-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .water-service-item {
            background: white;
            border-left: 4px solid #28a745;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .btn-add-water {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .btn-add-water:hover {
            transform: scale(1.05);
            color: white;
        }
        
        .water-amount {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
        }
        
        .modal-content {
            border-radius: 15px;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }
        
        .btn-close {
            filter: brightness(0) invert(1);
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="bi bi-card-list"></i> Service Cards
                        <small class="text-muted fs-6">Manage all service records</small>
                    </h2>
                    <div>
                        <span class="badge bg-primary p-3">
                            <i class="bi bi-water"></i> Total Water Services: <?php echo count($water_services, COUNT_RECURSIVE) - count($water_services); ?>
                        </span>
                    </div>
                </div>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <?php if (mysqli_num_rows($result) > 0): ?>
                        <?php while ($service = mysqli_fetch_assoc($result)): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="service-card">
                                    <div class="service-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0">
                                                <i class="bi bi-tools"></i> <?php echo htmlspecialchars($service['service_code']); ?>
                                            </h5>
                                            <span class="badge bg-light text-dark">
                                                <i class="bi bi-calendar"></i> <?php echo date('d M Y', strtotime($service['created_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="service-body">
                                        <!-- Customer Info -->
                                        <div class="row mb-3">
                                            <div class="col-12">
                                                <div class="info-label">
                                                    <i class="bi bi-person"></i> Customer:
                                                </div>
                                                <div class="info-value fw-bold">
                                                    <?php echo htmlspecialchars($service['customer_name'] ?? 'N/A'); ?>
                                                </div>
                                                <div class="info-value small">
                                                    <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($service['customer_phone'] ?? 'N/A'); ?><br>
                                                    <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($service['customer_email'] ?? 'N/A'); ?><br>
                                                    <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($service['customer_address'] ?? 'N/A'); ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Battery Info -->
                                        <div class="row mb-3">
                                            <div class="col-12">
                                                <div class="info-label">
                                                    <i class="bi bi-battery"></i> Battery:
                                                </div>
                                                <div class="info-value fw-bold">
                                                    <?php echo htmlspecialchars($service['battery_model'] ?? 'N/A'); ?>
                                                </div>
                                                <div class="info-value small">
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($service['battery_brand'] ?? 'N/A'); ?></span>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($service['battery_capacity'] ?? 'N/A'); ?></span>
                                                    <span class="badge bg-warning"><?php echo htmlspecialchars($service['battery_voltage'] ?? 'N/A'); ?></span>
                                                    <span class="badge bg-dark"><?php echo htmlspecialchars($service['battery_type'] ?? 'N/A'); ?></span>
                                                </div>
                                                <div class="info-value small mt-1">
                                                    <i class="bi bi-upc-scan"></i> Serial: <?php echo htmlspecialchars($service['battery_serial'] ?? 'N/A'); ?><br>
                                                    <i class="bi bi-calendar-check"></i> Installed: <?php echo date('d M Y', strtotime($service['installation_date'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Status Badges -->
                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <span class="badge <?php 
                                                    echo $service['warranty_status'] == 'in_warranty' ? 'bg-success' : 
                                                        ($service['warranty_status'] == 'extended_warranty' ? 'bg-warning' : 'bg-danger'); 
                                                ?> p-2 w-100">
                                                    <i class="bi bi-shield-check"></i> 
                                                    <?php echo ucwords(str_replace('_', ' ', $service['warranty_status'] ?? 'N/A')); ?>
                                                </span>
                                            </div>
                                            <div class="col-6">
                                                <span class="badge <?php 
                                                    echo $service['amc_status'] == 'active' ? 'bg-success' : 
                                                        ($service['amc_status'] == 'expired' ? 'bg-warning' : 'bg-secondary'); 
                                                ?> p-2 w-100">
                                                    <i class="bi bi-clock-history"></i> 
                                                    AMC: <?php echo ucwords(str_replace('_', ' ', $service['amc_status'] ?? 'N/A')); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <!-- Staff Info -->
                                        <div class="row mb-3">
                                            <div class="col-12">
                                                <div class="info-label">
                                                    <i class="bi bi-person-badge"></i> Service Staff:
                                                </div>
                                                <div class="info-value">
                                                    <?php echo htmlspecialchars($service['staff_name'] ?? 'Unassigned'); ?>
                                                    <?php if ($service['staff_email']): ?>
                                                        <br><small><?php echo htmlspecialchars($service['staff_email']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Notes -->
                                        <?php if (!empty($service['notes'])): ?>
                                            <div class="row mb-3">
                                                <div class="col-12">
                                                    <div class="info-label">
                                                        <i class="bi bi-chat"></i> Notes:
                                                    </div>
                                                    <div class="info-value p-2 bg-light rounded">
                                                        <?php echo htmlspecialchars($service['notes']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Water Service Section -->
                                        <div class="water-service-section">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="mb-0">
                                                    <i class="bi bi-water"></i> Water Services
                                                </h6>
                                                <button type="button" class="btn btn-sm btn-add-water" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#addWaterModal<?php echo $service['id']; ?>">
                                                    <i class="bi bi-plus-circle"></i> Add Water Service
                                                </button>
                                            </div>
                                            
                                            <!-- Water Service History -->
                                            <div class="water-service-list">
                                                <?php if (isset($water_services[$service['id']])): ?>
                                                    <?php foreach ($water_services[$service['id']] as $ws): ?>
                                                        <div class="water-service-item">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <span class="water-amount">
                                                                        ₹<?php echo number_format($ws['amount'], 2); ?>
                                                                    </span>
                                                                    <br>
                                                                    <small class="text-muted">
                                                                        <i class="bi bi-calendar"></i> 
                                                                        <?php echo date('d M Y', strtotime($ws['service_date'])); ?>
                                                                    </small>
                                                                    <?php if (!empty($ws['notes'])): ?>
                                                                        <br>
                                                                        <small>
                                                                            <i class="bi bi-chat"></i> 
                                                                            <?php echo htmlspecialchars($ws['notes']); ?>
                                                                        </small>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="dropdown">
                                                                    <button class="btn btn-sm btn-outline-secondary" 
                                                                            type="button" 
                                                                            data-bs-toggle="dropdown">
                                                                        <i class="bi bi-three-dots-vertical"></i>
                                                                    </button>
                                                                    <ul class="dropdown-menu">
                                                                        <li>
                                                                            <a class="dropdown-item text-danger" 
                                                                               href="?delete_water_service=<?php echo $ws['id']; ?>" 
                                                                               onclick="return confirm('Are you sure you want to delete this water service?');">
                                                                                <i class="bi bi-trash"></i> Delete
                                                                            </a>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p class="text-muted text-center mb-0 small">
                                                        No water services added yet
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Add Water Service Modal -->
                            <div class="modal fade" id="addWaterModal<?php echo $service['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="bi bi-water"></i> Add Water Service
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="">
                                            <div class="modal-body">
                                                <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Service Code</label>
                                                    <input type="text" class="form-control" 
                                                           value="<?php echo htmlspecialchars($service['service_code']); ?>" 
                                                           readonly disabled>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Customer</label>
                                                    <input type="text" class="form-control" 
                                                           value="<?php echo htmlspecialchars($service['customer_name'] ?? 'N/A'); ?>" 
                                                           readonly disabled>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="water_service_amount" class="form-label">
                                                        Service Amount (₹) <span class="text-danger">*</span>
                                                    </label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">₹</span>
                                                        <input type="number" step="0.01" min="0" 
                                                               class="form-control" 
                                                               id="water_service_amount" 
                                                               name="water_service_amount" 
                                                               required 
                                                               placeholder="Enter amount">
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="water_service_date" class="form-label">
                                                        Service Date <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="date" 
                                                           class="form-control" 
                                                           id="water_service_date" 
                                                           name="water_service_date" 
                                                           value="<?php echo date('Y-m-d'); ?>" 
                                                           required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="water_notes" class="form-label">
                                                        Notes
                                                    </label>
                                                    <textarea class="form-control" 
                                                              id="water_notes" 
                                                              name="water_notes" 
                                                              rows="2" 
                                                              placeholder="Enter any notes about the water service"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    Cancel
                                                </button>
                                                <button type="submit" name="add_water_service" class="btn btn-success">
                                                    <i class="bi bi-check-circle"></i> Add Water Service
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> No service records found.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>