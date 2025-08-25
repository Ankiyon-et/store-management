<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../models/User.php";
require_once __DIR__ . "/../helpers/functions.php";
require_once __DIR__ . "/../helpers/auth.php";

$userModel = new User($pdo);
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST': // login
        $input = json_decode(file_get_contents("php://input"), true);
        if (!isset($input['username'], $input['password'])) {
            jsonResponse(["error" => "Username and password required"], 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username=? AND status='active'");
        $stmt->execute([$input['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($input['password'], $user['password_hash'])) {
            $token = generateToken($user);
            auditLog($pdo, $user['id'], "login", "users", $user['id'], "User logged in");
            jsonResponse(["token" => $token, "user" => $user]);
        } else {
            jsonResponse(["error" => "Invalid credentials"], 401);
        }
        break;

    case 'DELETE': // logout (client should just delete token, but we can log it)
        $decoded = requireAuth();
        auditLog($pdo, $decoded->sub, "logout", "users", $decoded->sub, "User logged out");
        jsonResponse(["message" => "Logged out successfully"]);
        break;

    default:
        jsonResponse(["error" => "Method not allowed"], 405);
}
