<?php
class AuthMiddleware {
    public function handle() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if (!$authHeader) {
            ResponseHelper::unauthorized('Authorization header required');
            return false;
        }
        
        $token = str_replace('Bearer ', '', $authHeader);
        
        if (!isset(KLOOK_API_TOKENS[$token])) {
            ResponseHelper::unauthorized('Invalid token');
            return false;
        }
        
        // Store token info for use in controllers
        $_REQUEST['_auth'] = KLOOK_API_TOKENS[$token];
        $_REQUEST['_token'] = $token;
        
        return true;
    }
}
