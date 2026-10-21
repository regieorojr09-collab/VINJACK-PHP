<?php
declare(strict_types=1);

/**
 * VinJack Motorworks - Front Controller & API Gateway
 *
 * Intercepts all incoming API requests, sets secure response headers,
 * manages global error handling, and dispatches to the OOP Router.
 */

// 1. Configure Error & Exception Handling
error_reporting(E_ALL);
ini_set('display_errors', '0');

set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

set_exception_handler(function(\Throwable $exception) {
    $isDbError = ($exception instanceof \PDOException) || 
                 str_contains($exception->getMessage(), 'Database connection failure') || 
                 str_contains($exception->getMessage(), 'SQLSTATE');
    $statusCode = $isDbError ? 503 : 500;

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage() ?: 'Internal Server Error',
        'error'   => $exception->getMessage()
    ]);
    exit();
});

// 2. Set Default Response Headers
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// 3. Load Core Dependencies
require_once __DIR__ . '/middleware/CorsMiddleware.php';
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/JWT.php';
require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/controllers/AuthController.php';

// Handle CORS
CorsMiddleware::handle();

use Core\Router;
use Middleware\AuthMiddleware;
use Controllers\AuthController;

$router = new Router();

// ==========================================
// 4. Register Authentication & Diagnostic Endpoints
// ==========================================
// POST /api/auth/login (Lazy instantiation)
$router->post('/auth/login', [AuthController::class, 'login']);

// POST /api/auth/demo-login
$router->post('/auth/demo-login', [AuthController::class, 'demoLogin']);

// GET /api/auth/me (Protected by AuthMiddleware)
$router->get('/auth/me', [AuthController::class, 'me'], [
    [AuthMiddleware::class, 'authenticate']
]);

// GET /api/health - Cloud & DevOps connectivity diagnostic endpoint
$router->get('/health', function() {
    $dbStatus = \Core\Database::checkConnection();
    $statusCode = $dbStatus['connected'] ? 200 : 503;
    Response::json([
        'status'    => $dbStatus['connected'] ? 'healthy' : 'degraded',
        'timestamp' => date('c'),
        'database'  => $dbStatus
    ], $statusCode);
});

// ==========================================
// 5. Register Core Domain Resource Routes
// ==========================================
$resourceControllers = [
    'users'            => ['file' => 'UserController.php',            'class' => 'UserController'],
    'products'         => ['file' => 'ProductController.php',         'class' => 'ProductController'],
    'sales'            => ['file' => 'SaleController.php',            'class' => 'SaleController'],
    'categories'       => ['file' => 'CategoryController.php',        'class' => 'CategoryController'],
    'brands'           => ['file' => 'BrandController.php',           'class' => 'BrandController'],
    'suppliers'        => ['file' => 'SupplierController.php',        'class' => 'SupplierController'],
    'customers'        => ['file' => 'CustomerController.php',        'class' => 'CustomerController'],
    'motorcycles'      => ['file' => 'MotorcycleController.php',      'class' => 'MotorcycleController'],
    'purchase-orders'  => ['file' => 'PurchaseOrderController.php',   'class' => 'PurchaseOrderController'],
    'purchase_orders'  => ['file' => 'PurchaseOrderController.php',   'class' => 'PurchaseOrderController'],
    'movements'        => ['file' => 'MovementController.php',        'class' => 'MovementController'],
    'returns'          => ['file' => 'ReturnController.php',          'class' => 'ReturnController'],
    'consignment'      => ['file' => 'ConsignmentController.php',      'class' => 'ConsignmentController'],
    'reports'          => ['file' => 'ReportController.php',           'class' => 'ReportController'],
];

foreach ($resourceControllers as $resource => $info) {
    $filePath = __DIR__ . '/controllers/' . $info['file'];
    if (file_exists($filePath)) {
        require_once $filePath;
        $className = $info['class'];

        // Delegate to Controller::handleRequest
        $delegate = function(array $params = []) use ($className, $resource) {
            $controller = new $className();
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $id = isset($params['id']) && is_numeric($params['id']) ? (int)$params['id'] : null;
            $action = isset($params['id']) && !is_numeric($params['id']) ? (string)$params['id'] : null;
            
            // Build uriParts for backward compatibility
            $uriParts = [$resource];
            if ($id !== null) {
                $uriParts[] = (string)$id;
            } elseif ($action !== null) {
                $uriParts[] = $action;
            }
            if (isset($params['subresource'])) {
                $uriParts[] = (string)$params['subresource'];
            }

            $controller->handleRequest($method, $id, $uriParts);
        };

        // Collection routes
        $router->get("/{$resource}", $delegate);
        $router->post("/{$resource}", $delegate);

        // Member routes
        $router->get("/{$resource}/{id}", $delegate);
        $router->put("/{$resource}/{id}", $delegate);
        $router->delete("/{$resource}/{id}", $delegate);

        // Subresource routes (e.g. /sales/{id}/receipt, /suppliers/{id}/status, /customers/{id}/motorcycles)
        $router->get("/{$resource}/{id}/{subresource}", $delegate);
        $router->post("/{$resource}/{id}/{subresource}", $delegate);
        $router->put("/{$resource}/{id}/{subresource}", $delegate);
        $router->delete("/{$resource}/{id}/{subresource}", $delegate);
    }
}

// ==========================================
// 6. Dispatch Request
// ==========================================
$router->dispatch();
