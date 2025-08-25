<?php
class User {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function create($name, $username, $password, $email, $phone, $roleId, $status = 'active') {
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("INSERT INTO users (name, username, password_hash, email, phone, role_id, status) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $username, $passwordHash, $email, $phone, $roleId, $status]);
        return $this->pdo->lastInsertId();
    }

    public function getAll() {
        $stmt = $this->pdo->query("SELECT u.*, r.name AS role_name 
                                   FROM users u 
                                   LEFT JOIN roles r ON u.role_id = r.id 
                                   ORDER BY u.id DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT u.*, r.name AS role_name 
                                     FROM users u 
                                     LEFT JOIN roles r ON u.role_id = r.id 
                                     WHERE u.id=?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $name, $username, $password = null, $email = null, $phone = null, $roleId = null, $status = 'active') {
        if ($password) {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $this->pdo->prepare("UPDATE users 
                                         SET name=?, username=?, password_hash=?, email=?, phone=?, role_id=?, status=? 
                                         WHERE id=?");
            return $stmt->execute([$name, $username, $passwordHash, $email, $phone, $roleId, $status, $id]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE users 
                                         SET name=?, username=?, email=?, phone=?, role_id=?, status=? 
                                         WHERE id=?");
            return $stmt->execute([$name, $username, $email, $phone, $roleId, $status, $id]);
        }
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id=?");
        return $stmt->execute([$id]);
    }
}
?>
