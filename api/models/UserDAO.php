<?php
require_once __DIR__ . '/../config/Database.php';

class UserDAO {
    private $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    public function findByUsername($username) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function findById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getAllUsers() {
        $stmt = $this->conn->prepare("SELECT id, full_name, username, email, role, status, created_at FROM users");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function getUserPermissions($role) {
        $stmt = $this->conn->prepare("
            SELECT rp.permission_key 
            FROM role_permission_entries rp
            JOIN role_permissions r ON rp.role_permission_id = r.id
            WHERE r.role = :role
        ");
        $stmt->bindParam(':role', $role);
        $stmt->execute();
        $results = $stmt->fetchAll();
        return array_column($results, 'permission_key');
    }

    public function incrementFailedAttempts($id) {
        $stmt = $this->conn->prepare("UPDATE users SET failed_attempts = failed_attempts + 1, last_failed_attempt = NOW() WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    public function resetFailedAttempts($id) {
        $stmt = $this->conn->prepare("UPDATE users SET failed_attempts = 0, last_failed_attempt = NULL WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function lockAccount($id) {
        $stmt = $this->conn->prepare("UPDATE users SET status = 'locked' WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
