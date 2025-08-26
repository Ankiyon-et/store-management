<?php
header("Content-Type: application/json");
require_once("../config/db.php");

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

        /**
         * CREATE PURCHASE ITEM
         * Example JSON:
         * {
         *   "purchase_id": 1,
         *   "product_id": 3,
         *   "quantity": 10,
         *   "cost": 50
         * }
         */
        case "POST":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['purchase_id'], $data['product_id'], $data['quantity'], $data['cost'])) {
                echo json_encode(["success" => false, "message" => "Missing required fields"]);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, cost) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['purchase_id'],
                $data['product_id'],
                $data['quantity'],
                $data['cost']
            ]);

            echo json_encode([
                "success" => true,
                "message" => "Purchase item added",
                "id" => $pdo->lastInsertId()
            ]);
            break;

        /**
         * READ PURCHASE ITEMS
         */
        case "GET":
            if (isset($_GET['id'])) {
                // Single item
                $stmt = $pdo->prepare("SELECT pi.*, p.name AS product_name
                                       FROM purchase_items pi
                                       JOIN products p ON pi.product_id = p.id
                                       WHERE pi.id=?");
                $stmt->execute([$_GET['id']]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($item) {
                    echo json_encode(["success" => true, "data" => $item]);
                } else {
                    echo json_encode(["success" => false, "message" => "Purchase item not found"]);
                }
            } elseif (isset($_GET['purchase_id'])) {
                // All items for a specific purchase
                $stmt = $pdo->prepare("SELECT pi.*, p.name AS product_name
                                       FROM purchase_items pi
                                       JOIN products p ON pi.product_id = p.id
                                       WHERE pi.purchase_id=?");
                $stmt->execute([$_GET['purchase_id']]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(["success" => true, "data" => $items]);
            } else {
                // All items (not common, but for debugging)
                $stmt = $pdo->query("SELECT pi.*, p.name AS product_name
                                     FROM purchase_items pi
                                     JOIN products p ON pi.product_id = p.id
                                     ORDER BY pi.id DESC");
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(["success" => true, "data" => $items]);
            }
            break;

        /**
         * UPDATE PURCHASE ITEM
         * Example JSON:
         * {
         *   "id": 5,
         *   "quantity": 20,
         *   "cost": 45
         * }
         */
        case "PUT":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['id'], $data['quantity'], $data['cost'])) {
                echo json_encode(["success" => false, "message" => "Item ID, quantity, and unit price are required"]);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE purchase_items 
                                   SET quantity=?, cost=? 
                                   WHERE id=?");
            $stmt->execute([
                $data['quantity'],
                $data['cost'],
                $data['id']
            ]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Purchase item updated"]);
            } else {
                echo json_encode(["success" => false, "message" => "No changes or item not found"]);
            }
            break;

        /**
         * DELETE PURCHASE ITEM
         * Example JSON:
         * { "id": 5 }
         */
        case "DELETE":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['id'])) {
                echo json_encode(["success" => false, "message" => "Item ID is required"]);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM purchase_items WHERE id=?");
            $stmt->execute([$data['id']]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Purchase item deleted"]);
            } else {
                echo json_encode(["success" => false, "message" => "Purchase item not found"]);
            }
            break;

        default:
            echo json_encode(["success" => false, "message" => "Method not allowed"]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
