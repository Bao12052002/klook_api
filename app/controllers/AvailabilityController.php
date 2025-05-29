<?php
class AvailabilityController extends Controller {
    private $availabilityModel;
    private $productModel;
    
    public function __construct() {
        $this->availabilityModel = new Availability();
        $this->productModel = new Product();
    }
    
    public function index($productId) {
        try {
            // Verify product exists
            $product = $this->productModel->find($productId);
            if (!$product) {
                throw new NotFoundException('Product not found');
            }
            
            $params = $this->getQueryParams();
            $startDate = $params['start_date'] ?? date('Y-m-d');
            $endDate = $params['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
            
            $availability = $this->availabilityModel->findByProductAndDateRange($productId, $startDate, $endDate);
            
            ResponseHelper::success($availability);
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
    
    public function create($productId) {
        try {
            // Verify product exists
            $product = $this->productModel->find($productId);
            if (!$product) {
                throw new NotFoundException('Product not found');
            }
            
            $data = $this->getRequestBody();
            
            $this->validate($data, [
                'date' => 'required',
                'available_slots' => 'required'
            ]);
            
            $data['product_id'] = $productId;
            $availabilityId = $this->availabilityModel->updateAvailability(
                $productId, 
                $data['date'], 
                $data['available_slots']
            );
            
            $availability = $this->availabilityModel->findByProductAndDate($productId, $data['date']);
            
            ResponseHelper::success($availability, 'Availability updated successfully');
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
}