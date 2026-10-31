<div class="login-shell">
  <section class="login-story" aria-labelledby="login-story-title"><div><span class="eyebrow">Bienvenido a INMOVA AI</span><h2 id="login-story-title">Grandes espacios.<br>Nuevas oportunidades.</h2><p>Gestiona tus propiedades, conecta con tus clientes y haz crecer tu negocio desde un solo lugar.</p></div><div class="login-story-footer">Tu talento. Nuestra tecnología. Más posibilidades.</div></section>
  <section class="login-form-panel" aria-labelledby="login-title">
    <span class="eyebrow text-muted mb-3">Tu workspace inmobiliario</span>
    <h1 id="login-title" class="mb-2">Qué bueno verte de nuevo.</h1>
    <p class="text-muted small mb-4">Ingresa a tu cuenta para continuar.</p>
    <?php if (!empty($error)): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= BASE_URL ?>/login" novalidate>
      <?= Csrf::field() ?>
      <div class="mb-3"><label class="form-label" for="email">Correo electrónico</label><input class="form-control" type="email" id="email" name="email" autocomplete="username" placeholder="nombre@empresa.com" required autofocus></div>
      <div class="mb-4"><label class="form-label" for="password">Contraseña</label><input class="form-control" type="password" id="password" name="password" autocomplete="current-password" placeholder="Ingresa tu contraseña" required></div>
      <button class="btn btn-primary w-100" type="submit">Ingresar a mi cuenta<svg class="ui-icon" aria-hidden="true"><use href="#i-arrow"/></svg></button>
    </form>
    <p class="login-note"><svg class="ui-icon" aria-hidden="true"><use href="#i-lock"/></svg>Acceso privado a tu plataforma</p>
  </section>
</div>
