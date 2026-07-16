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
$string['handbook:managechangesets'] = 'Revisar y gestionar los conjuntos de cambios del manual';
$string['handbook:viewreports'] = 'Ver los informes del manual';
$string['handbook:manageapi'] = 'Configurar el acceso externo al manual';
$string['handbook:apiaccess'] = 'Usar las funciones de servicio externo del manual';
$string['handbook:apiproposemetadata'] = 'Proponer cambios de ficha (metadatos) del manual mediante la API';
$string['handbook:apiproposerelations'] = 'Proponer cambios de relaciones entre páginas del manual mediante la API';
$string['handbook:apiproposelifecycle'] = 'Proponer acciones de archivado/restauración del manual mediante la API';
$string['handbook:apiproposetaxonomy'] = 'Proponer cambios de categorías del manual mediante la API';
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
$string['approveall'] = 'Aprobar todo ({$a})';
$string['confirmapproveall'] = '¿Aprobar los {$a} borradores que están en revisión? Pasarán al estado aprobado, listos para publicar.';
$string['allrevisionsapproved'] = '{$a} revisiones aprobadas.';
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
$string['categoryicon'] = 'Icono';
$string['categoryicon_help'] = 'Clase de icono Font Awesome (sólido), p. ej. fa-children, fa-landmark, fa-sitemap (ver fontawesome.com/icons, set Free/Solid). Déjalo vacío para el icono de carpeta por defecto.';
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

// Required-reading acknowledgements (spec 16).
$string['acknowledgereading'] = 'Confirmar lectura';
$string['readingconfirmation'] = 'Confirmación de lectura';
$string['ackpendingnotice'] = 'Esta página es de lectura obligatoria y todavía no has confirmado la versión vigente (v{$a}).';
$string['ackreconfirmnotice'] = 'Esta página es de lectura obligatoria y una versión con cambios sustanciales (v{$a}) requiere tu nueva confirmación.';
$string['ackconfirmednotice'] = 'Confirmaste la lectura de la versión vigente (v{$a->version}) el {$a->date}.';
$string['gotoconfirmation'] = 'Ir a la confirmación de lectura';
$string['ackcheckboxlabel'] = 'He leído y comprendido la versión vigente de «{$a}».';
$string['confirmreading'] = 'Confirmar lectura';
$string['ackrecorded'] = 'Tu confirmación de lectura quedó registrada.';
$string['ackconfirmedrecord'] = 'Confirmada el {$a->date} · versión publicada v{$a->version}';
$string['ackconfirmedshort'] = 'Confirmada · {$a}';
$string['ackrecordinfo'] = 'La confirmación queda registrada con tu usuario, la versión publicada de la página y la fecha. No sustituye las evaluaciones de conocimiento en Moodle.';
$string['requiresreack'] = 'Requiere nueva confirmación al publicarse';
$string['requiresreack_help'] = 'Marca esta casilla en versiones con cambios sustanciales de páginas de lectura obligatoria: tras la publicación, todos deberán confirmar la lectura de nuevo. Déjala sin marcar para correcciones menores.';
$string['errornotrequiredreading'] = 'Esta página no está marcada como lectura obligatoria.';

