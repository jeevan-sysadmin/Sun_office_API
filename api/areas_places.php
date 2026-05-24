<?php
// areas_places.php - API for Areas and Places management

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration
$host = "127.0.0.1";
$dbname = "sun_office";
$username = "root";
$password = "";

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get query parameters
$params = $_GET;

// Determine endpoint from query parameter or URL path
$endpoint = isset($params['endpoint']) ? $params['endpoint'] : '';

// Remove endpoint from params if it exists
if (isset($params['endpoint'])) {
    unset($params['endpoint']);
}

// If no endpoint specified, try to determine from URL
if (empty($endpoint)) {
    $request_uri = $_SERVER['REQUEST_URI'];
    $path = parse_url($request_uri, PHP_URL_PATH);
    
    // Remove the script name
    $script_name = $_SERVER['SCRIPT_NAME'];
    $relative_path = str_replace(dirname($script_name), '', $path);
    $relative_path = trim($relative_path, '/');
    
    // Check if this is the main file without parameters
    if (empty($relative_path) || $relative_path === 'areas_places.php') {
        $endpoint = 'areas'; // Default endpoint
    }
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    switch ($method) {
        case 'GET':
            handleGetRequest($pdo, $endpoint, $params);
            break;
            
        case 'POST':
            handlePostRequest($pdo, $endpoint);
            break;
            
        case 'PUT':
            handlePutRequest($pdo, $endpoint);
            break;
            
        case 'DELETE':
            handleDeleteRequest($pdo, $endpoint);
            break;
            
        default:
            sendResponse(405, "Method not allowed");
            break;
    }
    
} catch (PDOException $e) {
    sendResponse(500, "Database error: " . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, "Server error: " . $e->getMessage());
}

function handleGetRequest($pdo, $endpoint, $params) {
    switch ($endpoint) {
        case '':
        case 'areas':
            getAreas($pdo, $params);
            break;
            
        case 'places':
            getPlaces($pdo, $params);
            break;
            
        case 'area-places':
            getAreaWithPlaces($pdo, $params);
            break;
            
        case 'popular-places':
            getPopularPlaces($pdo, $params);
            break;
            
        case 'search-places':
            searchPlaces($pdo, $params);
            break;
            
        case 'stats':
            getStats($pdo, $params);
            break;
            
        case 'help':
        case 'endpoints':
            showAvailableEndpoints();
            break;
            
        default:
            sendResponse(400, "Invalid endpoint. Use 'help' to see available endpoints.", [
                'available_endpoints' => [
                    'areas' => 'Get all areas',
                    'places' => 'Get all places',
                    'area-places' => 'Get area with its places',
                    'popular-places' => 'Get popular places',
                    'search-places' => 'Search places',
                    'stats' => 'Get statistics',
                    'help' => 'Show available endpoints'
                ]
            ]);
            break;
    }
}

