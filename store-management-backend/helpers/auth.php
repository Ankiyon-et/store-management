<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
require_once __DIR__ . '/../../vendor/autoload.php'; 

$JWT_SECRET = "your-secret-key"; // keep in env variable in production

function generateToken($user) {
    global $JWT_SECRET;
    $payload = [
        "sub" => $user['id'],
        "username" => $user['username'],
        "role_id" => $user['role_id'],
        "iat" => time(),
        "exp" => time() + (60*60*4) // 4 hours expiry
    ];
    return JWT::encode($payload, $JWT_SECRET, 'HS256');
}

function verifyToken($token) {
    global $JWT_SECRET;
    try {
        return JWT::decode($token, new Key($JWT_SECRET, 'HS256'));
    } catch (Exception $e) {
        return null;
    }
}

function requireAuth() {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        jsonResponse(["error" => "Authorization required"], 401);
    }
    $token = str_replace("Bearer ", "", $headers['Authorization']);
    $decoded = verifyToken($token);
    if (!$decoded) {
        jsonResponse(["error" => "Invalid or expired token"], 401);
    }
    return $decoded; // return user info from token
}
?>
