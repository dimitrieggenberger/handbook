# Glosario institucional y ciclo de vida del contenido

**Estado:** propuesta para la siguiente fase de implementación  
**Componente:** `local_handbook`  
**Documento relacionado:** `docs/HANDBOOK_AI_EDITORIAL_CAPABILITIES.md`  
**Referencias:** `docs/SPECIFICATION.md`, `docs/EDITORIAL_CONVENTIONS.md`

## 1. Propósito

Este documento define dos ampliaciones relacionadas:

1. un glosario institucional estructurado e integrado en la lectura y redacción del Manual Institucional;
2. un ciclo de vida completo para páginas nuevas, páginas archivadas y restauraciones.

Ambas ampliaciones deben respetar el mismo límite de autoridad: Handbook AI puede preparar propuestas dentro de un change set, pero únicamente una persona autorizada puede revisarlas, aprobarlas y publicarlas desde Moodle.

## 2. Decisiones técnicas aprobadas

### 2.1 Marcado dinámico del glosario

Los términos del glosario deben marcarse durante la visualización del artículo. El plugin no debe insertar automáticamente etiquetas permanentes dentro del HTML almacenado en cada revisión.

Esto permite:

- actualizar una definición en un solo lugar;
- reflejar el cambio en todos los artículos aplicables;
- mantener limpio el HTML editorial;
- activar o desactivar el marcado sin crear revisiones artificiales;
- aplicar reglas diferentes según idioma, alcance, audiencia o página;
- evitar inconsistencias entre etiquetas almacenadas y definiciones vigentes.

### 2.2 Archivado como propuesta versionada

Handbook AI no puede cambiar directamente el campo `archived` de una página publicada.

El archivado se representa como un ítem dentro de un change set. La propuesta debe someterse al flujo humano de revisión, aprobación y publicación. Al publicarse, el sistema aplica el cambio de estado y las redirecciones aprobadas dentro de una transacción.

El archivado no equivale a eliminación.

## 3. El glosario como entidad propia del plugin

El glosario no debe implementarse únicamente como una página ordinaria. Debe constituir una entidad estructurada con:

- identificadores estables;
- definiciones breves y completas;
- alias y términos desplazados;
- idioma y alcance;
- área responsable;
- vínculos con páginas canónicas;
- historial de revisiones;
- flujo editorial;
- auditoría;
- acceso mediante API y MCP.

Una página pública del glosario puede presentar estos datos, pero no debe ser la fuente estructural primaria.

## 4. Funciones del glosario

El glosario debe servir para:

- establecer vocabulario institucional preferido;
- explicar términos sin obligar al lector a salir del artículo;
- enlazar términos con artículos canónicos;
- detectar terminología obsoleta o desplazada;
- detectar definiciones duplicadas o contradictorias;
- apoyar a autores humanos y agentes de redacción;
- analizar el impacto de cambiar una definición;
- facilitar normalizaciones coordinadas en varios artículos.

## 5. Modelo de un término

Cada término debe incluir, como mínimo:

```text
id
term
normalizedterm
shortdefinition
fulldefinition
language
scope
categoryid
pageid
linkedpageid
linkedanchor
responsibleareakey
owneruserid
reviewdate
status
casesensitive
matchwholeword
displaytooltip
displaylink
sortorder
timecreated
timemodified
createdby
modifiedby
```

### 5.1 Definición breve

La definición breve aparece en el tooltip o popover. Debe:

- ser comprensible por sí sola;
- ser concisa;
- evitar reproducir artículos completos;
- utilizar la terminología institucional vigente.

### 5.2 Definición completa

La definición completa puede incluir:

- alcance;
- ejemplos;
- exclusiones;
- términos relacionados;
- contexto institucional;
- notas de uso;
- enlaces hacia artículos, procedimientos o descripciones de función.

## 6. Alcance de los términos

El campo `scope` debe admitir al menos:

```text
manual
category
page
```

### 6.1 Alcance manual

El término se aplica en todo el Manual Institucional.

Ejemplos:

- estudiante;
- parcial;
- Gerencia Académica;
- modalidad híbrida;
- calificación.

### 6.2 Alcance categoría

El término se aplica dentro de una categoría o área temática determinada.

### 6.3 Alcance página

El término tiene una definición específica dentro de una página. Este alcance debe usarse solamente cuando no exista una definición institucional general suficiente.

Las reglas de precedencia deben impedir que una definición de página contradiga silenciosamente una definición general.

## 7. Alias y términos desplazados

Tabla sugerida:

```text
local_handbook_glossary_alias
- id
- termid
- alias
- normalizedalias
- aliastype
- casesensitive
- timecreated
- createdby
```

Tipos de alias:

