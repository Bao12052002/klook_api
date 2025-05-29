<?php
class PickupController extends Controller {
    private $pickupModel;
    
    public function __construct() {
        $this->pickupModel = new Pickup();
    }
    
    public function index() {
        try {
            $params = $this->getQueryParams();
            
            if (isset($params['location'])) {
                $limit = isset($params['limit']) ? (int)$params['limit'] : 20;
                $offset = isset($params['offset']) ? (int)$params['offset'] : 0;
                $pickups = $this->pickupModel->findByLocation($params['location'], $limit, $offset);
            } else {
                $pickups = $this->pickupModel->findActivePickups();
            }
            
            ResponseHelper::success($pickups);
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
    
    public function create() {
        try {
            $data = $this->getRequestBody();
            
            $this->validate($data, [
                'location' => 'required',
                'address' => 'required'
            ]);
            
            $pickupId = $this->pickupModel->create($data);
            $pickup = $this->pickupModel->find($pickupId);
            
            ResponseHelper::created($pickup, 'Pickup location created successfully');
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
}