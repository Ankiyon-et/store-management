<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../models/Role.php";
require_once __DIR__ . "/../helpers/functions.php";

require_once __DIR__ . '/../helpers/auth.php';

$decoded = requireAuth();

$role = new Role($pdo);
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $data = $role->getById($_GET['id']);
            jsonResponse($data);
        } else {
            $data = $role->getAll();
            jsonResponse($data);
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents("php://input"), true);
        if (!$input || !isset($input['name'])) {
            jsonResponse(["error" => "Role name is required"], 400);
        }
        $id = $role->create($input['name'], $input['description'] ?? null);
        auditLog($pdo, 1, "create", "roles", $id, "Created role {$input['name']}");
        jsonResponse(["message" => "Role created", "id" => $id], 201);
        break;

    case 'PUT':
        if (!isset($_GET['id'])) {
            jsonResponse(["error" => "Role ID required"], 400);
        }
        $input = json_decode(file_get_contents("php://input"), true);
        $success = $role->update($_GET['id'], $input['name'], $input['description'] ?? null);
        auditLog($pdo, 1, "update", "roles", $_GET['id'], "Updated role");
        jsonResponse(["success" => $success]);
        break;

    case 'DELETE':
        if (!isset($_GET['id'])) {
            jsonResponse(["error" => "Role ID required"], 400);
        }
        $success = $role->delete($_GET['id']);
        auditLog($pdo, 1, "delete", "roles", $_GET['id'], "Deleted role");
        jsonResponse(["success" => $success]);
        break;

    default:
        jsonResponse(["error" => "Method not allowed"], 405);
}