// Reading paths (spec 15).
$string['myreadingpath'] = 'Mi ruta de lectura';
$string['managepaths'] = 'Gestionar rutas de lectura';
$string['newpath'] = 'Nueva ruta de lectura';
$string['editpath'] = 'Editar ruta de lectura';
$string['pathname'] = 'Nombre de la ruta';
$string['schoolyear'] = 'Año escolar';
$string['pathitems'] = 'Elementos de la ruta';
$string['sectionname'] = 'Sección';
$string['additem'] = 'Añadir elemento';
$string['pathsaved'] = 'Ruta de lectura guardada.';
$string['pathdeleted'] = 'Ruta de lectura eliminada.';
$string['confirmdeletepath'] = '¿Eliminar la ruta de lectura «{$a}» y todos sus elementos? Las confirmaciones registradas se conservan.';
$string['pathitemcount'] = '{$a} elementos';
$string['nopathsyet'] = 'Todavía no hay rutas de lectura activas.';
$string['emptypath'] = 'Esta ruta de lectura todavía no tiene elementos.';
$string['pathprogress'] = '{$a->confirmed} de {$a->total} páginas obligatorias confirmadas';
$string['sectionprogress'] = '{$a->confirmed} de {$a->total} confirmadas';
$string['optionalitem'] = 'Opcional';
$string['reconfirmitem'] = 'Reconfirmar: nueva versión publicada';
$string['pendingitem'] = 'Pendiente';
$string['readitem'] = 'Lectura';
$string['connectedquiz'] = 'Cuestionario Moodle';
$string['pathcohorts'] = 'Cohortes de la audiencia';
$string['pathroles'] = 'Roles de la audiencia (nivel de sistema)';
$string['pathaudience'] = 'Audiencia de la ruta';
$string['pathaudience_help'] = 'Deja ambos vacíos para mostrar la ruta a todo el personal con acceso al manual. En caso contrario, la ruta es visible para quienes pertenezcan a CUALQUIER cohorte seleccionada o tengan CUALQUIER rol seleccionado a nivel de sistema. Los gestores siempre ven todas las rutas, y el informe de avance cubre exactamente esta audiencia.';
$string['errorpathnotvisible'] = 'Esta ruta de lectura no está disponible para tu rol o tus grupos.';
$string['importpathscreated'] = 'Rutas de lectura creadas: {$a}';
$string['importpathsupdated'] = 'Rutas de lectura actualizadas: {$a}';

// Privacy API (acknowledgements).
$string['privacy:metadata:local_handbook_ack'] = 'Las confirmaciones de lectura obligatoria registran qué usuario confirmó qué revisión publicada y cuándo.';
$string['privacy:metadata:local_handbook_ack:userid'] = 'El usuario que confirmó la lectura.';
$string['privacy:metadata:local_handbook_ack:timeacknowledged'] = 'Cuándo se registró la confirmación.';

// Search.
$string['searchhandbook'] = 'Buscar en el manual';
$string['searchplaceholder'] = 'Buscar procedimientos, políticas, guías y formularios…';
$string['alltypes'] = 'Todos los tipos';
$string['allcategories'] = 'Todas las categorías';
$string['searchresultcount'] = '{$a} páginas encontradas';
$string['noresults'] = 'Ninguna página coincide con tu búsqueda.';

// Revision history and comparison.
$string['comparerevisions'] = 'Comparar revisiones';
$string['comparingversions'] = 'Comparando v{$a->from} → v{$a->to}';
$string['difflegend'] = 'Lo añadido aparece resaltado; lo eliminado, tachado.';
$string['comparewithpublished'] = 'Comparar con la versión publicada';
$string['comparewithprevious'] = 'Comparar con su versión base';
$string['viewchanges'] = 'Ver cambios';
$string['nocontentdiff'] = 'No hay cambios de texto entre estas versiones.';
$string['createdby'] = 'Creada por';
$string['backtopage'] = 'Volver a la página';

// Home personalization and reader polish (spec 12.1, 12.2).
$string['pendingreadingcard'] = 'Lectura obligatoria pendiente';
$string['noackpending'] = 'Estás al día con toda la lectura obligatoria.';
$string['continuepath'] = 'Continuar la ruta';
$string['continuereading'] = 'Leer y confirmar';
$string['currentsection'] = 'Sección actual';
$string['editorialwork'] = 'Trabajo editorial';
$string['draftsawaiting'] = 'Borradores esperando revisión: {$a}';
$string['changesrequestedcount'] = 'Cambios solicitados: {$a}';
$string['overduereviewcount'] = 'Revisiones vencidas: {$a}';
$string['safetycriticalpages'] = 'Páginas críticas para la seguridad';
$string['quickguides'] = 'Guías rápidas';
$string['formstemplates'] = 'Formularios y plantillas';
$string['viewall'] = 'Ver todo';
$string['onthispage'] = 'En esta página';
$string['printpage'] = 'Imprimir';
$string['printfooter'] = 'Impreso el {$a->date}. Las copias impresas envejecen: la versión vigente vive en {$a->url}';
$string['authoritynote'] = 'Esta guía resume {$a}. Ante cualquier diferencia, prevalece el procedimiento completo.';
$string['partofpath'] = 'Forma parte de la ruta de lectura: {$a}';

