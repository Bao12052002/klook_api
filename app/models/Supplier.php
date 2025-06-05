<?php
class Supplier extends Model {
    protected $table = 'tbl_klook_suppliers';
    
    public function findWithContactDetails($id) {
        $sql = "
            SELECT 
                s.id,
                s.name,
                s.endpoint,
                JSON_OBJECT(
                    'website', s.website,
                    'email', s.email,
                    'telephone', s.telephone,
                    'address', s.address
                ) as contact
            FROM {$this->table} s 
            WHERE s.id = ?
        ";
        
        $stmt = $this->query($sql, [$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            // Parse contact JSON
            $result['contact'] = json_decode($result['contact'], true);
            
            // Convert null values in contact to null (not string "null")
            foreach ($result['contact'] as $key => $value) {
                if ($value === null || $value === 'null') {
                    $result['contact'][$key] = null;
                }
            }
        }
        
        return $result;
    }
    
    public function findAllWithContactDetails($limit = null, $offset = 0) {
        $sql = "
            SELECT 
                s.id,
                s.name,
                s.endpoint,
                JSON_OBJECT(
                    'website', s.website,
                    'email', s.email,
                    'telephone', s.telephone,
                    'address', s.address
                ) as contact
            FROM {$this->table} s 
            WHERE s.status = 'active'
        ";
        
        if ($limit) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        
        $stmt = $this->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process each result
        foreach ($results as &$result) {
            // Parse contact JSON
            $result['contact'] = json_decode($result['contact'], true);
            
            // Convert null values in contact to null (not string "null")
            foreach ($result['contact'] as $key => $value) {
                if ($value === null || $value === 'null') {
                    $result['contact'][$key] = null;
                }
            }
        }
        
        return $results;
    }
    
    public function findByName($name) {
        $sql = "SELECT * FROM {$this->table} WHERE name LIKE ? AND status = 'active'";
        $stmt = $this->query($sql, ["%{$name}%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function findActiveSuppliers() {
        $sql = "SELECT * FROM {$this->table} WHERE status = 'active' ORDER BY name ASC";
        $stmt = $this->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}