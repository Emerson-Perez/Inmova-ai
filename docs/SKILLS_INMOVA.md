# Skills de desarrollo de INMOVA

Se seleccionaron diecisiete skills de `sickn33/agentic-awesome-skills`, con alcance local al proyecto en `.agents/skills/`. La revisión exacta de origen y los nombres se registran en `docs/skills-installation.json`. El campo `revision` es la revisión base; `skill_revisions` fija explícitamente la revisión de las incorporaciones posteriores, sin actualizar las skills anteriores.

## Uso

Codex puede seleccionar una skill por su descripción. También puedes solicitarla por nombre, por ejemplo:

- «Usa php-pro para revisar el manejo de errores del módulo de propiedades».
- «Usa software-architecture y database-design para proponer el flujo de comisiones».
- «Usa backend-security-coder para revisar permisos de descarga de documentos».
- «Usa systematic-debugging y test-driven-development para reproducir y corregir este fallo de reservas».
- «Usa webapp-testing para verificar el recorrido comercial en navegador con datos aislados».
- «Usa verification-before-completion para verificar el cambio y entregar evidencias».
- «Usa ui-ux-pro-max para mejorar los formularios del CRM manteniendo Bootstrap».
- «Usa accessibility-compliance-accessibility-audit para revisar teclado, foco y errores de formularios».
- «Usa sql-optimization-patterns para medir las consultas de reportes antes de proponer índices».
- «Usa deployment-procedures para preparar un plan de despliegue PHP/Apache/MySQL y reversión».
- «Usa llm-evaluation para definir casos de evaluación del asistente con datos ficticios».
- «Usa rag-implementation para diseñar búsqueda documental con permisos por usuario».
- «Usa security-auditor para auditar permisos de CRM y documentos dentro del entorno local».
- «Usa error-handling-patterns para mejorar errores de formularios y fallos del proveedor IA sin revelar información privada».
- «Usa code-review-excellence para revisar estos cambios, priorizando fallos de negocio y regresiones».

Las reglas específicas de INMOVA y la selección según tarea están en `AGENTS.md`. No es necesario cargar las diecisiete skills para cada cambio. La auditoría se complementa con `backend-security-coder` para implementar las correcciones; la revisión de código presenta hallazgos y no sustituye las pruebas.

## Alcance

Esta instalación configura herramientas de trabajo del agente. No modifica el comportamiento de la aplicación ni añade funciones al asistente inmobiliario. Los scripts y archivos de apoyo se conservan para poder consultar las referencias de cada skill; no se ejecutan automáticamente al instalarlas.

Los ejemplos externos deben adaptarse a PHP/PDO/MySQL y Windows/XAMPP. Playwright necesita sus dependencias antes de ejecutar pruebas de navegador; instalar la skill no instala navegadores ni paquetes Python.

La revisión importada contiene referencias opcionales que no siempre vienen incluidas: por ejemplo, `php-pro` menciona `resources/implementation-playbook.md`, pero su carpeta solo contiene `SKILL.md`. No asumir que una referencia mencionada existe; comprobarla antes de usarla. Se conservó el contenido de origen sin inventar los archivos ausentes.

## Verificación de instalación

`tests/verify_skills.py` comprueba los diecisiete manifiestos, sus nombres y descripciones y la revisión fijada para cada skill. Genera `output/audit/skills-inventory.json` con la lista de archivos y sus SHA-256. La instalación inicial de ocho skills encontró 29 archivos; el inventario generado refleja el conjunto actual. La verificación no ejecuta scripts de terceros ni certifica su comportamiento. Las nuevas skills estarán disponibles para Codex en el siguiente turno.

La descarga no instala dependencias de diseño, evaluación, embeddings ni bases vectoriales. `rag-implementation` referencia otras skills como parte de su metodología; esas referencias no se descargan automáticamente y no se debe asumir que están instaladas. Mantener los permisos existentes sobre los documentos antes de cualquier proyecto RAG.

## Mantenimiento

Antes de actualizar, comparar la revisión fijada en el manifiesto con la nueva y revisar cambios de instrucciones y scripts. Conservar las instrucciones propias de INMOVA fuera de las carpetas de terceros. No ejecutar actualizaciones automáticas sobre las skills.
