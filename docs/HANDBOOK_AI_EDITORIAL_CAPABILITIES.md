# Ampliación de capacidades editoriales de Handbook AI

**Estado:** propuesta para la siguiente fase de implementación  
**Componente:** `local_handbook`  
**Documento relacionado:** `docs/HANDBOOK_GLOSSARY_AND_CONTENT_LIFECYCLE.md`  
**Referencias:** `docs/SPECIFICATION.md`, `docs/EDITORIAL_CONVENTIONS.md`, `docs/EXTERNAL_API.md`

## 1. Propósito

Este documento define la ampliación de la API externa y del adaptador MCP para que Handbook AI pueda preparar propuestas editoriales completas y coordinadas.

Una propuesta podrá incluir:

- cambios en el contenido HTML de una página;
- cambios en la ficha y los metadatos de la página;
- creación de páginas nuevas;
- cambios de relaciones entre artículos;
- movimientos y reorganizaciones dentro del árbol de categorías;
- propuestas de archivado o restauración;
- cambios relacionados con el glosario institucional;
- cambios coordinados en varias entidades dentro de un mismo change set.

Todas las operaciones realizadas por Handbook AI deben permanecer como propuestas. El contenido publicado, la navegación visible, los permisos y el estado efectivo de las páginas no cambian hasta que una persona autorizada revise, apruebe y publique la propuesta desde Moodle.

## 2. Límite de autoridad

### 2.1 Handbook AI puede

- leer y buscar contenido autorizado;
- analizar contenido, metadatos, relaciones y categorías;
- crear change sets;
- crear borradores de páginas existentes;
- crear propuestas de páginas nuevas;
- actualizar sus propios borradores editables;
- proponer cambios de ficha, categoría y relaciones;
- proponer archivado o restauración;
- ejecutar validaciones y análisis de impacto;
- enviar un change set a revisión después de una instrucción explícita del usuario.

### 2.2 Handbook AI no puede

- aprobar revisiones;
- publicar revisiones;
- cambiar directamente datos publicados;
- archivar o restaurar directamente;
- eliminar contenido publicado;
- cambiar capacidades o permisos de Moodle;
- alterar audiencias sin revisión humana;
- sobrescribir borradores humanos o pertenecientes a otro change set;
- eludir los servicios del plugin mediante escritura directa en la base de datos.

No debe existir una función externa de aprobación o publicación disponible para la cuenta `handbook-ai`.

## 3. Principio de versionado integral

El título, el resumen, la categoría, el área responsable, las fechas, la audiencia y las relaciones forman parte del significado práctico de una página. Por tanto, deben revisarse con el mismo rigor que el cuerpo HTML.

La implementación debe distinguir entre:

```text
Página publicada
├── contenido publicado
├── metadatos publicados
├── relaciones publicadas
└── ubicación publicada

Propuesta dentro de un change set
├── contenido propuesto
├── parche de metadatos
├── relaciones propuestas
├── movimiento o categoría propuesta
└── resumen del cambio
```

La publicación humana debe aplicar el conjunto aprobado de manera atómica. No debe ser posible publicar el contenido y dejar sin aplicar los metadatos aprobados, o viceversa.

## 4. Metadatos que pueden formar parte de una propuesta

### 4.1 Identidad

- `title`;
- `summary`;
- `slug`;
- `language`;
- `translationgroupid`.

### 4.2 Organización

- `categoryid`;
- `sortorder`;
- etiquetas, si se incorporan;
- relaciones con otras páginas.

### 4.3 Clasificación editorial

- `contenttype`;
- `authoritylevel`;
- `criticality`;
- `requiredreading`;
- `requiresreacknowledgement`.

### 4.4 Aplicabilidad

- `scopejson`;
- `audiencejson`;
- modalidades;
- niveles, ciclos o grados;
- áreas a las que aplica.

### 4.5 Responsabilidad y gobierno

- `responsiblearea`;
- `owneruserid`;
- `approveruserid`;
- `effectivedate`;
- `reviewdate`.

### 4.6 Integraciones

- actividad o cuestionario relacionado;
- formularios y plantillas;
- rutas de lectura;
- términos del glosario vinculados.

### 4.7 Acceso y ciclo de vida

