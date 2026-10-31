<?php
class AiConfig
{
    private const PATH=__DIR__.'/../config/ai.local.json';
    public static function load(): array
    {
        $local=is_file(self::PATH) ? json_decode(file_get_contents(self::PATH),true) : [];
        if (!is_array($local)) $local=[];
        return [
            'enabled'=>getenv('INMOVA_AI_ENABLED')!==false ? getenv('INMOVA_AI_ENABLED')==='1' : (bool)($local['enabled'] ?? false),
            'api_key'=>getenv('OPENAI_API_KEY') ?: ($local['api_key'] ?? ''),
            'model'=>getenv('OPENAI_MODEL') ?: ($local['model'] ?? ''),
            'daily_limit'=>max(1,min(1000,(int)($local['daily_limit'] ?? 100))),
            'ip_limit'=>max(1,min(100,(int)($local['ip_limit'] ?? 20))),
        ];
    }
    public static function ready(?array $config=null): bool
    {
        $config=$config ?? self::load();
        return $config['enabled'] && $config['api_key']!=='' && $config['model']!=='' && extension_loaded('curl');
    }
    public static function save(array $data, string $key, bool $remove=false): void
    {
        $current=self::load();
        $key=$remove ? '' : ($key!=='' ? trim($key) : $current['api_key']);
        if (strlen($key)>500 || preg_match('/[\r\n]/',$key)) throw new DomainException('La clave no tiene un formato válido.');
        if ($data['model']!=='' && !preg_match('/^[a-zA-Z0-9._:-]{1,100}$/D',$data['model'])) throw new DomainException('Ingresa un ID de modelo válido.');
        if ($data['enabled'] && ($key==='' || $data['model']==='')) throw new DomainException('Configura una clave y un modelo antes de activar la IA.');
        if ($data['enabled'] && !extension_loaded('curl')) throw new DomainException('Activa la extensión cURL de PHP antes de habilitar la IA.');
        if ($data['daily_limit']<1 || $data['daily_limit']>1000 || $data['ip_limit']<1 || $data['ip_limit']>100) throw new DomainException('Límites válidos: de 1 a 1000 solicitudes globales y de 1 a 100 por IP al día.');
        // Las variables de entorno prevalecen; no se copian sus secretos al archivo local.
        $localKey=getenv('OPENAI_API_KEY') ? '' : $key;
        $json=json_encode($data+['api_key'=>$localKey],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $temp=__DIR__.'/../config/ai.local.'.bin2hex(random_bytes(8)).'.tmp';
        if (file_put_contents($temp,$json,LOCK_EX)===false) throw new RuntimeException('No se pudo guardar la configuración.');
        if (!rename($temp,self::PATH)) { @unlink($temp); throw new RuntimeException('No se pudo actualizar la configuración.'); }
    }
}
