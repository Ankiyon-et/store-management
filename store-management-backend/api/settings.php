<?php
header("Content-Type: application/json");
require_once("../config/db.php");

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {

        /**
         * CREATE SETTING
         * Example JSON:
         * {
         *   "key": "company_name",
         *   "value": "My Store"
         * }
         */
        case "POST":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['key'], $data['value'])) {
                echo json_encode(["success" => false, "message" => "Key and value are required"]);
                exit;
            }

            // Prevent duplicate keys
            $stmtCheck = $pdo->prepare("SELECT id FROM settings WHERE `key`=?");
            $stmtCheck->execute([$data['key']]);
            if ($stmtCheck->rowCount() > 0) {
                echo json_encode(["success" => false, "message" => "Setting with this key already exists"]);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?)");
            $stmt->execute([$data['key'], $data['value']]);

            echo json_encode([
                "success" => true,
                "message" => "Setting created",
                "id" => $pdo->lastInsertId()
            ]);
            break;

        /**
         * READ SETTINGS
         */
        case "GET":
            if (isset($_GET['key'])) {
                // Single setting
                $stmt = $pdo->prepare("SELECT * FROM settings WHERE `key`=?");
                $stmt->execute([$_GET['key']]);
                $setting = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($setting) {
                    echo json_encode(["success" => true, "data" => $setting]);
                } else {
                    echo json_encode(["success" => false, "message" => "Setting not found"]);
                }
            } else {
                // All settings
                $stmt = $pdo->query("SELECT * FROM settings ORDER BY `key` ASC");
                $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(["success" => true, "data" => $settings]);
            }
            break;

        /**
         * UPDATE SETTING
         * Example JSON:
         * {
         *   "key": "company_name",
         *   "value": "New Store Name"
         * }
         */
        case "PUT":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['key'], $data['value'])) {
                echo json_encode(["success" => false, "message" => "Key and value are required"]);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE settings SET `value`=? WHERE `key`=?");
            $stmt->execute([$data['value'], $data['key']]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Setting updated"]);
            } else {
                echo json_encode(["success" => false, "message" => "No changes or setting not found"]);
            }
            break;

        /**
         * DELETE SETTING
         * Example JSON:
         * { "key": "company_name" }
         */
        case "DELETE":
            $data = json_decode(file_get_contents("php://input"), true);

            if (!isset($data['key'])) {
                echo json_encode(["success" => false, "message" => "Key is required"]);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM settings WHERE `key`=?");
            $stmt->execute([$data['key']]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Setting deleted"]);
            } else {
                echo json_encode(["success" => false, "message" => "Setting not found"]);
            }
            break;

        default:
            echo json_encode(["success" => false, "message" => "Method not allowed"]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>