- `aiaccess`;
- audiencia restringida;
- propuesta de archivado;
- página reemplazante;
- comportamiento de redirección.

## 5. Campos sensibles

Los siguientes campos afectan gobierno, acceso o seguridad y deben señalarse explícitamente durante la revisión:

- audiencia y visibilidad;
- `aiaccess`;
- propietario y aprobador;
- nivel de autoridad;
- criticidad;
- lectura obligatoria;
- nueva confirmación de lectura;
- propuesta de archivado;
- restauración;
- fecha de vigencia.

Se recomienda separar capacidades de propuesta:

```text
local/handbook:apiproposemetadata
local/handbook:apiproposetaxonomy
local/handbook:apiproposerelations
local/handbook:apiproposelifecycle
local/handbook:apiproposesensitive
```

Estas capacidades permiten preparar borradores. No confieren revisión, aprobación, publicación ni administración directa.

## 6. Parches de metadatos

La API debe utilizar parches parciales:

- campo omitido: conservar el valor publicado;
- campo incluido con un valor: proponer ese valor;
- campo incluido explícitamente como vacío: eliminarlo solamente cuando el esquema lo permita.

No se debe exigir que el agente reenvíe toda la ficha de una página para modificar un solo campo. Esto reduce el riesgo de borrar datos no relacionados.

Ejemplo:

```json
{
  "title": "Evaluación y evidencias de aprendizaje",
  "summary": "Estándares para diseñar, aplicar y documentar la evaluación durante cada parcial.",
  "responsiblearea": "Coordinación Académica",
  "reviewdate": 1815600000
}
```

## 7. Título, resumen y slug

### 7.1 Título

El agente debe poder proponer un nuevo título sin cambiar automáticamente el slug.

### 7.2 Resumen

El resumen debe versionarse y mostrarse en la comparación antes/después. El sistema debe advertir cuando cambien sustancialmente el contenido, el título, el alcance o el responsable, pero el resumen permanezca idéntico.

### 7.3 Slug

Un cambio de slug debe:

1. verificar unicidad;
2. conservar el ID numérico de la página;
3. registrar el slug anterior como alias;
4. mantener resolubles las direcciones antiguas;
5. actualizar enlaces internos administrados por el plugin;
6. mostrar el impacto al revisor.

Tabla sugerida:

```text
local_handbook_pagealias
- id
- pageid
- oldslug
- timecreated
- createdby
```

## 8. Área responsable, propietario y aprobador

Deben mantenerse como conceptos separados:

- **área responsable:** unidad institucional responsable de la exactitud y mantenimiento del artículo;
- **propietario:** usuario encargado de coordinar su revisión;
- **aprobador:** autoridad humana competente para aprobarlo.

El agente puede proponer un área responsable mediante un identificador controlado. No debe inventar usuarios. La asignación de propietario o aprobador debe aceptar solamente IDs válidos o identidades obtenidas mediante una búsqueda autorizada.

Cuando no se pueda determinar una persona, debe registrarse un hallazgo o una advertencia de propietario pendiente.

## 9. Vocabularios controlados

Siempre que sea posible, los campos institucionales deben almacenar una clave estable y no un nombre libre.

Ejemplo:

```text
responsibleareakey: academic_coordination
displayname: Coordinación Académica
```

Esto evita variantes como:

- Coordinación Académica;
- Coordinación académica;
- coordinación académica.

Se recomiendan catálogos para:

- áreas responsables;
- autoridades;
- modalidades;
- niveles y ciclos;
- tipos documentales;
- criticidad;
- audiencia;
- acceso para IA;
- tipos de relación;
- razones de archivado.

## 10. Relaciones entre artículos

Handbook AI debe poder proponer:

- crear una relación;
- cambiar su tipo;
- eliminarla;
- cambiar su orden.

Tipos existentes o previstos:

```text
relatedto
dependson
implements
replaces
supersedes
exceptionto
procedurefor
quickguidefor
templatefor
assessmentconnectedto
translationof
```

La revisión debe mostrar relaciones actuales, agregadas, eliminadas y modificadas, así como las páginas afectadas en ambas direcciones.

## 11. Categorías y subcategorías

El agente debe poder preparar propuestas para:

