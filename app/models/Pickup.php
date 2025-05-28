<?php
class Pickup extends Model {
    protected $table = 'pickups';
    
    public function findByLocation($location, $limit = 20, $offset = 0) {
        $sql = "SELECT * FROM {$this->table} WHERE location LIKE ? LIMIT ? OFFSET ?";
        $searchTerm = "%{$location}%";
        $stmt = $this->query($sql, [$searchTerm, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function findActivePickups() {
        $sql = "SELECT * FROM {$this->table} WHERE status = 'active' ORDER BY location ASC";
        $stmt = $this->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function findByProductId($productId) {
        $sql = "
            SELECT p.* FROM {$this->table} p
            INNER JOIN product_pickups pp ON p.id = pp.pickup_id
            WHERE pp.product_id = ?
            ORDER BY p.location ASC
        ";
        $stmt = $this->query($sql, [$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
