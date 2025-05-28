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
            
            $products = $this->productModel->findAll($limit, $offset);
            
            ResponseHelper::success($products);
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
    
    public function show($id) {
        try {
            $product = $this->productModel->find($id);
            
            if (!$product) {
                throw new NotFoundException('Product not found');
            }
            
            ResponseHelper::success($product);
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
    
    public function create() {
        try {
            $data = $this->getRequestBody();
            
            $this->validate($data, [
                'name' => 'required',
                'description' => 'required',
                'price' => 'required'
            ]);
            
            $productId = $this->productModel->create($data);
            $product = $this->productModel->find($productId);
            
            ResponseHelper::created($product, 'Product created successfully');
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
            $this->productModel->update($id, $data);
            
            $updatedProduct = $this->productModel->find($id);
            ResponseHelper::success($updatedProduct, 'Product updated successfully');
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
}