```text
synonym
abbreviation
acronym
plural
former_term
misspelling
translation
```

Un alias `former_term` permite detectar expresiones desplazadas, por ejemplo:

- Rectorado;
- Coordinación de Convivencia;
- bimestre;
- alumno.

El sistema puede mostrar advertencias editoriales sin alterar automáticamente el contenido publicado.

## 8. Vínculo con un artículo canónico

Un término puede relacionarse con una página mediante `linkedpageid` y, opcionalmente, `linkedanchor`.

Ejemplos:

```text
Gerencia Académica
→ Estructura institucional

falta grave
→ Política de convivencia escolar

modalidad homeschool
→ Modalidad homeschool

evaluación formativa
→ Evaluación del aprendizaje
```

Cuando exista una página vinculada:

1. el término muestra la definición breve;
2. el término funciona como enlace;
3. el enlace conduce al artículo canónico;
4. puede abrir la sección exacta mediante un ancla estable;
5. el componente puede mostrar la acción “Ver artículo completo”.

No todos los términos requieren una página propia.

## 9. Presentación visual en artículos

El estilo debe ser discreto y consistente con Moodle y el tema institucional.

Se recomienda:

- subrayado punteado o indicador visual equivalente;
- cursor informativo;
- tooltip o popover accesible;
- enlace reconocible cuando exista artículo canónico;
- soporte para teclado y dispositivos táctiles.

Ejemplo conceptual:

```html
<a
  class="local-handbook-glossary-term"
  href="/local/handbook/view.php?page=estructura-institucional"
  data-termid="42"
  aria-describedby="local-handbook-glossary-definition-42">
  Gerencia Académica
</a>
```

No debe dependerse exclusivamente del atributo HTML `title`.

## 10. Accesibilidad

La definición debe estar disponible mediante:

- mouse;
- teclado;
- lector de pantalla;
- toque en móvil o tableta;
- enlace convencional;
- página general del glosario.

El componente debe:

- poder recibir foco;
- indicar que contiene una definición;
- mantener contraste suficiente;
- permitir cerrar el popover;
- no bloquear la navegación;
- conservar un enlace funcional cuando JavaScript no esté disponible.

Los tooltips deben contener solamente definiciones breves. El contenido extenso debe abrirse en una página o panel accesible.

## 11. Reglas de detección

El motor de marcado no debe procesar coincidencias dentro de:

- enlaces existentes;
- botones;
- campos de formulario;
- bloques de código;
- elementos `pre`, `code`, `script` o `style`;
- atributos HTML;
- texto alternativo;
- contenido oculto;
- elementos ya marcados como términos;
- componentes interactivos donde el marcado alteraría el comportamiento.

Debe admitir:

- palabra completa;
- sensibilidad opcional a mayúsculas;
- alias;
- prioridad entre términos superpuestos;
- exclusiones por página;
- exclusiones por fragmento;
- límites de repetición.

Ejemplo de superposición:

```text
Coordinación
Coordinación Académica
```

Debe prevalecer la coincidencia específica más larga cuando ambas sean válidas.

## 12. Frecuencia del marcado

Marcar todas las apariciones puede sobrecargar visualmente el artículo. Deben admitirse las opciones:

```text
first_in_page
first_in_section
all_occurrences
manual_only
```

Configuración institucional recomendada:

- primera aparición en cada sección;
- evitar varias apariciones idénticas en un mismo párrafo;
- permitir que el autor excluya una coincidencia concreta;
- permitir marcado manual cuando el término sea ambiguo.

## 13. Procesamiento y rendimiento

El marcado dinámico debe aplicarse al HTML renderizado mediante un filtro o servicio de presentación controlado por el plugin.

Debe considerarse:

- caché por combinación de revisión, idioma y versión del glosario;
- invalidación de caché al publicar un término;
- procesamiento del DOM en lugar de sustituciones globales inseguras;
- protección contra HTML inválido;
- límites para artículos extensos;
- pruebas de rendimiento con el corpus completo.

No se recomienda aplicar expresiones regulares directamente sobre todo el HTML sin analizar la estructura DOM.

## 14. Página general del glosario

El plugin debe ofrecer una vista navegable con:

- búsqueda;
- índice alfabético;
- filtros por área responsable;
- categoría;
- idioma;
- términos vigentes;
- términos desplazados;
- términos vinculados a páginas;
- fecha de revisión.

Cada entrada mostrará:

- término preferido;
- definición breve;
- definición completa;
- alias;
- formas desplazadas;
- artículo canónico;
- área responsable;
- fecha de revisión;
- términos relacionados.

## 15. Flujo editorial del glosario

Los términos deben contar con revisiones. Tabla sugerida:

