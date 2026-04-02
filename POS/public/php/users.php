<?php
/**
 * API de Usuarios (Vendedores)
 * GET    /users.php              - Listar vendedores
 * GET    /users.php?id=xxx       - Obtener vendedor
 * POST   /users.php              - Crear vendedor
 * PUT    /users.php?id=xxx       - Actualizar vendedor
 * DELETE /users.php?id=xxx       - Eliminar vendedor
 * PATCH  /users.php?id=xxx       - Toggle estado activo
 */

require_once 'config.php';
setHeaders();

$pdo = getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($id) {
            getUser($pdo, $id);
        } else {
            getUsers($pdo);
        }
        break;
    case 'POST':
        createUser($pdo);
        break;
    case 'PUT':
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
        updateUser($pdo, $id);
        break;
    case 'DELETE':
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
        deleteUser($pdo, $id);
        break;
    case 'PATCH':
        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
        toggleUserStatus($pdo, $id);
        break;
    default:
        jsonResponse(['error' => 'Metodo no permitido'], 405);
}

// Listar vendedores
function getUsers($pdo) {
    $stmt = $pdo->query("
        SELECT id, name, username, role, sales_count, active, created_at 
        FROM users 
        WHERE role = 'vendedor'
        ORDER BY created_at DESC
    ");
    jsonResponse($stmt->fetchAll());
}

// Obtener un vendedor
function getUser($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT id, name, username, role, sales_count, active, created_at 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(['error' => 'Usuario no encontrado'], 404);
    }
    
    jsonResponse($user);
}

// Crear vendedor
function createUser($pdo) {
    $data = getJsonInput();
    
    if (empty($data['name']) || empty($data['username']) || empty($data['password'])) {
        jsonResponse(['error' => 'Nombre, usuario y contrasena requeridos'], 400);
    }
    
    // Verificar si username existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$data['username']]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Este usuario ya existe'], 400);
    }
    
    $id = generateId();
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (id, name, username, password, role, active) 
        VALUES (?, ?, ?, ?, 'vendedor', 1)
    ");
    $stmt->execute([$id, $data['name'], $data['username'], $hashedPassword]);
    
    jsonResponse([
        'success' => true,
        'id' => $id,
        'message' => 'Vendedor creado correctamente'
    ], 201);
}

// Actualizar vendedor
function updateUser($pdo, $id) {
    $data = getJsonInput();
    
    if (empty($data['name']) || empty($data['username'])) {
        jsonResponse(['error' => 'Nombre y usuario requeridos'], 400);
    }
    
    // Verificar si username existe en otro usuario
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$data['username'], $id]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Este usuario ya existe'], 400);
    }
    
    if (!empty($data['password'])) {
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users SET name = ?, username = ?, password = ? WHERE id = ?
        ");
        $stmt->execute([$data['name'], $data['username'], $hashedPassword, $id]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE users SET name = ?, username = ? WHERE id = ?
        ");
        $stmt->execute([$data['name'], $data['username'], $id]);
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Vendedor actualizado correctamente'
    ]);
}

// Eliminar vendedor
function deleteUser($pdo, $id) {
    // No permitir eliminar admin
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(['error' => 'Usuario no encontrado'], 404);
    }
    
    if ($user['role'] === 'admin') {
        jsonResponse(['error' => 'No se puede eliminar al administrador'], 400);
    }
    
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Vendedor eliminado correctamente'
    ]);
}

// Toggle estado activo
function toggleUserStatus($pdo, $id) {
    $stmt = $pdo->prepare("UPDATE users SET active = NOT active WHERE id = ? AND role = 'vendedor'");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Usuario no encontrado o es admin'], 404);
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Estado actualizado'
    ]);
}
