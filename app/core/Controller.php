<?php
abstract class Controller {
    protected function getRequestBody() {
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }
    
    protected function getQueryParams() {
        return $_GET;
    }
    
    protected function validate($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            if (strpos($rule, 'required') !== false && empty($data[$field])) {
                $errors[$field] = "Field {$field} is required";
            }
        }
        
        if (!empty($errors)) {
            ResponseHelper::badRequest('Validation failed', $errors);
        }
        
        return true;
    }
}
