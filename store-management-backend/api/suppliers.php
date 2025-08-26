<?php
header("Content-Type: application/json");
require_once("../config/db.php");

$method = $_SERVER['REQUEST_METHOD'];

// Create Supplier
if ($method == "POST") {
    $data = json_decode(file_get_contents("php://input"));
    $stmt = $pdo->prepare("INSERT INTO suppliers (name, phone, address, notes) VALUES (?, ?, ?, ?)");
    $stmt->execute([$data->name, $data->phone, $data->address, $data->notes]);
    echo json_encode(["success" => true, "message" => "Supplier created"]);
}

// Get all suppliers
if ($method == "GET" && !isset($_GET['id'])) {
    $stmt = $pdo->query("SELECT * FROM suppliers");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($suppliers);
}

// Get single supplier
if ($method == "GET" && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
}

// Update supplier
if ($method == "PUT") {
    $data = json_decode(file_get_contents("php://input"), true); // Decode JSON input
    // Check if required fields are present
    if (isset($data['id'], $data['name'], $data['phone'], $data['address'], $data['notes'])) {
        $stmt = $pdo->prepare("UPDATE suppliers SET name=?, phone=?, address=?, notes=? WHERE id=?");
        $stmt->execute([$data['name'], $data['phone'], $data['address'], $data['notes'], $data['id']]);
        echo json_encode(["success" => true, "message" => "Supplier updated"]);
    } else {
        echo json_encode(["success" => false, "message" => "Missing required fields"]);
    }
}

// Delete supplier
if ($method == "DELETE") {
    $data = json_decode(file_get_contents("php://input"), true); // Decode JSON input
    // Check if the required 'id' field is present
    if (isset($data['id'])) {
        $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id=?");
        $stmt->execute([$data['id']]);
        echo json_encode(["success" => true, "message" => "Supplier deleted"]);
    } else {
        echo json_encode(["success" => false, "message" => "Missing required field: id"]);
    }
}