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
 * German strings for local_handbook.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Institutionelles Handbuch';

// Capabilities.
$string['handbook:view'] = 'Veröffentlichte Handbuchseiten ansehen';
$string['handbook:viewrestricted'] = 'Handbuchseiten mit eingeschränkter Zielgruppe ansehen';
$string['handbook:viewhistory'] = 'Versionsverlauf des Handbuchs ansehen';
$string['handbook:acknowledge'] = 'Pflichtlektüre-Bestätigungen erfassen';
$string['handbook:edit'] = 'Handbuchseiten und Entwürfe erstellen';
$string['handbook:review'] = 'Entwürfe prüfen und Änderungen anfordern';
$string['handbook:approve'] = 'Überarbeitungen zur Veröffentlichung freigeben';
$string['handbook:publish'] = 'Handbuchinhalte veröffentlichen, ersetzen, archivieren oder wiederherstellen';
$string['handbook:managecategories'] = 'Handbuchkategorien verwalten';
$string['handbook:managepaths'] = 'Lesepfade des Handbuchs verwalten';
$string['handbook:managefindings'] = 'Qualitätsbefunde des Handbuchs verwalten';
$string['handbook:viewreports'] = 'Handbuchberichte ansehen';
$string['handbook:manageapi'] = 'Externen Zugriff auf das Handbuch konfigurieren';
$string['handbook:apiaccess'] = 'Externe Servicefunktionen des Handbuchs verwenden';
$string['handbook:manage'] = 'Das Handbuch-Plugin administrieren';

// Navigation and page titles.
$string['handbookhome'] = 'Handbuch';
$string['managetools'] = 'Verwaltung';
$string['categories'] = 'Kategorien';
$string['category'] = 'Kategorie';
$string['reviewqueue'] = 'Prüfwarteschlange';
$string['newpage'] = 'Neue Seite';
$string['editpage'] = 'Seite bearbeiten';
$string['managecategories'] = 'Kategorien verwalten';

// Home page.
$string['recentlyupdated'] = 'Kürzlich aktualisiert';
$string['nocategoriesyet'] = 'Es wurden noch keine Kategorien erstellt.';
$string['nopagesyet'] = 'Noch keine veröffentlichten Seiten.';
$string['pagecount'] = '{$a} Seiten';
$string['pagecountone'] = '1 Seite';
$string['subcategories'] = 'Unterkategorien';
$string['pagesincategory'] = 'Seiten in dieser Kategorie';
$string['emptycategory'] = 'Diese Kategorie enthält noch keine veröffentlichten Seiten.';

// Reader view.
$string['summary'] = 'Zusammenfassung';
$string['effectivedate'] = 'Gültig seit';
$string['lastupdated'] = 'Zuletzt aktualisiert';
$string['publishedversion'] = 'Veröffentlichte Version';
$string['reviewdate'] = 'Nächste Überprüfung';
$string['responsiblearea'] = 'Zuständiger Bereich';
$string['owner'] = 'Verantwortlich';
$string['approver'] = 'Freigabe';
$string['pagedetails'] = 'Seitensteckbrief';
$string['relatedpages'] = 'Verwandte Seiten';
$string['contenttype'] = 'Inhaltstyp';
$string['authoritylevel'] = 'Verbindlichkeit';
$string['scope'] = 'Geltungsbereich';
$string['audience'] = 'Zielgruppe';
$string['languagelabel'] = 'Sprache';
$string['requiredreading'] = 'Pflichtlektüre';
$string['notpublished'] = 'Diese Seite hat noch keine veröffentlichte Version.';
$string['draftnotice'] = 'Für diese Seite existiert ein neuerer Entwurf (v{$a->version}, {$a->status}).';
$string['revisionhistory'] = 'Versionsverlauf';
$string['foreditors'] = 'Für Redakteure';
$string['viewrevision'] = 'Ansehen';
$string['archivedpage'] = 'Diese Seite ist archiviert. Sie dient nur noch als historische Referenz.';

// Content types (specification 10.1).
$string['contenttype_policy'] = 'Richtlinie';
$string['contenttype_procedure'] = 'Verfahren';
$string['contenttype_standard'] = 'Standard';
$string['contenttype_guideline'] = 'Leitfaden';
$string['contenttype_quickguide'] = 'Kurzanleitung';
$string['contenttype_template'] = 'Vorlage';
$string['contenttype_example'] = 'Beispiel';
$string['contenttype_roledescription'] = 'Funktionsbeschreibung';

// Criticality (specification 10.1).
$string['criticality'] = 'Kritikalität';
$string['criticality_reference'] = 'Referenz';
$string['criticality_operational'] = 'Operativ';
$string['criticality_mandatory'] = 'Verpflichtend';
$string['criticality_safetycritical'] = 'Sicherheitskritisch';

// AI access (specification 10.1).
$string['aiaccess'] = 'KI-Zugriff';
$string['aiaccess_full'] = 'Vollständiger Inhalt';
$string['aiaccess_metadata_only'] = 'Nur Metadaten';
$string['aiaccess_excluded'] = 'Ausgeschlossen';

// Authority levels (specification 10.3).
$string['authority_1'] = 'Stufe 1 · Institutionsweite Richtlinie';
$string['authority_2'] = 'Stufe 2 · Offizielles Verfahren';
$string['authority_3'] = 'Stufe 3 · Abteilungsstandard';
$string['authority_4'] = 'Stufe 4 · Operativer Leitfaden';
$string['authority_5'] = 'Stufe 5 · Vorlage';
$string['authority_6'] = 'Stufe 6 · Beispielmaterial';