// Relation type labels (spec 9.2): forward and reverse.
$string['relation_relatedto'] = 'Relacionada con';
$string['relationrev_relatedto'] = 'Relacionada con';
$string['relation_dependson'] = 'Depende de';
$string['relationrev_dependson'] = 'Requerida por';
$string['relation_implements'] = 'Implementa';
$string['relationrev_implements'] = 'Implementada por';
$string['relation_replaces'] = 'Reemplaza a';
$string['relationrev_replaces'] = 'Reemplazada por';
$string['relation_supersedes'] = 'Sustituye a';
$string['relationrev_supersedes'] = 'Sustituida por';
$string['relation_exceptionto'] = 'Excepción a';
$string['relationrev_exceptionto'] = 'Excepción definida en';
$string['relation_procedurefor'] = 'Procedimiento para';
$string['relationrev_procedurefor'] = 'Procedimiento conexo';
$string['relation_quickguidefor'] = 'Guía rápida de';
$string['relationrev_quickguidefor'] = 'Guía rápida';
$string['relation_templatefor'] = 'Plantilla para';
$string['relationrev_templatefor'] = 'Plantilla';
$string['relation_assessmentfor'] = 'Evaluación de';
$string['relationrev_assessmentfor'] = 'Evaluación conectada';
$string['relation_translationof'] = 'Traducción de';
$string['relationrev_translationof'] = 'Traducida como';

// Quality findings (spec 19).
$string['reportproblem'] = 'Reportar un error';
$string['reportintro'] = 'Describe lo que encontraste. Tu reporte crea un hallazgo de calidad, registrado con tu usuario y la versión publicada; el equipo editorial lo clasifica y registra la resolución.';
$string['problemtype'] = 'Tipo de problema';
$string['affectedsection'] = 'Sección afectada (opcional)';
$string['problemdescription'] = 'Descripción';
$string['reportplaceholder'] = 'Qué encontraste y, si lo sabes, cómo debería ser…';
$string['sendreport'] = 'Enviar reporte';
$string['reportthanks'] = 'Gracias. Se creó el hallazgo #F-{$a} y el equipo editorial fue notificado.';
$string['managefindings'] = 'Hallazgos de calidad';
$string['nofindings'] = 'Ningún hallazgo coincide con este filtro.';
$string['findingupdated'] = 'Hallazgo actualizado.';
$string['resolutionnote'] = 'Nota de resolución';
$string['filteropenish'] = 'Abiertos + en revisión';
$string['findingtype_contradiction'] = 'Posible contradicción';
$string['findingtype_duplicate'] = 'Contenido duplicado o solapado';
$string['findingtype_ambiguous_responsibility'] = 'Responsabilidad poco clara';
$string['findingtype_missing_escalation'] = 'Falta la vía de escalamiento';
$string['findingtype_missing_record'] = 'Falta un registro o formulario obligatorio';
$string['findingtype_outdated_reference'] = 'Referencia desactualizada (rol, fecha o sistema)';
$string['findingtype_incorrect_content'] = 'Información incorrecta';
$string['findingtype_inconsistent_terminology'] = 'Terminología inconsistente';
$string['findingtype_broken_link'] = 'Enlace interno roto';
$string['findingtype_missing_owner'] = 'Falta responsable o aprobador';
$string['findingtype_review_overdue'] = 'Fecha de revisión vencida';
$string['findingtype_procedure_without_policy'] = 'Procedimiento sin política asociada';
$string['findingtype_policy_without_procedure'] = 'Política sin procedimiento aplicable';
$string['findingtype_modality_difference'] = 'Diferencia entre modalidades sin explicación';
$string['findingtype_assessment_outdated'] = 'La evaluación conectada puede estar desactualizada';
$string['findingtype_accessibility'] = 'Problema de accesibilidad o legibilidad';
$string['findingtype_other'] = 'Otro';
$string['findingstatus_open'] = 'Abierto';
$string['findingstatus_under_review'] = 'En revisión';
$string['findingstatus_accepted'] = 'Aceptado';
$string['findingstatus_dismissed'] = 'Descartado';
$string['findingstatus_resolved'] = 'Resuelto';
$string['findingstatus_intentional_difference'] = 'Diferencia intencional';
$string['scale_low'] = 'Baja';
$string['scale_medium'] = 'Media';
$string['scale_high'] = 'Alta';

