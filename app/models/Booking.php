<?php
class Booking extends Model {
    protected $table = 'tbl_klook_bookings';
    
    public function findWithProduct($id) {
        $sql = "
            SELECT b.*, p.name as product_name, p.description as product_description
            FROM tbl_klook_bookings b
            LEFT JOIN tbl_klook_products p ON b.product_id = p.id
            WHERE b.id = ?
        ";
        
        $stmt = $this->query($sql, [$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function findByStatus($status, $limit = 20, $offset = 0) {
        $sql = "SELECT * FROM {$this->table} WHERE status = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->query($sql, [$status, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function findByDateRange($startDate, $endDate, $limit = 20, $offset = 0) {
        $sql = "
            SELECT * FROM {$this->table} 
            WHERE booking_date BETWEEN ? AND ? 
            ORDER BY booking_date ASC 
            LIMIT ? OFFSET ?
        ";
        $stmt = $this->query($sql, [$startDate, $endDate, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function countByStatus($status) {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE status = ?";
        $stmt = $this->query($sql, [$status]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
}