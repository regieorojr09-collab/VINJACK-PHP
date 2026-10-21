<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Database.php';

/**
 * Reporting & Business Intelligence Data Access Object
 * 
 * Provides complex financial and inventory aggregations:
 * Executive dashboard KPIs, inventory valuation, COGS/profitability, and top performers.
 */
class ReportDAO {
    private PDO $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    /**
     * Compute comprehensive executive dashboard overview metrics.
     *
     * @param string|null $startDate YYYY-MM-DD
     * @param string|null $endDate YYYY-MM-DD
     * @return array<string, mixed>
     */
    public function getDashboardOverview(?string $startDate = null, ?string $endDate = null): array {
        $whereSales = ["1=1"];
        $paramsSales = [];

        if (!empty($startDate)) {
            $whereSales[] = "s.created_at >= :start_date";
            $paramsSales[':start_date'] = trim($startDate) . ' 00:00:00';
        }

        if (!empty($endDate)) {
            $whereSales[] = "s.created_at <= :end_date";
            $paramsSales[':end_date'] = trim($endDate) . ' 23:59:59';
        }

        $salesWhereClause = implode(" AND ", $whereSales);

        // 1. Gross Sales, Ticket Count, and Average Order Value
        $salesSql = "
            SELECT 
                COUNT(*) AS total_transactions,
                COALESCE(SUM(s.total_amount), 0.0) AS gross_sales
            FROM sales s
            WHERE {$salesWhereClause}
        ";
        $salesStmt = $this->conn->prepare($salesSql);
        foreach ($paramsSales as $k => $v) $salesStmt->bindValue($k, $v);
        $salesStmt->execute();
        $salesRow = $salesStmt->fetch(PDO::FETCH_ASSOC);

        $grossSales = (float)($salesRow['gross_sales'] ?? 0.0);
        $totalTransactions = (int)($salesRow['total_transactions'] ?? 0);
        $avgOrderValue = $totalTransactions > 0 ? round($grossSales / $totalTransactions, 2) : 0.0;

        // 2. Cost of Goods Sold (Historical captured in sale_items)
        $cogsSql = "
            SELECT 
                COALESCE(SUM(si.quantity * si.cost_of_goods_sold), 0.0) AS total_cogs
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            WHERE {$salesWhereClause}
        ";
        $cogsStmt = $this->conn->prepare($cogsSql);
        foreach ($paramsSales as $k => $v) $cogsStmt->bindValue($k, $v);
        $cogsStmt->execute();
        $totalCogs = (float)$cogsStmt->fetchColumn();

        // 3. Customer Returns & Refunds
        $whereReturns = ["1=1"];
        $paramsReturns = [];
        if (!empty($startDate)) {
            $whereReturns[] = "r.created_at >= :ret_start";
            $paramsReturns[':ret_start'] = trim($startDate) . ' 00:00:00';
        }
        if (!empty($endDate)) {
            $whereReturns[] = "r.created_at <= :ret_end";
            $paramsReturns[':ret_end'] = trim($endDate) . ' 23:59:59';
        }
        $retWhereClause = implode(" AND ", $whereReturns);

        $retSql = "
            SELECT 
                COUNT(*) AS total_returns,
                COALESCE(SUM(r.total_refund_amount), 0.0) AS total_refunds
            FROM returns r
            WHERE {$retWhereClause}
        ";
        $retStmt = $this->conn->prepare($retSql);
        foreach ($paramsReturns as $k => $v) $retStmt->bindValue($k, $v);
        $retStmt->execute();
        $retRow = $retStmt->fetch(PDO::FETCH_ASSOC);
        $totalRefunds = (float)($retRow['total_refunds'] ?? 0.0);

        // 4. Net Sales, Gross Profit, and Margin
        $netSales = max(0.0, $grossSales - $totalRefunds);
        $grossProfit = $netSales - $totalCogs;
        $profitMargin = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 2) : 0.0;