// Reports (spec 12.5, 15.3).
$string['reports'] = 'Informes';
$string['reporthealth'] = 'Salud editorial';
$string['reportpaths'] = 'Avance de rutas';
$string['reportpageacks'] = 'Confirmaciones por página';
$string['reportoverdue'] = 'Revisión vencida';
$string['reportmissingowner'] = 'Sin responsable asignado';
$string['reportneverpublished'] = 'Nunca publicadas';
$string['reportagingdrafts'] = 'Borradores más antiguos en revisión';
$string['openfindingscount'] = 'Hallazgos de calidad abiertos: {$a}';
$string['reportpathintro'] = 'Páginas obligatorias confirmadas por persona ({$a} páginas obligatorias en esta ruta). Personal = usuarios con la capacidad de ver el manual.';
$string['pathprogressshort'] = 'Confirmadas';
$string['reportconfirmed'] = 'Confirmaron';
$string['reportpending'] = 'Pendientes';
$string['norequiredpages'] = 'Todavía no hay páginas de lectura obligatoria publicadas.';

// Notifications and scheduled tasks (spec 21).
$string['messageprovider:draftsubmitted'] = 'Borrador del manual enviado a revisión';
$string['messageprovider:changesrequested'] = 'Cambios solicitados en tu borrador del manual';
$string['messageprovider:findingcreated'] = 'Nuevo hallazgo de calidad del manual';
$string['messageprovider:reviewdue'] = 'Revisión de página del manual pendiente';
$string['notifydraftsubmitted_subject'] = 'Borrador para revisar: {$a->title} (v{$a->version})';
$string['notifydraftsubmitted_body'] = 'Se envió a revisión un borrador de «{$a->title}» (v{$a->version}). Resumen de cambios: {$a->summary}';
$string['notifychangesrequested_subject'] = 'Cambios solicitados: {$a->title} (v{$a->version})';
$string['notifychangesrequested_body'] = 'Tu borrador de «{$a->title}» (v{$a->version}) fue devuelto con la nota: {$a->note}';
$string['notifyfindingcreated_subject'] = 'Nuevo hallazgo de calidad #F-{$a->id}: {$a->type}';
$string['notifyfindingcreated_body'] = 'Se reportó un nuevo hallazgo de calidad: {$a->summary}';
$string['notifyreviewdue_subject'] = 'Revisión pendiente: {$a->title}';
$string['notifyreviewdue_body'] = 'La página «{$a->title}», de la que eres responsable, llega a su fecha de revisión el {$a->reviewdate}. Revísala y publica una versión actualizada o amplía la fecha de revisión.';
$string['task_reviewreminder'] = 'Recordatorios de revisión del manual';
$string['task_linkchecker'] = 'Verificador de enlaces del manual';
$string['brokenlinksummary'] = 'La página «{$a->page}» enlaza a «{$a->target}», que no existe o no está publicada.';
$string['brokenquizsummary'] = 'Un elemento de ruta de la página «{$a->page}» apunta al módulo de cuestionario {$a->cmid}, que ya no existe.';

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

