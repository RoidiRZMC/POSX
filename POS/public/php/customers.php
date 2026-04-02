<?php
/**
 * API de Clientes
 * GET    /customers.php              - Listar clientes
 * GET    /customers.php?id=xxx       - Obtener cliente con sus ventas
 * POST   /customers.php              - Crear cliente
 * PUT    /customers.php?id=xxx       - Actualizar cliente
 * DELETE /customers.php?id=xxx       - Eliminar cliente
 */

require_once 'config.php';
setHeaders();

$pdo = getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($id) {
            getCustomer($pdo, $id);
        } else {
            getCustomers($pdo);
        }
        break;
    case 'POST':
        createCustomer($pdo);
        break;
    case 'PUT':
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
        updateCustomer($pdo, $id);
        break;
    case 'DELETE':
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
        deleteCustomer($pdo, $id);
        break;
    default:
        jsonResponse(['error' => 'Metodo no permitido'], 405);
}

// Listar clientes
function getCustomers($pdo) {
    $stmt = $pdo->query("
        SELECT id, name, phone, email, total_purchases, last_purchase, created_at 
        FROM customers 
        ORDER BY name ASC
    ");
    
    $customers = $stmt->fetchAll();
    
    $formatted = array_map(function($c) {
        return [
            'id' => $c['id'],
            'name' => $c['name'],
            'phone' => $c['phone'],
            'email' => $c['email'],
            'totalPurchases' => (float) $c['total_purchases'],
            'lastPurchase' => $c['last_purchase'],
            'createdAt' => $c['created_at']
        ];
    }, $customers);
    
    jsonResponse($formatted);
}

// Obtener cliente con sus ventas
function getCustomer($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT id, name, phone, email, total_purchases, last_purchase, created_at 
        FROM customers 
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        jsonResponse(['error' => 'Cliente no encontrado'], 404);
    }
    
    // Obtener ventas del cliente
    $stmt = $pdo->prepare("
        SELECT s.id, s.total, s.created_at, u.name as user_name
        FROM sales s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.customer_id = ?
        ORDER BY s.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$id]);
    $sales = $stmt->fetchAll();
    
    jsonResponse([
        'id' => $customer['id'],
        'name' => $customer['name'],
        'phone' => $customer['phone'],
        'email' => $customer['email'],
        'totalPurchases' => (float) $customer['total_purchases'],
        'lastPurchase' => $customer['last_purchase'],
        'createdAt' => $customer['created_at'],
        'sales' => $sales
    ]);
}

// Crear cliente
function createCustomer($pdo) {
    $data = getJsonInput();
    
    if (empty($data['name'])) {
        jsonResponse(['error' => 'Nombre requerido'], 400);
    }
    
    $id = generateId();
    
    $stmt = $pdo->prepare("
        INSERT INTO customers (id, name, phone, email) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $id, 
        $data['name'], 
        $data['phone'] ?? null, 
        $data['email'] ?? null
    ]);
    
    jsonResponse([
        'success' => true,
        'id' => $id,
        'message' => 'Cliente creado correctamente'
    ], 201);
}

// Actualizar cliente
function updateCustomer($pdo, $id) {
    $data = getJsonInput();
    
    if (empty($data['name'])) {
        jsonResponse(['error' => 'Nombre requerido'], 400);
    }
    
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'Cliente no encontrado'], 404);
    }
    
    $stmt = $pdo->prepare("
        UPDATE customers SET name = ?, phone = ?, email = ? WHERE id = ?
    ");
    $stmt->execute([
        $data['name'], 
        $data['phone'] ?? null, 
        $data['email'] ?? null,
        $id
    ]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Cliente actualizado correctamente'
    ]);
}

// Eliminar cliente
function deleteCustomer($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Cliente no encontrado'], 404);
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Cliente eliminado correctamente'
    ]);
}
