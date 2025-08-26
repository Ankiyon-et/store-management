<?php
header("Content-Type: application/json");
require_once("../config/db.php");

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

        /**
         * CREATE CATEGORY
         * Example JSON:
         * { "name": "Utilities" }
         */
        case "POST":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['name']) || empty(trim($data['name']))) {
                echo json_encode(["success" => false, "message" => "Category name is required"]);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO expense_categories (name) VALUES (?)");
            $stmt->execute([trim($data['name'])]);

            echo json_encode([
                "success" => true,
                "message" => "Expense category created",
                "id" => $pdo->lastInsertId()
            ]);
            break;

        /**
         * READ CATEGORIES
         */
        case "GET":
            if (isset($_GET['id'])) {
                // Single category
                $stmt = $pdo->prepare("SELECT * FROM expense_categories WHERE id=?");
                $stmt->execute([$_GET['id']]);
                $category = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($category) {
                    echo json_encode(["success" => true, "data" => $category]);
                } else {
                    echo json_encode(["success" => false, "message" => "Category not found"]);
                }
            } else {
                // All categories
                $stmt = $pdo->query("SELECT * FROM expense_categories ORDER BY name ASC");
                $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(["success" => true, "data" => $categories]);
            }
            break;

        /**
         * UPDATE CATEGORY
         * Example JSON:
         * { "id": 1, "name": "Rent" }
         */
        case "PUT":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['id'], $data['name'])) {
                echo json_encode(["success" => false, "message" => "Category ID and name are required"]);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE expense_categories SET name=? WHERE id=?");
            $stmt->execute([$data['name'], $data['id']]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Category updated"]);
            } else {
                echo json_encode(["success" => false, "message" => "No changes or category not found"]);
            }
            break;

        /**
         * DELETE CATEGORY
         * Example JSON:
         * { "id": 1 }
         */
        case "DELETE":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['id'])) {
                echo json_encode(["success" => false, "message" => "Category ID is required"]);
                exit;
            }

            // ⚠️ Prevent deleting if linked to expenses
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE category_id=?");
            $stmtCheck->execute([$data['id']]);
            if ($stmtCheck->fetchColumn() > 0) {
                echo json_encode(["success" => false, "message" => "Category is linked to expenses and cannot be deleted"]);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM expense_categories WHERE id=?");
            $stmt->execute([$data['id']]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Category deleted"]);
            } else {
                echo json_encode(["success" => false, "message" => "Category not found"]);
            }
            break;

        default:
            echo json_encode(["success" => false, "message" => "Method not allowed"]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
