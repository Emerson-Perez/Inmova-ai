<?php
// Configuración general. Lee variables de entorno si existen (ej. Vercel/Cloud), o usa los valores locales de XAMPP por defecto.
return [
    'app_name' => getenv('APP_NAME') ?: 'INMOVA AI',
    'db' => [
        'host'    => getenv('DB_HOST') ?: '127.0.0.1',
        'port'    => (int)(getenv('DB_PORT') ?: 3306),
        'name'    => getenv('DB_NAME') ?: 'inmova_ai',
        'user'    => getenv('DB_USER') ?: 'root',
        'pass'    => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
        'charset' => 'utf8mb4',
    ],
];

