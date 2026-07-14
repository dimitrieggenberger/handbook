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

// Bootstrap mode and direct publish.
$string['bootstrapmode'] = 'Aufbaumodus';
$string['bootstrapmode_desc'] = 'Solange aktiviert, können Personen mit Veröffentlichungsrecht direkt aus dem Editor veröffentlichen, und Importe können sofort veröffentlichen — ohne Prüfwarteschlange. Der Versionsverlauf wird trotzdem erfasst. Nur für die anfängliche Befüllung gedacht: danach deaktivieren, damit der vollständige redaktionelle Workflow gilt.';
$string['saveandpublish'] = 'Speichern und veröffentlichen';
$string['bootstrapoffnotice'] = 'Der Aufbaumodus ist aus: Importierte Inhalte werden als Entwürfe angelegt und durchlaufen den normalen Prüfworkflow.';

// Seed import.
$string['importseed'] = 'Inhalte importieren';
$string['importfile'] = 'Seed-Datei (JSON)';
$string['publishonimport'] = 'Importierte Seiten sofort veröffentlichen';
$string['importcategoriescreated'] = 'Kategorien erstellt: {$a}';
$string['importcategoriesupdated'] = 'Kategorien aktualisiert: {$a}';
$string['importpagescreated'] = 'Seiten erstellt: {$a}';
$string['importpagesupdated'] = 'Seiten aktualisiert: {$a}';
$string['importpagespublished'] = 'Seiten veröffentlicht: {$a}';
$string['importrelationscreated'] = 'Beziehungen erstellt: {$a}';
$string['importerrors'] = 'Importhinweise';
$string['errorinvalidjson'] = 'Die hochgeladene Datei ist kein gültiges JSON.';

// Required-reading acknowledgements (spec 16).
$string['acknowledgereading'] = 'Lektüre bestätigen';
$string['readingconfirmation'] = 'Lesebestätigung';
$string['ackpendingnotice'] = 'Diese Seite ist Pflichtlektüre; die aktuelle Version (v{$a}) wurde noch nicht bestätigt.';
$string['ackreconfirmnotice'] = 'Diese Seite ist Pflichtlektüre; eine wesentlich geänderte Version (v{$a}) erfordert eine erneute Bestätigung.';
$string['ackconfirmednotice'] = 'Du hast die aktuelle Version (v{$a->version}) am {$a->date} bestätigt.';
$string['gotoconfirmation'] = 'Zur Lesebestätigung';
$string['ackcheckboxlabel'] = 'Ich habe die aktuelle Version von „{$a}“ gelesen und verstanden.';
$string['confirmreading'] = 'Lektüre bestätigen';
$string['ackrecorded'] = 'Deine Lesebestätigung wurde erfasst.';
$string['ackconfirmedrecord'] = 'Bestätigt am {$a->date} · veröffentlichte Version v{$a->version}';
$string['ackconfirmedshort'] = 'Bestätigt · {$a}';
$string['ackrecordinfo'] = 'Die Bestätigung wird mit Nutzer, veröffentlichter Version und Datum erfasst. Sie ersetzt keine Wissensüberprüfungen in Moodle.';
$string['requiresreack'] = 'Erfordert bei Veröffentlichung erneute Bestätigung';
$string['requiresreack_help'] = 'Bei wesentlich geänderten Versionen von Pflichtlektüre-Seiten ankreuzen: Nach der Veröffentlichung müssen alle die Lektüre erneut bestätigen. Für kleinere Korrekturen nicht ankreuzen.';
$string['errornotrequiredreading'] = 'Diese Seite ist nicht als Pflichtlektüre markiert.';

