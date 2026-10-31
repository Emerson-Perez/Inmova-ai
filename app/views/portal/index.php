<section class="portal-hero">
  <p class="eyebrow">Espacios para tu siguiente capítulo</p>
  <h1>Encuentra tu próximo inmueble</h1>
  <p>Un nuevo hogar. Un lugar para crecer. Explora propiedades y descubre el espacio que va contigo.</p>
  <a class="btn btn-primary" href="<?= BASE_URL ?>/assistant">Ayúdame a encontrar un inmueble<svg class="ui-icon" aria-hidden="true"><use href="#i-arrow"/></svg></a>
</section>
<form class="card card-body shadow-sm mb-4" method="get" action="<?= BASE_URL ?>/portal">
  <div class="row g-3">
    <div class="col-lg-6"><label class="form-label" for="q">Título o ubicación</label><input class="form-control" id="q" name="q" maxlength="160" placeholder="Ej. Miraflores, Lima, casa…" value="<?= e($filters['q']) ?>"></div>
    <?php foreach (['tipo_id'=>['Tipo',$types], 'modalidad_id'=>['Modalidad',$modes]] as $key=>[$label,$options]): ?>
    <div class="col-md-6 col-lg-3"><label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label><select class="form-select" id="<?= e($key) ?>" name="<?= e($key) ?>"><option value="">Todas las opciones</option><?php foreach ($options as $option): ?><option value="<?= (int)$option['id'] ?>" <?= $filters[$key] === (string)$option['id'] ? 'selected' : '' ?>><?= e($option['nombre']) ?></option><?php endforeach; ?></select></div>
    <?php endforeach; ?>
    <div class="col-md-4"><label class="form-label" for="moneda">Moneda</label><select class="form-select" id="moneda" name="moneda"><option value="">Todas</option><?php foreach (['PEN'=>'Soles (PEN)','USD'=>'Dólares (USD)'] as $key=>$label): ?><option value="<?= e($key) ?>" <?= $filters['moneda'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <?php foreach (['min'=>'Precio mínimo','max'=>'Precio máximo'] as $key=>$label): ?><div class="col-md-4"><label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label><input class="form-control" id="<?= e($key) ?>" name="<?= e($key) ?>" type="number" min="0" max="5000000" step="1" value="<?= e($filters[$key]) ?>"></div><?php endforeach; ?>
    <div class="col-12"><p class="small text-muted">Selecciona una moneda si deseas filtrar por precio.</p><button class="btn btn-primary">Buscar inmuebles</button> <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/portal">Limpiar filtros</a></div>
  </div>
</form>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php else: ?>
<p class="text-muted"><?= (int)$total ?> inmueble(s) encontrado(s)</p>
<?php if (!$properties): ?><div class="card card-body text-center py-5"><h2 class="h4">No hay inmuebles para esta búsqueda</h2><p class="text-muted mb-0">Prueba otros filtros o vuelve más adelante.</p></div><?php endif; ?>
<div class="row g-4">
<?php foreach ($properties as $item): ?>
  <article class="col-md-6 col-lg-4"><div class="card h-100 shadow-sm border-0">
    <?php if ($item['foto']): ?><img src="<?= BASE_URL ?>/<?= e($item['foto']) ?>" class="card-img-top" style="height:230px;object-fit:cover" alt="<?= e($item['titulo']) ?>" loading="lazy"><?php else: ?><div class="bg-secondary-subtle text-secondary d-flex align-items-center justify-content-center" style="height:230px">Fotografías próximamente</div><?php endif; ?>
    <div class="card-body d-flex flex-column"><div class="d-flex justify-content-between gap-2 mb-2"><span class="badge text-bg-light"><?= e($item['tipo']) ?></span><span class="badge text-bg-secondary"><?= e(ucfirst($item['disponibilidad'])) ?></span></div><p class="small text-muted mb-1"><?= e($item['modalidad']) ?></p><h2 class="h5"><?= e($item['titulo']) ?></h2><p class="text-muted small"><?= e(implode(', ',array_filter([$item['distrito'],$item['departamento']]))) ?: 'Ubicación por confirmar' ?></p><p class="h4"><?= e($item['moneda']) ?> <?= number_format((float)$item['precio'],0,'.',',') ?></p><p class="small text-muted"><?= $item['area_m2'] !== null ? e($item['area_m2']).' m² · ' : '' ?><?= $item['dormitorios'] !== null ? (int)$item['dormitorios'].' dorm. · ' : '' ?><?= $item['banos'] !== null ? (int)$item['banos'].' baños' : '' ?></p><a class="btn btn-outline-primary mt-auto" href="<?= BASE_URL ?>/portal/property?id=<?= (int)$item['id'] ?>">Ver inmueble</a></div>
  </div></article>
<?php endforeach; ?>
</div>
<?php if ($pages > 1): ?><nav class="d-flex justify-content-center align-items-center gap-3 mt-4" aria-label="Paginación"><?php if ($page > 1): ?><a class="btn btn-outline-primary" href="<?= BASE_URL ?>/portal?<?= e(http_build_query($filters + ['page'=>$page-1])) ?>">Anterior</a><?php endif; ?><span>Página <?= (int)$page ?> de <?= (int)$pages ?></span><?php if ($page < $pages): ?><a class="btn btn-outline-primary" href="<?= BASE_URL ?>/portal?<?= e(http_build_query($filters + ['page'=>$page+1])) ?>">Siguiente</a><?php endif; ?></nav><?php endif; ?>
<?php endif; ?>
