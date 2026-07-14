<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Spanish strings for local_handbook.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Manual Institucional';

// Capabilities.
$string['handbook:view'] = 'Ver páginas publicadas del manual';
$string['handbook:viewrestricted'] = 'Ver páginas del manual con audiencia restringida';
$string['handbook:viewhistory'] = 'Ver el historial de revisiones del manual';
$string['handbook:acknowledge'] = 'Registrar confirmaciones de lectura obligatoria';
$string['handbook:edit'] = 'Crear páginas y borradores del manual';
$string['handbook:review'] = 'Revisar borradores y solicitar cambios';
$string['handbook:approve'] = 'Aprobar revisiones para su publicación';
$string['handbook:publish'] = 'Publicar, sustituir, archivar o restaurar contenido del manual';
$string['handbook:managecategories'] = 'Gestionar las categorías del manual';
$string['handbook:managepaths'] = 'Gestionar las rutas de lectura del manual';
$string['handbook:managefindings'] = 'Gestionar los hallazgos de calidad del manual';
$string['handbook:viewreports'] = 'Ver los informes del manual';
$string['handbook:manageapi'] = 'Configurar el acceso externo al manual';
$string['handbook:apiaccess'] = 'Usar las funciones de servicio externo del manual';
$string['handbook:manage'] = 'Administrar el plugin del manual';

// Navigation and page titles.
$string['handbookhome'] = 'Manual';
$string['managetools'] = 'Gestión';
$string['categories'] = 'Categorías';
$string['category'] = 'Categoría';
$string['reviewqueue'] = 'Cola de revisión';
$string['newpage'] = 'Nueva página';
$string['editpage'] = 'Editar página';
$string['managecategories'] = 'Gestionar categorías';

// Home page.
$string['recentlyupdated'] = 'Actualizado recientemente';
$string['nocategoriesyet'] = 'Todavía no se han creado categorías.';
$string['nopagesyet'] = 'Todavía no hay páginas publicadas.';
$string['pagecount'] = '{$a} páginas';
$string['pagecountone'] = '1 página';
$string['subcategories'] = 'Subcategorías';
$string['pagesincategory'] = 'Páginas de esta categoría';
$string['emptycategory'] = 'Esta categoría todavía no tiene páginas publicadas.';

// Reader view.
$string['summary'] = 'Resumen';
$string['effectivedate'] = 'Vigente desde';
$string['lastupdated'] = 'Última actualización';
$string['publishedversion'] = 'Versión publicada';
$string['reviewdate'] = 'Próxima revisión';
$string['responsiblearea'] = 'Área responsable';
$string['owner'] = 'Responsable';
$string['approver'] = 'Aprobación';
$string['pagedetails'] = 'Ficha de la página';
$string['relatedpages'] = 'Páginas relacionadas';
$string['contenttype'] = 'Tipo de contenido';
$string['authoritylevel'] = 'Autoridad';
$string['scope'] = 'Ámbito';
$string['audience'] = 'Audiencia';
$string['languagelabel'] = 'Idioma';
$string['requiredreading'] = 'Lectura obligatoria';
$string['notpublished'] = 'Esta página todavía no tiene una revisión publicada.';
$string['draftnotice'] = 'Existe un borrador más reciente (v{$a->version}, {$a->status}) de esta página.';
$string['revisionhistory'] = 'Historial de revisiones';
$string['foreditors'] = 'Para editores';
$string['viewrevision'] = 'Ver';
$string['archivedpage'] = 'Esta página está archivada. Se conserva solo como referencia histórica.';

// Content types (specification 10.1).
$string['contenttype_policy'] = 'Política';
$string['contenttype_procedure'] = 'Procedimiento';
$string['contenttype_standard'] = 'Estándar';
$string['contenttype_guideline'] = 'Directriz';
$string['contenttype_quickguide'] = 'Guía rápida';
$string['contenttype_template'] = 'Plantilla';
$string['contenttype_example'] = 'Ejemplo';
$string['contenttype_roledescription'] = 'Descripción de función';

// Criticality (specification 10.1).
$string['criticality'] = 'Criticidad';
$string['criticality_reference'] = 'Referencia';
$string['criticality_operational'] = 'Operativa';
$string['criticality_mandatory'] = 'Obligatoria';
$string['criticality_safetycritical'] = 'Crítica para la seguridad';

// AI access (specification 10.1).
$string['aiaccess'] = 'Acceso de IA';
$string['aiaccess_full'] = 'Contenido completo';
$string['aiaccess_metadata_only'] = 'Solo metadatos';
$string['aiaccess_excluded'] = 'Excluida';

// Authority levels (specification 10.3).
$string['authority_1'] = 'Nivel 1 · Política institucional';
$string['authority_2'] = 'Nivel 2 · Procedimiento oficial';
$string['authority_3'] = 'Nivel 3 · Estándar departamental';
$string['authority_4'] = 'Nivel 4 · Guía operativa';
$string['authority_5'] = 'Nivel 5 · Plantilla';
$string['authority_6'] = 'Nivel 6 · Material de ejemplo';

// Revision statuses (specification 11.1).
$string['status_draft'] = 'Borrador';
$string['status_in_review'] = 'En revisión';
$string['status_changes_requested'] = 'Cambios solicitados';
$string['status_approved'] = 'Aprobada';
$string['status_published'] = 'Publicada';
$string['status_superseded'] = 'Sustituida';
$string['status_rejected'] = 'Rechazada';

