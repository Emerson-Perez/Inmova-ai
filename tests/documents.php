<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
spl_autoload_register(function ($class) {
    foreach (['core','models'] as $dir) {
        $file=__DIR__.'/../app/'.$dir.'/'.$class.'.php';
        if (is_file($file)) { require $file; return; }
    }
});
function checkDocument(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    echo 'OK: '.$label.PHP_EOL;
}
function denyDocument(callable $operation, string $label): void {
    try { $operation(); } catch (DomainException $error) { checkDocument(true,$label); return; }
    throw new RuntimeException('FAIL: '.$label);
}
$db=Database::connect(); $users=[]; $property=null; $files=[];
try {
    foreach (['admin','vendedor','vendedor','instructor'] as $role) {
        $st=$db->prepare('SELECT id FROM roles WHERE nombre=?'); $st->execute([$role]);
        $db->prepare("INSERT INTO usuarios (rol_id,nombres,apellidos,email,password_hash) VALUES (?,'QA','Documentos',?,?)")->execute([$st->fetchColumn(),bin2hex(random_bytes(8)).'@inmova.test',password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]); $users[]=(int)$db->lastInsertId();
    }
    $admin=['id'=>$users[0],'rol'=>'admin']; $owner=['id'=>$users[1],'rol'=>'vendedor']; $other=['id'=>$users[2],'rol'=>'vendedor']; $teacher=['id'=>$users[3],'rol'=>'instructor'];
    $type=(int)$db->query('SELECT id FROM tipos_propiedad LIMIT 1')->fetchColumn(); $mode=(int)$db->query('SELECT id FROM modalidades LIMIT 1')->fetchColumn();
    $db->prepare("INSERT INTO propiedades (tipo_id,modalidad_id,creado_por,titulo,precio) VALUES (?,?,?,'QA Documentos temporal',1000)")->execute([$type,$mode,$owner['id']]); $property=(int)$db->lastInsertId();
    $leaf=bin2hex(random_bytes(16)).'.pdf'; $files[]=$leaf; $path=DocumentStorage::path($leaf);
    file_put_contents($path,"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
    $inspection=DocumentStorage::inspect($path,"..\\carpeta\\plano\r\n.pdf");
    checkDocument($inspection['nombre_original']==='plano.pdf' && $inspection['tamano']>0,'nombre normalizado y PDF reconocido');
    denyDocument(fn()=>DocumentStorage::path('../config/config.php'),'rutas fuera de almacenamiento rechazadas');
    denyDocument(fn()=>DocumentStorage::inspect($path,'plano.exe'),'extensión no PDF rechazada');
    $badLeaf=bin2hex(random_bytes(16)).'.pdf'; $files[]=$badLeaf; $bad=DocumentStorage::path($badLeaf); file_put_contents($bad,'<?php echo "no pdf";');
    denyDocument(fn()=>DocumentStorage::inspect($bad,'falso.pdf'),'contenido disfrazado rechazado');
    file_put_contents($bad,"%PDF-1.4\n".str_repeat('x',DocumentStorage::MAX_SIZE)); clearstatcache(true,$bad);
    denyDocument(fn()=>DocumentStorage::inspect($bad,'grande.pdf'),'límite dos MB aplicado');
    $db->prepare("INSERT INTO propiedad_documentos (propiedad_id,titulo,categoria,nombre_original,storage_name,tamano,subido_por) VALUES (?,'QA Documento','plano','plano.pdf',?,?,?)")->execute([$property,$leaf,$inspection['tamano'],$owner['id']]); $document=(int)$db->lastInsertId();
    checkDocument(count(PropertyDocument::listing($property,true,$owner))===1 && PropertyDocument::find($document,$admin)!==null,'propietario y administrador tienen acceso');
    checkDocument(PropertyDocument::find($document,$other)===null,'vendedor ajeno no encuentra documento');
    denyDocument(fn()=>PropertyDocument::listing($property,true,$other),'vendedor ajeno no lista documentos');
    denyDocument(fn()=>PropertyDocument::property($property,$teacher),'instructor sin acceso documental');
    denyDocument(fn()=>PropertyDocument::download($document,$other),'descarga ajena bloqueada');
    $download=PropertyDocument::download($document,$owner);
    checkDocument($download['path']===$path && count(PropertyDocument::history($property,$admin))===1,'descarga autorizada registra evento');
    PropertyDocument::setActive($document,false,$owner);
    checkDocument(PropertyDocument::find($document,$owner)===null && is_file($path),'retiro bloquea descarga y conserva archivo');
    denyDocument(fn()=>PropertyDocument::download($document,$admin),'retirado no se descarga ni como administrador');
    $history=count(PropertyDocument::history($property,$admin)); PropertyDocument::setActive($document,false,$owner);
    checkDocument(count(PropertyDocument::history($property,$admin))===$history,'retiro repetido no duplica eventos');
    PropertyDocument::setActive($document,true,$admin);
    checkDocument(PropertyDocument::find($document,$owner)!==null,'restauración recupera acceso');
    PropertyDocument::setActive($document,false,$owner);
    foreach (range(1,PropertyDocument::MAX_ACTIVE) as $unused) $db->prepare("INSERT INTO propiedad_documentos (propiedad_id,titulo,categoria,nombre_original,storage_name,tamano,subido_por) VALUES (?,'QA Límite','otro','prueba.pdf',?,1,?)")->execute([$property,bin2hex(random_bytes(16)).'.pdf',$owner['id']]);
    denyDocument(fn()=>PropertyDocument::setActive($document,true,$owner),'restauración respeta máximo de documentos activos');
    denyDocument(fn()=>PropertyDocument::upload($property,'Otro','plano',[],$owner),'carga respeta máximo de documentos activos');
    $db->prepare('UPDATE propiedades SET estado=0 WHERE id=?')->execute([$property]);
    checkDocument(PropertyDocument::find($document,$admin,false)===null,'inmueble inactivo no expone documentos');
} finally {
    if ($property) {
        $db->prepare('DELETE e FROM documento_eventos e JOIN propiedad_documentos d ON d.id=e.documento_id WHERE d.propiedad_id=?')->execute([$property]);
        $db->prepare('DELETE FROM propiedad_documentos WHERE propiedad_id=?')->execute([$property]);
        $db->prepare('DELETE FROM propiedades WHERE id=?')->execute([$property]);
    }
    foreach ($files as $name) DocumentStorage::discard($name);
    foreach ($users as $id) $db->prepare('DELETE FROM usuarios WHERE id=?')->execute([$id]);
}
