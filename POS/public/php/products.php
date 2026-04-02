<?php
/**
 * API de Productos
 * GET    /products.php                    - Listar productos
 * GET    /products.php?id=xxx             - Obtener producto
 * GET    /products.php?category=xxx       - Filtrar por categoria
 * GET    /products.php?search=xxx         - Buscar productos
 * GET    /products.php?lowstock=1         - Productos con stock bajo
 * POST   /products.php                    - Crear producto
 * PUT    /products.php?id=xxx             - Actualizar producto
 * DELETE /products.php?id=xxx             - Eliminar producto
 */

require_once 'config.php';
setHeaders();

$pdo = getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($id) {
            getProduct($pdo, $id);
        } else {
            getProducts($pdo);
        }
        break;
    case 'POST':
        createProduct($pdo);
        break;
    case 'PUT':
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
        updateProduct($pdo, $id);
        break;
    case 'DELETE':
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
        deleteProduct($pdo, $id);
        break;
    default:
        jsonResponse(['error' => 'Metodo no permitido'], 405);
}

// Listar productos con filtros
function getProducts($pdo) {
    $category = $_GET['category'] ?? null;
    $search = $_GET['search'] ?? null;
    $lowstock = isset($_GET['lowstock']);
    
    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category = c.id 
            WHERE p.active = 1";
    $params = [];
    
    if ($category && $category !== 'all') {
        $sql .= " AND c.name = ?";
        $params[] = $category;
    }
    
    if ($search) {
        $sql .= " AND p.name LIKE ?";
        $params[] = '%' . $search . '%';
    }
    
    if ($lowstock) {
        $sql .= " AND p.stock <= 5";
    }
    
    $sql .= " ORDER BY p.name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $products = $stmt->fetchAll();
    
    // Formatear para compatibilidad con frontend
    $formatted = array_map(function($p) {
        return [
            'id' => $p['id'],
            'name' => $p['name'],
            'category' => $p['category_name'],
            'price' => (float) $p['price'],
            'stock' => (int) $p['stock'],
            'createdAt' => $p['created_at']
        ];
    }, $products);
    
    jsonResponse($formatted);
}

// Obtener un producto
function getProduct($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category = c.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        jsonResponse(['error' => 'Producto no encontrado'], 404);
    }
    
    jsonResponse([
        'id' => $product['id'],
        'name' => $product['name'],
        'category' => $product['category_name'],
        'price' => (float) $product['price'],
        'stock' => (int) $product['stock'],
        'createdAt' => $product['created_at']
    ]);
}

// Crear producto
function createProduct($pdo) {
    $data = getJsonInput();
    
    if (empty($data['name']) || empty($data['category']) || !isset($data['price']) || !isset($data['stock'])) {
        jsonResponse(['error' => 'Nombre, categoria, precio y stock requeridos'], 400);
    }
    
    // Obtener ID de categoria
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
    $stmt->execute([$data['category']]);
    $category = $stmt->fetch();
    
    if (!$category) {
        jsonResponse(['error' => 'Categoria no valida'], 400);
    }
    
    $id = generateId();
    
    $stmt = $pdo->prepare("
        INSERT INTO products (id, name, category, price, stock, active) 
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
        $id, 
        $data['name'], 
        $category['id'], 
        $data['price'], 
        $data['stock']
    ]);
    
    jsonResponse([
        'success' => true,
        'id' => $id,
        'message' => 'Producto creado correctamente'
    ], 201);
}

// Actualizar producto
function updateProduct($pdo, $id) {
    $data = getJsonInput();
    
    if (empty($data['name']) || empty($data['category']) || !isset($data['price']) || !isset($data['stock'])) {
        jsonResponse(['error' => 'Nombre, categoria, precio y stock requeridos'], 400);
    }
    
    // Verificar que existe
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'Producto no encontrado'], 404);
    }
    
    // Obtener ID de categoria
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
    $stmt->execute([$data['category']]);
    $category = $stmt->fetch();
    
    if (!$category) {
        jsonResponse(['error' => 'Categoria no valida'], 400);
    }
    
    $stmt = $pdo->prepare("
        UPDATE products SET name = ?, category = ?, price = ?, stock = ? WHERE id = ?
    ");
    $stmt->execute([
        $data['name'], 
        $category['id'], 
        $data['price'], 
        $data['stock'],
        $id
    ]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Producto actualizado correctamente'
    ]);
}

// Eliminar producto (soft delete)
function deleteProduct($pdo, $id) {
    $stmt = $pdo->prepare("UPDATE products SET active = 0 WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Producto no encontrado'], 404);
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Producto eliminado correctamente'
    ]);
}
