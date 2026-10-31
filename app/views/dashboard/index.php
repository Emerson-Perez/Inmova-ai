<?php
/**
 * Datos recibidos desde DashboardController y Controller::view().
 * @var array{nombre?: string, rol?: string}|null $user
 * @var array{totals: array<string, int>}|null $summary
 * @var array{from: string, to: string}|null $period
 * @var string|null $reportError
 * @var array<string, int>|null $pendingSummary
 * @var string|null $pendingError
 */
$user = ($user ?? []) + ['nombre'=>'Usuario','rol'=>''];
$summary = $summary ?? null;
$period = $period ?? null;
$reportError = $reportError ?? null;
$pendingSummary = $pendingSummary ?? null;
$pendingError = $pendingError ?? null;
$commercial = in_array($user['rol'], ['superadmin','admin','vendedor'], true);
?>
<section class="dashboard-hero" aria-labelledby="welcome-title">
  <div class="hero-copy"><span class="eyebrow">Tu espacio de trabajo</span><h1 id="welcome-title">Bienvenido, <?= e($user['nombre']) ?></h1><p><?= $commercial ? ($user['rol']==='vendedor' ? 'Resumen de tu actividad comercial. Cada conversación puede ser tu próxima gran oportunidad.' : 'Resumen de la actividad del equipo. Todo lo que necesitas para avanzar, en un solo lugar.') : 'Conecta con nuevas oportunidades y sigue desarrollando tu talento inmobiliario.' ?></p></div>
  <?php if ($commercial): ?><a class="btn btn-light hero-action" href="<?= BASE_URL ?>/properties/create"><svg class="ui-icon" aria-hidden="true"><use href="#i-plus"/></svg>Nueva propiedad</a><?php endif; ?>
</section>
<?php if ($commercial): ?>
<?php if ($reportError): ?><div class="alert alert-danger" role="alert"><?= e($reportError) ?></div><?php endif; ?>
<?php if ($summary && $period): ?>
<section aria-labelledby="activity-title">
  <div class="section-heading"><div><h2 id="activity-title">Actividad comercial</h2><p>Del <?= e($period['from']) ?> al <?= e($period['to']) ?> · Hora de Lima. Inventario actual.</p></div><a href="<?= BASE_URL ?>/reports">Ver reportes<svg class="ui-icon" aria-hidden="true"><use href="#i-arrow"/></svg></a></div>
  <div class="metric-grid">
  <?php $metricIcons=['home','users','calendar','bookmark','check']; $metricIndex=0; foreach ($summary['totals'] as $label=>$total): ?>
    <div class="card metric-card"><span class="metric-icon" aria-hidden="true"><svg class="ui-icon"><use href="#i-<?= e($metricIcons[$metricIndex % count($metricIcons)]) ?>"/></svg></span><strong class="metric-value"><?= (int)$total ?></strong><span class="metric-label"><?= e($label) ?></span></div>
  <?php $metricIndex++; endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php if ($pendingError): ?><div class="alert alert-danger" role="alert"><?= e($pendingError) ?></div><?php endif; ?>
<div class="dashboard-columns">
  <section class="card card-body" aria-labelledby="pending-title"><div class="section-heading"><div><h2 id="pending-title">Pendientes de atención</h2><p>Enfócate en tu siguiente paso.</p></div><a href="<?= BASE_URL ?>/tasks">Ver todos<svg class="ui-icon" aria-hidden="true"><use href="#i-arrow"/></svg></a></div>
  <?php if ($pendingSummary): ?>
    <div class="pending-list">
    <?php $pendingCards=[['overdue','Seguimientos vencidos','/tasks?filter=vencidas'],['today','Seguimientos para hoy','/tasks?filter=hoy'],['upcoming','Seguimientos después de hoy','/tasks?filter=proximas'],['visitsOverdue','Visitas por regularizar','/visits'],['visitsSoon','Visitas en próximos 7 días','/visits'],['reservations','Reservas pendientes','/reservations?estado=pendiente']];
    if ($user['rol']!=='vendedor') $pendingCards[]=['unassigned','Consultas sin vendedor','/tasks'];
    foreach ($pendingCards as [$key,$label,$path]): $pendingCount=(int)($pendingSummary[$key] ?? 0); ?>
      <a class="pending-item<?= $key==='overdue' && $pendingCount>0 ? ' is-urgent' : '' ?>" href="<?= BASE_URL ?><?= e($path) ?>"><strong class="pending-number"><?= $pendingCount ?></strong><span><?= e($label) ?></span><svg class="ui-icon" aria-hidden="true"><use href="#i-arrow"/></svg></a>
    <?php endforeach; ?>
    </div>
  <?php else: ?><p class="text-muted small mb-0">Abre el Centro de pendientes para consultar tus próximas acciones.</p><?php endif; ?>
  </section>
  <section class="card card-body" aria-labelledby="quick-title"><div class="section-heading"><div><h2 id="quick-title">Accesos rápidos</h2><p>Haz que las oportunidades avancen.</p></div></div>
  <?php foreach ([['home','Gestionar propiedades','Tu inventario, siempre al día.','/properties'],['users','Gestionar consultas','Conecta con tu próximo cliente.','/crm'],['trend','Ver oportunidades','Da seguimiento a cada negociación.','/opportunities']] as [$icon,$label,$description,$path]): ?>
    <a class="quick-action" href="<?= BASE_URL ?><?= e($path) ?>"><span aria-hidden="true"><svg class="ui-icon"><use href="#i-<?= e($icon) ?>"/></svg></span><span><strong><?= e($label) ?></strong><small><?= e($description) ?></small></span><svg class="ui-icon" aria-hidden="true"><use href="#i-arrow"/></svg></a>
  <?php endforeach; ?>
  </section>
</div>
<?php endif; ?>
<section class="explore-strip" aria-labelledby="explore-title"><div><h2 id="explore-title">Más posibilidades para seguir creciendo</h2><p>Descubre inmuebles, encuentra respuestas y fortalece tus habilidades.</p></div><div class="explore-links"><a class="btn btn-outline-primary" href="<?= BASE_URL ?>/portal">Portal público</a><a class="btn btn-outline-primary" href="<?= BASE_URL ?>/assistant">Asistente inmobiliario</a><a class="btn btn-outline-primary" href="<?= BASE_URL ?>/academy">Academia</a></div></section>
