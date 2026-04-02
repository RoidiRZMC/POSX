<?php
/**
 * API de Ventas
 * GET    /sales.php                    - Listar ventas
 * GET    /sales.php?id=xxx             - Obtener venta con items
 * GET    /sales.php?date=2024-01-15    - Ventas de un dia
 * GET    /sales.php?from=X&to=Y        - Ventas en rango
 * GET    /sales.php?today=1            - Ventas de hoy
 * POST   /sales.php                    - Crear venta
 */

require_once 'config.php';
setHeaders();

$pdo = getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($id) {
            getSale($pdo, $id);
        } else {
            getSales($pdo);
        }
        break;
    case 'POST':
        createSale($pdo);
        break;
    default:
        jsonResponse(['error' => 'Metodo no permitido'], 405);
}

// Listar ventas con filtros
function getSales($pdo) {
    $date = $_GET['date'] ?? null;
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;
    $today = isset($_GET['today']);
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
    
    $sql = "SELECT s.*, u.name as user_name, c.name as customer_name 
            FROM sales s 
            LEFT JOIN users u ON s.user_id = u.id 
            LEFT JOIN customers c ON s.customer_id = c.id 
            WHERE 1=1";
    $params = [];
    
    if ($today) {
        $sql .= " AND DATE(s.created_at) = CURDATE()";
    } elseif ($date) {
        $sql .= " AND DATE(s.created_at) = ?";
        $params[] = $date;
    } elseif ($from && $to) {
        $sql .= " AND DATE(s.created_at) BETWEEN ? AND ?";
        $params[] = $from;
        $params[] = $to;
    }
    
    $sql .= " ORDER BY s.created_at DESC LIMIT " . $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $sales = $stmt->fetchAll();
    
    // Obtener items de cada venta
    $result = [];
    foreach ($sales as $sale) {
        $stmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
        $stmt->execute([$sale['id']]);
        $items = $stmt->fetchAll();
        
        $result[] = [
            'id' => $sale['id'],
            'total' => (float) $sale['total'],
            'customerId' => $sale['customer_id'],
            'customerName' => $sale['customer_name'],
            'userId' => $sale['user_id'],
            'userName' => $sale['user_name'],
            'items' => array_map(function($item) {
                return [
                    'productId' => $item['product_id'],
                    'name' => $item['product_name'],
                    'price' => (float) $item['price'],
                    'quantity' => (int) $item['quantity'],
                    'subtotal' => (float) $item['subtotal']
                ];
            }, $items),
            'date' => $sale['created_at']
        ];
    }
    
    jsonResponse($result);
}

// Obtener una venta
function getSale($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT s.*, u.name as user_name, c.name as customer_name 
        FROM sales s 
        LEFT JOIN users u ON s.user_id = u.id 
        LEFT JOIN customers c ON s.customer_id = c.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $sale = $stmt->fetch();
    
    if (!$sale) {
        jsonResponse(['error' => 'Venta no encontrada'], 404);
    }
    
    // Obtener items
    $stmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll();
    
    jsonResponse([
        'id' => $sale['id'],
        'total' => (float) $sale['total'],
        'customerId' => $sale['customer_id'],
        'customerName' => $sale['customer_name'],
        'userId' => $sale['user_id'],
        'userName' => $sale['user_name'],
        'items' => array_map(function($item) {
            return [
                'productId' => $item['product_id'],
                'name' => $item['product_name'],
                'price' => (float) $item['price'],
                'quantity' => (int) $item['quantity'],
                'subtotal' => (float) $item['subtotal']
            ];
        }, $items),
        'date' => $sale['created_at']
    ]);
}

// Crear venta
function createSale($pdo) {
    $data = getJsonInput();
    
    if (empty($data['items']) || !is_array($data['items']) || empty($data['userId'])) {
        jsonResponse(['error' => 'Items y userId requeridos'], 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        $saleId = generateId();
        $total = 0;
        
        // Calcular total y verificar stock
        foreach ($data['items'] as $item) {
            $stmt = $pdo->prepare("SELECT id, name, price, stock FROM products WHERE id = ? AND active = 1");
            $stmt->execute([$item['productId']]);
            $product = $stmt->fetch();
            
            if (!$product) {
                throw new Exception("Producto no encontrado: " . $item['productId']);
            }
            
            if ($product['stock'] < $item['quantity']) {
                throw new Exception("Stock insuficiente para: " . $product['name']);
            }
            
            $total += $product['price'] * $item['quantity'];
        }
        
        // Crear venta
        $stmt = $pdo->prepare("
            INSERT INTO sales (id, customer_id, user_id, total) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $saleId, 
            $data['customerId'] ?? null, 
            $data['userId'], 
            $total
        ]);
        
        // Crear items y actualizar stock
        foreach ($data['items'] as $item) {
            $stmt = $pdo->prepare("SELECT name, price FROM products WHERE id = ?");
            $stmt->execute([$item['productId']]);
            $product = $stmt->fetch();
            
            $subtotal = $product['price'] * $item['quantity'];
            
            // Insertar item
            $stmt = $pdo->prepare("
                INSERT INTO sale_items (sale_id, product_id, product_name, price, quantity, subtotal) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $saleId,
                $item['productId'],
                $product['name'],
                $product['price'],
                $item['quantity'],
                $subtotal
            ]);
            
            // Actualizar stock
            $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['productId']]);
        }
        
        // Actualizar contador de ventas del usuario
        $stmt = $pdo->prepare("UPDATE users SET sales_count = sales_count + 1 WHERE id = ?");
        $stmt->execute([$data['userId']]);
        
        // Actualizar cliente si existe
        if (!empty($data['customerId'])) {
            $stmt = $pdo->prepare("
                UPDATE customers 
                SET total_purchases = total_purchases + ?, last_purchase = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$total, $data['customerId']]);
        }
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'id' => $saleId,
            'total' => $total,
            'message' => 'Venta registrada correctamente'
        ], 201);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => $e->getMessage()], 400);
    }
}