// Archive and restore (spec 11.3).
$string['archivepage'] = 'Archivar';
$string['unarchivepage'] = 'Desarchivar';
$string['pagearchived'] = 'Página archivada. Su historial de revisiones se conserva.';
$string['pageunarchived'] = 'Página restaurada del archivo.';
$string['confirmarchive'] = '¿Archivar «{$a}»? Los lectores dejarán de verla; los editores conservan el acceso y todo el historial se mantiene.';
$string['confirmunarchive'] = '¿Restaurar «{$a}» del archivo? Volverá a ser visible para los lectores.';
$string['restoreasdraft'] = 'Restaurar como borrador';
$string['confirmrestore'] = '¿Crear un nuevo borrador basado en la v{$a}? El historial posterior se conserva; el borrador sigue el flujo de revisión normal.';
$string['restoredsummary'] = 'Restaurada desde la v{$a}.';
$string['revisionrestored'] = 'La v{$a} se restauró como nuevo borrador de trabajo.';

// Privacy export paths.
$string['privacy:acknowledgementspath'] = 'Confirmaciones de lectura';
$string['privacy:authoredpath'] = 'Revisiones creadas';
$string['privacy:metadata:local_handbook_finding'] = 'Los hallazgos de calidad registran quién los reportó, a quién se asignaron y quién los resolvió.';

// Privacy API.
$string['privacy:metadata:local_handbook_revision'] = 'Las revisiones del manual registran qué usuario las creó, modificó, revisó, aprobó o publicó.';
$string['privacy:metadata:local_handbook_revision:createdby'] = 'El usuario que creó la revisión.';
$string['privacy:metadata:local_handbook_revision:modifiedby'] = 'El usuario que modificó la revisión por última vez.';
$string['privacy:metadata:local_handbook_revision:publishedby'] = 'El usuario que publicó la revisión.';
$string['privacy:metadata:local_handbook_page'] = 'Las páginas del manual registran su responsable, su aprobador y los usuarios que las crearon y modificaron.';
$string['privacy:metadata:local_handbook_page:owneruserid'] = 'El usuario responsable de mantener la página actualizada.';
$string['privacy:metadata:local_handbook_category'] = 'Las categorías del manual registran los usuarios que las crearon y modificaron.';

// Conjuntos de cambios y autoría pública (especificación 36).
$string['author'] = 'Autor';
$string['changesets'] = 'Conjuntos de cambios';
$string['changeset'] = 'Conjunto de cambios';
$string['changesetdefaultsummary'] = 'Conjunto de cambios: {$a}';
$string['event_revision_approved'] = 'Revisión del manual aprobada';
$string['event_revision_rejected'] = 'Revisión del manual rechazada';
$string['event_changes_requested'] = 'Cambios solicitados en el manual';
$string['event_changeset_created'] = 'Conjunto de cambios del manual creado';
$string['event_changeset_submitted'] = 'Conjunto de cambios del manual enviado';
$string['errorchangesetlocked'] = 'Este conjunto de cambios está completado o cancelado y ya no puede modificarse.';
$string['errorchangeitemlocked'] = 'Este elemento está en revisión, aprobado o publicado y no puede eliminarse del conjunto de cambios.';
$string['conflict_humandraft'] = 'Ya existe para esta página un borrador de trabajo (v{$a}) que no forma parte de este conjunto de cambios; una persona debe resolverlo antes de que el conjunto pueda redactar aquí.';
$string['conflict_foreignchangeset'] = 'Esta página ya tiene un borrador de trabajo (v{$a}) en otro conjunto de cambios.';
$string['conflict_inreview'] = 'El borrador de este conjunto de cambios (v{$a}) está en revisión o aprobado; una persona debe devolverlo antes de seguir editando.';
$string['conflict_basemismatch'] = 'La revisión publicada cambió desde que se leyó; actualice la página antes de redactar.';
$string['conflict_concurrency'] = 'El borrador (v{$a}) se modificó desde la última lectura; vuelva a leerlo antes de actualizar.';
$string['changesetstatus_draft'] = 'Borrador';
$string['changesetstatus_in_review'] = 'En revisión';
$string['changesetstatus_partially_completed'] = 'Parcialmente completado';
$string['changesetstatus_completed'] = 'Completado';
$string['changesetstatus_cancelled'] = 'Cancelado';
$string['itemstatus_draft'] = 'Borrador';
$string['itemstatus_conflict'] = 'Conflicto';
$string['itemstatus_in_review'] = 'En revisión';
$string['itemstatus_approved'] = 'Aprobado';
$string['itemstatus_published'] = 'Publicado';
$string['itemstatus_rejected'] = 'Rechazado';
$string['itemstatus_skipped'] = 'Omitido';

