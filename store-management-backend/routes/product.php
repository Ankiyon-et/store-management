<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../models/Product.php";
require_once __DIR__ . "/../helpers/functions.php";

require_once __DIR__ . '/../helpers/auth.php';

$decoded = requireAuth();

$product = new Product($pdo);

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $data = $product->getById($_GET['id']);
            jsonResponse($data);
        } else {
            $data = $product->getAll();
            jsonResponse($data);
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents("php://input"), true);
        if (!isset($input['name'], $input['price'], $input['cost'])) {
            jsonResponse(["error" => "Required fields missing"], 400);
        }
        $id = $product->create(
            $input['name'],
            $input['barcode'] ?? null,
            $input['category_id'] ?? null,
            $input['price'],
            $input['cost'],
            $input['unit'] ?? null,
            $input['image_url'] ?? null,
            $input['description'] ?? null
        );
        auditLog($pdo, 1, "create", "products", $id, "Created product {$input['name']}");
        jsonResponse(["message" => "Product created", "id" => $id], 201);
        break;

    case 'PUT':
        if (!isset($_GET['id'])) {
            jsonResponse(["error" => "Product ID required"], 400);
        }
        $input = json_decode(file_get_contents("php://input"), true);
        $success = $product->update(
            $_GET['id'],
            $input['name'],
            $input['barcode'] ?? null,
            $input['category_id'] ?? null,
            $input['price'],
            $input['cost'],
            $input['unit'] ?? null,
            $input['image_url'] ?? null,
            $input['description'] ?? null
        );
        auditLog($pdo, 1, "update", "products", $_GET['id'], "Updated product");
        jsonResponse(["success" => $success]);
        break;

    case 'DELETE':
        if (!isset($_GET['id'])) {
            jsonResponse(["error" => "Product ID required"], 400);
        }
        $success = $product->delete($_GET['id']);
        auditLog($pdo, 1, "delete", "products", $_GET['id'], "Deleted product");
        jsonResponse(["success" => $success]);
        break;

    default:
        jsonResponse(["error" => "Method not allowed"], 405);
}
