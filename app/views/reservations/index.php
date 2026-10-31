<h1 class="h3">Reservas de propiedades</h1>
<p class="text-muted">Solicita una reserva desde CRM → Gestionar consulta → Solicitar reserva. Una solicitud pendiente no bloquea el inmueble; la aprobación sí.</p>
<div class="alert alert-info">Para eliminar una propiedad con reserva aprobada, un administrador debe elegir <strong>Modificar estado → Cancelar</strong> e indicar el motivo. Luego puede volver a Propiedades y eliminarla del listado. Las operaciones cerradas conservan su historial y no se pueden cancelar.</div>
<form class="d-flex gap-2 mb-4" action="<?= BASE_URL ?>/reservations" method="get">
  <?php if ($propertyId!==null): ?><input type="hidden" name="propiedad_id" value="<?= (int)$propertyId ?>"><?php endif; ?>
  <label class="visually-hidden" for="estado">Estado</label>
  <select class="form-select" id="estado" name="estado"><option value="">Todos los estados</option><?php foreach (Reservation::STATES as $value=>$label): ?><option value="<?= e($value) ?>" <?= $state===$value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
  <button class="btn btn-primary">Filtrar</button>
</form>
<?php if ($propertyId!==null): ?><p class="small text-muted">Reservas de la propiedad #<?= (int)$propertyId ?>. <a href="<?= BASE_URL ?>/reservations">Ver todas las reservas</a></p><?php endif; ?>
<?php if (!$rows): ?>
<div class="card card-body text-center py-5"><h2 class="h5">No hay reservas para mostrar</h2><a href="<?= BASE_URL ?>/crm">Ir al CRM</a></div>
<?php else: ?>
<div class="card table-responsive"><table class="table align-middle mb-0">
<thead><tr><th scope="col">N.º</th><th>Cliente / Inmueble</th><th>Vendedor</th><th>Monto</th><th>Estado</th><th>Acciones</th></tr></thead>
<tbody><?php $rowNumber=0; foreach ($rows as $item):
  $admin=in_array($user['rol'],['superadmin','admin'],true);
  $canModify=($item['estado_reserva']==='pendiente' && ($admin || (int)$item['vendedor_id']===(int)$user['id'])) || ($item['estado_reserva']==='aprobada' && $admin);
?>
<tr><th scope="row" class="text-muted text-center"><?= ++$rowNumber ?></th><td><?= e($item['cliente']) ?><div class="small text-muted"><?= e($item['titulo']) ?></div></td><td><?= e($item['vendedor']) ?></td><td><?= e($item['moneda']) ?> <?= number_format((float)$item['monto_reserva'],0) ?></td><td><span class="badge text-bg-info"><?= e(Reservation::STATES[$item['estado_reserva']]) ?></span></td><td>
  <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/reservations/show?id=<?= (int)$item['id'] ?>">Ver detalle</a>
  <a class="btn btn-sm btn-primary" href="<?= BASE_URL ?>/reservations/show?id=<?= (int)$item['id'] ?>#modificar-estado">Modificar estado</a>
  <?php if (!$canModify && $item['estado_reserva']==='aprobada'): ?><span class="small text-muted d-block mt-1">Solo un administrador puede cancelar esta reserva.</span><?php endif; ?>
</td></tr><?php endforeach; ?></tbody></table></div>
<p class="small text-muted mt-2">Hasta 100 reservas, de más reciente a más antigua. Cada vendedor ve las reservas a su nombre.</p>
<?php endif; ?>
