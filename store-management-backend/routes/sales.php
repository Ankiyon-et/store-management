<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../models/Sale.php";
require_once __DIR__ . "/../helpers/functions.php";
require_once __DIR__ . "/../helpers/auth.php";

$decoded = requireAuth(); // 🔒 must login
$sale = new Sale($pdo);
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET': // 📌 list with search & pagination
        $search = $_GET['search'] ?? "";
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

        if (isset($_GET['id'])) {
            $data = $sale->getById($_GET['id']);
            if (!$data) {
                jsonResponse(["error" => "Sale not found"], 404);
            }
            jsonResponse($data);
        } else {
            $data = $sale->getAll($search, $page, $limit);
            jsonResponse($data);
        }
        break;

    case 'POST': // 📌 create
        $input = json_decode(file_get_contents("php://input"), true);

        if (!isset($input['customer_id'], $input['payment_method'], $input['status'], $input['items']) || !is_array($input['items'])) {
            jsonResponse(["error" => "customer_id, payment_method, status and items[] are required"], 400);
        }

        try {
            // validate payment method
            $validMethods = ['cash','card','bank_transfer','mobile'];
            if (!in_array($input['payment_method'], $validMethods)) {
                jsonResponse(["error" => "Invalid payment method"], 400);
            }

            // validate status
            $validStatuses = ['completed','refunded'];
            if (!in_array($input['status'], $validStatuses)) {
                jsonResponse(["error" => "Invalid sale status"], 400);
            }

            $id = $sale->create(
                $decoded->sub,
                $input['customer_id'],
                $input['payment_method'],
                $input['status'],
                $input['items'],
                $input['discount_amount'] ?? 0,
                $input['tax_amount'] ?? 0
            );

            auditLog($pdo, $decoded->sub, "create", "sales", $id, "Created sale");
            jsonResponse(["message" => "Sale created successfully", "id" => $id], 201);

        } catch (Exception $e) {
            jsonResponse(["error" => $e->getMessage()], 500);
        }
        break;

    case 'PUT': // 📌 update
        parse_str(file_get_contents("php://input"), $input);
        if (!isset($_GET['id'])) {
            jsonResponse(["error" => "Sale ID required"], 400);
        }

        try {
            $paymentMethod = $input['payment_method'] ?? null;
            $status = $input['status'] ?? null;

            if ($paymentMethod) {
                $validMethods = ['cash','card','bank_transfer','mobile'];
                if (!in_array($paymentMethod, $validMethods)) {
                    jsonResponse(["error" => "Invalid payment method"], 400);
                }
            }

            if ($status) {
                $validStatuses = ['completed','refunded'];
                if (!in_array($status, $validStatuses)) {
                    jsonResponse(["error" => "Invalid sale status"], 400);
                }
            }

            $ok = $sale->update($_GET['id'], $paymentMethod, $status);
            auditLog($pdo, $decoded->sub, "update", "sales", $_GET['id'], "Updated sale");
            jsonResponse(["message" => $ok ? "Sale updated" : "No changes"]);

        } catch (Exception $e) {
            jsonResponse(["error" => $e->getMessage()], 500);
        }
        break;

    case 'DELETE': // 📌 delete
        if (!isset($_GET['id'])) {
            jsonResponse(["error" => "Sale ID required"], 400);
        }

        try {
            $ok = $sale->delete($_GET['id']);
            auditLog($pdo, $decoded->sub, "delete", "sales", $_GET['id'], "Deleted sale");
            jsonResponse(["message" => "Sale deleted"]);

        } catch (Exception $e) {
            jsonResponse(["error" => $e->getMessage()], 500);
        }
        break;

    default:
        jsonResponse(["error" => "Method not allowed"], 405);
}
