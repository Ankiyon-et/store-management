<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../models/Category.php";
require_once __DIR__ . "/../helpers/functions.php";
require_once __DIR__ . '/../helpers/auth.php';

$decoded = requireAuth();

$category = new Category($pdo);
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $data = $category->getById($_GET['id']);
            jsonResponse($data);
        } else {
            $data = $category->getAll();
            jsonResponse($data);
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents("php://input"), true);
        if (!$input || !isset($input['name'])) {
            jsonResponse(["error" => "Category name is required"], 400);
        }
        $id = $category->create($input['name'], $input['parent_id'] ?? null);
        auditLog($pdo, 1, "create", "categories", $id, "Created category {$input['name']}");
        jsonResponse(["message" => "Category created", "id" => $id], 201);
        break;

    case 'PUT':
        $input = json_decode(file_get_contents("php://input"), true);
        if (!isset($_GET['id'])) {
            jsonResponse(["error" => "Category ID required"], 400);
        }
        $success = $category->update($_GET['id'], $input['name'], $input['parent_id'] ?? null);
        auditLog($pdo, 1, "update", "categories", $_GET['id'], "Updated category");
        jsonResponse(["success" => $success]);
        break;

    case 'DELETE':
        if (!isset($_GET['id'])) {
            jsonResponse(["error" => "Category ID required"], 400);
        }
        $success = $category->delete($_GET['id']);
        auditLog($pdo, 1, "delete", "categories", $_GET['id'], "Deleted category");
        jsonResponse(["success" => $success]);
        break;

    default:
        jsonResponse(["error" => "Method not allowed"], 405);
}