- crear una categoría;
- renombrarla;
- modificar su descripción;
- cambiar icono y orden;
- moverla bajo otra categoría;
- convertir una categoría principal en subcategoría o viceversa;
- trasladar páginas;
- fusionar categorías;
- proponer archivado o restauración.

Campos propuestos:

- nombre;
- slug;
- descripción;
- padre;
- orden;
- icono;
- visibilidad;
- audiencia;
- estado.

Validaciones obligatorias:

- slugs únicos;
- ausencia de ciclos;
- una categoría no puede ser descendiente de sí misma;
- ninguna página debe quedar sin categoría cuando esta sea obligatoria;
- una categoría con páginas o subcategorías no puede archivarse sin resolver su destino;
- una reorganización no puede ocultar accidentalmente contenido obligatorio;
- la audiencia de la categoría debe ser compatible con la de sus páginas.

## 12. Change sets con distintos tipos de ítem

Un change set debe poder contener:

```text
page_revision
page_create
page_metadata_change
page_move
page_archive
page_restore
relation_create
relation_update
relation_remove
category_create
category_update
category_move
category_merge
category_archive
glossary_term_create
glossary_term_update
glossary_term_archive
glossary_page_link_change
path_change
```

Los ítems pueden depender entre sí. La interfaz debe indicar cuándo un cambio solamente puede aprobarse junto con otro.

## 13. Creación de páginas nuevas

Handbook AI debe poder proponer páginas que todavía no existen. La página se crea como borrador y como ítem de un change set.

La propuesta debe incluir:

- título;
- slug;
- resumen;
- categoría existente o propuesta en el mismo change set;
- contenido HTML;
- tipo documental;
- autoridad;
- criticidad;
- alcance y audiencia;
- modalidades y grados;
- área responsable;
- propietario y aprobador, cuando estén identificados;
- fechas de vigencia y revisión;
- lectura obligatoria;
- acceso para IA;
- idioma;
- relaciones iniciales;
- términos de glosario relacionados.

No debe permitirse una página sin categoría.

### 13.1 Identificadores temporales

Una página nueva debe recibir un identificador estable dentro del change set, por ejemplo:

```text
newpage:direccion-oficial
```

El identificador temporal podrá utilizarse en relaciones, enlaces, términos del glosario, rutas y movimientos. Al publicar, el servicio resolverá todas las referencias al ID definitivo dentro de una transacción.

## 14. Validación de páginas nuevas

Antes de enviar una página a revisión, comprobar:

- título, resumen y slug presentes;
- slug válido y único;
- categoría válida;
- tipo documental válido;
- encabezados desde `h2`;
- estructura mínima según el tipo;
- área responsable válida;
- autoridad y criticidad definidas;
- ausencia de términos desplazados;
- relaciones canónicas;
- enlaces internos resolubles;
- fecha de revisión cuando corresponda;
- ausencia de una página sustancialmente duplicada.

La detección de duplicados produce una advertencia para el revisor, no una decisión automática.

## 15. Vista previa y análisis de impacto

Antes de enviar un change set, la API debe poder mostrar:

- árbol de categorías actual y propuesto;
- páginas que cambiarán de ubicación;
- títulos, resúmenes y slugs modificados;
- enlaces y aliases afectados;
- relaciones modificadas;
- rutas de lectura afectadas;
- términos del glosario afectados;
- cuestionarios, formularios y plantillas vinculados;
- responsables y aprobadores modificados;
- campos pendientes;
- errores y advertencias.

Funciones sugeridas:

```text
local_handbook_validate_changeset
local_handbook_preview_changeset
local_handbook_get_changeset_impact
```

## 16. Comparación editorial en Moodle

La interfaz de revisión debe mostrar separadamente:

### Contenido

- adiciones y eliminaciones;
- comparación HTML o textual;
- archivos e imágenes modificados.

### Metadatos

- valor publicado;
- valor propuesto.

### Relaciones

- agregadas;
- eliminadas;
- modificadas.

### Taxonomía

- categoría y posición anteriores;
- categoría y posición propuestas.

### Gobierno

- área responsable;
- propietario;
- aprobador;
- autoridad;
- criticidad;
- vigencia;
- revisión;
- lectura obligatoria;
- nueva confirmación requerida.

No debe ser necesario inspeccionar HTML para descubrir cambios de gobierno o alcance.

