<?php
/**
 * SAVE_FCM_TOKEN.PHP - Simpan token FCM ke database
 * Version: 1.0.0
 */
require_once 'config.php';

header('Content-Type: application/json');

// Ambil input JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

$token = $input['token'] ?? '';
$user_id = isset($input['user_id']) ? (int)$input['user_id'] : 0;
$marketing_id = isset($input['marketing_id']) ? (int)$input['marketing_id'] : 0;

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Token required']);
    exit;
}

$conn = getDB();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

try {
    // Buat tabel jika belum ada
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `fcm_tokens` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `token` VARCHAR(255) NOT NULL,
            `user_id` INT NULL,
            `marketing_id` INT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE INDEX `token_UNIQUE` (`token` ASC),
            INDEX `idx_user` (`user_id` ASC),
            INDEX `idx_marketing` (`marketing_id` ASC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Simpan token
    $sql = "INSERT INTO fcm_tokens (token, user_id, marketing_id, created_at) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                user_id = VALUES(user_id),
                marketing_id = VALUES(marketing_id),
                updated_at = NOW()";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$token, $user_id, $marketing_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Token saved successfully',
        'token' => $token
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>