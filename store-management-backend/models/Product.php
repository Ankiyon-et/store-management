<?php
class Product {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function create($name, $barcode, $categoryId, $price, $cost, $unit, $imageUrl, $description) {
        $stmt = $this->pdo->prepare("INSERT INTO products (name, barcode, category_id, price, cost, unit, image_url, description) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $barcode, $categoryId, $price, $cost, $unit, $imageUrl, $description]);
        return $this->pdo->lastInsertId();
    }

    public function getAll() {
        $stmt = $this->pdo->query("SELECT p.*, c.name as category_name 
                                   FROM products p 
                                   LEFT JOIN categories c ON p.category_id = c.id 
                                   ORDER BY p.id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $name, $barcode, $categoryId, $price, $cost, $unit, $imageUrl, $description) {
        $stmt = $this->pdo->prepare("UPDATE products 
                                     SET name=?, barcode=?, category_id=?, price=?, cost=?, unit=?, image_url=?, description=? 
                                     WHERE id=?");
        return $stmt->execute([$name, $barcode, $categoryId, $price, $cost, $unit, $imageUrl, $description, $id]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM products WHERE id=?");
        return $stmt->execute([$id]);
    }
}
?>
