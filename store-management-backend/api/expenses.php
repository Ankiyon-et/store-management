<?php
header("Content-Type: application/json");
require_once("../config/db.php");

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

        /**
         * CREATE EXPENSE
         * Example JSON body:
         * {
         *   "category_id": 1,
         *   "amount": 2000,
         *   "description": "Monthly rent",
         *   "expense_date": "2025-08-24",
         *   "payment_method": "cash",
         *   "bank_account_id": 1
         * }
         */
        case "POST":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['category_id'], $data['amount'], $data['expense_date'], $data['payment_method'])) {
                echo json_encode(["success" => false, "message" => "Missing required fields"]);
                exit;
            }

            // ✅ Check category exists
            $stmtCheck = $pdo->prepare("SELECT id FROM expense_categories WHERE id=?");
            $stmtCheck->execute([$data['category_id']]);
            if ($stmtCheck->rowCount() == 0) {
                echo json_encode(["success" => false, "message" => "Expense category not found"]);
                exit;
            }

            // Insert expense
            $stmt = $pdo->prepare("INSERT INTO expenses (category_id, amount, description, expense_date, payment_method, bank_account_id, user_id) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['category_id'],
                $data['amount'],
                $data['description'] ?? null,
                $data['expense_date'],
                $data['payment_method'],
                $data['bank_account_id'] ?? null,
                1 // TODO: replace with logged-in user_id
            ]);

            $expense_id = $pdo->lastInsertId();

            // (Optional) Insert into bank_transactions if linked to a bank account
            if (!empty($data['bank_account_id'])) {
                $stmtBank = $pdo->prepare("INSERT INTO bank_transactions (bank_account_id, transaction_type, amount, description, transaction_date, related_expense_id) 
                                           VALUES (?, 'EXPENSE', ?, ?, ?, ?)");
                $stmtBank->execute([
                    $data['bank_account_id'],
                    $data['amount'],
                    $data['description'] ?? 'Expense Payment',
                    $data['expense_date'],
                    $expense_id
                ]);
            }

            echo json_encode(["success" => true, "message" => "Expense recorded", "id" => $expense_id]);
            break;

        /**
         * READ EXPENSE(S)
         */
        case "GET":
            if (isset($_GET['id'])) {
                // Single expense
                $stmt = $pdo->prepare("SELECT e.*, c.name AS category_name, b.name AS bank_name 
                                       FROM expenses e 
                                       JOIN expense_categories c ON e.category_id = c.id
                                       LEFT JOIN bank_accounts b ON e.bank_account_id = b.id
                                       WHERE e.id=?");
                $stmt->execute([$_GET['id']]);
                $expense = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($expense) {
                    echo json_encode(["success" => true, "data" => $expense]);
                } else {
                    echo json_encode(["success" => false, "message" => "Expense not found"]);
                }
            } else {
                // All expenses
                $stmt = $pdo->query("SELECT e.*, c.name AS category_name, b.name AS bank_name 
                                     FROM expenses e 
                                     JOIN expense_categories c ON e.category_id = c.id
                                     LEFT JOIN bank_accounts b ON e.bank_account_id = b.id
                                     ORDER BY e.expense_date DESC");
                $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(["success" => true, "data" => $expenses]);
            }
            break;

        /**
         * UPDATE EXPENSE
         */
        case "PUT":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['id'], $data['category_id'], $data['amount'], $data['expense_date'], $data['payment_method'])) {
                echo json_encode(["success" => false, "message" => "Missing required fields"]);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE expenses SET category_id=?, amount=?, description=?, expense_date=?, payment_method=?, bank_account_id=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([
                $data['category_id'],
                $data['amount'],
                $data['description'],
                $data['expense_date'],
                $data['payment_method'],
                $data['bank_account_id'],
                $data['id']
            ]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Expense updated"]);
            } else {
                echo json_encode(["success" => false, "message" => "No changes or expense not found"]);
            }
            break;

        /**
         * DELETE EXPENSE
         */
        case "DELETE":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['id'])) {
                echo json_encode(["success" => false, "message" => "Expense ID is required"]);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM expenses WHERE id=?");
            $stmt->execute([$data['id']]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Expense deleted"]);
            } else {
                echo json_encode(["success" => false, "message" => "Expense not found"]);
            }
            break;

        default:
            echo json_encode(["success" => false, "message" => "Method not allowed"]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