// Interfaz editorial de conjuntos de cambios (especificación 36, fase 2).
$string['newchangeset'] = 'Nuevo conjunto de cambios';
$string['changesetinstructions'] = 'Resumen de la instrucción';
$string['changesetinstructions_help'] = 'Un resumen breve y en lenguaje claro del cambio que realiza este conjunto. Guarde la instrucción aprobada, no una transcripción completa del chat.';
$string['createchangeset'] = 'Crear conjunto de cambios';
$string['nochangesets'] = 'Todavía no hay conjuntos de cambios.';
$string['changesetcreated'] = 'Conjunto de cambios creado.';
$string['changesetdetails'] = 'Detalles del conjunto de cambios';
$string['changesetitems'] = 'Páginas de este conjunto de cambios';
$string['nochangesetitems'] = 'Todavía no se han añadido páginas a este conjunto de cambios.';
$string['addpagetochangeset'] = 'Añadir una página';
$string['selectpageadd'] = 'Seleccione una página…';
$string['addpagebutton'] = 'Añadir';
$string['pageaddedtochangeset'] = 'Página añadida al conjunto de cambios.';
$string['removeitem'] = 'Quitar';
$string['itemremoved'] = 'Página quitada del conjunto de cambios; se conserva su borrador.';
$string['confirmremoveitem'] = '¿Quitar esta página del conjunto de cambios? Se conserva su borrador y podrá seguir editándose con normalidad.';
$string['submitchangeset'] = 'Enviar a revisión';
$string['changesetsubmittednotice'] = 'Los borradores elegibles se enviaron a revisión.';
$string['cancelchangeset'] = 'Cancelar conjunto de cambios';
$string['confirmcancelchangeset'] = '¿Cancelar este conjunto de cambios? Se conservan los borradores y el historial de revisiones; el conjunto se cierra.';
$string['changesetcancelled'] = 'Conjunto de cambios cancelado.';
$string['editdraft'] = 'Editar borrador';
$string['reject'] = 'Rechazar';
$string['revisionrejected'] = 'Revisión rechazada.';
$string['changesetsource'] = 'Origen';
$string['source_human'] = 'Persona';
$string['source_ai'] = 'IA del manual';
$string['changesetsponsor'] = 'Patrocinador';
$string['changesetpreparedby'] = 'Preparado por';
$string['changesetcreatedon'] = 'Creado';
$string['backtochangesets'] = 'Volver a los conjuntos de cambios';
$string['draftmatchespublished'] = 'Este borrador coincide con la versión publicada: todavía no hay cambios.';
$string['changesetnewpage'] = 'Página nueva (aún sin publicar)';
$string['externalreference'] = 'Referencia externa';

// Propuestas de ficha (metadatos) en los change sets (Fase 1).
$string['metadatachangesummary'] = 'Metadatos: {$a}';
$string['metadatafield'] = 'Campo';
$string['metadatacurrentvalue'] = 'Actual';
$string['metadataproposedvalue'] = 'Propuesto';
$string['metadatanochanges'] = 'Esta propuesta no contiene cambios de campos.';
$string['applychange'] = 'Aplicar cambio';
$string['changeitemapproved'] = 'Cambio aprobado.';
$string['changeitemapplied'] = 'Cambio aplicado y publicado.';
$string['changeitemrejected'] = 'Cambio rechazado.';
$string['metafield_title'] = 'Título';
$string['metafield_slug'] = 'Slug';
$string['metafield_summary'] = 'Resumen';
$string['metafield_contenttype'] = 'Tipo de contenido';
$string['metafield_authoritylevel'] = 'Nivel de autoridad';
$string['metafield_criticality'] = 'Criticidad';
$string['metafield_responsiblearea'] = 'Área responsable';
$string['metafield_reviewdate'] = 'Fecha de revisión';
$string['metafield_requiredreading'] = 'Lectura obligatoria';
$string['conflict_metadataconcurrency'] = 'La ficha de la página cambió después de preparar esta propuesta; recárguela y propóngala de nuevo.';
$string['errormetadatafieldunsupported'] = 'El campo de metadatos «{$a}» no puede cambiarse mediante una propuesta de metadatos.';
$string['errormetadatavalue'] = 'El valor propuesto para «{$a}» no es válido.';
$string['errormetadatapatchempty'] = 'Una propuesta de metadatos debe cambiar al menos un campo.';
$string['errorunsupportedkind'] = 'Este tipo de cambio («{$a}») no puede aplicarse automáticamente.';
$string['errorwrongitemkind'] = 'Esta acción no se aplica a una revisión de contenido de la página.';

