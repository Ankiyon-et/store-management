<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../models/User.php";
require_once __DIR__ . "/../helpers/functions.php";

require_once __DIR__ . '/../helpers/auth.php';

$decoded = requireAuth();

$user = new User($pdo);
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $data = $user->getById($_GET['id']);
            jsonResponse($data);
        } else {
            $data = $user->getAll();
            jsonResponse($data);
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents("php://input"), true);
        if (!isset($input['name'], $input['username'], $input['password'], $input['role_id'])) {
            jsonResponse(["error" => "Required fields missing"], 400);
        }
        $id = $user->create(
            $input['name'],
            $input['username'],
            $input['password'],
            $input['email'] ?? null,
            $input['phone'] ?? null,
            $input['role_id'],
            $input['status'] ?? 'active'
        );
        auditLog($pdo, 1, "create", "users", $id, "Created user {$input['username']}");
        jsonResponse(["message" => "User created", "id" => $id], 201);
        break;

    case 'PUT':
        if (!isset($_GET['id'])) {
            jsonResponse(["error" => "User ID required"], 400);
        }
        $input = json_decode(file_get_contents("php://input"), true);
        $success = $user->update(
            $_GET['id'],
            $input['name'],
            $input['username'],
            $input['password'] ?? null,
            $input['email'] ?? null,
            $input['phone'] ?? null,
            $input['role_id'] ?? null,
            $input['status'] ?? 'active'
        );
        auditLog($pdo, 1, "update", "users", $_GET['id'], "Updated user");
        jsonResponse(["success" => $success]);
        break;

    case 'DELETE':
        if (!isset($_GET['id'])) {
            jsonResponse(["error" => "User ID required"], 400);
        }
        $success = $user->delete($_GET['id']);
        auditLog($pdo, 1, "delete", "users", $_GET['id'], "Deleted user");
        jsonResponse(["success" => $success]);
        break;

    default:
        jsonResponse(["error" => "Method not allowed"], 405);
}
