<?php
class Unit extends Model {
    protected $table = 'units';
    
    public function findByProductId($productId) {
        $sql = "SELECT * FROM {$this->table} WHERE product_id = ? ORDER BY sort_order ASC";
        $stmt = $this->query($sql, [$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function findAvailableUnits($productId, $date) {
        $sql = "
            SELECT u.* FROM {$this->table} u
            LEFT JOIN bookings b ON u.id = b.unit_id AND b.booking_date = ? AND b.status != 'cancelled'
            WHERE u.product_id = ? AND b.id IS NULL
            ORDER BY u.sort_order ASC
        ";
        $stmt = $this->query($sql, [$date, $productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateStatus($id, $status) {
        return $this->update($id, ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
    }
}
