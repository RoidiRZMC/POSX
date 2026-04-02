<?php
/**
 * Esquema de Base de Datos MySQL
 * Ejecutar una sola vez para crear las tablas
 * 
 * php schema.php
 */

require_once 'config.php';

try {
    // Crear base de datos si no existe
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");
    
    // Tabla: configuracion (setup inicial)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `config` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `business_name` VARCHAR(255) NOT NULL,
            `is_setup` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");
    
    // Tabla: usuarios (admin y vendedores)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` VARCHAR(32) PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `username` VARCHAR(100) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `role` ENUM('admin', 'vendedor') NOT NULL DEFAULT 'vendedor',
            `sales_count` INT DEFAULT 0,
            `active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_username` (`username`),
            INDEX `idx_role` (`role`),
            INDEX `idx_active` (`active`)
        ) ENGINE=InnoDB
    ");
    
    // Tabla: categorias
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `categories` (
            `id` VARCHAR(32) PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL UNIQUE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");
    
    // Insertar categorias por defecto
    $pdo->exec("
        INSERT IGNORE INTO `categories` (`id`, `name`) VALUES
        ('cat_bebidas', 'bebidas'),
        ('cat_comidas', 'comidas'),
        ('cat_dulces', 'dulces'),
        ('cat_otros', 'otros')
    ");
    
    // Tabla: productos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `products` (
            `id` VARCHAR(32) PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `category` VARCHAR(32) NOT NULL,
            `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            `stock` INT NOT NULL DEFAULT 0,
            `active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_category` (`category`),
            INDEX `idx_active` (`active`),
            INDEX `idx_stock` (`stock`),
            FOREIGN KEY (`category`) REFERENCES `categories`(`id`) ON UPDATE CASCADE
        ) ENGINE=InnoDB
    ");
    
    // Tabla: clientes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `customers` (
            `id` VARCHAR(32) PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `phone` VARCHAR(50) NULL,
            `email` VARCHAR(255) NULL,
            `total_purchases` DECIMAL(12, 2) DEFAULT 0.00,
            `last_purchase` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_name` (`name`),
            INDEX `idx_phone` (`phone`)
        ) ENGINE=InnoDB
    ");
    
    // Tabla: ventas
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `sales` (
            `id` VARCHAR(32) PRIMARY KEY,
            `customer_id` VARCHAR(32) NULL,
            `user_id` VARCHAR(32) NOT NULL,
            `total` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_customer` (`customer_id`),
            INDEX `idx_user` (`user_id`),
            INDEX `idx_date` (`created_at`),
            FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB
    ");
    
    // Tabla: detalle de ventas (items)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `sale_items` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `sale_id` VARCHAR(32) NOT NULL,
            `product_id` VARCHAR(32) NOT NULL,
            `product_name` VARCHAR(255) NOT NULL,
            `price` DECIMAL(10, 2) NOT NULL,
            `quantity` INT NOT NULL DEFAULT 1,
            `subtotal` DECIMAL(12, 2) NOT NULL,
            INDEX `idx_sale` (`sale_id`),
            INDEX `idx_product` (`product_id`),
            FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB
    ");
    
    // Tabla: cierres de caja
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `cash_closures` (
            `id` VARCHAR(32) PRIMARY KEY,
            `open_time` TIMESTAMP NOT NULL,
            `close_time` TIMESTAMP NOT NULL,
            `total_sales` INT NOT NULL DEFAULT 0,
            `total_cash` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
            `closed_by` VARCHAR(32) NOT NULL,
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_closed_by` (`closed_by`),
            INDEX `idx_close_time` (`close_time`),
            FOREIGN KEY (`closed_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB
    ");
    
    // Tabla: detalle de cierre (productos vendidos)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `closure_products` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `closure_id` VARCHAR(32) NOT NULL,
            `product_name` VARCHAR(255) NOT NULL,
            `quantity` INT NOT NULL,
            INDEX `idx_closure` (`closure_id`),
            FOREIGN KEY (`closure_id`) REFERENCES `cash_closures`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    echo "Base de datos y tablas creadas exitosamente!\n";
    echo "Base de datos: " . DB_NAME . "\n";
    echo "Tablas creadas:\n";
    echo "  - config\n";
    echo "  - users\n";
    echo "  - categories\n";
    echo "  - products\n";
    echo "  - customers\n";
    echo "  - sales\n";
    echo "  - sale_items\n";
    echo "  - cash_closures\n";
    echo "  - closure_products\n";
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
