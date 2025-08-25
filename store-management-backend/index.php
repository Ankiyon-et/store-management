<?php
$path = $_GET['path'] ?? null;

switch ($path) {
    case 'user':
        require __DIR__ . '/routes/user.php';
        break;

    default:
        header("Content-Type: application/json");
        echo json_encode(["status" => "error", "message" => "Invalid route"]);
}
