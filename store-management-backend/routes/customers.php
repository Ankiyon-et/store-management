<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../models/Customer.php";
require_once __DIR__ . "/../helpers/functions.php";
require_once __DIR__ . "/../helpers/auth.php";

$decoded = requireAuth(); // 🔒 protect with auth
$customer = new Customer($pdo);
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $data = $customer->getById($_GET['id']);
            jsonResponse($data);
        } else {
            $data = $customer->getAll();
            jsonResponse($data);
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents("php://input"), true);
        if (!isset($input['name'])) {
            jsonResponse(["error" => "Customer name required"], 400);
        }
        $id = $customer->create(
            $input['name'],
            $input['email'] ?? null,
            $input['phone'] ?? null,
            $input['address'] ?? null
        );
        auditLog($pdo, $decoded->sub, "create", "customers", $id, "Created customer {$input['name']}");
        jsonResponse(["message" => "Customer created", "id" => $id], 201);
        break;

    case 'PUT':
        if (!isset($_GET['id'])) {
            jsonResponse(["error" => "Customer ID required"], 400);
        }
        $input = json_decode(file_get_contents("php://input"), true);
        $success = $customer->update(
            $_GET['id'],
            $input['name'] ?? null,
            $input['email'] ?? null,
            $input['phone'] ?? null,
            $input['address'] ?? null
        );
        auditLog($pdo, $decoded->sub, "update", "customers", $_GET['id'], "Updated customer");
        jsonResponse(["success" => $success]);
        break;

    case 'DELETE':
        if (!isset($_GET['id'])) {
            jsonResponse(["error" => "Customer ID required"], 400);
        }
        $success = $customer->delete($_GET['id']);
        auditLog($pdo, $decoded->sub, "delete", "customers", $_GET['id'], "Deleted customer");
        jsonResponse(["success" => $success]);
        break;

    default:
        jsonResponse(["error" => "Method not allowed"], 405);
}