// Páginas nuevas, alias de slug, relaciones y áreas (Fase 1).
$string['newpagechangesummary'] = 'Página nueva: {$a}';
$string['newpagesubmitsummary'] = 'Página nueva propuesta mediante un change set.';
$string['relationchangesummary'] = 'Relaciones: {$a} cambio(s)';
$string['itemkindnewpage'] = 'Página nueva';
$string['relationopcreate'] = 'Añadir';
$string['relationopremove'] = 'Quitar';
$string['errornewpagecategory'] = 'Una página nueva debe referirse a una categoría existente.';
$string['errornewpagecontent'] = 'Una página nueva debe incluir contenido.';
$string['errorslugtaken'] = 'El slug «{$a}» ya está en uso.';
$string['errorrelationop'] = 'Una operación de relación debe ser «create» o «remove».';
$string['errorrelationtype'] = 'Tipo de relación desconocido «{$a}».';
$string['errorrelationself'] = 'Una página no puede relacionarse consigo misma.';
$string['errorrelationtarget'] = 'Una operación de relación necesita una página de destino válida.';
$string['errorrelationempty'] = 'Una propuesta de relaciones debe contener al menos una operación.';
$string['errorrelationunresolved'] = 'No se pudo resolver el destino de la relación «{$a}»; aplique primero la página nueva a la que apunta.';
$string['errortempkeyrequired'] = 'Una propuesta de página nueva necesita un tempkey estable.';
$string['errorunknownarea'] = 'El área responsable «{$a}» no está en el vocabulario controlado.';

// Gestión del vocabulario de áreas responsables (Fase 1).
$string['manageareas'] = 'Áreas responsables';
$string['manageareas_help'] = 'El vocabulario controlado de áreas responsables. Las propuestas de metadatos y de páginas nuevas deben referirse a un área activa de esta lista.';
$string['newarea'] = 'Área nueva';
$string['editarea'] = 'Editar área';
$string['areaname'] = 'Nombre';
$string['areakey'] = 'Clave';
$string['areakey_help'] = 'Una clave estable (minúsculas, números y guiones). Déjela vacía para generarla a partir del nombre. La API puede referirse a un área por su clave o por su nombre.';
$string['areaactive'] = 'Activa';
$string['areainactive'] = 'Inactiva';
$string['areaactivate'] = 'Activar';
$string['areadeactivate'] = 'Desactivar';
$string['areasaved'] = 'Área responsable guardada.';
$string['areadeleted'] = 'Área responsable eliminada.';
$string['noareas'] = 'Todavía no se han definido áreas responsables.';
$string['confirmdeletearea'] = '¿Eliminar el área responsable «{$a}»? Las páginas que ya usan este nombre lo conservan; solo se elimina la entrada del vocabulario.';