// Revision statuses (specification 11.1).
$string['status_draft'] = 'Entwurf';
$string['status_in_review'] = 'In Prüfung';
$string['status_changes_requested'] = 'Änderungen angefordert';
$string['status_approved'] = 'Freigegeben';
$string['status_published'] = 'Veröffentlicht';
$string['status_superseded'] = 'Ersetzt';
$string['status_rejected'] = 'Abgelehnt';

// Editor and workflow.
$string['pagetitle'] = 'Titel';
$string['pageslug'] = 'Slug';
$string['pageslug_help'] = 'Stabiler URL-Bezeichner: Kleinbuchstaben, Zahlen und Bindestriche. Er sollte nach der Veröffentlichung nicht geändert werden, da Links und die externe API ihn verwenden.';
$string['pagecontent'] = 'Seiteninhalt';
$string['changesummary'] = 'Änderungszusammenfassung';
$string['changesummary_help'] = 'Beim Einreichen zur Prüfung erforderlich: eine kurze Beschreibung, was sich geändert hat und warum.';
$string['savedraft'] = 'Entwurf speichern';
$string['submitforreview'] = 'Zur Prüfung einreichen';
$string['requestchanges'] = 'Änderungen anfordern';
$string['approve'] = 'Freigeben';
$string['publish'] = 'Veröffentlichen';
$string['reviewnote'] = 'Prüfnotiz';
$string['version'] = 'Version';
$string['versionnumber'] = 'v{$a}';
$string['basedon'] = 'Basiert auf v{$a}';
$string['draftsaved'] = 'Entwurf gespeichert.';
$string['draftsubmitted'] = 'Entwurf zur Prüfung eingereicht.';
$string['changesrequested'] = 'Änderungen angefordert; der Entwurf ging zurück an die Autorin oder den Autor.';
$string['revisionapproved'] = 'Überarbeitung freigegeben.';
$string['revisionpublished'] = 'Überarbeitung veröffentlicht.';
$string['nodraftsinreview'] = 'Keine Entwürfe warten auf Prüfung.';
$string['submittedby'] = 'Eingereicht von {$a->name} am {$a->date}';
$string['confirmpublish'] = 'Version v{$a} veröffentlichen und die aktuell veröffentlichte Version ersetzen?';

// Category management.
$string['categoryname'] = 'Kategoriename';
$string['categoryslug'] = 'Slug';
$string['categorydescription'] = 'Beschreibung';
$string['categoryparent'] = 'Übergeordnete Kategorie';
$string['categoryvisible'] = 'Sichtbar';
$string['topcategory'] = '(oberste Ebene)';
$string['newcategory'] = 'Neue Kategorie';
$string['editcategory'] = 'Kategorie bearbeiten';
$string['deletecategory'] = 'Kategorie löschen';
$string['categorysaved'] = 'Kategorie gespeichert.';
$string['categorydeleted'] = 'Kategorie gelöscht.';
$string['confirmdeletecategory'] = 'Kategorie „{$a}“ löschen? Das ist nur möglich, solange sie keine Seiten und keine Unterkategorien enthält.';
$string['categorynotempty'] = 'Diese Kategorie enthält noch Seiten oder Unterkategorien und kann nicht gelöscht werden.';

// Errors.
$string['errorpagenotfound'] = 'Handbuchseite nicht gefunden.';
$string['errorcategorynotfound'] = 'Handbuchkategorie nicht gefunden.';
$string['errorslugexists'] = 'Dieser Slug wird bereits verwendet.';
$string['errordraftexists'] = 'Für diese Seite existiert bereits ein unveröffentlichter Entwurf. Bearbeite diesen Entwurf, statt einen neuen zu erstellen.';
$string['errornodraft'] = 'Für diese Seite existiert kein bearbeitbarer Entwurf.';
$string['errorrevisionconflict'] = 'Die Überarbeitung wurde während der Bearbeitung von jemand anderem geändert. Prüfe die neuere Version, bevor du erneut speicherst.';
$string['errorworkflowstate'] = 'Diese Aktion ist im aktuellen Workflow-Status der Überarbeitung nicht erlaubt.';

// Privacy API.
$string['privacy:metadata:local_handbook_revision'] = 'Handbuch-Überarbeitungen erfassen, welche Nutzer sie erstellt, geändert, geprüft, freigegeben oder veröffentlicht haben.';
$string['privacy:metadata:local_handbook_revision:createdby'] = 'Nutzer/in, die die Überarbeitung erstellt hat.';
$string['privacy:metadata:local_handbook_revision:modifiedby'] = 'Nutzer/in, die die Überarbeitung zuletzt geändert hat.';
$string['privacy:metadata:local_handbook_revision:publishedby'] = 'Nutzer/in, die die Überarbeitung veröffentlicht hat.';
$string['privacy:metadata:local_handbook_page'] = 'Handbuchseiten erfassen Verantwortliche, Freigebende sowie erstellende und ändernde Nutzer.';
$string['privacy:metadata:local_handbook_page:owneruserid'] = 'Nutzer/in, die für die Richtigkeit der Seite verantwortlich ist.';
$string['privacy:metadata:local_handbook_category'] = 'Handbuchkategorien erfassen die Nutzer, die sie erstellt und geändert haben.';
