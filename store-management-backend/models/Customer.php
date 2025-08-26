<?php
class Customer {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAll() {
        $stmt = $this->pdo->query("SELECT * FROM customers ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM customers WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($name, $email, $phone, $address) {
        $stmt = $this->pdo->prepare("INSERT INTO customers (name, email, phone, address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $phone, $address]);
        return $this->pdo->lastInsertId();
    }

    public function update($id, $name, $email, $phone, $address) {
        $stmt = $this->pdo->prepare("UPDATE customers SET name=?, email=?, phone=?, address=? WHERE id=?");
        return $stmt->execute([$name, $email, $phone, $address, $id]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM customers WHERE id=?");
        return $stmt->execute([$id]);
    }
}
?>
