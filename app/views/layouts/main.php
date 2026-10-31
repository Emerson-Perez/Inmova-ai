<?php
/** @var string $content */
/** @var array|null $user */
$user = $user ?? null;
$route = parse_url($_SERVER['REQUEST_URI'] ?? BASE_URL.'/dashboard', PHP_URL_PATH) ?: '';
$route = substr($route, strlen(BASE_URL)) ?: '/';
$isCommercial = $user && in_array($user['rol'], ['superadmin','admin','vendedor'], true);
$roleLabels = ['superadmin'=>'Superadministrador','admin'=>'Administrador','vendedor'=>'Vendedor','instructor'=>'Instructor','practicante'=>'Practicante','cliente'=>'Cliente'];
$navigation = [['Workspace', [['/dashboard','Vista general','grid']]]];
if ($isCommercial) $navigation[] = ['Gestión comercial', [
    ['/properties','Propiedades','home'], ['/crm','Consultas CRM','users'],
    ['/opportunities','Oportunidades','trend'], ['/visits','Agenda de visitas','calendar'],
    ['/reservations','Reservas','bookmark'], ['/closings','Cierres','check'],
    ['/reports','Reportes','chart'], ['/tasks','Pendientes','task'],
]];
$learning = [['/assistant','Asistente IA','spark'], ['/academy','Academia','book']];
if ($user && Simulation::allowed($user)) $learning[] = ['/simulations','Simulador de ventas','play'];
$learning[] = ['/academy/certificates','Mis certificados','award'];
$navigation[] = ['Aprendizaje e IA', $learning];
if ($user && in_array($user['rol'], ['superadmin','admin'], true)) $navigation[] = ['Administración', [['/users','Usuarios y accesos','users']]];
$styleVersion = (string)filemtime(__DIR__.'/../../../public/assets/css/app.css');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#12337c">
  <title><?= e($title ?? 'INMOVA AI') ?> · INMOVA AI</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/app.css?v=<?= e($styleVersion) ?>" rel="stylesheet">
  <link href="<?= BASE_URL ?>/assets/css/theme-vibrant.css?v=<?= e((string)filemtime(__DIR__.'/../../../public/assets/css/theme-vibrant.css')) ?>" rel="stylesheet">
  <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= e((string)filemtime(__DIR__.'/../../../public/assets/js/app.js')) ?>" defer></script>
</head>
<body class="<?= $user ? 'app-authenticated' : 'app-public' ?><?= $route==='/login' ? ' is-login' : '' ?>">
<?php require __DIR__.'/symbols.php'; ?>
<a class="skip-link" href="#main-content">Saltar al contenido</a>
<?php if ($user): ?>
<button class="nav-overlay" id="nav-overlay" type="button" aria-label="Cerrar menú" tabindex="-1"></button>
<aside class="app-sidebar" id="app-sidebar" aria-label="Menú principal">
  <div class="sidebar-brand-row">
    <a class="app-brand" href="<?= BASE_URL ?>/dashboard" aria-label="INMOVA AI · Panel principal">
      <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M6 25V13l10-7 10 7v12M12 25V15h8v10M3 25h26"/></svg></span>
      <span>inmova<span class="brand-ai">ai</span><small>REAL ESTATE WORKSPACE</small></span>
    </a>
    <button type="button" class="sidebar-close" id="nav-close" aria-label="Cerrar menú"><svg class="ui-icon"><use href="#i-close"/></svg></button>
  </div>
  <nav class="sidebar-navigation" aria-label="Módulos">
  <?php foreach ($navigation as [$group,$links]): ?>
    <div class="nav-group"><p class="nav-group-label"><?= e($group) ?></p>
    <?php foreach ($links as [$href,$label,$icon]):
        $active = $route===$href || ($href!=='/dashboard' && str_starts_with($route,$href.'/'));
        if ($href==='/academy' && str_starts_with($route,'/academy/certificate')) $active=false;
        if ($href==='/academy/certificates' && $route==='/academy/certificate') $active=true;
    ?>
      <a class="sidebar-link<?= $active ? ' is-active' : '' ?>" href="<?= BASE_URL ?><?= e($href) ?>"<?= $active ? ' aria-current="page"' : '' ?>><svg class="ui-icon" aria-hidden="true"><use href="#i-<?= e($icon) ?>"/></svg><span><?= e($label) ?></span><?php if ($href==='/assistant'): ?><span class="nav-mini-badge">IA</span><?php endif; ?></a>
    <?php endforeach; ?></div>
  <?php endforeach; ?>
  </nav>
  <div class="sidebar-bottom"><a class="sidebar-link" href="<?= BASE_URL ?>/portal"><svg class="ui-icon" aria-hidden="true"><use href="#i-globe"/></svg>Explorar portal<svg class="ui-icon ms-auto" aria-hidden="true"><use href="#i-arrow"/></svg></a><div class="sidebar-signature">Conecta espacios. Crea oportunidades.</div></div>
