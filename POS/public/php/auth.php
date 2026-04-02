<?php
/**
 * API de Autenticacion
 * POST /auth.php?action=setup     - Configuracion inicial
 * POST /auth.php?action=login     - Iniciar sesion
 * GET  /auth.php?action=check     - Verificar si hay setup
 */

require_once 'config.php';
setHeaders();

$action = $_GET['action'] ?? '';
$pdo = getConnection();

switch ($action) {
    case 'check':
        checkSetup($pdo);
        break;
    case 'setup':
        initialSetup($pdo);
        break;
    case 'login':
        login($pdo);
        break;
    default:
        jsonResponse(['error' => 'Accion no valida'], 400);
}

// Verificar si ya se hizo el setup
function checkSetup($pdo) {
    $stmt = $pdo->query("SELECT * FROM config LIMIT 1");
    $config = $stmt->fetch();
    
    if ($config && $config['is_setup']) {
        jsonResponse([
            'isSetup' => true,
            'businessName' => $config['business_name']
        ]);
    } else {
        jsonResponse(['isSetup' => false]);
    }
}

// Setup inicial - Crear admin
function initialSetup($pdo) {
    // Verificar si ya existe setup
    $stmt = $pdo->query("SELECT is_setup FROM config LIMIT 1");
    $config = $stmt->fetch();
    
    if ($config && $config['is_setup']) {
        jsonResponse(['error' => 'El sistema ya fue configurado'], 400);
    }
    
    $data = getJsonInput();
    
    if (empty($data['businessName']) || empty($data['adminName']) || 
        empty($data['adminUser']) || empty($data['adminPass'])) {
        jsonResponse(['error' => 'Todos los campos son requeridos'], 400);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Guardar configuracion
        if ($config) {
            $stmt = $pdo->prepare("UPDATE config SET business_name = ?, is_setup = 1");
            $stmt->execute([$data['businessName']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO config (business_name, is_setup) VALUES (?, 1)");
            $stmt->execute([$data['businessName']]);
        }
        
        // Crear usuario admin
        $userId = generateId();
        $hashedPassword = password_hash($data['adminPass'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (id, name, username, password, role, active) 
            VALUES (?, ?, ?, ?, 'admin', 1)
        ");
        $stmt->execute([$userId, $data['adminName'], $data['adminUser'], $hashedPassword]);
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'Sistema configurado correctamente'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Error al configurar: ' . $e->getMessage()], 500);
    }
}

// Login
function login($pdo) {
    $data = getJsonInput();
    
    if (empty($data['username']) || empty($data['password'])) {
        jsonResponse(['error' => 'Usuario y contrasena requeridos'], 400);
    }
    
    $stmt = $pdo->prepare("
        SELECT id, name, username, password, role, sales_count, active 
        FROM users 
        WHERE username = ? AND active = 1
    ");
    $stmt->execute([$data['username']]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($data['password'], $user['password'])) {
        jsonResponse(['error' => 'Usuario o contrasena incorrectos'], 401);
    }
    
    // Quitar password de la respuesta
    unset($user['password']);
    
    // Generar token simple (en produccion usar JWT)
    $token = bin2hex(random_bytes(32));
    
    jsonResponse([
        'success' => true,
        'user' => $user,
        'token' => $token
    ]);
}