        // 5. Real-Time Stock Status Health Distribution
        $stockSql = "
            SELECT 
                COUNT(*) AS total_skus,
                COALESCE(SUM(quantity), 0) AS total_physical_units,
                COALESCE(SUM(consigned_stock), 0) AS total_consigned_units,
                SUM(CASE WHEN stock_status = 'Healthy' THEN 1 ELSE 0 END) AS healthy_count,
                SUM(CASE WHEN stock_status = 'Low' THEN 1 ELSE 0 END) AS low_count,
                SUM(CASE WHEN stock_status = 'Critical' THEN 1 ELSE 0 END) AS critical_count,
                SUM(CASE WHEN stock_status = 'Out of Stock' THEN 1 ELSE 0 END) AS out_of_stock_count
            FROM products 
            WHERE status = 'active'
        ";
        $stockStmt = $this->conn->query($stockSql);
        $stockSummary = $stockStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // 6. Recent Activity (10 most recent inventory movements)
        $recentSql = "
            SELECT 
                m.id,
                m.type,
                m.quantity_change,
                m.stock_before,
                m.stock_after,
                m.reference_id,
                m.notes,
                m.created_at,
                p.name AS product_name,
                p.item_code,
                u.full_name AS recorded_by_name
            FROM movements m
            JOIN products p ON m.product_id = p.id
            JOIN users u ON m.recorded_by = u.id
            ORDER BY m.id DESC
            LIMIT 10
        ";
        $recentStmt = $this->conn->query($recentSql);
        $recentActivity = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'gross_sales'           => $grossSales,
            'total_refunds'         => $totalRefunds,
            'net_sales'             => $netSales,
            'total_cogs'            => $totalCogs,
            'gross_profit'          => $grossProfit,
            'profit_margin_percent' => $profitMargin,
            'total_transactions'    => $totalTransactions,
            'average_order_value'   => $avgOrderValue,
            'stock_summary'         => [
                'total_skus'           => (int)($stockSummary['total_skus'] ?? 0),
                'total_physical_units' => (int)($stockSummary['total_physical_units'] ?? 0),
                'total_consigned'      => (int)($stockSummary['total_consigned_units'] ?? 0),
                'healthy'              => (int)($stockSummary['healthy_count'] ?? 0),
                'low'                  => (int)($stockSummary['low_count'] ?? 0),
                'critical'             => (int)($stockSummary['critical_count'] ?? 0),
                'out_of_stock'         => (int)($stockSummary['out_of_stock_count'] ?? 0)
            ],
            'recent_activity'       => $recentActivity
        ];
    }

    /**
     * Retrieve top performing products and services by sales volume and gross revenue.
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @param int $limit
     * @return array{products: array<int, array<string, mixed>>, services: array<int, array<string, mixed>>}
     */
    public function getTopSelling(?string $startDate = null, ?string $endDate = null, int $limit = 5): array {
        $where = ["1=1"];
        $params = [];

        if (!empty($startDate)) {
            $where[] = "s.created_at >= :start_date";
            $params[':start_date'] = trim($startDate) . ' 00:00:00';
        }

        if (!empty($endDate)) {
            $where[] = "s.created_at <= :end_date";
            $params[':end_date'] = trim($endDate) . ' 23:59:59';
        }

        $whereClause = implode(" AND ", $where);
        $limit = max(1, min(50, $limit));

        // 1. Top Products
        $prodSql = "
            SELECT 
                p.id AS product_id,
                p.name AS product_name,
                p.item_code,
                p.unit,
                c.name AS category_name,
                b.name AS brand_name,
                COALESCE(SUM(si.quantity), 0) AS total_units_sold,
                COALESCE(SUM(si.quantity * si.price_at_time), 0.0) AS total_revenue,
                COALESCE(SUM(si.quantity * si.cost_of_goods_sold), 0.0) AS total_cogs,
                COALESCE(SUM(si.quantity * (si.price_at_time - si.cost_of_goods_sold)), 0.0) AS gross_profit
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            JOIN products p ON si.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE {$whereClause}
            GROUP BY p.id, p.name, p.item_code, p.unit, c.name, b.name
            ORDER BY total_revenue DESC, total_units_sold DESC
            LIMIT :limit
        ";

        $prodStmt = $this->conn->prepare($prodSql);
        foreach ($params as $k => $v) $prodStmt->bindValue($k, $v);
        $prodStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $prodStmt->execute();
        $topProducts = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Top Services
        $svcSql = "
            SELECT 
                srv.id AS service_id,
                srv.name AS service_name,
                COUNT(ss.id) AS times_rendered,
                COALESCE(SUM(ss.price_at_time), 0.0) AS total_revenue
            FROM sale_services ss
            JOIN sales s ON ss.sale_id = s.id
            JOIN services srv ON ss.service_id = srv.id
            WHERE {$whereClause}
            GROUP BY srv.id, srv.name
            ORDER BY total_revenue DESC, times_rendered DESC
            LIMIT :limit
        ";

        $svcStmt = $this->conn->prepare($svcSql);
        foreach ($params as $k => $v) $svcStmt->bindValue($k, $v);
        $svcStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $svcStmt->execute();
        $topServices = $svcStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'products' => $topProducts,
            'services' => $topServices
        ];
    }

    /**
     * Compute current warehouse inventory valuation, cost basis, and margins.
     *
     * @return array<string, mixed>
     */
    public function getInventoryValuation(): array {
        // Overall Totals
        $totalsSql = "
            SELECT 
                COUNT(*) AS total_skus,
                COALESCE(SUM(quantity), 0) AS total_units,
                COALESCE(SUM(quantity * price), 0.0) AS total_retail_value,
                COALESCE(SUM(quantity * default_cost), 0.0) AS total_cost_basis,
                COALESCE(SUM(consigned_stock), 0) AS consigned_units,
                COALESCE(SUM(consigned_stock * default_cost), 0.0) AS consigned_cost_basis,
                COALESCE(SUM((quantity - consigned_stock) * default_cost), 0.0) AS owned_cost_basis
            FROM products
            WHERE status = 'active'
        ";
        $totalsStmt = $this->conn->query($totalsSql);
        $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC);

        $retailVal = (float)($totals['total_retail_value'] ?? 0.0);
        $costBasis = (float)($totals['total_cost_basis'] ?? 0.0);
        $potentialProfit = $retailVal - $costBasis;
        $potentialMargin = $retailVal > 0 ? round(($potentialProfit / $retailVal) * 100, 2) : 0.0;

        // Breakdown by Category
        $catSql = "
            SELECT 
                c.id AS category_id,
                c.name AS category_name,
                COUNT(p.id) AS sku_count,
                COALESCE(SUM(p.quantity), 0) AS total_units,
                COALESCE(SUM(p.quantity * p.price), 0.0) AS retail_value,
                COALESCE(SUM(p.quantity * p.default_cost), 0.0) AS cost_basis,
                COALESCE(SUM(p.quantity * (p.price - p.default_cost)), 0.0) AS potential_profit
            FROM categories c
            LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
            GROUP BY c.id, c.name
            ORDER BY retail_value DESC, c.name ASC
        ";
        $catStmt = $this->conn->query($catSql);
        $byCategory = $catStmt->fetchAll(PDO::FETCH_ASSOC);

        // Breakdown by Brand
        $brandSql = "
            SELECT 
                b.id AS brand_id,
                b.name AS brand_name,
                COUNT(p.id) AS sku_count,
                COALESCE(SUM(p.quantity), 0) AS total_units,
                COALESCE(SUM(p.quantity * p.price), 0.0) AS retail_value,
                COALESCE(SUM(p.quantity * p.default_cost), 0.0) AS cost_basis,
                COALESCE(SUM(p.quantity * (p.price - p.default_cost)), 0.0) AS potential_profit
            FROM brands b
            LEFT JOIN products p ON b.id = p.brand_id AND p.status = 'active'
            GROUP BY b.id, b.name
            ORDER BY retail_value DESC, b.name ASC
        ";
        $brandStmt = $this->conn->query($brandSql);
        $byBrand = $brandStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'summary' => [
                'total_skus'             => (int)($totals['total_skus'] ?? 0),
                'total_units'            => (int)($totals['total_units'] ?? 0),
                'total_retail_value'     => $retailVal,
                'total_cost_basis'       => $costBasis,
                'potential_gross_profit' => $potentialProfit,
                'potential_margin'       => $potentialMargin,
                'owned_cost_basis'       => (float)($totals['owned_cost_basis'] ?? 0.0),
                'consigned_cost_basis'   => (float)($totals['consigned_cost_basis'] ?? 0.0),
                'consigned_units'        => (int)($totals['consigned_units'] ?? 0)
            ],
            'categories' => $byCategory,
            'brands'     => $byBrand
        ];
    }

    /**
     * Compute daily sales, COGS, customer refunds, and net profit report.
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @param int|null $categoryId
     * @param int|null $supplierId
     * @return array{records: array<int, array<string, mixed>>, totals: array<string, mixed>}
     */
    public function getSalesReport(
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $categoryId = null,
        ?int $supplierId = null
    ): array {
        $where = ["1=1"];
        $params = [];

        if (!empty($startDate)) {
            $where[] = "s.created_at >= :start_date";
            $params[':start_date'] = trim($startDate) . ' 00:00:00';
        }

        if (!empty($endDate)) {
            $where[] = "s.created_at <= :end_date";
            $params[':end_date'] = trim($endDate) . ' 23:59:59';
        }

        if (!empty($categoryId)) {
            $where[] = "p.category_id = :category_id";
            $params[':category_id'] = $categoryId;
        }

        if (!empty($supplierId)) {
            $where[] = "EXISTS (SELECT 1 FROM product_supplier_costs psc WHERE psc.product_id = p.id AND psc.supplier_id = :supplier_id)";
            $params[':supplier_id'] = $supplierId;
        }

        $whereClause = implode(" AND ", $where);

        $sql = "
            SELECT 
                DATE(s.created_at) AS sale_date,
                COUNT(DISTINCT s.id) AS transaction_count,
                COALESCE(SUM(si.quantity), 0) AS total_units_sold,
                COALESCE(SUM(si.quantity * si.price_at_time), 0.0) AS items_revenue,
                COALESCE(SUM(si.quantity * si.cost_of_goods_sold), 0.0) AS total_cogs
            FROM sales s
            JOIN sale_items si ON s.id = si.sale_id
            JOIN products p ON si.product_id = p.id
            WHERE {$whereClause}
            GROUP BY DATE(s.created_at)
            ORDER BY sale_date DESC
        ";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        $dailyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch daily services revenue and returns
        $records = [];
        $grandGross = 0.0;
        $grandCogs = 0.0;
        $grandRefunds = 0.0;
        $grandNet = 0.0;
        $grandProfit = 0.0;
        $grandUnits = 0;
        $grandTx = 0;

        foreach ($dailyRows as $row) {
            $date = (string)$row['sale_date'];

            // Daily services
            $svcStmt = $this->conn->prepare("
                SELECT COALESCE(SUM(ss.price_at_time), 0.0) 
                FROM sale_services ss 
                JOIN sales s ON ss.sale_id = s.id 
                WHERE DATE(s.created_at) = :sdate
            ");
            $svcStmt->bindValue(':sdate', $date);
            $svcStmt->execute();
            $svcRevenue = (float)$svcStmt->fetchColumn();

            // Daily refunds
            $retStmt = $this->conn->prepare("
                SELECT COALESCE(SUM(r.total_refund_amount), 0.0) 
                FROM returns r 
                WHERE DATE(r.created_at) = :rdate
            ");
            $retStmt->bindValue(':rdate', $date);
            $retStmt->execute();
            $refundAmount = (float)$retStmt->fetchColumn();

            $itemsRev = (float)$row['items_revenue'];
            $grossRev = $itemsRev + $svcRevenue;
            $cogs = (float)$row['total_cogs'];
            $netRev = max(0.0, $grossRev - $refundAmount);
            $grossProfit = $netRev - $cogs;
            $margin = $netRev > 0 ? round(($grossProfit / $netRev) * 100, 2) : 0.0;
            $txCount = (int)$row['transaction_count'];
            $units = (int)$row['total_units_sold'];

            $records[] = [
                'sale_date'             => $date,
                'transaction_count'     => $txCount,
                'total_units_sold'      => $units,
                'items_revenue'         => $itemsRev,
                'services_revenue'      => $svcRevenue,
                'gross_revenue'         => $grossRev,
                'total_cogs'            => $cogs,
                'total_refunds'         => $refundAmount,
                'net_revenue'           => $netRev,
                'gross_profit'          => $grossProfit,
                'profit_margin_percent' => $margin
            ];

            $grandGross += $grossRev;
            $grandCogs += $cogs;
            $grandRefunds += $refundAmount;
            $grandNet += $netRev;
            $grandProfit += $grossProfit;
            $grandUnits += $units;
            $grandTx += $txCount;
        }

        $overallMargin = $grandNet > 0 ? round(($grandProfit / $grandNet) * 100, 2) : 0.0;

        return [
            'records' => $records,
            'totals'  => [
                'transaction_count'     => $grandTx,
                'total_units_sold'      => $grandUnits,
                'gross_revenue'         => $grandGross,
                'total_cogs'            => $grandCogs,
                'total_refunds'         => $grandRefunds,
                'net_revenue'           => $grandNet,
                'gross_profit'          => $grandProfit,
                'profit_margin_percent' => $overallMargin
            ]
        ];
    }
}
