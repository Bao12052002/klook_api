<?php
class ErrorHelper {
    public static function handleException($exception) {
        error_log("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
        
        if ($exception instanceof ValidationException) {
            ResponseHelper::badRequest($exception->getMessage(), $exception->getDetails());
        } elseif ($exception instanceof UnauthorizedException) {
            ResponseHelper::unauthorized($exception->getMessage());
        } elseif ($exception instanceof ForbiddenException) {
            ResponseHelper::forbidden($exception->getMessage());
        } elseif ($exception instanceof NotFoundException) {
            ResponseHelper::notFound($exception->getMessage());
        } else {
            ResponseHelper::error('Internal Server Error');
        }
    }
    
    public static function logError($message, $context = []) {
        $logMessage = $message;
        if (!empty($context)) {
            $logMessage .= ' | Context: ' . json_encode($context);
        }
        error_log($logMessage);
    }
}

// Custom Exception Classes
class ValidationException extends Exception {
    private $details;
    
    public function __construct($message, $details = null) {
        parent::__construct($message);
        $this->details = $details;
    }
    
    public function getDetails() {
        return $this->details;
    }
}

class UnauthorizedException extends Exception {}
class ForbiddenException extends Exception {}
class NotFoundException extends Exception {}