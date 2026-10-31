<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>No se pudo completar la solicitud · INMOVA AI</title>
<style>body{margin:0;background:#f1f5fc;color:#172b4d;font:16px/1.65 system-ui,sans-serif;min-height:100vh;display:grid;place-items:center}main{max-width:540px;margin:24px;padding:36px;background:white;border:1px solid #dce5f3;border-radius:20px}h1{font-size:26px;line-height:1.3}a{display:inline-block;padding:12px 20px;background:#1d4ed8;color:white;border-radius:10px;text-decoration:none}a:focus-visible{outline:3px solid #0e7490;outline-offset:4px}.reference{font-size:14px;color:#54657d;overflow-wrap:anywhere}.diag-box{margin-top:16px;margin-bottom:20px;padding:12px;background:#fef2f2;border:1px solid #f87171;border-radius:10px;font-size:13px;color:#991b1b;font-family:monospace;word-break:break-all}</style></head>
<body><main><p>INMOVA AI</p><h1>No pudimos completar la solicitud</h1><p>Vuelve al panel y revisa el estado de tu operación. Si el problema continúa, comparte esta referencia con el administrador.</p>
<?php if (isset($error) && $error instanceof Throwable): ?>
<div class="diag-box">
  <strong>Diagnóstico:</strong> <?= htmlspecialchars($error->getMessage()) ?><br>
  <strong>Archivo:</strong> <?= htmlspecialchars(basename($error->getFile()) . ':' . $error->getLine()) ?>
</div>
<?php endif; ?>
<p class="reference" data-error-reference="<?= $reference ?>">Referencia: <strong><?= $reference ?></strong></p><a href="<?= $home ?>">Volver al panel</a></main></body></html>