// Ciclo de vida de archivado/restauración (Fase 2).
$string['archivechangesummary'] = 'Archivar: {$a}';
$string['restorechangesummary'] = 'Restaurar: {$a}';
$string['archiveproposal'] = 'Propuesta de archivado';
$string['restoreproposal'] = 'Restaurar esta página archivada';
$string['archivereasonlabel'] = 'Motivo';
$string['replacementpage'] = 'Página reemplazante';
$string['redirectmodelabel'] = 'Redirección';
$string['archiveimpact'] = 'Impacto: {$a->relations} relación(es) entrante(s); {$a->paths} ruta(s) de lectura activa(s).';
$string['archivedredirectnotice'] = 'La página «{$a}» fue archivada; se le ha llevado a la página vigente.';
$string['archivedseereplacement'] = 'Página vigente: {$a}.';
$string['archivereason_obsolete'] = 'Obsoleta';
$string['archivereason_superseded'] = 'Sustituida';
$string['archivereason_duplicate'] = 'Duplicada';
$string['archivereason_merged'] = 'Fusionada';
$string['archivereason_temporary_content_expired'] = 'Contenido temporal caducado';
$string['archivereason_role_no_longer_exists'] = 'La función ya no existe';
$string['archivereason_procedure_no_longer_used'] = 'Procedimiento en desuso';
$string['archivereason_incorrect_legacy_import'] = 'Importación heredada incorrecta';
$string['archivereason_other'] = 'Otro';
$string['redirectmode_notice_only'] = 'Solo aviso';
$string['redirectmode_redirect_with_notice'] = 'Redirigir con aviso';
$string['redirectmode_automatic_redirect'] = 'Redirección automática';
$string['redirectmode_no_redirect'] = 'Sin redirección';
$string['errorarchivereason'] = 'Se requiere un motivo de archivado válido.';
$string['errorarchivenote'] = 'Se requiere una explicación cuando el motivo es «otro».';
$string['errorredirectmode'] = 'Modo de redirección no válido.';
$string['errorreplacementself'] = 'Una página no puede ser su propia reemplazante.';
$string['errorreplacementinvalid'] = 'La página reemplazante no existe o está archivada.';
$string['errorreplacementrequired'] = 'Un modo con redirección necesita una página reemplazante.';
$string['errornotarchived'] = 'Esta página no está archivada.';

// Propuestas de categorías (Fase 2).
$string['categorychangesummary_create'] = 'Categoría nueva';
$string['categorychangesummary_update'] = 'Actualización de categoría';
$string['categorychangesummary_move'] = 'Mover categoría';
$string['categorychangesummary_merge'] = 'Fusión de categorías';
$string['categoryop_create'] = 'Crear categoría';
$string['categoryop_update'] = 'Actualizar categoría';
$string['categoryop_move'] = 'Mover categoría';
$string['categoryop_merge'] = 'Fusionar categorías';
$string['categoryoplabel'] = 'Operación';
$string['categorymergesource'] = 'Fusionar desde';
$string['categorymergetarget'] = 'Fusionar en';
$string['itemkindcategory'] = 'Categoría';
$string['errorcategoryop'] = 'Operación de categoría no válida.';
$string['errorcategoryname'] = 'Se requiere un nombre de categoría válido.';
$string['errorcategoryparent'] = 'La categoría principal no existe.';
$string['errorcategorynotfound'] = 'La categoría no existe.';
$string['errorcategorynochange'] = 'Una actualización de categoría debe cambiar al menos un campo.';
$string['errorcategorycycle'] = 'Ese cambio crearía un ciclo de categorías.';
$string['errorcategorymergeself'] = 'Una categoría no puede fusionarse consigo misma.';
$string['categoryop_delete_empty'] = 'Disolver categoría vacía';
$string['categorychangesummary_delete_empty'] = 'Disolver categoría vacía';

// Movimientos de páginas (requisitos de la siguiente versión, taxonomía fase 1).
$string['pagemovechangesummary'] = 'Mover: {$a}';
$string['pagemoveto'] = 'Mover a la categoría: {$a}';
$string['conflict_pagemove'] = 'La página se movió o cambió después de preparar esta propuesta; recárguela y propóngala de nuevo.';
$string['errorpagemovesame'] = 'La página ya está en esa categoría.';
$string['event_page_moved'] = 'Página del manual movida';
$string['errortemprefunresolved'] = 'La categoría «{$a}» se propone en este change set pero aún no se ha creado; aplique primero su creación.';
