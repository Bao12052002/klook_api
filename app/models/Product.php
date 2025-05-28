<?php
class Product extends Model {
    protected $table = 'products';
    
    public function findWithOptions($id) {
        $sql = "
            SELECT p.*, 
                   GROUP_CONCAT(po.id) as option_ids,
                   GROUP_CONCAT(po.name) as option_names,
                   GROUP_CONCAT(po.price) as option_prices
            FROM products p
            LEFT JOIN product_options po ON p.id = po.product_id
            WHERE p.id = ?
            GROUP BY p.id
        ";
        
        $stmt = $this->query($sql, [$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Parse options
            $result['options'] = [];
            if ($result['option_ids']) {
                $ids = explode(',', $result['option_ids']);
                $names = explode(',', $result['option_names']);
                $prices = explode(',', $result['option_prices']);
                
                for ($i = 0; $i < count($ids); $i++) {
                    $result['options'][] = [
                        'id' => $ids[$i],
                        'name' => $names[$i],
                        'price' => $prices[$i]
                    ];
                }
            }
            
            // Clean up helper fields
            unset($result['option_ids'], $result['option_names'], $result['option_prices']);
        }
        
        return $result;
    }
    
    public function findByCategory($category, $limit = 20, $offset = 0) {
        $sql = "SELECT * FROM {$this->table} WHERE category = ? LIMIT ? OFFSET ?";
        $stmt = $this->query($sql, [$category, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function search($keyword, $limit = 20, $offset = 0) {
        $sql = "
            SELECT * FROM {$this->table} 
            WHERE name LIKE ? OR description LIKE ? 
            LIMIT ? OFFSET ?
        ";
        $searchTerm = "%{$keyword}%";
        $stmt = $this->query($sql, [$searchTerm, $searchTerm, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
