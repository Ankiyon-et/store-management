<?php
header("Content-Type: application/json");
require_once("../config/db.php");

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

        /**
         * CREATE TRANSFER
         * Example JSON:
         * {
         *   "from_account_id": 1,
         *   "to_account_id": 2,
         *   "amount": 1000,
         *   "transfer_date": "2025-08-24",
         *   "notes": "Moving money to savings"
         * }
         */
        case "POST":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['from_account_id'], $data['to_account_id'], $data['amount'], $data['transfer_date'])) {
                echo json_encode(["success" => false, "message" => "Missing required fields"]);
                exit;
            }

            if ($data['from_account_id'] == $data['to_account_id']) {
                echo json_encode(["success" => false, "message" => "Source and destination accounts cannot be the same"]);
                exit;
            }

            $pdo->beginTransaction();

            // Insert into bank_transfers
            $stmt = $pdo->prepare("INSERT INTO bank_transfers (from_account_id, to_account_id, amount, transfer_date, notes) 
                                   VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['from_account_id'],
                $data['to_account_id'],
                $data['amount'],
                $data['transfer_date'],
                $data['notes'] ?? null
            ]);
            $transfer_id = $pdo->lastInsertId();

            // Insert OUT transaction
            $stmtOut = $pdo->prepare("INSERT INTO bank_transactions 
                (bank_account_id, transaction_type, amount, description, transaction_date, related_transfer_id) 
                VALUES (?, 'EXPENSE', ?, ?, ?, ?)");
            $stmtOut->execute([
                $data['from_account_id'],
                $data['amount'],
                "Transfer to account #" . $data['to_account_id'],
                $data['transfer_date'],
                $transfer_id
            ]);

            // Insert IN transaction
            $stmtIn = $pdo->prepare("INSERT INTO bank_transactions 
                (bank_account_id, transaction_type, amount, description, transaction_date, related_transfer_id) 
                VALUES (?, 'INCOME', ?, ?, ?, ?)");
            $stmtIn->execute([
                $data['to_account_id'],
                $data['amount'],
                "Transfer from account #" . $data['from_account_id'],
                $data['transfer_date'],
                $transfer_id
            ]);

            $pdo->commit();
            echo json_encode(["success" => true, "message" => "Transfer recorded", "id" => $transfer_id]);
            break;

        /**
         * GET TRANSFER(S)
         */
        case "GET":
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("SELECT bt.*, 
                                              a1.name AS from_account_name, 
                                              a2.name AS to_account_name
                                       FROM bank_transfers bt
                                       JOIN bank_accounts a1 ON bt.from_account_id = a1.id
                                       JOIN bank_accounts a2 ON bt.to_account_id = a2.id
                                       WHERE bt.id=?");
                $stmt->execute([$_GET['id']]);
                $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($transfer) {
                    echo json_encode(["success" => true, "data" => $transfer]);
                } else {
                    echo json_encode(["success" => false, "message" => "Transfer not found"]);
                }
            } else {
                $stmt = $pdo->query("SELECT bt.*, 
                                            a1.name AS from_account_name, 
                                            a2.name AS to_account_name
                                     FROM bank_transfers bt
                                     JOIN bank_accounts a1 ON bt.from_account_id = a1.id
                                     JOIN bank_accounts a2 ON bt.to_account_id = a2.id
                                     ORDER BY bt.transfer_date DESC");
                $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(["success" => true, "data" => $transfers]);
            }
            break;

        /**
         * DELETE TRANSFER
         * (auto-removes linked bank_transactions via related_transfer_id)
         */
        case "DELETE":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['id'])) {
                echo json_encode(["success" => false, "message" => "Transfer ID is required"]);
                exit;
            }

            $pdo->beginTransaction();

            // Delete linked bank_transactions
            $stmtTx = $pdo->prepare("DELETE FROM bank_transactions WHERE related_transfer_id=?");
            $stmtTx->execute([$data['id']]);

            // Delete transfer record
            $stmt = $pdo->prepare("DELETE FROM bank_transfers WHERE id=?");
            $stmt->execute([$data['id']]);

            if ($stmt->rowCount() > 0) {
                $pdo->commit();
                echo json_encode(["success" => true, "message" => "Transfer deleted"]);
            } else {
                $pdo->rollBack();
                echo json_encode(["success" => false, "message" => "Transfer not found"]);
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