</aside>
<div class="app-workspace">
  <header class="app-topbar">
    <div class="d-flex align-items-center gap-3"><button class="mobile-toggle" id="nav-toggle" type="button" aria-controls="app-sidebar" aria-expanded="false" aria-label="Abrir menú"><svg class="ui-icon" aria-hidden="true"><use href="#i-menu"/></svg></button><div class="topbar-context"><span class="topbar-eyebrow">Tu plataforma inmobiliaria</span><span class="topbar-title"><?= e($title ?? 'Panel principal') ?></span></div></div>
    <div class="topbar-actions">
      <a class="topbar-portal" href="<?= BASE_URL ?>/portal"><svg class="ui-icon" aria-hidden="true"><use href="#i-globe"/></svg>Ver portal</a>
      <details class="account-menu"><summary><span class="user-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['nombre'],0,1))) ?></span><span class="account-name"><?= e($user['nombre']) ?><small><?= e($roleLabels[$user['rol']] ?? $user['rol']) ?></small></span><span class="account-chevron" aria-hidden="true">⌄</span></summary><div class="account-dropdown"><a href="<?= BASE_URL ?>/account/password"><svg class="ui-icon" aria-hidden="true"><use href="#i-lock"/></svg>Mi contraseña</a><form method="post" action="<?= BASE_URL ?>/logout"><?= Csrf::field() ?><button type="submit"><svg class="ui-icon" aria-hidden="true"><use href="#i-exit"/></svg>Cerrar sesión</button></form></div></details>
    </div>
  </header>
<?php else: ?>
<header class="public-header"><div class="public-header-inner">
  <a class="app-brand" href="<?= BASE_URL ?>/portal" aria-label="INMOVA AI · Inicio"><span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M6 25V13l10-7 10 7v12M12 25V15h8v10M3 25h26"/></svg></span><span>inmova<span class="brand-ai">ai</span></span></a>
  <nav class="public-navigation" aria-label="Navegación pública"><a href="<?= BASE_URL ?>/portal"<?= in_array($route,['/','/portal'],true) ? ' aria-current="page"' : '' ?>>Propiedades</a><a href="<?= BASE_URL ?>/assistant">Asistente IA</a><a class="btn btn-primary" href="<?= BASE_URL ?>/login">Acceso al sistema<svg class="ui-icon" aria-hidden="true"><use href="#i-arrow"/></svg></a></nav>
</div></header>
<?php endif; ?>
<main id="main-content" class="<?= $user ? 'app-content' : 'public-content' ?>" tabindex="-1"><?= $content ?></main>
<footer class="app-footer"><span>INMOVA AI <span aria-hidden="true">·</span> Tu siguiente oportunidad empieza aquí.</span><span>Gestión inmobiliaria inteligente</span></footer>
<?php if ($user): ?></div><?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
