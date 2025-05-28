<?php
class ResponseHelper {
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public static function success($data = null, $message = 'Success') {
        
        
            $response = $data;
        
        
        self::json($response, 200);
    }
    
    public static function error($message, $statusCode = 500, $details = null) {
        $response = [
            'status' => 'error',
            'message' => $message
        ];
        
        if ($details !== null) {
            $response['details'] = $details;
        }
        
        self::json($response, $statusCode);
    }
    
    public static function badRequest($message = 'Bad Request', $details = null) {
        self::error($message, 400, $details);
    }
    
    public static function unauthorized($message = 'Unauthorized') {
        self::error($message, 401);
    }
    
    public static function forbidden($message = 'Forbidden') {
        self::error($message, 403);
    }
    
    public static function notFound($message = 'Not Found') {
        self::error($message, 404);
    }
    
    public static function methodNotAllowed($message = 'Method Not Allowed') {
        self::error($message, 405);
    }
    
    public static function created($data = null, $message = 'Resource created successfully') {
        $response = [
            'status' => 'success',
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        self::json($response, 201);
    }
}
