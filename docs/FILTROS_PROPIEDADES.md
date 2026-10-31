# Filtros del inventario

En **Propiedades**, combina título, modalidad, disponibilidad, publicación y moneda. El rango de precio admite mínimo, máximo o ambos, e incluye sus extremos. Para filtrar por precio debes elegir PEN o USD; no se convierten ni mezclan monedas. Se admiten precios enteros de 0 a 5 000 000, como en el portal público.

**Buscar propiedades** aplica los criterios y conserva los valores elegidos en el formulario y en la URL. **Limpiar** vuelve al inventario sin filtros. Publicación distingue **Todas**, **Publicada** y **Borrador**. Los vendedores mantienen acceso exclusivamente a sus propias propiedades; solo se listan registros activos. La lista conserva el límite de 100 resultados y la cantidad mostrada no representa un total paginado.

No requiere migraciones ni cambios en datos existentes. Se implementa mediante consultas preparadas y validación en el servidor. Los parámetros inválidos devuelven HTTP 422 con un mensaje, sin ejecutar una búsqueda sin restricciones.

Verificación: `tests/property_filters.php` cubre 18 casos con registros temporales dentro de una transacción que se revierte al terminar. `tests/style_http.ps1` incluye selección persistente de filtros, publicación Borrador, limpieza y errores HTTP 422; su cuenta temporal se desactiva al finalizar.
