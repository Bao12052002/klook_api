<?php
class Product extends Model {
    protected $table = 'tbl_klook_products';
    public function find($productId) {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        $stmt = $this->query($sql, [$productId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function findAllWithDetails($limit = null, $offset = 0) {
        $sql = "SELECT * FROM {$this->table} WHERE status = 'active'";
        if ($limit) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }
        $stmt = $this->query($sql);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Load options and units for each product
        foreach ($products as &$product) {
            $product['options'] = $this->getProductOptions($product['id']);
        }
        
        return $products;
    }
    
    public function findWithFullDetails($id) {
        $product = $this->find($id);
        if ($product) {
            $product['options'] = $this->getProductOptions($id);
        }
        return $product;
    }
    
    public function getProductOptions($productId) {
        $sql = "SELECT * FROM tbl_klook_product_options WHERE product_id = ? ORDER BY is_default DESC";
        $stmt = $this->query($sql, [$productId]);
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Load units for each option
        foreach ($options as &$option) {
            $option['units'] = $this->getOptionUnits($productId, $option['id']);
        }
        
        return $options;
    }
    
    public function getOptionUnits($productId, $optionId) {
        $sql = "SELECT * FROM tbl_klook_units WHERE product_id = ? AND option_id = ? AND status = 'active' ORDER BY type";
        $stmt = $this->query($sql, [$productId, $optionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function createWithOptions($data) {
        try {
            $this->db->beginTransaction();
            
            // Extract options and units data
            $options = $data['options'] ?? [];
            unset($data['options']);
            
            // Create main product
            $this->create($data);
            $productId = $data['id']; // We use provided ID instead of auto-increment
            
            // Create options
            foreach ($options as $option) {
                $this->createProductOption($productId, $option);
            }
            
            $this->db->commit();
            return $productId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function updateWithOptions($id, $data) {
        try {
            $this->db->beginTransaction();
            
            // Extract options data
            $options = $data['options'] ?? null;
            unset($data['options']);
            
            // Update main product
            if (!empty($data)) {
                $this->update($id, $data);
            }
            
            // Update options if provided
            if ($options !== null) {
                // Delete existing options and units
                $this->query("DELETE FROM tbl_klook_units WHERE product_id = ?", [$id]);
                $this->query("DELETE FROM tbl_klook_product_options WHERE product_id = ?", [$id]);
                
                // Create new options
                foreach ($options as $option) {
                    $this->createProductOption($id, $option);
                }
            }
            
            $this->db->commit();
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    private function createProductOption($productId, $optionData) {
        // Extract units data
        $units = $optionData['units'] ?? [];
        unset($optionData['units']);
        
        // Prepare option data
        $optionData['product_id'] = $productId;
        
        // Handle JSON fields
        if (isset($optionData['availabilityLocalStartTimes']) && is_array($optionData['availabilityLocalStartTimes'])) {
            $optionData['availability_local_start_times'] = json_encode($optionData['availabilityLocalStartTimes']);
            unset($optionData['availabilityLocalStartTimes']);
        }
        
        if (isset($optionData['requiredContactFields']) && is_array($optionData['requiredContactFields'])) {
            $optionData['required_contact_fields'] = json_encode($optionData['requiredContactFields']);
            unset($optionData['requiredContactFields']);
        }
        
        // Handle restrictions
        if (isset($optionData['restrictions'])) {
            $restrictions = $optionData['restrictions'];
            $optionData['min_units'] = $restrictions['minUnits'] ?? null;
            $optionData['max_units'] = $restrictions['maxUnits'] ?? null;
            unset($optionData['restrictions']);
        }
        
        // Handle boolean conversion
        if (isset($optionData['default'])) {
            $optionData['is_default'] = $optionData['default'];
            unset($optionData['default']);
        }
        
        // Rename fields to match database
        if (isset($optionData['internalName'])) {
            $optionData['internal_name'] = $optionData['internalName'];
            unset($optionData['internalName']);
        }
        
        // Create option
        $sql = "INSERT INTO tbl_klook_product_options (" . implode(', ', array_keys($optionData)) . ") VALUES (:" . implode(', :', array_keys($optionData)) . ")";
        $this->query($sql, $optionData);
        
        // Create units for this option
        foreach ($units as $unit) {
            $this->createOptionUnit($productId, $optionData['id'], $unit);
        }
    }
    
    private function createOptionUnit($productId, $optionId, $unitData) {
        $unitData['product_id'] = $productId;
        $unitData['option_id'] = $optionId;
        
        // Handle JSON fields
        if (isset($unitData['requiredContactFields']) && is_array($unitData['requiredContactFields'])) {
            $unitData['required_contact_fields'] = json_encode($unitData['requiredContactFields']);
            unset($unitData['requiredContactFields']);
        }
        
        // Handle restrictions
        if (isset($unitData['restrictions'])) {
            $restrictions = $unitData['restrictions'];
            $unitData['min_age'] = $restrictions['minAge'] ?? null;
            $unitData['max_age'] = $restrictions['maxAge'] ?? null;
            $unitData['id_required'] = $restrictions['idRequired'] ?? false;
            $unitData['min_quantity'] = $restrictions['minQuantity'] ?? null;
            $unitData['max_quantity'] = $restrictions['maxQuantity'] ?? null;
            $unitData['pax_count'] = $restrictions['paxCount'] ?? 1;
            
            if (isset($restrictions['accompaniedBy']) && is_array($restrictions['accompaniedBy'])) {
                $unitData['accompanied_by'] = json_encode($restrictions['accompaniedBy']);
            }
            
            unset($unitData['restrictions']);
        }
        
        // Rename fields to match database
        if (isset($unitData['internalName'])) {
            $unitData['internal_name'] = $unitData['internalName'];
            unset($unitData['internalName']);
        }
        
        // Create unit
        $sql = "INSERT INTO tbl_klook_units (" . implode(', ', array_keys($unitData)) . ") VALUES (:" . implode(', :', array_keys($unitData)) . ")";
        $this->query($sql, $unitData);
    }
    
    public function findByCategory($category, $limit = 20, $offset = 0) {
        // This method can be enhanced with category field if needed
        return $this->findAllWithDetails($limit, $offset);
    }
    
    public function search($keyword, $limit = 20, $offset = 0) {
        $sql = "
            SELECT * FROM {$this->table} 
            WHERE (internal_name LIKE ? OR reference LIKE ?) AND status = 'active'
            LIMIT ? OFFSET ?
        ";
        $searchTerm = "%{$keyword}%";
        $stmt = $this->query($sql, [$searchTerm, $searchTerm, $limit, $offset]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Load options for each product
        foreach ($products as &$product) {
            $product['options'] = $this->getProductOptions($product['id']);
        }
        
        return $products;
    }
        public function getUnitPricingForDisplay($productId, $optionId, $unitId, $currency, $referenceDateStr) {
        $sql = "SELECT original_price, retail_price, net_price, currency_precision, included_taxes 
                FROM tbl_klook_pricing_rules 
                WHERE product_id = :product_id 
                  AND option_id = :option_id
                  AND unit_id = :unit_id
                  AND currency = :currency
                  AND is_active = 1 -- Sử dụng 1 cho boolean
                  AND :ref_date_gte >= COALESCE(valid_from, '1900-01-01') 
                  AND :ref_date_lte <= COALESCE(valid_to, '9999-12-31')
                ORDER BY 
                    (valid_from IS NOT NULL AND valid_to IS NOT NULL) DESC, 
                    (valid_from IS NOT NULL OR valid_to IS NOT NULL) DESC,
                    created_at DESC 
                LIMIT 1";
        
        $params = [
            'product_id' => $productId,
            'option_id' => $optionId,
            'unit_id' => $unitId,
            'currency' => $currency,
            'ref_date_gte' => $referenceDateStr, // Placeholder mới
            'ref_date_lte' => $referenceDateStr, // Placeholder mới (cùng giá trị)
        ];

        $stmt = $this->query($sql, $params);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($rule) {
            return [
                'original' => (int)$rule['original_price'],
                'retail' => (int)$rule['retail_price'],
                'net' => $rule['net_price'] === null ? null : (int)$rule['net_price'],
                'currency' => $currency,
                'currencyPrecision' => (int)$rule['currency_precision'],
                'includedTaxes' => $rule['included_taxes'] ? json_decode($rule['included_taxes'], true) : [] 
            ];
        }
        return null;
    }

    /**
     * Lấy thông tin giá hiển thị cho một Option hoặc Product (nếu pricingPer="BOOKING").
     */
    public function getBookingLevelPricingForDisplay($productId, $optionId, $currency, $referenceDateStr) {
        $sql = "SELECT original_price, retail_price, net_price, currency_precision, included_taxes 
                FROM tbl_klook_pricing_rules 
                WHERE product_id = :product_id 
                  AND unit_id IS NULL 
                  AND currency = :currency
                  AND is_active = 1 -- Sử dụng 1 cho boolean
                  AND :ref_date_gte >= COALESCE(valid_from, '1900-01-01')
                  AND :ref_date_lte <= COALESCE(valid_to, '9999-12-31')";
        
        $params = [
            'product_id' => $productId,
            'currency' => $currency,
            'ref_date_gte' => $referenceDateStr, // Placeholder mới
            'ref_date_lte' => $referenceDateStr, // Placeholder mới (cùng giá trị)
        ];

        if ($optionId) {
            $sql .= " AND option_id = :option_id";
            $params['option_id'] = $optionId;
        } else {
            $sql .= " AND option_id IS NULL";
        }
        
        $sql .= " ORDER BY 
                    (valid_from IS NOT NULL AND valid_to IS NOT NULL) DESC, 
                    (valid_from IS NOT NULL OR valid_to IS NOT NULL) DESC,
                    (option_id IS NOT NULL) DESC, 
                    created_at DESC 
                  LIMIT 1";

        $stmt = $this->query($sql, $params);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($rule) {
            return [
                'original' => (int)$rule['original_price'],
                'retail' => (int)$rule['retail_price'],
                'net' => $rule['net_price'] === null ? null : (int)$rule['net_price'],
                'currency' => $currency,
                'currencyPrecision' => (int)$rule['currency_precision'],
                'includedTaxes' => $rule['included_taxes'] ? json_decode($rule['included_taxes'], true) : []
            ];
        }
        return null;
    }
}