// Reading paths (spec 15).
$string['myreadingpath'] = 'Mein Lesepfad';
$string['managepaths'] = 'Lesepfade verwalten';
$string['newpath'] = 'Neuer Lesepfad';
$string['editpath'] = 'Lesepfad bearbeiten';
$string['pathname'] = 'Name des Pfads';
$string['schoolyear'] = 'Schuljahr';
$string['pathitems'] = 'Pfadelemente';
$string['sectionname'] = 'Abschnitt';
$string['additem'] = 'Element hinzufügen';
$string['pathsaved'] = 'Lesepfad gespeichert.';
$string['pathdeleted'] = 'Lesepfad gelöscht.';
$string['confirmdeletepath'] = 'Lesepfad „{$a}“ mit allen Elementen löschen? Erfasste Bestätigungen bleiben erhalten.';
$string['pathitemcount'] = '{$a} Elemente';
$string['nopathsyet'] = 'Noch keine aktiven Lesepfade.';
$string['emptypath'] = 'Dieser Lesepfad enthält noch keine Elemente.';
$string['pathprogress'] = '{$a->confirmed} von {$a->total} Pflichtseiten bestätigt';
$string['sectionprogress'] = '{$a->confirmed} von {$a->total} bestätigt';
$string['optionalitem'] = 'Optional';
$string['reconfirmitem'] = 'Erneut bestätigen: neue Version veröffentlicht';
$string['pendingitem'] = 'Ausstehend';
$string['readitem'] = 'Lektüre';
$string['connectedquiz'] = 'Moodle-Test';
$string['importpathscreated'] = 'Lesepfade erstellt: {$a}';
$string['importpathsupdated'] = 'Lesepfade aktualisiert: {$a}';

// Privacy API (acknowledgements).
$string['privacy:metadata:local_handbook_ack'] = 'Pflichtlektüre-Bestätigungen erfassen, welche Person welche veröffentlichte Version wann bestätigt hat.';
$string['privacy:metadata:local_handbook_ack:userid'] = 'Nutzer/in, die die Lektüre bestätigt hat.';
$string['privacy:metadata:local_handbook_ack:timeacknowledged'] = 'Zeitpunkt der Bestätigung.';

// Search.
$string['searchhandbook'] = 'Im Handbuch suchen';
$string['searchplaceholder'] = 'Verfahren, Richtlinien, Leitfäden und Formulare suchen…';
$string['alltypes'] = 'Alle Typen';
$string['allcategories'] = 'Alle Kategorien';
$string['searchresultcount'] = '{$a} Seiten gefunden';
$string['noresults'] = 'Keine Seite entspricht deiner Suche.';

// Revision history and comparison.
$string['comparerevisions'] = 'Versionen vergleichen';
$string['comparingversions'] = 'Vergleich v{$a->from} → v{$a->to}';
$string['difflegend'] = 'Ergänzungen sind hervorgehoben, Entferntes ist durchgestrichen.';
$string['comparewithpublished'] = 'Mit der veröffentlichten Version vergleichen';
$string['comparewithprevious'] = 'Mit der Basisversion vergleichen';
$string['viewchanges'] = 'Änderungen ansehen';
$string['nocontentdiff'] = 'Keine Textänderungen zwischen diesen Versionen.';
$string['createdby'] = 'Erstellt von';
$string['backtopage'] = 'Zurück zur Seite';

