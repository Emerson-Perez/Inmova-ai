<?php
/** @var array<string, string> $filters */
/** @var array<int, array{id: int|string, nombre: string}> $modes */
/** @var array<int, string> $filterErrors */
/** @var array<int, array<string, mixed>> $properties */
/** @var string $query */
/** @var string|null $success */
/** @var string|null $error */
?>
<div class="d-flex justify-content-between align-items-center mb-4 gap-3">
  <div><h1 class="h3 mb-1">Propiedades</h1><p class="text-muted mb-0">Gestiona tu inventario inmobiliario.</p></div>
  <a class="btn btn-primary" href="<?= BASE_URL ?>/properties/create">Nueva propiedad</a>
</div>
<?php if ($success): ?><div class="alert alert-success" role="status"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="get" action="<?= BASE_URL ?>/properties" class="card card-body mb-4" aria-label="Filtrar propiedades">
  <div class="row g-3">
    <div class="col-12 col-lg-5"><label class="form-label" for="q">Título de la propiedad</label><input class="form-control" id="q" name="q" maxlength="160" placeholder="Buscar por título…" value="<?= e($query) ?>"></div>
    <div class="col-sm-6 col-lg-3"><label class="form-label" for="modalidad_id">Modalidad</label><select class="form-select" id="modalidad_id" name="modalidad_id"><option value="">Todas las modalidades</option><?php foreach ($modes as $mode): ?><option value="<?= (int)$mode['id'] ?>" <?= $filters['modalidad_id']===(string)$mode['id'] ? 'selected' : '' ?>><?= e($mode['nombre']) ?></option><?php endforeach; ?></select></div>
    <div class="col-sm-6 col-lg-2"><label class="form-label" for="disponibilidad">Disponibilidad</label><select class="form-select" id="disponibilidad" name="disponibilidad"><option value="">Todas</option><?php foreach (['disponible'=>'Disponible','reservado'=>'Reservado','vendido'=>'Vendido','alquilado'=>'Alquilado'] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $filters['disponibilidad']===$value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="col-sm-6 col-lg-2"><label class="form-label" for="publicado">Publicación</label><select class="form-select" id="publicado" name="publicado"><option value="">Todas</option><option value="1" <?= $filters['publicado']==='1' ? 'selected' : '' ?>>Publicada</option><option value="0" <?= $filters['publicado']==='0' ? 'selected' : '' ?>>Borrador</option></select></div>
    <div class="col-sm-6 col-lg-2"><label class="form-label" for="moneda">Moneda</label><select class="form-select" id="moneda" name="moneda" aria-describedby="price-filter-help"><option value="">Todas</option><option value="PEN" <?= $filters['moneda']==='PEN' ? 'selected' : '' ?>>Soles (PEN)</option><option value="USD" <?= $filters['moneda']==='USD' ? 'selected' : '' ?>>Dólares (USD)</option></select></div>
    <div class="col-sm-6 col-lg-3"><label class="form-label" for="min">Precio mínimo</label><input class="form-control" id="min" name="min" type="number" min="0" max="5000000" step="1" placeholder="Desde" value="<?= e($filters['min']) ?>" aria-describedby="price-filter-help"></div>
    <div class="col-sm-6 col-lg-3"><label class="form-label" for="max">Precio máximo</label><input class="form-control" id="max" name="max" type="number" min="0" max="5000000" step="1" placeholder="Hasta" value="<?= e($filters['max']) ?>" aria-describedby="price-filter-help"></div>
    <div class="col-12 col-lg-4 d-flex align-items-end gap-2"><button class="btn btn-primary flex-grow-1" type="submit">Buscar propiedades</button><a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/properties">Limpiar</a></div>
    <div class="col-12"><p class="form-text mb-0" id="price-filter-help">Combina los filtros que necesites. Para buscar por precio, selecciona soles o dólares. Los límites del rango están incluidos.</p></div>
  </div>
</form>
<?php if ($filterErrors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($filterErrors as $filterError): ?><li><?= e($filterError) ?></li><?php endforeach; ?></ul></div>
<?php elseif (!$properties): ?>
<div class="card card-body text-center py-5"><h2 class="h5">No se encontraron propiedades</h2><p class="text-muted mb-0">Registra tu primer inmueble o modifica la búsqueda.</p></div>
<?php else: ?>
<p class="text-muted small mb-3" role="status"><?= count($properties) ?> propiedad(es) mostradas con los filtros actuales.</p>
<div class="card shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th scope="col">N.º</th><th>Propiedad</th><th>Modalidad</th><th>Precio</th><th>Disponibilidad</th><th>Publicación</th><th><span class="visually-hidden">Acciones</span></th></tr></thead><tbody>
<?php $rowNumber = 0; foreach ($properties as $item): ?>
<tr><th scope="row" class="text-muted text-center"><?= ++$rowNumber ?></th><td><?php if ($item['foto']): ?><img src="<?= BASE_URL ?>/<?= e($item['foto']) ?>" alt="Fotografía principal" width="80" height="60" style="object-fit:cover" class="rounded mb-2 d-block"><?php endif; ?><strong><?= e($item['titulo']) ?></strong><div class="text-muted small"><?= e($item['tipo']) ?></div><div class="small text-muted"><?= e(implode(', ', array_filter([$item['distrito'], $item['departamento']]))) ?></div></td><td><?= e($item['modalidad']) ?></td><td class="text-nowrap"><?= e($item['moneda']) ?> <?= number_format((float)$item['precio'], 2) ?></td><td><?= e(ucfirst($item['disponibilidad'])) ?></td><td><span class="badge <?= $item['publicado'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $item['publicado'] ? 'Publicada' : 'Borrador' ?></span></td><td><a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/properties/edit?id=<?= (int)$item['id'] ?>">Editar</a> <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/properties/documents?property_id=<?= (int)$item['id'] ?>">Documentos</a> <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/reservations?propiedad_id=<?= (int)$item['id'] ?>">Ver reservas</a><form class="d-inline-block ms-1" method="post" action="<?= BASE_URL ?>/properties/delete?id=<?= (int)$item['id'] ?>" onsubmit="return confirm('¿Eliminar esta propiedad del listado? También dejará de estar marcada para publicación.')"><?= Csrf::field() ?><button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Eliminar <?= e($item['titulo']) ?>">Eliminar</button></form></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<p class="text-muted small mt-2">Se muestran hasta 100 propiedades. Los vendedores solo ven sus propios registros.</p>
<?php endif; ?>




