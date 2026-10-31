<?php
// Router mínimo: asocia "MÉTODO + ruta" con "Controlador@acción".
class Router
{
    private array $routes = [];

    public function get(string $path, string $handler): void  { $this->routes['GET'][$path]  = $handler; }
    public function post(string $path, string $handler): void { $this->routes['POST'][$path] = $handler; }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = substr($path, strlen(BASE_URL)) ?: '/';
        $path = '/' . trim($path, '/');

        $handler = $this->routes[$method][$path] ?? null;
        if (!$handler) {
            http_response_code(404);
            exit('404 · Página no encontrada');
        }
        [$class, $action] = explode('@', $handler);
        (new $class())->$action();
    }
}
