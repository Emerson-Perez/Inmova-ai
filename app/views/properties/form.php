<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0"><?= e($title) ?></h1>
  <?php if (isset($property['id'])): ?><a class="btn btn-outline-primary" href="<?= BASE_URL ?>/properties/documents?property_id=<?= (int)$property['id'] ?>">Documentos privados</a><?php endif; ?>
  <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/properties">Volver al listado</a>
</div>
<?php if ($errors): ?>
<div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form class="card card-body shadow-sm" method="post" enctype="multipart/form-data" action="<?= BASE_URL ?>/properties/save<?= isset($property['id']) ? '?id=' . (int)$property['id'] : '' ?>">
  <?= Csrf::field() ?>
  <div class="row g-3">
    <div class="col-12"><label for="titulo" class="form-label">Título *</label><input id="titulo" name="titulo" class="form-control" required maxlength="160" value="<?= e($property['titulo'] ?? '') ?>"></div>
    <?php foreach (['tipo_id' => ['Tipo de propiedad', $types], 'modalidad_id' => ['Modalidad', $modes]] as $key => [$label, $options]): ?>
    <div class="col-md-6"><label class="form-label" for="<?= e($key) ?>"><?= e($label) ?> *</label><select class="form-select" id="<?= e($key) ?>" name="<?= e($key) ?>" required><option value="">Seleccionar</option><?php foreach ($options as $option): ?><option value="<?= (int)$option['id'] ?>" <?= (string)($property[$key] ?? '') === (string)$option['id'] ? 'selected' : '' ?>><?= e($option['nombre']) ?></option><?php endforeach; ?></select></div>
    <?php endforeach; ?>
    <div class="col-md-4"><label for="precio" class="form-label">Precio *</label><input class="form-control" id="precio" name="precio" type="number" inputmode="numeric" required min="1" max="5000000" step="1" aria-describedby="precio-help" value="<?= e(isset($property['precio']) && is_numeric($property['precio']) && (float)$property['precio'] === floor((float)$property['precio']) ? (string)(int)$property['precio'] : ($property['precio'] ?? '')) ?>"><div class="form-text" id="precio-help">Solo enteros, de 1 a 5 000 000 en PEN o USD.</div></div>
    <div class="col-md-4"><label for="moneda" class="form-label">Moneda *</label><select class="form-select" id="moneda" name="moneda"><?php foreach (['PEN' => 'Soles (PEN)', 'USD' => 'Dólares (USD)'] as $value => $label): ?><option value="<?= e($value) ?>" <?= ($property['moneda'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label for="disponibilidad" class="form-label">Disponibilidad *</label><select class="form-select" name="disponibilidad" id="disponibilidad"><?php foreach (['disponible','reservado','vendido','alquilado'] as $value): ?><option value="<?= e($value) ?>" <?= ($property['disponibilidad'] ?? '') === $value ? 'selected' : '' ?>><?= e(ucfirst($value)) ?></option><?php endforeach; ?></select></div>
    <?php foreach (['area_m2' => 'Área (m²)', 'dormitorios' => 'Dormitorios', 'banos' => 'Baños'] as $key => $label): ?>
    <div class="col-md-4"><label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label><input class="form-control" id="<?= e($key) ?>" name="<?= e($key) ?>" type="number" min="<?= $key === 'area_m2' ? '0.01' : '0' ?>" max="<?= $key === 'area_m2' ? '99999999.99' : '255' ?>" step="<?= $key === 'area_m2' ? '0.01' : '1' ?>" value="<?= e($property[$key] ?? '') ?>"></div>
    <?php endforeach; ?>
    <div class="col-12"><label class="form-label" for="descripcion">Descripción</label><textarea class="form-control" id="descripcion" name="descripcion" rows="5" maxlength="15000"><?= e($property['descripcion'] ?? '') ?></textarea></div>
    <div class="col-12"><h2 class="h5 mt-3">Ubicación</h2><p class="text-muted small">Opcional. Si agregas una ubicación, completa departamento, provincia y distrito.</p></div>
    <?php foreach (['departamento'=>'Departamento','provincia'=>'Provincia','distrito'=>'Distrito','direccion'=>'Dirección'] as $key=>$label): ?>
    <div class="<?= $key === 'direccion' ? 'col-12' : 'col-md-4' ?>"><label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label><input class="form-control" id="<?= e($key) ?>" name="<?= e($key) ?>" maxlength="<?= $key === 'direccion' ? 200 : 60 ?>" value="<?= e($property[$key] ?? '') ?>"></div>
    <?php endforeach; ?>
    <div class="col-12"><h2 class="h5 mt-3">Fotografías</h2><label for="photos" class="form-label">Agregar fotografías</label><input class="form-control" type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple aria-describedby="photo-help"><p class="text-muted small mt-2" id="photo-help">Hasta 10 fotos en total, JPG, PNG o WebP. Máximo 2 MB por foto y 24 megapíxeles. El servidor puede limitar el tamaño total de la solicitud. La primera foto será la principal; después puedes cambiarla al editar. Si hay errores, vuelve a seleccionar los archivos.</p></div>
    <?php foreach ($photos as $photo): ?>
    <div class="col-sm-6 col-lg-3"><div class="card h-100"><img src="<?= BASE_URL ?>/<?= e($photo['ruta']) ?>" class="card-img-top" style="height:180px;object-fit:cover" alt="Fotografía del inmueble"><div class="card-body"><div class="form-check"><input class="form-check-input" type="radio" name="primary_photo" value="<?= (int)$photo['id'] ?>" id="primary-<?= (int)$photo['id'] ?>" <?= $photo['es_principal'] ? 'checked' : '' ?>><label class="form-check-label" for="primary-<?= (int)$photo['id'] ?>">Imagen principal</label></div><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_photos[]" value="<?= (int)$photo['id'] ?>" id="remove-<?= (int)$photo['id'] ?>"><label class="form-check-label text-danger" for="remove-<?= (int)$photo['id'] ?>">Retirar al guardar</label></div></div></div></div>
    <?php endforeach; ?>
    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="publicado" name="publicado" value="1" <?= !empty($property['publicado']) ? 'checked' : '' ?>><label class="form-check-label" for="publicado">Publicar en el portal público</label></div></div>
    <div class="col-12"><button class="btn btn-primary">Guardar propiedad</button></div>
  </div>
</form>

