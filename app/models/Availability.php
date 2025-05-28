<?php
class Availability extends Model {
    protected $table = 'availability';
    
    public function findByProductAndDate($productId, $date) {
        $sql = "SELECT * FROM {$this->table} WHERE product_id = ? AND date = ?";
        $stmt = $this->query($sql, [$productId, $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function findByProductAndDateRange($productId, $startDate, $endDate) {
        $sql = "
            SELECT * FROM {$this->table} 
            WHERE product_id = ? AND date BETWEEN ? AND ? 
            ORDER BY date ASC
        ";
        $stmt = $this->query($sql, [$productId, $startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateAvailability($productId, $date, $availableSlots) {
        $existing = $this->findByProductAndDate($productId, $date);
        
        if ($existing) {
            return $this->update($existing['id'], ['available_slots' => $availableSlots]);
        } else {
            return $this->create([
                'product_id' => $productId,
                'date' => $date,
                'available_slots' => $availableSlots
            ]);
        }
    }
    
    public function reduceAvailability($productId, $date, $quantity = 1) {
        $sql = "
            UPDATE {$this->table} 
            SET available_slots = available_slots - ? 
            WHERE product_id = ? AND date = ? AND available_slots >= ?
        ";
        $stmt = $this->query($sql, [$quantity, $productId, $date, $quantity]);
        return $stmt->rowCount() > 0;
    }
}