// Editor and workflow.
$string['pagetitle'] = 'Título';
$string['pageslug'] = 'Slug';
$string['pageslug_help'] = 'Identificador estable de URL: minúsculas, números y guiones. No conviene cambiarlo tras la publicación, porque los enlaces y la API externa lo utilizan.';
$string['pagecontent'] = 'Contenido de la página';
$string['changesummary'] = 'Resumen de cambios';
$string['changesummary_help'] = 'Obligatorio al enviar a revisión: una descripción breve de qué cambió y por qué.';
$string['savedraft'] = 'Guardar borrador';
$string['submitforreview'] = 'Enviar a revisión';
$string['requestchanges'] = 'Solicitar cambios';
$string['approve'] = 'Aprobar';
$string['publish'] = 'Publicar';
$string['reviewnote'] = 'Nota de revisión';
$string['version'] = 'Versión';
$string['versionnumber'] = 'v{$a}';
$string['basedon'] = 'Basada en v{$a}';
$string['draftsaved'] = 'Borrador guardado.';
$string['draftsubmitted'] = 'Borrador enviado a revisión.';
$string['changesrequested'] = 'Se solicitaron cambios; el borrador volvió a su autor.';
$string['revisionapproved'] = 'Revisión aprobada.';
$string['revisionpublished'] = 'Revisión publicada.';
$string['nodraftsinreview'] = 'No hay borradores esperando revisión.';
$string['submittedby'] = 'Enviado por {$a->name} el {$a->date}';
$string['confirmpublish'] = '¿Publicar la revisión v{$a} y sustituir la revisión publicada actual?';

// Category management.
$string['categoryname'] = 'Nombre de la categoría';
$string['categoryslug'] = 'Slug';
$string['categorydescription'] = 'Descripción';
$string['categoryparent'] = 'Categoría superior';
$string['categoryvisible'] = 'Visible';
$string['topcategory'] = '(nivel superior)';
$string['newcategory'] = 'Nueva categoría';
$string['editcategory'] = 'Editar categoría';
$string['deletecategory'] = 'Eliminar categoría';
$string['categorysaved'] = 'Categoría guardada.';
$string['categorydeleted'] = 'Categoría eliminada.';
$string['confirmdeletecategory'] = '¿Eliminar la categoría «{$a}»? Solo es posible mientras no tenga páginas ni subcategorías.';
$string['categorynotempty'] = 'Esta categoría todavía contiene páginas o subcategorías y no puede eliminarse.';

// Bootstrap mode and direct publish.
$string['bootstrapmode'] = 'Modo de arranque';
$string['bootstrapmode_desc'] = 'Mientras está activo, quienes tienen la capacidad de publicar pueden hacerlo directamente desde el editor, y las importaciones pueden publicar de inmediato, sin pasar por la cola de revisión. El historial de revisiones se registra igualmente. Pensado solo para la fase de carga inicial: desactívalo después para aplicar el flujo editorial completo.';
$string['saveandpublish'] = 'Guardar y publicar';
$string['bootstrapoffnotice'] = 'El modo de arranque está desactivado: el contenido importado se crea como borradores y sigue el flujo de revisión normal.';

// Seed import.
$string['importseed'] = 'Importar contenido';
$string['importfile'] = 'Archivo semilla (JSON)';
$string['publishonimport'] = 'Publicar de inmediato las páginas importadas';
$string['importcategoriescreated'] = 'Categorías creadas: {$a}';
$string['importcategoriesupdated'] = 'Categorías actualizadas: {$a}';
$string['importpagescreated'] = 'Páginas creadas: {$a}';
$string['importpagesupdated'] = 'Páginas actualizadas: {$a}';
$string['importpagespublished'] = 'Páginas publicadas: {$a}';
$string['importrelationscreated'] = 'Relaciones creadas: {$a}';
$string['importerrors'] = 'Avisos de la importación';
$string['errorinvalidjson'] = 'El archivo subido no es JSON válido.';

// External API.
$string['errorexcludedpage'] = 'Esta página está excluida del acceso externo y de IA.';
$string['errormetadataonly'] = 'Esta página es de solo metadatos para el acceso externo y de IA; su contenido no puede leerse ni editarse por la API.';
$string['errorbasemismatch'] = 'La revisión publicada cambió desde que se leyó. Obtén la versión actual antes de crear el borrador.';

// Errors.
$string['errorbootstrapoff'] = 'La publicación directa requiere el modo de arranque (ver ajustes del plugin).';
$string['errorpagenotfound'] = 'Página del manual no encontrada.';
$string['errorcategorynotfound'] = 'Categoría del manual no encontrada.';
$string['errorslugexists'] = 'Este slug ya está en uso.';
$string['errordraftexists'] = 'Ya existe un borrador sin publicar de esta página. Edita ese borrador en lugar de crear uno nuevo.';
$string['errornodraft'] = 'No existe un borrador editable de esta página.';
$string['errorrevisionconflict'] = 'Otra persona modificó la revisión mientras editabas. Revisa la versión más reciente antes de guardar de nuevo.';
$string['errorworkflowstate'] = 'Esta acción no está permitida en el estado actual de la revisión.';

// Privacy API.
$string['privacy:metadata:local_handbook_revision'] = 'Las revisiones del manual registran qué usuario las creó, modificó, revisó, aprobó o publicó.';
$string['privacy:metadata:local_handbook_revision:createdby'] = 'El usuario que creó la revisión.';
$string['privacy:metadata:local_handbook_revision:modifiedby'] = 'El usuario que modificó la revisión por última vez.';
$string['privacy:metadata:local_handbook_revision:publishedby'] = 'El usuario que publicó la revisión.';
$string['privacy:metadata:local_handbook_page'] = 'Las páginas del manual registran su responsable, su aprobador y los usuarios que las crearon y modificaron.';
$string['privacy:metadata:local_handbook_page:owneruserid'] = 'El usuario responsable de mantener la página actualizada.';
$string['privacy:metadata:local_handbook_category'] = 'Las categorías del manual registran los usuarios que las crearon y modificaron.';
