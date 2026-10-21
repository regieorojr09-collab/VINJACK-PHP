<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/SaleDAO.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RBACMiddleware.php';

use Middleware\AuthMiddleware;

/**
 * Sale Controller
 * 
 * Manages POS sales checkouts, registry listings, and structured XML receipt generation.
 */
class SaleController {
    private SaleDAO $saleDAO;

    public function __construct() {
        $this->saleDAO = new SaleDAO();
    }

    public function handleRequest(string $method, ?int $id, array $uriParts): void {
        $user = AuthMiddleware::authenticate();
        $subresource = $uriParts[2] ?? null;

        switch ($method) {
            case 'GET':
                RBACMiddleware::checkPermission($user, 'canManageSales');
                if ($id !== null && $subresource === 'receipt') {
                    $this->getReceiptXml($id);
                } elseif ($id !== null) {
                    $this->getSaleById($id);
                } else {
                    $this->getSales();
                }
                break;

            case 'POST':
                RBACMiddleware::checkPermission($user, 'canManageSales');
                $this->processSale((int)$user['id']);
                break;

            default:
                Response::json(['success' => false, 'message' => "Method {$method} not allowed"], 405);
                break;
        }
    }

    public function getSales(): void {
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 20;

        $result = $this->saleDAO->getSales($page, $limit);
        Response::json([
            'success'    => true,
            'data'       => $result['data'],
            'pagination' => $result['pagination']
        ], 200);
    }

    public function getSaleById(int $id): void {
        $sale = $this->saleDAO->getSaleById($id);
        if (!$sale) {
            Response::json(['success' => false, 'message' => 'Sale record not found.'], 404);
        }

        Response::json([
            'success' => true,
            'data'    => $sale
        ], 200);
    }

    public function processSale(int $userId): void {
        $raw = file_get_contents("php://input");
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            Response::json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
        }

        $items = $data['items'] ?? [];
        $services = $data['services'] ?? [];

        if (empty($items) && empty($services)) {
            Response::json(['success' => false, 'message' => 'A sale transaction must include at least one product or service.'], 400);
        }

        try {
            $saleId = $this->saleDAO->createSale($data, $items, $services, $userId);
            $createdSale = $this->saleDAO->getSaleById($saleId);

            Response::json([
                'success' => true,
                'message' => 'Sale transaction completed successfully.',
                'sale_id' => $saleId,
                'data'    => $createdSale
            ], 201);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Transaction failure: ' . $e->getMessage()], 500);
        }
    }

    public function getReceiptXml(int $id): void {
        try {
            $xmlString = $this->saleDAO->getReceiptXml($id);
            Response::xml($xmlString);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Receipt generation failure: ' . $e->getMessage()], 500);
        }
    }
}