// Quality findings (spec 19).
$string['reportproblem'] = 'Fehler melden';
$string['reportintro'] = 'Beschreibe, was dir aufgefallen ist. Deine Meldung erzeugt einen Qualitätsbefund, erfasst mit deinem Nutzer und der veröffentlichten Version; die Redaktion sichtet ihn und dokumentiert die Lösung.';
$string['problemtype'] = 'Art des Problems';
$string['affectedsection'] = 'Betroffener Abschnitt (optional)';
$string['problemdescription'] = 'Beschreibung';
$string['reportplaceholder'] = 'Was ist dir aufgefallen und, falls bekannt, wie sollte es sein?';
$string['sendreport'] = 'Meldung senden';
$string['reportthanks'] = 'Danke. Befund #F-{$a} wurde erstellt und die Redaktion benachrichtigt.';
$string['managefindings'] = 'Qualitätsbefunde';
$string['nofindings'] = 'Keine Befunde entsprechen diesem Filter.';
$string['findingupdated'] = 'Befund aktualisiert.';
$string['resolutionnote'] = 'Lösungsnotiz';
$string['filteropenish'] = 'Offen + in Prüfung';
$string['findingtype_contradiction'] = 'Möglicher Widerspruch';
$string['findingtype_duplicate'] = 'Doppelter oder überlappender Inhalt';
$string['findingtype_ambiguous_responsibility'] = 'Unklare Zuständigkeit';
$string['findingtype_missing_escalation'] = 'Fehlender Eskalationsweg';
$string['findingtype_missing_record'] = 'Fehlendes Pflichtdokument oder Formular';
$string['findingtype_outdated_reference'] = 'Veraltete Angabe (Rolle, Datum oder System)';
$string['findingtype_incorrect_content'] = 'Falsche Information';
$string['findingtype_inconsistent_terminology'] = 'Uneinheitliche Terminologie';
$string['findingtype_broken_link'] = 'Defekter interner Link';
$string['findingtype_missing_owner'] = 'Fehlende/r Verantwortliche/r oder Freigebende/r';
$string['findingtype_review_overdue'] = 'Überprüfungsdatum überschritten';
$string['findingtype_procedure_without_policy'] = 'Verfahren ohne zugehörige Richtlinie';
$string['findingtype_policy_without_procedure'] = 'Richtlinie ohne umsetzbares Verfahren';
$string['findingtype_modality_difference'] = 'Unerklärter Unterschied zwischen Modalitäten';
$string['findingtype_assessment_outdated'] = 'Verbundener Test möglicherweise veraltet';
$string['findingtype_accessibility'] = 'Barrierefreiheits- oder Lesbarkeitsproblem';
$string['findingtype_other'] = 'Sonstiges';
$string['findingstatus_open'] = 'Offen';
$string['findingstatus_under_review'] = 'In Prüfung';
$string['findingstatus_accepted'] = 'Akzeptiert';
$string['findingstatus_dismissed'] = 'Verworfen';
$string['findingstatus_resolved'] = 'Gelöst';
$string['findingstatus_intentional_difference'] = 'Beabsichtigter Unterschied';
$string['scale_low'] = 'Niedrig';
$string['scale_medium'] = 'Mittel';
$string['scale_high'] = 'Hoch';

// Reports (spec 12.5, 15.3).
$string['reports'] = 'Berichte';
$string['reporthealth'] = 'Redaktionelle Gesundheit';
$string['reportpaths'] = 'Pfad-Fortschritt';
$string['reportpageacks'] = 'Bestätigungen pro Seite';
$string['reportoverdue'] = 'Überprüfung überfällig';
$string['reportmissingowner'] = 'Ohne Verantwortliche/n';
$string['reportneverpublished'] = 'Nie veröffentlicht';
$string['reportagingdrafts'] = 'Älteste Entwürfe in Prüfung';
$string['openfindingscount'] = 'Offene Qualitätsbefunde: {$a}';
$string['reportpathintro'] = 'Bestätigte Pflichtseiten pro Person ({$a} Pflichtseiten in diesem Pfad). Personal = Nutzer mit der Anzeigeberechtigung für das Handbuch.';
$string['pathprogressshort'] = 'Bestätigt';
$string['reportconfirmed'] = 'Bestätigt';
$string['reportpending'] = 'Ausstehend';
$string['norequiredpages'] = 'Noch keine veröffentlichten Pflichtlektüre-Seiten.';

// External API.
$string['errorexcludedpage'] = 'Diese Seite ist vom externen und KI-Zugriff ausgeschlossen.';
$string['errormetadataonly'] = 'Diese Seite ist für externen und KI-Zugriff auf Metadaten beschränkt; ihr Inhalt kann über die API weder gelesen noch bearbeitet werden.';
$string['errorbasemismatch'] = 'Die veröffentlichte Version hat sich seit dem Lesen geändert. Hole die aktuelle Version, bevor du einen Entwurf erstellst.';

// Errors.
$string['errorbootstrapoff'] = 'Direktes Veröffentlichen erfordert den Aufbaumodus (siehe Plugin-Einstellungen).';
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
