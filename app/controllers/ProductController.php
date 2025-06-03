<?php
class ProductController extends Controller {
    private $productModel;
    
    public function __construct() {
        $this->productModel = new Product();
    }
    
    public function index() {
        try {
            $params = $this->getQueryParams();
            $limit = isset($params['limit']) ? (int)$params['limit'] : 20;
            $offset = isset($params['offset']) ? (int)$params['offset'] : 0;
            
            $products = $this->productModel->findAllWithDetails($limit, $offset);
            
            // Transform to Klook format
            $klookProducts = [];
            foreach ($products as $product) {
                $klookProducts[] = $this->transformToKlookFormat($product);
            }
            
            ResponseHelper::success($klookProducts);
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
    
    public function show($id) {
        try {
            $product = $this->productModel->findWithFullDetails($id);
            
            if (!$product) {
                throw new NotFoundException('Product not found');
            }
            
            $klookProduct = $this->transformToKlookFormat($product);
            ResponseHelper::success($klookProduct);
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
    
    public function create() {
        try {
            $data = $this->getRequestBody();
            
            $this->validate($data, [
                'id' => 'required',
                'internal_name' => 'required',
                'locale' => 'required',
                'time_zone' => 'required',
                'delivery_formats' => 'required',
                'delivery_methods' => 'required',
                'options' => 'required'
            ]);
            
            // Validate JSON fields
            if (is_array($data['delivery_formats'])) {
                $data['delivery_formats'] = json_encode($data['delivery_formats']);
            }
            if (is_array($data['delivery_methods'])) {
                $data['delivery_methods'] = json_encode($data['delivery_methods']);
            }
            
            // Create product
            $productId = $this->productModel->createWithOptions($data);
            $product = $this->productModel->findWithFullDetails($productId);
            
            $klookProduct = $this->transformToKlookFormat($product);
            ResponseHelper::created($klookProduct, 'Product created successfully');
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
    
    public function update($id) {
        try {
            $product = $this->productModel->find($id);
            if (!$product) {
                throw new NotFoundException('Product not found');
            }
            
            $data = $this->getRequestBody();
            
            // Handle JSON fields
            if (isset($data['delivery_formats']) && is_array($data['delivery_formats'])) {
                $data['delivery_formats'] = json_encode($data['delivery_formats']);
            }
            if (isset($data['delivery_methods']) && is_array($data['delivery_methods'])) {
                $data['delivery_methods'] = json_encode($data['delivery_methods']);
            }
            
            $this->productModel->updateWithOptions($id, $data);
            
            $updatedProduct = $this->productModel->findWithFullDetails($id);
            $klookProduct = $this->transformToKlookFormat($updatedProduct);
            ResponseHelper::success($klookProduct, 'Product updated successfully');
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
    
    public function delete($id) {
        try {
            $product = $this->productModel->find($id);
            if (!$product) {
                throw new NotFoundException('Product not found');
            }
            
            $this->productModel->delete($id);
            ResponseHelper::success(null, 'Product deleted successfully');
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
    
    /**
     * Transform product data to Klook API format
     */
    private function transformToKlookFormat($product) {
        return [
            'id' => $product['id'],
            'internalName' => $product['internal_name'],
            'reference' => $product['reference'],
            'locale' => $product['locale'],
            'timeZone' => $product['time_zone'],
            'allowFreesale' => (bool)$product['allow_freesale'],
            'instantConfirmation' => (bool)$product['instant_confirmation'],
            'instantDelivery' => (bool)$product['instant_delivery'],
            'availabilityRequired' => (bool)$product['availability_required'],
            'availabilityType' => $product['availability_type'],
            'deliveryFormats' => json_decode($product['delivery_formats'], true),
            'deliveryMethods' => json_decode($product['delivery_methods'], true),
            'redemptionMethod' => $product['redemption_method'],
            'options' => $this->transformOptions($product['options'] ?? [])
        ];
    }
    
    /**
     * Transform options to Klook format
     */
    private function transformOptions($options) {
        $transformedOptions = [];
        
        foreach ($options as $option) {
            $transformedOptions[] = [
                'id' => $option['id'],
                'default' => (bool)$option['is_default'],
                'internalName' => $option['internal_name'],
                'reference' => $option['reference'],
                'availabilityLocalStartTimes' => json_decode($option['availability_local_start_times'], true),
                'cancellationCutoff' => $option['cancellation_cutoff'],
                'cancellationCutoffAmount' => (int)$option['cancellation_cutoff_amount'],
                'cancellationCutoffUnit' => $option['cancellation_cutoff_unit'],
                'requiredContactFields' => json_decode($option['required_contact_fields'], true),
                'restrictions' => [
                    'minUnits' => $option['min_units'],
                    'maxUnits' => $option['max_units']
                ],
                'units' => $this->transformUnits($option['units'] ?? [])
            ];
        }
        
        return $transformedOptions;
    }
    
    /**
     * Transform units to Klook format
     */
    private function transformUnits($units) {
        $transformedUnits = [];
        
        foreach ($units as $unit) {
            $restrictions = [
                'minAge' => $unit['min_age'],
                'maxAge' => $unit['max_age'],
                'idRequired' => (bool)$unit['id_required'],
                'minQuantity' => $unit['min_quantity'],
                'maxQuantity' => $unit['max_quantity'],
                'paxCount' => (int)$unit['pax_count'],
                'accompaniedBy' => $unit['accompanied_by'] ? json_decode($unit['accompanied_by'], true) : null
            ];
            
            // Remove null values from restrictions
            $restrictions = array_filter($restrictions, function($value) {
                return $value !== null;
            });
            
            $transformedUnits[] = [
                'id' => $unit['id'],
                'internalName' => $unit['internal_name'],
                'reference' => $unit['reference'],
                'type' => $unit['type'],
                'requiredContactFields' => json_decode($unit['required_contact_fields'], true),
                'restrictions' => $restrictions
            ];
        }
        
        return $transformedUnits;
    }
}