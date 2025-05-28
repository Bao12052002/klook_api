<?php
class ProductOption extends Model {
    protected $table = 'product_options';
    
    public function findByProductId($productId) {
        $sql = "SELECT * FROM {$this->table} WHERE product_id = ?";
        $stmt = $this->query($sql, [$productId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function deleteByProductId($productId) {
        $sql = "DELETE FROM {$this->table} WHERE product_id = ?";
        return $this->query($sql, [$productId]);
    }
}