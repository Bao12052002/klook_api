<?php
class HealthController extends Controller {
    public function check() {
        try {
            // Check database connection
            $db = Database::getInstance();
            $stmt = $db->query('SELECT 1');
            
            $health = [
                'status' => 'healthy',
                'timestamp' => date('Y-m-d H:i:s'),
                'version' => '1.0.0',
                'database' => 'connected'
            ];
            
            ResponseHelper::success($health);
        } catch (Exception $e) {
            ResponseHelper::error('Service unhealthy', 503, [
                'database' => 'disconnected',
                'error' => $e->getMessage()
            ]);
        }
    }
}