```text
local_handbook_glossary_revision
- id
- termid
- status
- baserevisionid
- proposeddatajson
- changesummary
- timecreated
- timemodified
- createdby
- modifiedby
- reviewedby
- approvedby
- publishedby
- timeapproved
- timepublished
```

Tipos de propuesta dentro de un change set:

```text
glossary_term_create
glossary_term_update
glossary_term_archive
glossary_alias_add
glossary_alias_remove
glossary_page_link_add
glossary_page_link_remove
glossary_scope_change
```

La publicación de una revisión del glosario debe invalidar las cachés necesarias, pero no crear revisiones artificiales de todas las páginas donde aparece el término.

## 16. Capacidades de Handbook AI para el glosario

Handbook AI puede:

- listar y buscar términos;
- leer definiciones y alias;
- identificar artículos canónicos;
- detectar términos no definidos;
- detectar términos desplazados;
- detectar duplicidades y contradicciones;
- proponer términos nuevos;
- proponer cambios de definición;
- proponer alias;
- proponer vínculos con páginas;
- proponer archivado de términos;
- analizar impacto;
- incluir propuestas en change sets;
- enviar propuestas a revisión con instrucción explícita.

No puede aprobar ni publicar términos.

## 17. Herramientas MCP sugeridas para el glosario

### Lectura

```text
handbook_list_glossary_terms
handbook_search_glossary
handbook_get_glossary_term
handbook_get_glossary_usage
handbook_preview_glossary_rendering
```

### Propuestas

```text
handbook_upsert_changeset_glossary_term
handbook_propose_glossary_alias
handbook_propose_glossary_page_link
handbook_propose_glossary_archive
```

### Auditoría

```text
handbook_audit_undefined_terms
handbook_audit_displaced_terms
handbook_audit_glossary_conflicts
handbook_get_glossary_change_impact
```

## 18. Creación de páginas nuevas

Handbook AI debe poder proponer páginas inexistentes dentro de un change set.

La ficha debe incluir:

- título;
- slug;
- resumen;
- categoría existente o propuesta;
- contenido HTML;
- tipo documental;
- nivel de autoridad;
- criticidad;
- alcance y audiencia;
- modalidades y grados;
- área responsable;
- propietario y aprobador, cuando estén identificados;
- fecha de vigencia;
- fecha de revisión;
- lectura obligatoria;
- nueva confirmación de lectura;
- acceso para IA;
- idioma;
- relaciones iniciales;
- términos del glosario relacionados.

No debe permitirse crear una página sin categoría.

## 19. Identificadores temporales para páginas nuevas

Una página nueva debe recibir un identificador estable dentro del change set:

```text
newpage:direccion-oficial
```

Este identificador puede utilizarse en:

- relaciones;
- enlaces internos;
- términos del glosario;
- rutas de lectura;
- movimientos de categoría;
- páginas reemplazantes.

Al publicar, el servicio reemplaza las referencias temporales por IDs definitivos dentro de una transacción.

## 20. Validación de páginas nuevas

Antes del envío a revisión se debe comprobar:

- título, resumen y slug presentes;
- slug válido y único;
- categoría válida;
- tipo documental permitido;
- HTML con encabezados desde `h2`;
- estructura mínima según el tipo documental;
- área responsable válida;
- autoridad y criticidad definidas;
- fechas coherentes;
- ausencia de términos desplazados no justificados;
- enlaces internos válidos;
- relaciones canónicas;
- ausencia de una página sustancialmente duplicada;
- identificación de artículos que deberían relacionarse con la página nueva.

La detección de duplicidad produce una advertencia, no una resolución automática.

## 21. Propuesta de archivado

El archivado debe representarse como:

```text
page_archive
```

La propuesta debe incluir:

- página afectada;
- razón estructurada;
- explicación;
- fecha propuesta;
- página reemplazante, cuando exista;
- modo de redirección;
- enlaces entrantes;
- relaciones;
- rutas de lectura afectadas;
- términos del glosario vinculados;
- formularios, plantillas y cuestionarios relacionados;
- confirmaciones históricas;
- riesgos identificados.

## 22. Razones de archivado

Catálogo sugerido:

```text
obsolete
superseded
duplicate
merged
temporary_content_expired
role_no_longer_exists
procedure_no_longer_used
incorrect_legacy_import
other
```

`other` requiere explicación obligatoria.

## 23. Comportamiento de una página archivada

Una página archivada:

- deja de aparecer en navegación ordinaria;
- deja de aparecer en rutas activas;
- deja de aparecer en búsquedas ordinarias;
- permanece accesible para administradores o usuarios con capacidad de archivo;
- conserva revisiones, archivos, aprobaciones y auditoría;
- conserva confirmaciones históricas;
- no puede reutilizarse silenciosamente como contenido distinto;
- puede señalar una página reemplazante;
- puede redirigir al artículo vigente;
- puede restaurarse mediante un nuevo flujo humano.

