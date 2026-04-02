<?php
/**
 * API de Cierre de Caja
 * GET  /cash.php                    - Obtener datos de caja actual
 * GET  /cash.php?history=1          - Historial de cierres
 * GET  /cash.php?id=xxx             - Obtener un cierre especifico
 * POST /cash.php                    - Cerrar caja
 */

require_once 'config.php';
setHeaders();

$pdo = getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['history'])) {
            getClosureHistory($pdo);
        } elseif (isset($_GET['id'])) {
            getClosure($pdo, $_GET['id']);
        } else {
            getCurrentCashData($pdo);
        }
        break;
    case 'POST':
        closeCash($pdo);
        break;
    default:
        jsonResponse(['error' => 'Metodo no permitido'], 405);
}

// Obtener datos de caja actual (desde ultimo cierre o inicio del dia)
function getCurrentCashData($pdo) {
    // Obtener ultimo cierre
    $stmt = $pdo->query("
        SELECT close_time FROM cash_closures ORDER BY close_time DESC LIMIT 1
    ");
    $lastClosure = $stmt->fetch();
    
    $openTime = $lastClosure ? $lastClosure['close_time'] : date('Y-m-d 00:00:00');
    
    // Ventas desde el ultimo cierre
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_sales,
            COALESCE(SUM(total), 0) as total_cash
        FROM sales 
        WHERE created_at >= ?
    ");
    $stmt->execute([$openTime]);
    $summary = $stmt->fetch();
    
    // Productos vendidos desde ultimo cierre
    $stmt = $pdo->prepare("
        SELECT 
            si.product_name,
            SUM(si.quantity) as quantity
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        WHERE s.created_at >= ?
        GROUP BY si.product_name
        ORDER BY quantity DESC
    ");
    $stmt->execute([$openTime]);
    $products = $stmt->fetchAll();
    
    jsonResponse([
        'openTime' => $openTime,
        'totalSales' => (int) $summary['total_sales'],
        'totalCash' => (float) $summary['total_cash'],
        'productsSold' => array_map(function($p) {
            return [
                'name' => $p['product_name'],
                'quantity' => (int) $p['quantity']
            ];
        }, $products)
    ]);
}

// Historial de cierres
function getClosureHistory($pdo) {
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 30;
    
    $stmt = $pdo->prepare("
        SELECT cc.*, u.name as closed_by_name
        FROM cash_closures cc
        JOIN users u ON cc.closed_by = u.id
        ORDER BY cc.close_time DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $closures = $stmt->fetchAll();
    
    jsonResponse(array_map(function($c) {
        return [
            'id' => $c['id'],
            'openTime' => $c['open_time'],
            'closeTime' => $c['close_time'],
            'totalSales' => (int) $c['total_sales'],
            'totalCash' => (float) $c['total_cash'],
            'closedBy' => $c['closed_by_name'],
            'notes' => $c['notes']
        ];
    }, $closures));
}

// Obtener un cierre especifico
function getClosure($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT cc.*, u.name as closed_by_name
        FROM cash_closures cc
        JOIN users u ON cc.closed_by = u.id
        WHERE cc.id = ?
    ");
    $stmt->execute([$id]);
    $closure = $stmt->fetch();
    
    if (!$closure) {
        jsonResponse(['error' => 'Cierre no encontrado'], 404);
    }
    
    // Obtener productos de este cierre
    $stmt = $pdo->prepare("
        SELECT product_name, quantity FROM closure_products WHERE closure_id = ?
    ");
    $stmt->execute([$id]);
    $products = $stmt->fetchAll();
    
    jsonResponse([
        'id' => $closure['id'],
        'openTime' => $closure['open_time'],
        'closeTime' => $closure['close_time'],
        'totalSales' => (int) $closure['total_sales'],
        'totalCash' => (float) $closure['total_cash'],
        'closedBy' => $closure['closed_by_name'],
        'notes' => $closure['notes'],
        'productsSold' => array_map(function($p) {
            return [
                'name' => $p['product_name'],
                'quantity' => (int) $p['quantity']
            ];
        }, $products)
    ]);
}

// Cerrar caja
function closeCash($pdo) {
    $data = getJsonInput();
    
    if (empty($data['userId'])) {
        jsonResponse(['error' => 'userId requerido'], 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Obtener ultimo cierre
        $stmt = $pdo->query("
            SELECT close_time FROM cash_closures ORDER BY close_time DESC LIMIT 1
        ");
        $lastClosure = $stmt->fetch();
        
        $openTime = $lastClosure ? $lastClosure['close_time'] : date('Y-m-d 00:00:00');
        $closeTime = date('Y-m-d H:i:s');
        
        // Obtener resumen de ventas
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_sales,
                COALESCE(SUM(total), 0) as total_cash
            FROM sales 
            WHERE created_at >= ?
        ");
        $stmt->execute([$openTime]);
        $summary = $stmt->fetch();
        
        // Obtener productos vendidos
        $stmt = $pdo->prepare("
            SELECT 
                si.product_name,
                SUM(si.quantity) as quantity
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            WHERE s.created_at >= ?
            GROUP BY si.product_name
        ");
        $stmt->execute([$openTime]);
        $products = $stmt->fetchAll();
        
        // Crear cierre
        $closureId = generateId();
        $stmt = $pdo->prepare("
            INSERT INTO cash_closures (id, open_time, close_time, total_sales, total_cash, closed_by, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $closureId,
            $openTime,
            $closeTime,
            $summary['total_sales'],
            $summary['total_cash'],
            $data['userId'],
            $data['notes'] ?? null
        ]);
        
        // Guardar productos del cierre
        foreach ($products as $product) {
            $stmt = $pdo->prepare("
                INSERT INTO closure_products (closure_id, product_name, quantity) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $closureId,
                $product['product_name'],
                $product['quantity']
            ]);
        }
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'id' => $closureId,
            'openTime' => $openTime,
            'closeTime' => $closeTime,
            'totalSales' => (int) $summary['total_sales'],
            'totalCash' => (float) $summary['total_cash'],
            'message' => 'Caja cerrada correctamente'
        ], 201);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}
