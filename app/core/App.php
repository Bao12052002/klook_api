<?php
class App {
    private $router;
    
    public function __construct() {
        $this->router = new Router();
        $this->loadRoutes();
    }
    
    private function loadRoutes() {
        $router = $this->router; // Make router available to routes file
        require_once __DIR__ . '/../routes/api.php';
    }
    
    public function run() {
        try {
            $this->router->dispatch();
        } catch (Exception $e) {
            ErrorHelper::handleException($e);
        }
    }
}