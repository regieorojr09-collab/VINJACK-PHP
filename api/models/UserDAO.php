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

    public function isUsernameTaken(string $username, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM users WHERE username = :username" . ($excludeId ? " AND id != :excludeId" : "");
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':username', $username);
        if ($excludeId) {
            $stmt->bindValue(':excludeId', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }

    public function isEmailTaken(string $email, ?int $excludeId = null): bool {
        $sql = "SELECT COUNT(*) FROM users WHERE email = :email" . ($excludeId ? " AND id != :excludeId" : "");
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':email', $email);
        if ($excludeId) {
            $stmt->bindValue(':excludeId', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn() > 0;
    }

    public function createUser(array $data): int {
        if ($this->isUsernameTaken($data['username'])) {
            throw new \InvalidArgumentException("Username '{$data['username']}' is already taken.");
        }
        if ($this->isEmailTaken($data['email'])) {
            throw new \InvalidArgumentException("Email '{$data['email']}' is already registered.");
        }

        $allowedRoles = ['Super Admin', 'Admin', 'Salesperson'];
        $role = in_array($data['role'] ?? '', $allowedRoles, true) ? $data['role'] : 'Salesperson';

        $allowedStatuses = ['active', 'pending', 'locked', 'inactive'];
        $status = in_array($data['status'] ?? '', $allowedStatuses, true) ? $data['status'] : 'active';

        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);

        $sql = "
            INSERT INTO users (full_name, username, email, password, role, status)
            VALUES (:full_name, :username, :email, :password, :role, :status)
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':full_name', trim($data['full_name']));
        $stmt->bindValue(':username', trim($data['username']));
        $stmt->bindValue(':email', trim($data['email']));
        $stmt->bindValue(':password', $hashedPassword);
        $stmt->bindValue(':role', $role);
        $stmt->bindValue(':status', $status);
        $stmt->execute();

        return (int)$this->conn->lastInsertId();
    }

    public function updateUser(int $id, array $data): bool {
        $existing = $this->findById($id);
        if (!$existing) {
            throw new \RuntimeException("User ID {$id} not found.");
        }

        if (isset($data['username']) && $this->isUsernameTaken($data['username'], $id)) {
            throw new \InvalidArgumentException("Username '{$data['username']}' is already in use by another account.");
        }
        if (isset($data['email']) && $this->isEmailTaken($data['email'], $id)) {
            throw new \InvalidArgumentException("Email '{$data['email']}' is already in use by another account.");
        }

        $fields = [
            'full_name = :full_name',
            'username = :username',
            'email = :email',
            'role = :role'
        ];

        $params = [
            ':id'        => $id,
            ':full_name' => isset($data['full_name']) ? trim($data['full_name']) : $existing['full_name'],
            ':username'  => isset($data['username']) ? trim($data['username']) : $existing['username'],
            ':email'     => isset($data['email']) ? trim($data['email']) : $existing['email'],
            ':role'      => isset($data['role']) ? $data['role'] : $existing['role']
        ];

        if (!empty($data['password'])) {
            $fields[] = 'password = :password';
            $params[':password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        if (isset($data['status'])) {
            $allowedStatuses = ['active', 'pending', 'locked', 'inactive'];
            if (in_array($data['status'], $allowedStatuses, true)) {
                $fields[] = 'status = :status';
                $params[':status'] = $data['status'];
            }
        }

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    public function updateStatus(int $id, string $status): bool {
        $allowed = ['active', 'pending', 'locked', 'inactive'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid status '{$status}'. Allowed: " . implode(', ', $allowed));
        }

        $stmt = $this->conn->prepare("UPDATE users SET status = :status WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':status', $status);
        return $stmt->execute();
    }

    public function deleteUser(int $id, int $currentUserId): bool {
        if ($id === $currentUserId) {
            throw new \RuntimeException("You cannot delete your own authenticated account.");
        }
        if ($id === 1) {
            throw new \RuntimeException("The primary Super Admin account cannot be deleted.");
        }

        // Check if user has associated transactions
        $chkSales = $this->conn->prepare("SELECT COUNT(*) FROM sales WHERE recorded_by = :id");
        $chkSales->bindValue(':id', $id, PDO::PARAM_INT);
        $chkSales->execute();
        if ((int)$chkSales->fetchColumn() > 0) {
            // Deactivate instead of hard deleting to preserve audit history
            return $this->updateStatus($id, 'inactive');
        }

        $stmt = $this->conn->prepare("DELETE FROM users WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
