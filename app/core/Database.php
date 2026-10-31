<?php
// Conexión PDO única (patrón singleton). Siempre usar consultas preparadas.
class Database
{
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            $c = require __DIR__ . '/../config/config.php';
            $db = $c['db'];
            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // Conexiones remotas en la nube (ej. TiDB Cloud) exigen SSL/TLS obligatorio:
            if (!in_array($db['host'], ['127.0.0.1', 'localhost'], true)) {
                $caCandidates = [
                    '/etc/pki/tls/certs/ca-bundle.crt',
                    '/etc/ssl/certs/ca-certificates.crt',
                    ini_get('openssl.cafile'),
                    ini_get('curl.cainfo'),
                    'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt',
                ];
                foreach ($caCandidates as $ca) {
                    if ($ca && is_file($ca)) {
                        $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
                        break;
                    }
                }
                if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }
            }

            self::$pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
        }
        return self::$pdo;
    }
}
