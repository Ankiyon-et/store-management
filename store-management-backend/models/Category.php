<?php
class Category {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function create($name, $parentId = null) {
        $stmt = $this->pdo->prepare("INSERT INTO categories (name, parent_id) VALUES (?, ?)");
        $stmt->execute([$name, $parentId]);
        return $this->pdo->lastInsertId();
    }

    public function getAll() {
        $stmt = $this->pdo->query("SELECT * FROM categories ORDER BY id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $name, $parentId = null) {
        $stmt = $this->pdo->prepare("UPDATE categories SET name=?, parent_id=? WHERE id=?");
        return $stmt->execute([$name, $parentId, $id]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM categories WHERE id=?");
        return $stmt->execute([$id]);
    }
}
?>
