# Diagnóstico de acceso al asistente IA — 28/10/2026

La comprobación explícita de metadatos del modelo configurado devolvió HTTP 401 y el código `invalid_api_key`. La configuración estaba habilitada y PHP tenía cURL disponible. Se utilizó la credencial existente exclusivamente para autenticar una consulta GET de metadatos a OpenAI; no se imprimió ni se registró su valor, no se envió una conversación y no se generó una respuesta de modelo.

## Acción pendiente del administrador

En **Asistente → Configurar IA**, reemplazar la clave por una válida del proyecto de OpenAI y guardar. No compartirla por chat, capturas ni archivos de diagnóstico. No se modificó automáticamente la clave ni el modelo. El acceso real del modelo y su compatibilidad con Responses API siguen pendientes de validación tras corregir la credencial.

`C:\xampp\php\php.exe app/cli/diagnose-ai.php` permite repetir de forma explícita la consulta de metadatos. Un resultado 200 solo demuestra acceso a esos metadatos; no acredita permisos de generación, saldo ni compatibilidad de salidas estructuradas. El script no forma parte de las suites automáticas y solo admite ejecución CLI.

## Corrección del cliente

`AiClient::decodeHttpResponse` diferencia autenticación, permisos, modelo no disponible, parámetros incompatibles, cuota/créditos y límites temporales. Los mensajes se generan con textos propios; no se devuelven cuerpos ni mensajes privados del proveedor. La configuración incluye instrucciones para reemplazar una clave rechazada.

Verificación: 21 comprobaciones de errores HTTP simulados, 24 del asistente y 26 del simulador aprobadas. Estas suites no hicieron llamadas reales ni cambiaron credenciales. También se verificó la sintaxis de los archivos modificados.

La consulta local de departamentos en Santa Anita devolvió cero coincidencias activas, publicadas y disponibles al momento del diagnóstico. El inventario puede cambiar; este resultado no causa el 401. Mientras se corrige la credencial se puede usar la búsqueda guiada sin IA.

Referencia: https://developers.openai.com/api/docs/guides/error-codes
