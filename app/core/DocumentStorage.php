<?php
class DocumentStorage
{
    public const MAX_SIZE=2*1024*1024;
    private const DIRECTORY=__DIR__.'/../storage/documents';
    public static function path(string $name): string
    {
        if (!preg_match('/^[a-f0-9]{32}\.pdf$/D',$name)) throw new DomainException('Referencia de documento inválida.');
        return self::DIRECTORY.'/'.$name;
    }
    public static function inspect(string $path, string $original): array
    {
        if (!is_file($path) || !is_readable($path)) throw new DomainException('No se pudo leer el archivo recibido.');
        $size=filesize($path);
        if ($size===false || $size<1 || $size>self::MAX_SIZE) throw new DomainException('Cada documento admite hasta 2 MB.');
        $name=basename(str_replace('\\','/',$original));
        $name=preg_replace('/[\x00-\x1F\x7F]/','',$name);
        if (strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='pdf') throw new DomainException('Selecciona un archivo con extensión PDF.');
        $stream=fopen($path,'rb'); $signature=fread($stream,8); fclose($stream);
        if (!str_starts_with($signature,'%PDF-') || (new finfo(FILEINFO_MIME_TYPE))->file($path)!=='application/pdf') throw new DomainException('El archivo recibido no tiene formato PDF.');
        $name=mb_substr($name,0,176); if (strtolower(substr($name,-4))!=='.pdf') $name.='.pdf';
        return ['nombre_original'=>$name,'tamano'=>$size];
    }
    public static function receive(array $file): array
    {
        if (!isset($file['error']) || !is_int($file['error']) || $file['error']!==UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_string($file['name'] ?? null) || !is_uploaded_file($file['tmp_name'])) throw new DomainException('No se recibió el documento. Selecciona un PDF de hasta 2 MB.');
        $metadata=self::inspect($file['tmp_name'],$file['name']);
        if (!is_dir(self::DIRECTORY) && !mkdir(self::DIRECTORY,0750,true) && !is_dir(self::DIRECTORY)) throw new RuntimeException('No se pudo preparar el almacenamiento privado.');
        $name=bin2hex(random_bytes(16)).'.pdf';
        if (!move_uploaded_file($file['tmp_name'],self::path($name))) throw new RuntimeException('No se pudo guardar el documento.');
        return $metadata+['storage_name'=>$name];
    }
    public static function discard(string $name): void
    {
        $path=self::path($name); if (is_file($path)) @unlink($path);
    }
}
