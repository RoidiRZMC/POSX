<?php
/**
 * API de Reportes
 * GET /reports.php?type=daily                   - Reporte diario (hoy)
 * GET /reports.php?type=daily&date=2024-01-15   - Reporte de un dia
 * GET /reports.php?type=monthly                 - Reporte mensual (mes actual)
 * GET /reports.php?type=monthly&month=01&year=2024
 * GET /reports.php?type=annual                  - Reporte anual (año actual)
 * GET /reports.php?type=annual&year=2024
 * GET /reports.php?type=dashboard               - Datos para dashboard
 */

require_once 'config.php';
setHeaders();

$pdo = getConnection();
$type = $_GET['type'] ?? 'dashboard';

switch ($type) {
    case 'daily':
        getDailyReport($pdo);
        break;
    case 'monthly':
        getMonthlyReport($pdo);
        break;
    case 'annual':
        getAnnualReport($pdo);
        break;
    case 'dashboard':
        getDashboard($pdo);
        break;
    default:
        jsonResponse(['error' => 'Tipo de reporte no valido'], 400);
}

// Reporte diario
function getDailyReport($pdo) {
    $date = $_GET['date'] ?? date('Y-m-d');
    
    // Total vendido y cantidad de ventas
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sales,
            COALESCE(SUM(total), 0) as total_amount
        FROM sales 
        WHERE DATE(created_at) = ?
    ");
    $stmt->execute([$date]);
    $summary = $stmt->fetch();
    
    // Productos vendidos con detalle
    $stmt = $pdo->prepare("
        SELECT 
            si.product_name,
            SUM(si.quantity) as quantity,
            SUM(si.subtotal) as total
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        WHERE DATE(s.created_at) = ?
        GROUP BY si.product_name
        ORDER BY quantity DESC
    ");
    $stmt->execute([$date]);
    $products = $stmt->fetchAll();
    
    // Ventas por vendedor
    $stmt = $pdo->prepare("
        SELECT 
            u.name as user_name,
            COUNT(s.id) as sales_count,
            COALESCE(SUM(s.total), 0) as total_amount
        FROM sales s
        JOIN users u ON s.user_id = u.id
        WHERE DATE(s.created_at) = ?
        GROUP BY s.user_id, u.name
        ORDER BY total_amount DESC
    ");
    $stmt->execute([$date]);
    $byVendor = $stmt->fetchAll();
    
    jsonResponse([
        'date' => $date,
        'summary' => [
            'totalSales' => (int) $summary['total_sales'],
            'totalAmount' => (float) $summary['total_amount']
        ],
        'productsSold' => array_map(function($p) {
            return [
                'name' => $p['product_name'],
                'quantity' => (int) $p['quantity'],
                'total' => (float) $p['total']
            ];
        }, $products),
        'byVendor' => array_map(function($v) {
            return [
                'name' => $v['user_name'],
                'salesCount' => (int) $v['sales_count'],
                'totalAmount' => (float) $v['total_amount']
            ];
        }, $byVendor)
    ]);
}

// Reporte mensual
function getMonthlyReport($pdo) {
    $month = $_GET['month'] ?? date('m');
    $year = $_GET['year'] ?? date('Y');
    
    // Total del mes
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sales,
            COALESCE(SUM(total), 0) as total_amount
        FROM sales 
        WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?
    ");
    $stmt->execute([$month, $year]);
    $summary = $stmt->fetch();
    
    // Ventas por dia
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as sales_count,
            SUM(total) as total_amount
        FROM sales 
        WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$month, $year]);
    $byDay = $stmt->fetchAll();
    
    // Top productos
    $stmt = $pdo->prepare("
        SELECT 
            si.product_name,
            SUM(si.quantity) as quantity,
            SUM(si.subtotal) as total
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        WHERE MONTH(s.created_at) = ? AND YEAR(s.created_at) = ?
        GROUP BY si.product_name
        ORDER BY quantity DESC
        LIMIT 10
    ");
    $stmt->execute([$month, $year]);
    $topProducts = $stmt->fetchAll();
    
    jsonResponse([
        'month' => $month,
        'year' => $year,
        'summary' => [
            'totalSales' => (int) $summary['total_sales'],
            'totalAmount' => (float) $summary['total_amount']
        ],
        'byDay' => array_map(function($d) {
            return [
                'date' => $d['date'],
                'salesCount' => (int) $d['sales_count'],
                'totalAmount' => (float) $d['total_amount']
            ];
        }, $byDay),
        'topProducts' => array_map(function($p) {
            return [
                'name' => $p['product_name'],
                'quantity' => (int) $p['quantity'],
                'total' => (float) $p['total']
            ];
        }, $topProducts)
    ]);
}

// Reporte anual
function getAnnualReport($pdo) {
    $year = $_GET['year'] ?? date('Y');
    
    // Total del año
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sales,
            COALESCE(SUM(total), 0) as total_amount
        FROM sales 
        WHERE YEAR(created_at) = ?
    ");
    $stmt->execute([$year]);
    $summary = $stmt->fetch();
    
    // Ventas por mes
    $stmt = $pdo->prepare("
        SELECT 
            MONTH(created_at) as month,
            COUNT(*) as sales_count,
            SUM(total) as total_amount
        FROM sales 
        WHERE YEAR(created_at) = ?
        GROUP BY MONTH(created_at)
        ORDER BY month ASC
    ");
    $stmt->execute([$year]);
    $byMonth = $stmt->fetchAll();
    
    $monthNames = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    
    jsonResponse([
        'year' => $year,
        'summary' => [
            'totalSales' => (int) $summary['total_sales'],
            'totalAmount' => (float) $summary['total_amount']
        ],
        'byMonth' => array_map(function($m) use ($monthNames) {
            return [
                'month' => $monthNames[(int) $m['month']],
                'monthNumber' => (int) $m['month'],
                'salesCount' => (int) $m['sales_count'],
                'totalAmount' => (float) $m['total_amount']
            ];
        }, $byMonth)
    ]);
}

// Dashboard
function getDashboard($pdo) {
    $today = date('Y-m-d');
    
    // Ventas de hoy
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sales,
            COALESCE(SUM(total), 0) as total_amount
        FROM sales 
        WHERE DATE(created_at) = ?
    ");
    $stmt->execute([$today]);
    $todaySales = $stmt->fetch();
    
    // Total productos
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE active = 1");
    $totalProducts = $stmt->fetch()['count'];
    
    // Productos con stock bajo
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE active = 1 AND stock <= 5");
    $lowStock = $stmt->fetch()['count'];
    
    // Ultimas 5 ventas
    $stmt = $pdo->query("
        SELECT s.id, s.total, s.created_at, u.name as user_name,
               (SELECT COUNT(*) FROM sale_items WHERE sale_id = s.id) as items_count
        FROM sales s
        JOIN users u ON s.user_id = u.id
        ORDER BY s.created_at DESC
        LIMIT 5
    ");
    $recentSales = $stmt->fetchAll();
    
    // Top 5 productos mas vendidos
    $stmt = $pdo->query("
        SELECT 
            si.product_name,
            SUM(si.quantity) as quantity
        FROM sale_items si
        GROUP BY si.product_name
        ORDER BY quantity DESC
        LIMIT 5
    ");
    $topProducts = $stmt->fetchAll();
    
    jsonResponse([
        'today' => [
            'totalSales' => (int) $todaySales['total_sales'],
            'totalAmount' => (float) $todaySales['total_amount']
        ],
        'totalProducts' => (int) $totalProducts,
        'lowStockCount' => (int) $lowStock,
        'recentSales' => array_map(function($s) {
            return [
                'id' => $s['id'],
                'total' => (float) $s['total'],
                'itemsCount' => (int) $s['items_count'],
                'userName' => $s['user_name'],
                'date' => $s['created_at']
            ];
        }, $recentSales),
        'topProducts' => array_map(function($p) {
            return [
                'name' => $p['product_name'],
                'quantity' => (int) $p['quantity']
            ];
        }, $topProducts)
    ]);
}
