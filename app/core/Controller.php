<?php
// Clase base de los controladores: renderiza vistas dentro del layout.
abstract class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'main'): void
    {
        extract($data, EXTR_SKIP);
        $user = Auth::user();
        $viewFile = __DIR__ . "/../views/{$view}.php";
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        require __DIR__ . "/../views/layouts/{$layout}.php";
    }

    protected function redirect(string $path): void
    {
        header('Location: ' . BASE_URL . $path);
        exit;
    }
}
