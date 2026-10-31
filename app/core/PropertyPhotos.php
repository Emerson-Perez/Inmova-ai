<?php
class PropertyPhotos
{
    public static function receive(): array
    {
        $files = $_FILES['photos'] ?? null;
        if (!$files) return [];
        if (!is_array($files['error'] ?? null) || count($files['error']) > 10) throw new RuntimeException('Selecciona hasta 10 fotografías.');
        $paths = [];
        try {
            foreach ($files['error'] as $i => $error) {
                if ($error === UPLOAD_ERR_NO_FILE) continue;
                if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo recibir una fotografía. Revisa el límite de tamaño del servidor.');
                $tmp = $files['tmp_name'][$i] ?? null;
                if (!is_string($tmp) || !is_uploaded_file($tmp)) throw new RuntimeException('Archivo de fotografía inválido.');
                if (filesize($tmp) > 2 * 1024 * 1024) throw new RuntimeException('Cada fotografía admite hasta 2 MB.');
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
                $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                $size = @getimagesize($tmp);
                if (!isset($extensions[$mime]) || !$size || $size[0] > 8000 || $size[1] > 8000 || $size[0] * $size[1] > 24000000) throw new RuntimeException('Usa imágenes JPG, PNG o WebP de hasta 24 megapíxeles y 8000 píxeles por lado.');
                $path = 'uploads/properties/' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
                if (!move_uploaded_file($tmp, __DIR__ . '/../../public/' . $path)) throw new RuntimeException('No se pudo guardar la fotografía.');
                $paths[] = $path;
            }
            return $paths;
        } catch (Throwable $error) {
            self::discard($paths);
            throw $error;
        }
    }

    public static function discard(array $paths): void
    {
        foreach ($paths as $path) {
            if (preg_match('~^uploads/properties/[a-f0-9]{32}\.(jpg|png|webp)$~D', $path)) @unlink(__DIR__ . '/../../public/' . $path);
        }
    }
}
