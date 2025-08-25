<?php
if (!function_exists('jsonResponse')) {
    function jsonResponse($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

if (!function_exists('auditLog')) {
    function auditLog($pdo, $userId, $action, $table, $recordId, $description) {
        $stmt = $pdo->prepare("INSERT INTO audit_logs 
            (user_id, action, table_name, record_id, description, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $table, $recordId, $description]);
    }
}
