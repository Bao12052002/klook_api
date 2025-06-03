<?php
class Router {
    private $routes = [];
    private $middlewares = [];
    
    public function get($path, $handler, $middlewares = []) {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }
    
    public function post($path, $handler, $middlewares = []) {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }
    
    public function put($path, $handler, $middlewares = []) {
        $this->addRoute('PUT', $path, $handler, $middlewares);
    }
    
    public function delete($path, $handler, $middlewares = []) {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
    }
    
    private function addRoute($method, $path, $handler, $middlewares) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $middlewares
        ];
    }
    
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remove /klook prefix if exists
        $uri = preg_replace('#^/klook#', '', $uri);
        if (empty($uri)) $uri = '/';
        
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->matchPath($route['path'], $uri)) {
                $this->executeRoute($route, $uri);
                return;
            }
        }
        
        ResponseHelper::notFound('Route not found');
    }
    
    private function matchPath($routePath, $uri) {
        $routePattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $routePath);
        $routePattern = '#^' . $routePattern . '$#';
        return preg_match($routePattern, $uri);
    }
    
    private function executeRoute($route, $uri) {
        // Run middlewares
        foreach ($route['middlewares'] as $middleware) {
            $middlewareInstance = new $middleware();
            if (!$middlewareInstance->handle()) {
                return;
            }
        }
        
        // Extract parameters
        $params = $this->extractParams($route['path'], $uri);
        
        // Execute handler
        [$controller, $method] = explode('@', $route['handler']);
        $controllerInstance = new $controller();
        call_user_func_array([$controllerInstance, $method], $params);
    }
    
    private function extractParams($routePath, $uri) {
        $routePattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $routePath);
        $routePattern = '#^' . $routePattern . '$#';
        
        preg_match($routePattern, $uri, $matches);
        array_shift($matches); // Remove full match
        
        return $matches;
    }
}
