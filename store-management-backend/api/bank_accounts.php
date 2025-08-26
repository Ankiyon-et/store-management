<?php
header("Content-Type: application/json");
require_once("../config/db.php");

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

        /**
         * CREATE BANK ACCOUNT
         * Example JSON:
         * {
         *   "name": "Main Account",
         *   "account_number": "123456789",
         *   "bank_name": "Commercial Bank of Ethiopia",
         *   "initial_balance": 5000
         * }
         */
        case "POST":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['name']) || empty(trim($data['name']))) {
                echo json_encode(["success" => false, "message" => "Bank account name is required"]);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO bank_accounts (name, account_number, bank_name, initial_balance) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['account_number'] ?? null,
                $data['bank_name'] ?? null,
                $data['initial_balance'] ?? 0
            ]);

            echo json_encode([
                "success" => true,
                "message" => "Bank account created",
                "id" => $pdo->lastInsertId()
            ]);
            break;

        /**
         * READ BANK ACCOUNT(S)
         */
        case "GET":
            if (isset($_GET['id'])) {
                // Single account
                $stmt = $pdo->prepare("SELECT * FROM bank_accounts WHERE id=?");
                $stmt->execute([$_GET['id']]);
                $account = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($account) {
                    echo json_encode(["success" => true, "data" => $account]);
                } else {
                    echo json_encode(["success" => false, "message" => "Bank account not found"]);
                }
            } else {
                // All accounts
                $stmt = $pdo->query("SELECT * FROM bank_accounts ORDER BY name ASC");
                $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(["success" => true, "data" => $accounts]);
            }
            break;

        /**
         * UPDATE BANK ACCOUNT
         * Example JSON:
         * {
         *   "id": 1,
         *   "name": "Updated Account",
         *   "account_number": "987654321",
         *   "bank_name": "Dashen Bank",
         *   "initial_balance": 10000
         * }
         */
        case "PUT":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['id'], $data['name'])) {
                echo json_encode(["success" => false, "message" => "Bank account ID and name are required"]);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE bank_accounts SET name=?, account_number=?, bank_name=?, initial_balance=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([
                $data['name'],
                $data['account_number'],
                $data['bank_name'],
                $data['initial_balance'],
                $data['id']
            ]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Bank account updated"]);
            } else {
                echo json_encode(["success" => false, "message" => "No changes or account not found"]);
            }
            break;

        /**
         * DELETE BANK ACCOUNT
         */
        case "DELETE":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['id'])) {
                echo json_encode(["success" => false, "message" => "Bank account ID is required"]);
                exit;
            }

            // ⚠️ Prevent deleting if linked to transactions
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM bank_transactions WHERE bank_account_id=?");
            $stmtCheck->execute([$data['id']]);
            if ($stmtCheck->fetchColumn() > 0) {
                echo json_encode(["success" => false, "message" => "Account has transactions and cannot be deleted"]);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM bank_accounts WHERE id=?");
            $stmt->execute([$data['id']]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Bank account deleted"]);
            } else {
                echo json_encode(["success" => false, "message" => "Bank account not found"]);
            }
            break;

        default:
            echo json_encode(["success" => false, "message" => "Method not allowed"]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
