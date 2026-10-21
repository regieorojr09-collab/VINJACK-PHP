<?php
require_once __DIR__ . '/../config/Database.php';

class MotorcycleDAO {
    private $conn;

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    public function getAll() {
        $stmt = $this->conn->prepare("
            SELECT m.*, c.name as owner_name 
            FROM motorcycles m
            LEFT JOIN customers c ON m.owner_id = c.id
            ORDER BY m.plate_number ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("
            SELECT m.*, c.name as owner_name 
            FROM motorcycles m
            LEFT JOIN customers c ON m.owner_id = c.id
            WHERE m.id = :id
        ");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function create($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO motorcycles (owner_id, make, model, year, plate_number, vin) 
            VALUES (:owner_id, :make, :model, :year, :plate_number, :vin)
        ");
        
        $ownerId = isset($data['owner_id']) ? $data['owner_id'] : null;
        $stmt->bindParam(':owner_id', $ownerId);
        
        $stmt->bindParam(':make', $data['make']);
        $stmt->bindParam(':model', $data['model']);
        
        $year = isset($data['year']) ? $data['year'] : null;
        $stmt->bindParam(':year', $year);
        
        $plate = isset($data['plate_number']) ? $data['plate_number'] : null;
        $stmt->bindParam(':plate_number', $plate);
        
        $vin = isset($data['vin']) ? $data['vin'] : null;
        $stmt->bindParam(':vin', $vin);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function update($id, $data) {
        $stmt = $this->conn->prepare("
            UPDATE motorcycles 
            SET owner_id = :owner_id, make = :make, model = :model, 
                year = :year, plate_number = :plate_number, vin = :vin
            WHERE id = :id
        ");
        
        $stmt->bindParam(':id', $id);
        
        $ownerId = isset($data['owner_id']) ? $data['owner_id'] : null;
        $stmt->bindParam(':owner_id', $ownerId);
        
        $stmt->bindParam(':make', $data['make']);
        $stmt->bindParam(':model', $data['model']);
        
        $year = isset($data['year']) ? $data['year'] : null;
        $stmt->bindParam(':year', $year);
        
        $plate = isset($data['plate_number']) ? $data['plate_number'] : null;
        $stmt->bindParam(':plate_number', $plate);
        
        $vin = isset($data['vin']) ? $data['vin'] : null;
        $stmt->bindParam(':vin', $vin);
        
        return $stmt->execute();
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM motorcycles WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