## 17. Control de concurrencia

Cada operación debe incluir, según corresponda:

- revisión publicada esperada;
- versión o fecha de modificación de la ficha;
- versión del árbol de categorías;
- `expectedtimemodified` del borrador;
- versión de las relaciones.

Los conflictos deben devolver una respuesta estructurada y nunca sobrescribir trabajo más reciente.

## 18. Cambios masivos

El agente debe poder preparar un dry run para operaciones como:

- sustituir un área responsable obsoleta;
- normalizar capitalización;
- reemplazar `bimestre` por `parcial`;
- establecer fechas de revisión;
- trasladar grupos de páginas;
- agregar relaciones hacia artículos canónicos;
- actualizar resúmenes de páginas modificadas.

El dry run debe indicar páginas coincidentes, valores actuales, valores propuestos, excepciones y conflictos. La aplicación crea ítems individuales dentro de un change set.

## 19. Herramientas MCP recomendadas

### Lectura

```text
handbook_get_context_index
handbook_get_page
handbook_get_working_page
handbook_get_category
handbook_get_category_tree
handbook_get_related_pages
handbook_get_changeset
handbook_preview_changeset
handbook_validate_changeset
```

### Escritura de propuestas

```text
handbook_create_changeset
handbook_upsert_changeset_page
handbook_upsert_changeset_new_page
handbook_upsert_changeset_category
handbook_upsert_changeset_relation
handbook_move_page_in_changeset
handbook_remove_changeset_item
handbook_submit_changeset_for_review
```

La herramienta de página puede aceptar opcionalmente `content`, `metadata` y `relations`, exigiendo al menos uno.

## 20. Reglas automáticas de calidad

Antes de enviar a revisión, comprobar:

- título y resumen presentes;
- resumen revisado cuando el contenido cambia sustancialmente;
- encabezados desde `h2`;
- ausencia de `[BORRADOR]`;
- terminología institucional vigente;
- área responsable válida;
- estructura mínima por tipo;
- relaciones requeridas;
- enlaces internos válidos;
- fecha de revisión;
- coherencia entre criticidad y lectura obligatoria;
- posible necesidad de nueva confirmación;
- coherencia entre audiencia, alcance y categoría;
- ausencia de slugs duplicados;
- ausencia de categorías creadas accidentalmente sin uso.

Los errores estructurales o de seguridad bloquean el envío. Las observaciones editoriales generan advertencias.

## 21. Auditoría

Cada operación debe registrar:

- cuenta técnica;
- patrocinador humano;
- change set;
- entidad afectada;
- valor anterior y propuesto;
- fecha;
- fuente;
- resumen del cambio;
- validaciones;
- conflictos;
- envío a revisión.

La autoría pública corresponde al responsable humano que revisa y asume el contenido. La atribución técnica del agente permanece en el registro interno.

## 22. Priorización

### Fase 1: ficha completa y páginas nuevas

- metadatos versionados;
- parches parciales;
- páginas nuevas dentro de change sets;
- relaciones hacia páginas nuevas;
- comparación de ficha;
- publicación transaccional.

### Fase 2: glosario institucional

Implementar según `docs/HANDBOOK_GLOSSARY_AND_CONTENT_LIFECYCLE.md`.

### Fase 3: taxonomía y ciclo de vida

- categorías y movimientos;
- archivado y restauración;
- aliases y redirecciones;
- análisis de impacto.

### Fase 4: cambios masivos e integraciones

- dry runs masivos;
- rutas de lectura;
- cuestionarios;
- adjuntos;
- traducciones.

## 23. Criterios de aceptación

1. Handbook AI puede proponer contenido y ficha dentro del mismo change set.
2. Puede crear una página nueva con su ficha completa.
3. Puede relacionar páginas nuevas con otras propuestas antes de publicarlas.
4. Puede proponer cambios de categoría y relaciones.
5. Los valores publicados no cambian antes de la aprobación humana.
6. La interfaz muestra diferencias de contenido, metadatos, relaciones y ubicación.
7. Los slugs anteriores continúan funcionando después de un cambio aprobado.
8. Ningún borrador humano puede ser sobrescrito.
9. Toda operación utiliza control de concurrencia y auditoría.
10. No existe una función externa de aprobación o publicación.