## 24. Página reemplazante y redirección

Campos sugeridos:

```text
replacementpageid
redirectmode
archivenote
```

Modos:

```text
notice_only
redirect_with_notice
automatic_redirect
no_redirect
```

Configuración recomendada para artículos sustituidos:

```text
redirect_with_notice
```

El usuario debe poder entender que el artículo anterior fue archivado y cuál es el contenido vigente.

## 25. Análisis de impacto del archivado

El sistema debe bloquear o advertir fuertemente cuando la página:

- sea una política canónica con procedimientos dependientes;
- tenga guías rápidas o plantillas vinculadas;
- forme parte de una ruta obligatoria activa;
- tenga cuestionarios o actividades asociados;
- sea artículo canónico de términos del glosario;
- reciba relaciones `implements`, `quickguidefor` o `templatefor`;
- tenga enlaces entrantes sin reemplazo;
- sea la única fuente de una obligación vigente.

El cambio debe poder coordinarse con actualizaciones, movimientos, relaciones y términos del glosario dentro del mismo change set.

## 26. Restauración

La restauración debe ser también una propuesta:

```text
page_restore
```

Debe:

- conservar el historial;
- crear una nueva revisión o propuesta de restauración;
- validar categoría y metadatos;
- comprobar si el slug está ocupado;
- revisar la página reemplazante;
- actualizar redirecciones;
- requerir aprobación humana antes de volver a mostrarse.

## 27. Archivado de categorías

En una fase posterior puede permitirse proponer el archivado de categorías.

La propuesta debe resolver el destino de:

- páginas publicadas;
- páginas archivadas;
- subcategorías;
- rutas;
- audiencias;
- enlaces;
- términos del glosario.

Una categoría activa no puede archivarse dejando contenido sin ubicación válida.

## 28. Herramientas MCP para ciclo de vida

### Páginas nuevas

```text
handbook_upsert_changeset_new_page
handbook_validate_new_page
handbook_preview_new_page
```

### Archivado y restauración

```text
handbook_propose_page_archive
handbook_get_archive_impact
handbook_propose_page_restore
```

### Consulta de archivo

```text
handbook_list_archived_pages
handbook_get_archived_page
```

## 29. Actualización del límite de autoridad

La especificación debe indicar:

> Handbook AI puede proponer cambios de categorías, relaciones, rutas, metadatos, términos del glosario y estado de archivo únicamente dentro de un change set. Ninguna propuesta modifica el estado publicado hasta que una persona autorizada la revise, apruebe y publique desde Moodle.

Se mantiene la prohibición absoluta de:

- aprobar;
- publicar;
- archivar directamente;
- restaurar directamente;
- eliminar;
- cambiar permisos o capacidades;
- sobrescribir trabajo humano;
- escribir directamente en la base de datos.

## 30. Pruebas mínimas

### Glosario

- coincidencia de palabra completa;
- alias y términos desplazados;
- términos superpuestos;
- exclusión dentro de enlaces y código;
- marcado por primera aparición;
- teclado y lector de pantalla;
- móvil y tableta;
- caché e invalidación;
- página vinculada archivada;
- cambio de definición sin modificar revisiones de páginas.

### Páginas nuevas

- slug duplicado;
- categoría propuesta en el mismo change set;
- relaciones hacia IDs temporales;
- validación de estructura;
- publicación transaccional.

### Archivado

- página con enlaces entrantes;
- página canónica del glosario;
- página dentro de ruta obligatoria;
- redirección con aviso;
- consulta administrativa;
- restauración;
- conservación de historial y confirmaciones.

## 31. Criterios de aceptación

La implementación se considera completa cuando:

1. existe un glosario estructurado y versionado;
2. los términos pueden mostrar definiciones accesibles en artículos;
3. los términos pueden enlazar artículos canónicos;
4. el marcado se genera durante la visualización y no modifica el HTML almacenado;
5. Handbook AI puede proponer cambios de glosario dentro de change sets;
6. Handbook AI puede proponer páginas nuevas con ficha completa;
7. las páginas nuevas pueden utilizar identificadores temporales en relaciones;
8. Handbook AI puede proponer archivado y restauración;
9. una página archivada conserva historial, archivos, auditoría y confirmaciones;
10. los enlaces antiguos pueden dirigir al contenido reemplazante;
11. el sistema presenta análisis de impacto antes de revisión;
12. ninguna función externa permite aprobar, publicar, archivar o restaurar directamente.