function getAreas($pdo, $params) {
    try {
        $sql = "SELECT * FROM areas WHERE 1=1";
        $conditions = [];
        $values = [];
        
        // Add filters
        if (isset($params['is_active']) && $params['is_active'] !== '') {
            $conditions[] = "is_active = ?";
            $values[] = (int)filter_var($params['is_active'], FILTER_VALIDATE_BOOLEAN);
        }
        
        if (isset($params['district_code']) && !empty($params['district_code'])) {
            $conditions[] = "district_code = ?";
            $values[] = $params['district_code'];
        }
        
        if (isset($params['search']) && !empty($params['search'])) {
            $conditions[] = "(district_name LIKE ? OR headquarters LIKE ? OR description LIKE ?)";
            $searchTerm = "%{$params['search']}%";
            array_push($values, $searchTerm, $searchTerm, $searchTerm);
        }
        
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY district_name";
        
        // Add pagination
        $limit = isset($params['limit']) ? (int)$params['limit'] : 0;
        $offset = isset($params['offset']) ? (int)$params['offset'] : 0;
        
        if ($limit > 0) {
            $sql .= " LIMIT ? OFFSET ?";
            $values[] = $limit;
            $values[] = $offset;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get place count for each area
        foreach ($areas as &$area) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as place_count FROM places WHERE area_id = ? AND is_active = 1");
            $stmt->execute([$area['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $area['place_count'] = (int)$result['place_count'];
            
            // Get top place
            $stmt = $pdo->prepare("SELECT place_name FROM places WHERE area_id = ? AND is_active = 1 ORDER BY popularity_rank ASC LIMIT 1");
            $stmt->execute([$area['id']]);
            $topPlace = $stmt->fetch(PDO::FETCH_ASSOC);
            $area['top_place'] = $topPlace ? $topPlace['place_name'] : null;
            
            // Format numeric values
            $area['population'] = (int)$area['population'];
            $area['is_active'] = (bool)$area['is_active'];
        }
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM areas WHERE 1=1";
        if (!empty($conditions)) {
            $countSql .= " AND " . implode(" AND ", $conditions);
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute(array_slice($values, 0, count($values) - ($limit > 0 ? 2 : 0)));
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        sendResponse(200, "Areas retrieved successfully", [
            'count' => count($areas),
            'total' => (int)$totalCount,
            'areas' => $areas,
            'filters' => $params,
            'pagination' => [
                'limit' => $limit > 0 ? $limit : 'none',
                'offset' => $offset
            ]
        ]);
        
    } catch (Exception $e) {
        sendResponse(500, "Error retrieving areas: " . $e->getMessage());
    }
}

function getPlaces($pdo, $params) {
    try {
        $sql = "SELECT p.*, a.district_name, a.district_code, a.headquarters as district_headquarters 
                FROM places p 
                LEFT JOIN areas a ON p.area_id = a.id 
                WHERE 1=1";
        $conditions = [];
        $values = [];
        
        // Add filters
        if (isset($params['is_active']) && $params['is_active'] !== '') {
            $conditions[] = "p.is_active = ?";
            $values[] = (int)filter_var($params['is_active'], FILTER_VALIDATE_BOOLEAN);
        }
        
        if (isset($params['area_id']) && !empty($params['area_id'])) {
            $conditions[] = "p.area_id = ?";
            $values[] = (int)$params['area_id'];
        }
        
        if (isset($params['district_code']) && !empty($params['district_code'])) {
            $conditions[] = "a.district_code = ?";
            $values[] = $params['district_code'];
        }
        
        if (isset($params['place_type']) && !empty($params['place_type'])) {
            $conditions[] = "p.place_type = ?";
            $values[] = $params['place_type'];
        }
        
        if (isset($params['popularity_min']) && is_numeric($params['popularity_min'])) {
            $conditions[] = "p.popularity_rank >= ?";
            $values[] = (int)$params['popularity_min'];
        }
        
        if (isset($params['popularity_max']) && is_numeric($params['popularity_max'])) {
            $conditions[] = "p.popularity_rank <= ?";
            $values[] = (int)$params['popularity_max'];
        }
        
        if (isset($params['search']) && !empty($params['search'])) {
            $conditions[] = "(p.place_name LIKE ? OR p.pincode LIKE ? OR p.special_notes LIKE ? OR a.district_name LIKE ?)";
            $searchTerm = "%{$params['search']}%";
            array_push($values, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
        }
        
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY p.popularity_rank ASC, p.place_name ASC";
        
        // Add pagination
        $limit = isset($params['limit']) ? (int)$params['limit'] : 0;
        $offset = isset($params['offset']) ? (int)$params['offset'] : 0;
        
        if ($limit > 0) {
            $sql .= " LIMIT ? OFFSET ?";
            $values[] = $limit;
            $values[] = $offset;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        $places = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data
        foreach ($places as &$place) {
            // Format numeric values
            $place['id'] = (int)$place['id'];
            $place['area_id'] = (int)$place['area_id'];
            $place['popularity_rank'] = (int)$place['popularity_rank'];
            $place['population'] = $place['population'] !== null ? (int)$place['population'] : null;
            $place['is_active'] = (bool)$place['is_active'];
            
            // Format coordinates
            $place['latitude'] = $place['latitude'] !== null ? (float)$place['latitude'] : null;
            $place['longitude'] = $place['longitude'] !== null ? (float)$place['longitude'] : null;
        }
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM places p LEFT JOIN areas a ON p.area_id = a.id WHERE 1=1";
        if (!empty($conditions)) {
            $countSql .= " AND " . implode(" AND ", $conditions);
        }
        
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute(array_slice($values, 0, count($values) - ($limit > 0 ? 2 : 0)));
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        sendResponse(200, "Places retrieved successfully", [
            'count' => count($places),
            'total' => (int)$totalCount,
            'places' => $places,
            'filters' => $params,
            'pagination' => [
                'limit' => $limit > 0 ? $limit : 'none',
                'offset' => $offset
            ]
        ]);
        
    } catch (Exception $e) {
        sendResponse(500, "Error retrieving places: " . $e->getMessage());
    }
}

function getAreaWithPlaces($pdo, $params) {
    try {
        if (!isset($params['id']) && !isset($params['district_code'])) {
            sendResponse(400, "Missing required parameter: 'id' or 'district_code'");
            return;
        }
        
        // Build WHERE clause
        $whereClause = "";
        $whereValue = null;
        
        if (isset($params['id'])) {
            $whereClause = "id = ?";
            $whereValue = (int)$params['id'];
        } elseif (isset($params['district_code'])) {
            $whereClause = "district_code = ?";
            $whereValue = $params['district_code'];
        }
        
        // Get area
        $sql = "SELECT * FROM areas WHERE $whereClause";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$whereValue]);
        $area = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$area) {
            sendResponse(404, "Area not found");
            return;
        }
        
        // Format area data
        $area['id'] = (int)$area['id'];
        $area['population'] = (int)$area['population'];
        $area['is_active'] = (bool)$area['is_active'];
        
        // Get places for this area
        $sql = "SELECT * FROM places WHERE area_id = ? AND is_active = 1 ORDER BY popularity_rank ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$area['id']]);
        $places = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format places data
        foreach ($places as &$place) {
            $place['id'] = (int)$place['id'];
            $place['area_id'] = (int)$place['area_id'];
            $place['popularity_rank'] = (int)$place['popularity_rank'];
            $place['population'] = $place['population'] !== null ? (int)$place['population'] : null;
            $place['is_active'] = (bool)$place['is_active'];
            $place['latitude'] = $place['latitude'] !== null ? (float)$place['latitude'] : null;
            $place['longitude'] = $place['longitude'] !== null ? (float)$place['longitude'] : null;
        }
        
        $area['places'] = $places;
        $area['places_count'] = count($places);
        
        sendResponse(200, "Area with places retrieved successfully", $area);
        
    } catch (Exception $e) {
        sendResponse(500, "Error retrieving area with places: " . $e->getMessage());
    }
}

function getPopularPlaces($pdo, $params) {
    try {
        $limit = isset($params['limit']) ? (int)$params['limit'] : 10;
        $limit = min($limit, 100); // Max 100 results
        
        $sql = "SELECT p.*, a.district_name, a.district_code, a.headquarters as district_headquarters 
                FROM places p 
                LEFT JOIN areas a ON p.area_id = a.id 
                WHERE p.is_active = 1 
                ORDER BY p.popularity_rank ASC 
                LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        $places = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data
        foreach ($places as &$place) {
            $place['id'] = (int)$place['id'];
            $place['area_id'] = (int)$place['area_id'];
            $place['popularity_rank'] = (int)$place['popularity_rank'];
            $place['population'] = $place['population'] !== null ? (int)$place['population'] : null;
            $place['is_active'] = (bool)$place['is_active'];
            $place['latitude'] = $place['latitude'] !== null ? (float)$place['latitude'] : null;
            $place['longitude'] = $place['longitude'] !== null ? (float)$place['longitude'] : null;
        }
        
        sendResponse(200, "Popular places retrieved successfully", [
            'count' => count($places),
            'limit' => $limit,
            'places' => $places
        ]);
        
    } catch (Exception $e) {
        sendResponse(500, "Error retrieving popular places: " . $e->getMessage());
    }
}

function searchPlaces($pdo, $params) {
    try {
        if (!isset($params['q']) || empty(trim($params['q']))) {
            sendResponse(400, "Missing search query parameter 'q'");
            return;
        }
        
        $searchTerm = "%" . trim($params['q']) . "%";
        $limit = isset($params['limit']) ? (int)$params['limit'] : 20;
        $limit = min($limit, 50); // Max 50 results
        
        $sql = "SELECT p.*, a.district_name, a.district_code, a.headquarters as district_headquarters 
                FROM places p 
                LEFT JOIN areas a ON p.area_id = a.id 
                WHERE (p.place_name LIKE ? OR p.pincode LIKE ? OR p.special_notes LIKE ? 
                       OR a.district_name LIKE ? OR a.headquarters LIKE ?)
                AND p.is_active = 1 
                ORDER BY p.popularity_rank ASC 
                LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
        $places = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data
        foreach ($places as &$place) {
            $place['id'] = (int)$place['id'];
            $place['area_id'] = (int)$place['area_id'];
            $place['popularity_rank'] = (int)$place['popularity_rank'];
            $place['population'] = $place['population'] !== null ? (int)$place['population'] : null;
            $place['is_active'] = (bool)$place['is_active'];
            $place['latitude'] = $place['latitude'] !== null ? (float)$place['latitude'] : null;
            $place['longitude'] = $place['longitude'] !== null ? (float)$place['longitude'] : null;
        }
        
        sendResponse(200, "Search results retrieved successfully", [
            'search_term' => trim($params['q']),
            'count' => count($places),
            'limit' => $limit,
            'places' => $places
        ]);
        
    } catch (Exception $e) {
        sendResponse(500, "Error searching places: " . $e->getMessage());
    }
}

function getStats($pdo, $params) {
    try {
        // Get total areas
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_areas FROM areas WHERE is_active = 1");
        $stmt->execute();
        $totalAreas = $stmt->fetch(PDO::FETCH_ASSOC)['total_areas'];
        
        // Get total places
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_places FROM places WHERE is_active = 1");
        $stmt->execute();
        $totalPlaces = $stmt->fetch(PDO::FETCH_ASSOC)['total_places'];
        
        // Get places by type
        $stmt = $pdo->prepare("SELECT place_type, COUNT(*) as count FROM places WHERE is_active = 1 GROUP BY place_type ORDER BY count DESC");
        $stmt->execute();
        $placesByType = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get areas with most places
        $stmt = $pdo->prepare("
            SELECT a.district_name, a.district_code, COUNT(p.id) as place_count 
            FROM areas a 
            LEFT JOIN places p ON a.id = p.area_id AND p.is_active = 1 
            WHERE a.is_active = 1 
            GROUP BY a.id 
            ORDER BY place_count DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $areasWithMostPlaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get most popular places
        $stmt = $pdo->prepare("
            SELECT p.place_name, a.district_name, p.popularity_rank 
            FROM places p 
            LEFT JOIN areas a ON p.area_id = a.id 
            WHERE p.is_active = 1 
            ORDER BY p.popularity_rank ASC 
            LIMIT 5
        ");
        $stmt->execute();
        $mostPopularPlaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format numeric values
        $totalAreas = (int)$totalAreas;
        $totalPlaces = (int)$totalPlaces;
        
        foreach ($placesByType as &$type) {
            $type['count'] = (int)$type['count'];
        }
        
        foreach ($areasWithMostPlaces as &$area) {
            $area['place_count'] = (int)$area['place_count'];
        }
        
        foreach ($mostPopularPlaces as &$place) {
            $place['popularity_rank'] = (int)$place['popularity_rank'];
        }
        
        sendResponse(200, "Statistics retrieved successfully", [
            'totals' => [
                'areas' => $totalAreas,
                'places' => $totalPlaces
            ],
            'places_by_type' => $placesByType,
            'areas_with_most_places' => $areasWithMostPlaces,
            'most_popular_places' => $mostPopularPlaces,
            'generated_at' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        sendResponse(500, "Error retrieving statistics: " . $e->getMessage());
    }
}

function showAvailableEndpoints() {
    $endpoints = [
        'GET Endpoints' => [
            'areas' => [
                'url' => '?endpoint=areas',
                'description' => 'Get all areas',
                'parameters' => [
                    'is_active' => 'Filter by active status (true/false)',
                    'district_code' => 'Filter by district code (TVL, TUT, KK, TSI)',
                    'search' => 'Search in district name, headquarters, or description',
                    'limit' => 'Limit results (number)',
                    'offset' => 'Offset for pagination (number)'
                ]
            ],
            'places' => [
                'url' => '?endpoint=places',
                'description' => 'Get all places',
                'parameters' => [
                    'is_active' => 'Filter by active status',
                    'area_id' => 'Filter by area ID',
                    'district_code' => 'Filter by district code',
                    'place_type' => 'Filter by place type (city, town, tourist, etc.)',
                    'popularity_min' => 'Minimum popularity rank',
                    'popularity_max' => 'Maximum popularity rank',
                    'search' => 'Search in place name, pincode, or special notes',
                    'limit' => 'Limit results',
                    'offset' => 'Offset for pagination'
                ]
            ],
            'area-places' => [
                'url' => '?endpoint=area-places&id=1 OR ?endpoint=area-places&district_code=TVL',
                'description' => 'Get area with its places',
                'parameters' => [
                    'id' => 'Area ID (required if no district_code)',
                    'district_code' => 'District code (required if no id)'
                ]
            ],
            'popular-places' => [
                'url' => '?endpoint=popular-places',
                'description' => 'Get most popular places',
                'parameters' => [
                    'limit' => 'Number of results (default: 10, max: 100)'
                ]
            ],
            'search-places' => [
                'url' => '?endpoint=search-places&q=tirunel',
                'description' => 'Search places',
                'parameters' => [
                    'q' => 'Search query (required)',
                    'limit' => 'Number of results (default: 20, max: 50)'
                ]
            ],
            'stats' => [
                'url' => '?endpoint=stats',
                'description' => 'Get statistics',
                'parameters' => 'No parameters'
            ]
        ],
        'Database Info' => [
            'database' => 'sun_office',
            'tables' => ['areas', 'places'],
            'current_time' => date('Y-m-d H:i:s')
        ]
    ];
    
    sendResponse(200, "Available endpoints", $endpoints);
}

function handlePostRequest($pdo, $endpoint) {
    sendResponse(501, "POST method not implemented yet. Use GET endpoints.");
}

function handlePutRequest($pdo, $endpoint) {
    sendResponse(501, "PUT method not implemented yet. Use GET endpoints.");
}

function handleDeleteRequest($pdo, $endpoint) {
    sendResponse(501, "DELETE method not implemented yet. Use GET endpoints.");
}

function sendResponse($code, $message, $data = null) {
    http_response_code($code);
    
    $response = [
        'success' => $code >= 200 && $code < 300,
        'message' => $message,
        'timestamp' => date('c'),
        'status' => $code
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>