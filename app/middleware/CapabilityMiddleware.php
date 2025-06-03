<?php
class CapabilityMiddleware {
    private $requiredCapability;
    
    public function __construct($capability = 'read') {
        $this->requiredCapability = $capability;
    }
    
    public function handle() {
        if (!isset($_REQUEST['_auth'])) {
            ResponseHelper::unauthorized('Authentication required');
            return false;
        }
        
        $userCapabilities = $_REQUEST['_auth']['capabilities'] ?? [];
        
        if (!in_array($this->requiredCapability, $userCapabilities)) {
            ResponseHelper::forbidden('Insufficient permissions');
            return false;
        }
        
        return true;
    }
}