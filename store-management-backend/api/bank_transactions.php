<?php
header("Content-Type: application/json");
require_once("../config/db.php");

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

        /**
         * CREATE TRANSACTION (Manual only)
         * Example JSON:
         * {
         *   "bank_account_id": 1,
         *   "transaction_type": "INCOME",   // INCOME | EXPENSE | TRANSFER
         *   "amount": 1500,
         *   "description": "Loan deposit",
         *   "transaction_date": "2025-08-24"
         * }
         */
        case "POST":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['bank_account_id'], $data['transaction_type'], $data['amount'], $data['transaction_date'])) {
                echo json_encode(["success" => false, "message" => "Missing required fields"]);
                exit;
            }

            // ✅ Check bank account exists
            $stmtCheck = $pdo->prepare("SELECT id FROM bank_accounts WHERE id=?");
            $stmtCheck->execute([$data['bank_account_id']]);
            if ($stmtCheck->rowCount() == 0) {
                echo json_encode(["success" => false, "message" => "Bank account not found"]);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO bank_transactions 
                (bank_account_id, transaction_type, amount, description, transaction_date) 
                VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['bank_account_id'],
                strtoupper($data['transaction_type']),
                $data['amount'],
                $data['description'] ?? null,
                $data['transaction_date']
            ]);

            echo json_encode([
                "success" => true,
                "message" => "Bank transaction recorded",
                "id" => $pdo->lastInsertId()
            ]);
            break;

        /**
         * READ TRANSACTIONS
         */
        case "GET":
            if (isset($_GET['id'])) {
                // Single transaction
                $stmt = $pdo->prepare("SELECT bt.*, ba.name AS bank_name 
                                       FROM bank_transactions bt
                                       JOIN bank_accounts ba ON bt.bank_account_id = ba.id
                                       WHERE bt.id=?");
                $stmt->execute([$_GET['id']]);
                $tx = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($tx) {
                    echo json_encode(["success" => true, "data" => $tx]);
                } else {
                    echo json_encode(["success" => false, "message" => "Transaction not found"]);
                }
            } else {
                // All transactions
                $stmt = $pdo->query("SELECT bt.*, ba.name AS bank_name 
                                     FROM bank_transactions bt
                                     JOIN bank_accounts ba ON bt.bank_account_id = ba.id
                                     ORDER BY bt.transaction_date DESC");
                $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(["success" => true, "data" => $transactions]);
            }
            break;

        /**
         * UPDATE TRANSACTION (Manual only)
         */
        case "PUT":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['id'], $data['bank_account_id'], $data['transaction_type'], $data['amount'], $data['transaction_date'])) {
                echo json_encode(["success" => false, "message" => "Missing required fields"]);
                exit;
            }

            // ⚠️ Prevent updating if linked to sale/expense/transfer
            $stmtCheck = $pdo->prepare("SELECT related_sale_id, related_expense_id, related_transfer_id 
                                        FROM bank_transactions WHERE id=?");
            $stmtCheck->execute([$data['id']]);
            $check = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($check && ($check['related_sale_id'] || $check['related_expense_id'] || $check['related_transfer_id'])) {
                echo json_encode(["success" => false, "message" => "Cannot update auto-generated transaction"]);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE bank_transactions 
                                   SET bank_account_id=?, transaction_type=?, amount=?, description=?, transaction_date=?, updated_at=NOW() 
                                   WHERE id=?");
            $stmt->execute([
                $data['bank_account_id'],
                strtoupper($data['transaction_type']),
                $data['amount'],
                $data['description'],
                $data['transaction_date'],
                $data['id']
            ]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Transaction updated"]);
            } else {
                echo json_encode(["success" => false, "message" => "No changes or transaction not found"]);
            }
            break;

        /**
         * DELETE TRANSACTION (Manual only)
         */
        case "DELETE":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['id'])) {
                echo json_encode(["success" => false, "message" => "Transaction ID is required"]);
                exit;
            }

            // ⚠️ Prevent deleting auto-generated transactions
            $stmtCheck = $pdo->prepare("SELECT related_sale_id, related_expense_id, related_transfer_id 
                                        FROM bank_transactions WHERE id=?");
            $stmtCheck->execute([$data['id']]);
            $check = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($check && ($check['related_sale_id'] || $check['related_expense_id'] || $check['related_transfer_id'])) {
                echo json_encode(["success" => false, "message" => "Cannot delete auto-generated transaction"]);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM bank_transactions WHERE id=?");
            $stmt->execute([$data['id']]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Transaction deleted"]);
            } else {
                echo json_encode(["success" => false, "message" => "Transaction not found"]);
            }
            break;

        default:
            echo json_encode(["success" => false, "message" => "Method not allowed"]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
