<?php
header("Content-Type: application/json");
require_once("../config/db.php");

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

        /**
         * CREATE PURCHASE
         */
        case "POST":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['supplier_id'], $data['purchase_date'], $data['items']) || !is_array($data['items'])) {
                echo json_encode(["success" => false, "message" => "Missing required fields"]);
                exit;
            }

            $pdo->beginTransaction();

            // ✅ Check supplier exists
            $stmtCheck = $pdo->prepare("SELECT id FROM suppliers WHERE id=?");
            $stmtCheck->execute([$data['supplier_id']]);
            if ($stmtCheck->rowCount() == 0) {
                echo json_encode(["success" => false, "message" => "Supplier not found"]);
                exit;
            }

            // Calculate total
            $total = 0;
            foreach ($data['items'] as $item) {
                $total += $item['quantity'] * $item['cost'];
            }
            $status = ($data['amount_paid'] >= $total) ? "paid" : (($data['amount_paid'] > 0) ? "partial" : "unpaid");

            // Insert into purchases
            $stmt = $pdo->prepare("INSERT INTO purchases (supplier_id, purchase_date, total_amount, amount_paid, status, user_id) 
                                   VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['supplier_id'],
                $data['purchase_date'],
                $total,
                $data['amount_paid'],
                $status,
                1 // TODO: replace with logged-in user_id
            ]);
            $purchase_id = $pdo->lastInsertId();

            // Insert purchase_items
            $stmtItem = $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, cost, total) 
                                       VALUES (?, ?, ?, ?, ?)");
            foreach ($data['items'] as $item) {
                // ✅ Check product exists
                $stmtProd = $pdo->prepare("SELECT id FROM products WHERE id=?");
                $stmtProd->execute([$item['product_id']]);
                if ($stmtProd->rowCount() == 0) {
                    $pdo->rollBack();
                    echo json_encode(["success" => false, "message" => "Product not found: ID " . $item['product_id']]);
                    exit;
                }

                $line_total = $item['quantity'] * $item['cost'];
                $stmtItem->execute([$purchase_id, $item['product_id'], $item['quantity'], $item['cost'], $line_total]);
            }

            $pdo->commit();
            echo json_encode(["success" => true, "message" => "Purchase created", "id" => $purchase_id]);
            break;

        /**
         * GET PURCHASE(S)
         */
        case "GET":
            if (isset($_GET['id'])) {
                // Single purchase
                $stmt = $pdo->prepare("SELECT p.*, s.name AS supplier_name 
                                       FROM purchases p 
                                       JOIN suppliers s ON p.supplier_id = s.id 
                                       WHERE p.id=?");
                $stmt->execute([$_GET['id']]);
                $purchase = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($purchase) {
                    $stmtItems = $pdo->prepare("SELECT pi.*, pr.name AS product_name 
                                                FROM purchase_items pi 
                                                JOIN products pr ON pi.product_id = pr.id 
                                                WHERE pi.purchase_id=?");
                    $stmtItems->execute([$_GET['id']]);
                    $purchase['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode(["success" => true, "data" => $purchase]);
                } else {
                    echo json_encode(["success" => false, "message" => "Purchase not found"]);
                }
            } else {
                // All purchases
                $stmt = $pdo->query("SELECT p.*, s.name AS supplier_name 
                                     FROM purchases p 
                                     JOIN suppliers s ON p.supplier_id = s.id 
                                     ORDER BY p.purchase_date DESC");
                $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(["success" => true, "data" => $purchases]);
            }
            break;

        /**
         * UPDATE PURCHASE
         * For simplicity: delete old items & re-insert
         */
        case "PUT":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['id'], $data['supplier_id'], $data['purchase_date'], $data['items']) || !is_array($data['items'])) {
                echo json_encode(["success" => false, "message" => "Missing required fields"]);
                exit;
            }

            $pdo->beginTransaction();

            // Recalculate total
            $total = 0;
            foreach ($data['items'] as $item) {
                $total += $item['quantity'] * $item['cost'];
            }
            $status = ($data['amount_paid'] >= $total) ? "paid" : (($data['amount_paid'] > 0) ? "partial" : "unpaid");

            // Update purchases
            $stmt = $pdo->prepare("UPDATE purchases SET supplier_id=?, purchase_date=?, total_amount=?, amount_paid=?, status=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$data['supplier_id'], $data['purchase_date'], $total, $data['amount_paid'], $status, $data['id']]);

            // Delete old items
            $pdo->prepare("DELETE FROM purchase_items WHERE purchase_id=?")->execute([$data['id']]);

            // Insert new items
            $stmtItem = $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, cost, total) VALUES (?, ?, ?, ?, ?)");
            foreach ($data['items'] as $item) {
                $line_total = $item['quantity'] * $item['cost'];
                $stmtItem->execute([$data['id'], $item['product_id'], $item['quantity'], $item['cost'], $line_total]);
            }

            $pdo->commit();
            echo json_encode(["success" => true, "message" => "Purchase updated"]);
            break;

        /**
         * DELETE PURCHASE
         */
        case "DELETE":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['id'])) {
                echo json_encode(["success" => false, "message" => "Purchase ID is required"]);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM purchases WHERE id=?");
            $stmt->execute([$data['id']]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Purchase deleted"]);
            } else {
                echo json_encode(["success" => false, "message" => "Purchase not found"]);
            }
            break;

        default:
            echo json_encode(["success" => false, "message" => "Method not allowed"]);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
