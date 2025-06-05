<?php
class ResponseHelper {
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        // Thêm JSON_PRETTY_PRINT để dễ đọc hơn khi debug, có thể bỏ đi trong môi trường production
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Trả về dữ liệu thành công.
     * Theo đặc tả Klook, các phản hồi thành công thường là dữ liệu trực tiếp (đối tượng hoặc mảng đối tượng).
     */
    public static function success($data = null) {
        self::json($data, 200);
    }

    /**
     * Trả về phản hồi lỗi theo chuẩn Klook.
     *
     * @param int $statusCode Mã trạng thái HTTP (ví dụ: 400, 404, 500).
     * @param string $errorCode Mã lỗi của Klook (ví dụ: INVALID_PRODUCT_ID).
     * @param string $errorMessage Thông điệp lỗi.
     * @param array $extraData Các trường dữ liệu bổ sung (ví dụ: ['productId' => '123']).
     */
    public static function error($statusCode, $errorCode, $errorMessage, $extraData = []) {
        $response = [
            'error' => $errorCode,
            'errorMessage' => $errorMessage
        ];
        if (!empty($extraData) && is_array($extraData)) {
            $response = array_merge($response, $extraData);
        }
        self::json($response, $statusCode);
    }

    /**
     * Phản hồi lỗi 400 Bad Request.
     */
    public static function badRequest($errorCode, $errorMessage, $extraData = []) {
        self::error(400, $errorCode, $errorMessage, $extraData);
    }

    /**
     * Phản hồi lỗi 401 Unauthorized.
     */
    public static function unauthorized($errorMessage = 'Authorization header required', $errorCode = 'UNAUTHORIZED') {
        self::error(401, $errorCode, $errorMessage);
    }

    /**
     * Phản hồi lỗi 403 Forbidden.
     */
    public static function forbidden($errorMessage = 'Insufficient permissions', $errorCode = 'FORBIDDEN') {
        self::error(403, $errorCode, $errorMessage);
    }

    /**
     * Phản hồi lỗi 404 Not Found.
     */
    public static function notFound($errorMessage = 'Resource not found', $errorCode = 'NOT_FOUND') {
        // Ví dụ: nếu route không tìm thấy, Router.php sẽ gọi cái này
        self::error(404, $errorCode, $errorMessage);
    }

    /**
     * Phản hồi lỗi 405 Method Not Allowed.
     */
    public static function methodNotAllowed($errorMessage = 'Method Not Allowed', $errorCode = 'METHOD_NOT_ALLOWED') {
        self::error(405, $errorCode, $errorMessage);
    }
    
    /**
     * Phản hồi 201 Created.
     * Theo đặc tả Klook, thường trả về tài nguyên vừa được tạo.
     */
    public static function created($data) {
        self::json($data, 201);
    }
}