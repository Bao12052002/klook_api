<?php
class SupplierController extends Controller {
    private $supplierModel;
    
    public function __construct() {
        $this->supplierModel = new Supplier();
    }
    
    public function show($id) {
        try {
            $supplier = $this->supplierModel->findWithContactDetails($id);
            
            if (!$supplier) {
                throw new NotFoundException('Supplier not found');
            }
            
            ResponseHelper::success($supplier);
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
    
    public function index() {
        try {
            $params = $this->getQueryParams();
            $limit = isset($params['limit']) ? (int)$params['limit'] : 20;
            $offset = isset($params['offset']) ? (int)$params['offset'] : 0;
            
            $suppliers = $this->supplierModel->findAllWithContactDetails($limit, $offset);
            
            ResponseHelper::success($suppliers);
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
}