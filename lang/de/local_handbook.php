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
$string['handbook:managechangesets'] = 'Änderungssätze des Handbuchs prüfen und bearbeiten';
$string['handbook:viewreports'] = 'Handbuchberichte ansehen';
$string['handbook:manageapi'] = 'Externen Zugriff auf das Handbuch konfigurieren';
$string['handbook:apiaccess'] = 'Externe Servicefunktionen des Handbuchs verwenden';
$string['handbook:apiproposemetadata'] = 'Metadaten-Änderungen (Steckbrief) des Handbuchs über die API vorschlagen';
$string['handbook:apiproposerelations'] = 'Beziehungsänderungen zwischen Handbuchseiten über die API vorschlagen';
$string['handbook:apiproposelifecycle'] = 'Archivierungs-/Wiederherstellungsaktionen des Handbuchs über die API vorschlagen';
$string['handbook:apiproposetaxonomy'] = 'Kategorieänderungen des Handbuchs über die API vorschlagen';
$string['handbook:apiproposepaths'] = 'Änderungen an Lesepfaden des Handbuchs über die API vorschlagen';
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
$string['approveall'] = 'Alle freigeben ({$a})';
$string['confirmapproveall'] = 'Alle {$a} derzeit in Prüfung befindlichen Entwürfe freigeben? Sie wechseln in den Status „Freigegeben“ und sind bereit zur Veröffentlichung.';
$string['allrevisionsapproved'] = '{$a} Überarbeitungen freigegeben.';
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
$string['categoryicon'] = 'Symbol';
$string['categoryicon_help'] = 'Font-Awesome-Symbolklasse (Solid), z. B. fa-children, fa-landmark, fa-sitemap (siehe fontawesome.com/icons, Set Free/Solid). Leer lassen für das Standard-Ordnersymbol.';
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

// Bannerbild (Kategoriekarten + Artikelkopf).
$string['bannerimage'] = 'Bannerbild';
$string['bannerimage_help'] = 'Optional. Ein Querformat-Bild, das auf der Kategoriekarte (16:9) und im Artikelkopf (3:1) angezeigt wird. Das Bild wird automatisch zugeschnitten und zentriert — kein manueller Zuschnitt nötig. Ohne Bild zeigt die Karte einen dezenten Platzhalter je nach Inhaltstyp.';

// Inhalts-Stilrichtlinie (hb-*-Muster).
// Automatische Querverweise.
$string['autolink'] = 'Seitentitel automatisch verlinken';
$string['autolink_desc'] = 'Querverweise im Wikipedia-Stil: Erwähnt ein Artikel den exakten Titel einer anderen veröffentlichten Handbuchseite, wird die erste Erwähnung zu einem Link auf diese Seite. Nur bei der Anzeige angewendet — der gespeicherte Inhalt wird nie verändert, Links folgen Umbenennungen und Archivierungen automatisch, und beim Deaktivieren verschwinden sie sofort überall. Es zählt der vollständige Titel, ohne Groß-/Kleinschreibung; in Überschriften, bestehenden Links und den Normverweis-Mustern werden keine Links ergänzt.';

// Bildoptimierung.
$string['imageoptimize'] = 'Bilder beim Speichern optimieren';
$string['imageoptimize_desc'] = 'Beim Speichern einer Seite werden übergroße Bilder (Banner und Artikelbilder, einschließlich eingefügter Screenshots) auf die Maximalbreite verkleinert, gemäß EXIF gedreht, von Metadaten befreit und neu kodiert. Bilder werden nie vergrößert, Dateinamen ändern sich nie, und ein Ersatz wird nur behalten, wenn er kleiner als das Original ist. Screenshots mit Transparenz bleiben PNG; Fotos werden als JPEG (neu) kodiert.';
$string['imagemaxwidth'] = 'Maximale Bildbreite (px)';
$string['imagemaxwidth_desc'] = 'Breitere Bilder werden beim Speichern auf diese Breite verkleinert. 1500 deckt das breiteste Handbuch-Layout ab.';
$string['imagejpegquality'] = 'JPEG-Qualität';
$string['imagejpegquality_desc'] = 'Qualität (50–100) für die JPEG-Neukodierung. 85 ist am Bildschirm visuell nicht von höheren Werten zu unterscheiden und deutlich kleiner.';
$string['manageimages'] = 'Bilder optimieren';
$string['imagesintro'] = 'Neue Bilder werden beim Speichern einer Seite automatisch optimiert (Maximalbreite {$a->width}px, JPEG-Qualität {$a->quality}). Diese Seite wendet dieselbe Behandlung auf Bilder an, die vor dem Optimierer hochgeladen wurden: Banner und Artikelbilder werden verkleinert, gemäß EXIF gedreht, von Metadaten befreit und neu kodiert — Dateinamen ändern sich nie, die Seiten funktionieren also weiter.';
$string['imageoptimizeoff'] = 'Die automatische Optimierung beim Speichern ist in den Plugin-Einstellungen derzeit deaktiviert; die Schaltfläche unten funktioniert dennoch als einmaliger Lauf.';
$string['imagesreport'] = '{$a->scanned} Bilder geprüft, {$a->optimized} optimiert. Gesamtgröße {$a->before} → {$a->after}, Ersparnis {$a->saved}.';
$string['imagesarea'] = 'Dateibereich';
$string['imagescount'] = 'Bilder';
$string['imagessize'] = 'Größe';
$string['imagesareabanners'] = 'Bannerbilder';
$string['imagesareacontent'] = 'Artikelbilder (alle Revisionen)';
$string['imagesoptimizenow'] = 'Alle Bilder jetzt optimieren';
$string['imagesnote'] = 'GIF- (möglicherweise animiert) und SVG-Dateien werden nie angetastet. Bilder, die bereits auf oder unter der Maximalbreite liegen, werden nur neu kodiert, wenn das mindestens 10 % spart — kleine, effiziente Dateien bleiben unverändert. Historische Revisionen sind eingeschlossen; der Lauf kann bei einem großen Handbuch einen Moment dauern.';

$string['styleguide'] = 'Inhalts-Stilrichtlinie';
$string['styleguideintro'] = 'Wiederverwendbare Formatmuster für Artikel. Öffnen Sie eine Seite im Bearbeitungsmodus, wechseln Sie im Editor zur HTML-Quelltextansicht und fügen Sie ein Muster von unten ein — den Text anpassen. Derselbe Katalog steht der Handbuch-KI zur Verfügung, sodass auch generierte Entwürfe diese Muster verwenden.';
$string['styleguidepatterns'] = 'Muster';
$string['styleguidecopy'] = 'Kopieren Sie dieses HTML in die HTML-Quelltextansicht des Editors:';
$string['sgtitle_steps'] = 'Mehrstufiges Verfahren';
$string['sguse_steps'] = 'Nummerierte Schritte mit optionalen Rollen-Tags, Buchstaben-Unterschritten und Hinweisen. Das Rückgrat jedes Verfahrens.';
$string['sgtitle_callouts'] = 'Hinweise: Notiz, Tipp, Warnung, Wichtig';
$string['sguse_callouts'] = 'Hebt eine Bemerkung vom Text ab. Vier Stufen: Notiz (Kontext), Tipp (gute Praxis), Warnung (Risiko), Wichtig (nie auslassen).';
$string['sgtitle_branches'] = 'Entscheidungszweige';
$string['sguse_branches'] = 'Nebeneinanderstehende „wenn dies, dann das“-Optionen innerhalb eines Verfahrens.';
$string['sgtitle_compact'] = 'Kurzanleitungs-Schritte';
$string['sguse_compact'] = 'Eine kompaktere, checklistenartige Version der Schrittliste für Kurzanleitungen.';
$string['sgtitle_org'] = 'Organigramm';
$string['sguse_org'] = 'Leitung und Berichtslinien. Ein Team (hb-org-team) ist eine flache Gruppe von Gleichrangigen; eine Einheit (hb-org-node) ist ein einzelnes Feld.';
$string['sgtitle_roles'] = 'Rollen & Verantwortlichkeiten';
$string['sguse_roles'] = 'Karten, die jede Rolle, ihre Inhaberin oder ihren Inhaber und die Aufgaben nennen.';
$string['sgtitle_escalation'] = 'Eskalationsleiter';
$string['sguse_escalation'] = 'Der geordnete Weg, ein Anliegen vorzubringen — an wen man sich der Reihe nach wendet.';
$string['sgtitle_dodont'] = 'Erwünscht / Unerwünscht';
$string['sguse_dodont'] = 'Zwei Spalten, die erwartetes und inakzeptables Verhalten gegenüberstellen.';
$string['sgtitle_timeline'] = 'Zeitleiste / Phasen';
$string['sguse_timeline'] = 'Datierte Meilensteine in Reihenfolge; fügen Sie abgeschlossenen Punkten die Klasse is-done hinzu.';
$string['sgtitle_contact'] = 'Kontakte & Notfall';
$string['sguse_contact'] = 'Wen man wann erreicht. Fügen Sie is-emergency für eine rote, gut sichtbare Karte hinzu.';
$string['sgtitle_define'] = 'Definition / Glossar';
$string['sguse_define'] = 'Definiert institutionelles Vokabular, als Block oder inline (hb-term).';
$string['sgtitle_matrix'] = 'Verantwortlichkeitsmatrix (RACI)';
$string['sguse_matrix'] = 'Wer ist je Aufgabe verantwortlich, rechenschaftspflichtig, konsultiert oder informiert.';
$string['sgtitle_figure'] = 'Abbildung mit Bildunterschrift';
$string['sguse_figure'] = 'Ein gerahmtes Bild oder Diagramm im Text mit Bildunterschrift. Ersetzen Sie das src durch ein hochgeladenes Bild.';
$string['sgtitle_keyvalue'] = 'Datenblatt (Ficha)';
$string['sguse_keyvalue'] = 'Ein kompaktes Bezeichnung→Wert-Blatt für ein Gremium, eine Rolle oder ein Objekt.';
$string['sgtitle_checklist'] = 'Checkliste';
$string['sguse_checklist'] = 'Eine druckbare Checkliste für ein Verfahren. Die Kästchen sind für Druck/Arbeitsgebrauch; Häkchen werden nicht gespeichert.';
$string['sgtitle_email'] = 'E-Mail-Beispiel (Mailclient-Ansicht)';
$string['sguse_email'] = 'Eine Beispiel-E-Mail so zeigen, wie das Personal sie am Bildschirm sieht: Kopffelder (Von/An/CC/Betreff), Text, Anhang-Chips und die institutionelle Signatur. is-good oder is-bad ergänzen das „así sí / así no“-Abzeichen; für neutrale Beispiele beide weglassen. Nur erfundene Namen und Adressen — nie echte Korrespondenz einfügen.';
$string['sgtitle_chat'] = 'Chat-Beispiel (WhatsApp-Ansicht)';
$string['sguse_chat'] = 'Ein Chat-Verlauf in Telefonbreite: chat-title und chat-day sind optional; Blasen sind is-in (weiß, links) oder is-out (grün, rechts), mit optionalem Absender (who) und Uhrzeit (when). Einzelne Blasen für Stil-Lektionen mit is-good / is-bad markieren — mit einem chat-verdict-Chip darüber. Nur erfundene Namen — nie echte Unterhaltungen einfügen.';
$string['sgtitle_dialogue'] = 'Gesprächsleitfaden';
$string['sguse_dialogue'] = 'Sprecherbeschriftete Repliken wie in einem Drehbuch, für Telefonprotokoll, Deeskalation und schwierige Gespräche. is-staff hebt institutionelle Repliken hervor; dlg-note ist eine kursive Regieanweisung; is-good / is-bad ergänzen eine farbige Leiste und einen Urteils-Chip pro Replik. Für Anrufskripte is-call am Container ergänzen: der Kopf erhält ein Telefon-Symbol, und die Repliken sollten beide Stimmen abwechseln (was die Familie sagt, was das Personal sagt). Nur erfundene Namen.';
$string['sgtitle_acta'] = 'Agenda & Protokoll (Acta)';
$string['sguse_acta'] = 'Das Sitzungspaar. hb-agenda: Zeilen mit Uhrzeit (ag-time · ag-topic · ag-who). hb-acta: Kopfblock (Teilnehmende, Vorsitz, Abwesenheiten) und eine Beschlusstabelle, in der jeder Beschluss Was, Wer (Responsable) und Bis wann (Fecha límite) trägt — die Nummerierung schreibt die Autorin/der Autor (14.1 = Acta 14, Punkt 1); ac-done kennzeichnet erledigte Beschlüsse.';
$string['sgtitle_letter'] = 'Formeller Brief / Rundschreiben';
$string['sguse_letter'] = 'Ein Briefkopf-Dokument in Serifenschrift, wie es gedruckt wird: Briefkopf (lt-head), Ort und Datum (lt-place), Referenzzeile (lt-ref), formeller Text und Unterschriftsblock (lt-sign). Für Rundschreiben, Bescheinigungen und offizielle Mitteilungen.';
$string['sgtitle_acc'] = 'Akkordeons (Vorlagen-Bibliotheken)';
$string['sguse_acc'] = 'Für lange Listenseiten — Bibliotheken von Kommunikationsvorlagen, FAQ-artige Sammlungen. Jedes hb-acc ist ein Eintrag: acc-title (der Name, mit optionalem acc-chip als Kanalhinweis) plus acc-body (der Inhalt). Zusammengehörige Einträge in hb-acc-group bündeln: Gruppen ab zwei Einträgen erhalten automatisch eine Alle-ausklappen/-einklappen-Steuerung. Die Einschübe starten geschlossen, öffnen mit sanfter Animation und sind per Tastatur bedienbar; ohne JavaScript und in der Druckansicht wird alles offen dargestellt. Ein hb-keyvalue in einem Einschub wird automatisch zu einer schmalen Ficha kompaktiert. Hinweis: Die Browsersuche findet keinen Text in geschlossenen Einschüben — dafür gibt es die Alle-ausklappen-Steuerung.';
$string['accexpandall'] = 'Alle ausklappen';
$string['acccollapseall'] = 'Alle einklappen';
$string['sgtitle_course'] = 'Simulierter Kursabschnitt';
$string['sguse_course'] = 'Eine stilisierte Nachbildung der Kursseite der Plattform für Artikel zur Kursstruktur: Abschnitte (crs-sec; is-collapsed, is-empty für den gedämpften Neukurs-Zustand, Farbtöne is-green/is-red/is-blue), Wochen-Unterabschnitte (crs-week; is-collapsed), Aktivitätszeilen (crs-act mit is-page / is-pdf / is-pptx / is-assign / is-url / is-quiz / is-forum / is-video, act-chip für die Dateityp-Pille, is-hidden + crs-badge für verborgene Elemente), Metazeilen (is-dates mit einem oder beiden Daten, is-lock für Verfügbarkeitsbedingungen) und crs-desc für die Inline-Beschreibung einer Aktivität (z. B. Prüfungsanweisungen). Zeilen mit is-good / is-bad und crs-note für Strukturstandards annotieren. Nur illustrativ — für die exakten Pixel eines echten Kurses hb-figure mit einem Screenshot verwenden.';
$string['sgtitle_feedback'] = 'Schriftliches Feedback-Feld';
$string['sguse_feedback'] = 'Ein Muster für jedes schriftliche Feedback-Feld: Hausaufgabenkommentare, Zeugnisbemerkungen, Lehrkräfte-Evaluationen, Beobachtungsnotizen. Der fb-type-Chip benennt den Kontext (Tarea / Informe / Evaluación docente — Freitext), fb-meta gibt die Richtung an (z. B. Docente → Estudiante), der Kommentar erscheint in einem ausgefüllten Feld (fb-field), fb-grade ist ein optionaler Noten-Chip, und is-good / is-bad ergänzen ein Urteils-Abzeichen für kontrastierende Beispiele. Nur erfundene Namen.';
$string['pathnext'] = 'Pfad fortsetzen';
$string['pathnextup'] = 'Weiter: {$a}';
$string['pathnextconfirm'] = 'Bestätigen Sie oben Ihre Lektüre, um den Pfad fortzusetzen.';
$string['pathend'] = 'Sie haben das Ende dieses Pfads erreicht.';
$string['viewfullpath'] = 'Den ganzen Pfad ansehen';
$string['sgtitle_next'] = 'Weiter- / Zurück-Links';
$string['sguse_next'] = 'Handgeschriebene Fortsetzung am Artikelende: eine hb-next-Karte (z. B. nächstes Kapitel des Reglements), eine hb-next-group bei mehreren Optionen (z. B. nach Rolle), is-prev für zurück. Für Lesepfade NICHT nötig: das Plugin zeigt den Weiter-im-Pfad-Button automatisch.';
$string['sgtitle_refs'] = 'Normative Querverweise';
$string['sguse_refs'] = 'Verknüpft einen Artikel mit den exakten Artikeln, die das Thema in einem anderen Dokument regeln. Vier Stufen: hb-ref (Inline-§-Chip für ein entscheidendes Zitat), hb-seealso (eine \"Ver normativa\"-Zeile nach einem Abschnitt — Standard im Text), hb-refbox (Karte, wenn die Rechtsgrundlage erklärt werden muss), hb-refs (Block am Artikelende, nach Dokument gruppiert — Standard zum Abschluss). Links sind einfache Anker auf slug#art-N. Dokument-Badges: hb-doc mit is-ri / is-rp / is-ed, ohne Modifikator für andere Quellen. Dieselben Muster eignen sich für BELIEBIGE verwandte Seiten (nicht nur Reglemente): neutrales hb-doc-Badge oder eigene Beschriftung, Link zur Seite (mit oder ohne #art-N-Anker).';
$string['sgtitle_legal'] = 'Reglement / Rechtsartikel';
$string['sguse_legal'] = 'Für Reglemente und normative Dokumente: Titel und nummerierte Abschnitte als Überschriften (sie speisen das Seiteninhaltsverzeichnis), vom Autor geschriebene Artikelnummern (nie automatisch — sie sind kanonisch), Buchstaben-Literale, Gültigkeitsnotizen und aufgehobene Artikel. Jeder Artikel trägt id=\"art-N\" für Direktlinks. Empfohlen: eine Handbuchseite pro Titel. Nummerierte Klauseln (Numerales) verwenden ein einfaches ol mit li value=\"N\" — die Nummerierung ist nativ, kanonische Nummern bleiben erhalten und richten sich automatisch an der Artikelspalte aus; Buchstaben-Klauseln verwenden hb-literals; Datenblätter und Skalen hb-keyvalue.';

// Lesepfad-Empfehlungen und Audits (Spez. 10).
$string['recommendations'] = 'Pfad-Empfehlungen';
$string['coverage'] = 'Lesepfad-Abdeckung';
$string['audit'] = 'Audit';
$string['openrecommendations'] = 'Offene Empfehlungen';
$string['norecommendations'] = 'Keine offenen Empfehlungen.';
$string['coverage_covered'] = 'Seiten in einem Pfad';
$string['coverage_orphans'] = 'Verwaiste Seiten';
$string['coverage_required'] = 'Pflicht abgedeckt';
$string['coverage_overlap'] = 'In mehreren Pfaden';
$string['coverage_paths'] = 'Aktive Pfade';
$string['recchangesettitle'] = 'Empfehlung: {$a}';
$string['recaccepted'] = 'Ein Pfad-Revisionsentwurf wurde in einem Change-Set zur Prüfung vorbereitet.';
$string['recupdated'] = 'Empfehlung aktualisiert.';
$string['recaccept'] = 'Übernehmen (Entwurf ins Change-Set)';
$string['recdismiss'] = 'Verwerfen';
$string['rectopath'] = 'zu „{$a}“';
$string['source_ai'] = 'KI';
$string['errorrectype'] = 'Unbekannter Empfehlungstyp.';
$string['errorrecstatus'] = 'Unbekannter Empfehlungsstatus.';
$string['errorrecnopath'] = 'Diese Empfehlung ist keinem Pfad zugeordnet und kann nicht direkt übernommen werden.';
$string['recreason_relation'] = 'Diese Seite „{$a->relation}“ „{$a->target}“, die bereits in diesem Pfad ist.';
$string['recreason_category'] = 'Gleiche Kategorie wie Seiten, die bereits in diesem Pfad sind.';
$string['rectype_add'] = 'Zum Pfad hinzufügen';
$string['rectype_remove'] = 'Aus dem Pfad entfernen';
$string['rectype_reorder'] = 'Im Pfad umsortieren';
$string['rectype_replace'] = 'Im Pfad ersetzen';
$string['rectype_split_path'] = 'Pfad aufteilen';
$string['rectype_merge_paths'] = 'Pfade zusammenführen';
$string['rectype_update_required_status'] = 'Pflichtstatus ändern';
$string['recstatus_open'] = 'Offen';
$string['recstatus_accepted'] = 'Übernommen';
$string['recstatus_dismissed'] = 'Verworfen';
$string['recstatus_deferred'] = 'Zurückgestellt';
$string['recstatus_already_covered'] = 'Bereits abgedeckt';
$string['recstatus_intentional_omission'] = 'Bewusste Auslassung';
$string['recstatus_resolved'] = 'Erledigt';
$string['auditorphanrequired'] = 'Pflichtlektüre, aber in keinem aktiven Pfad.';
$string['auditreviewdue'] = 'Pfad hat sein Überprüfungsdatum überschritten.';
$string['auditnorequired'] = 'Aktiver Pfad hat keine Pflichtelemente.';
$string['auditoversized'] = 'Großer Pfad ({$a} Elemente) — Aufteilung erwägen.';

// Geteilter Leseabschluss für pfad-erforderliche Artikel (Spez. 8).
$string['readingcompletion'] = 'Leseabschluss';
$string['markasread'] = 'Als gelesen markieren';
$string['completioncheckboxlabel'] = 'Ich habe die aktuelle Version von „{$a}“ gelesen.';
$string['completedrecord'] = 'Gelesen am {$a->date} · veröffentlichte Version v{$a->version}';
$string['completionreread'] = 'Dieser Artikel hat sich seit Ihrem letzten Lesen geändert (jetzt v{$a}). Bitte lesen Sie ihn erneut.';
$string['completioninfo'] = 'Einen Artikel als gelesen zu markieren zählt für jeden Lesepfad, der ihn enthält. Einmal lesen genügt; eine wesentlich geänderte Version kann ein erneutes Lesen verlangen.';

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
$string['pathcohorts'] = 'Zielgruppen-Kohorten';
$string['pathroles'] = 'Zielgruppen-Rollen (Systemebene)';
$string['pathaudience'] = 'Zielgruppe des Pfads';
$string['pathaudience_help'] = 'Beide leer lassen, um den Pfad allen Handbuch-Berechtigten zu zeigen. Andernfalls ist der Pfad sichtbar für Mitglieder EINER ausgewählten Kohorte oder Inhaber EINER ausgewählten Rolle auf Systemebene. Verwaltende sehen immer alle Pfade; der Fortschrittsbericht umfasst genau diese Zielgruppe.';
$string['errorpathnotvisible'] = 'Dieser Lesepfad ist für deine Rolle oder Gruppen nicht verfügbar.';
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
$string['viewallresults'] = 'Alle {$a} Ergebnisse anzeigen';
$string['opencategorylink'] = 'Kategorie öffnen';
$string['openall'] = 'Alle öffnen';
$string['closeall'] = 'Alle schließen';

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

// Home personalization and reader polish (spec 12.1, 12.2).
$string['pendingreadingcard'] = 'Ausstehende Pflichtlektüre';
$string['noackpending'] = 'Alle Pflichtlektüre ist bestätigt.';
$string['continuepath'] = 'Pfad fortsetzen';
$string['continuereading'] = 'Lesen und bestätigen';
$string['currentsection'] = 'Aktueller Abschnitt';
$string['editorialwork'] = 'Redaktionelle Arbeit';
$string['draftsawaiting'] = 'Entwürfe in der Prüfwarteschlange: {$a}';
$string['changesrequestedcount'] = 'Änderungen angefordert: {$a}';
$string['overduereviewcount'] = 'Überfällige Überprüfungen: {$a}';
$string['safetycriticalpages'] = 'Sicherheitskritische Seiten';
$string['quickguides'] = 'Kurzanleitungen';
$string['formstemplates'] = 'Formulare und Vorlagen';
$string['viewall'] = 'Alle ansehen';
$string['onthispage'] = 'Auf dieser Seite';
$string['printpage'] = 'Drucken';
$string['printfooter'] = 'Gedruckt am {$a->date}. Gedruckte Kopien veralten: die gültige Version liegt unter {$a->url}';
$string['authoritynote'] = 'Diese Kurzanleitung fasst {$a} zusammen. Bei Abweichungen gilt das vollständige Verfahren.';
$string['partofpath'] = 'Teil des Lesepfads: {$a}';

// Relation type labels (spec 9.2): forward and reverse.
$string['relation_relatedto'] = 'Verwandt mit';
$string['relationrev_relatedto'] = 'Verwandt mit';
$string['relation_dependson'] = 'Setzt voraus';
$string['relationrev_dependson'] = 'Vorausgesetzt von';
$string['relation_implements'] = 'Setzt um';
$string['relationrev_implements'] = 'Umgesetzt durch';
$string['relation_replaces'] = 'Ersetzt';
$string['relationrev_replaces'] = 'Ersetzt durch';
$string['relation_supersedes'] = 'Löst ab';
$string['relationrev_supersedes'] = 'Abgelöst durch';
$string['relation_exceptionto'] = 'Ausnahme zu';
$string['relationrev_exceptionto'] = 'Ausnahme definiert in';
$string['relation_procedurefor'] = 'Verfahren für';
$string['relationrev_procedurefor'] = 'Zugehöriges Verfahren';
$string['relation_quickguidefor'] = 'Kurzanleitung für';
$string['relationrev_quickguidefor'] = 'Kurzanleitung';
$string['relation_templatefor'] = 'Vorlage für';
$string['relationrev_templatefor'] = 'Vorlage';
$string['relation_assessmentfor'] = 'Überprüfung für';
$string['relationrev_assessmentfor'] = 'Verbundene Überprüfung';
$string['relation_translationof'] = 'Übersetzung von';
$string['relationrev_translationof'] = 'Übersetzt als';

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

// Notifications and scheduled tasks (spec 21).
$string['messageprovider:draftsubmitted'] = 'Handbuch-Entwurf zur Prüfung eingereicht';
$string['messageprovider:changesrequested'] = 'Änderungen an deinem Handbuch-Entwurf angefordert';
$string['messageprovider:findingcreated'] = 'Neuer Qualitätsbefund im Handbuch';
$string['messageprovider:reviewdue'] = 'Überprüfung einer Handbuchseite fällig';
$string['notifydraftsubmitted_subject'] = 'Entwurf zur Prüfung: {$a->title} (v{$a->version})';
$string['notifydraftsubmitted_body'] = 'Ein Entwurf von „{$a->title}“ (v{$a->version}) wurde zur Prüfung eingereicht. Änderungszusammenfassung: {$a->summary}';
$string['notifychangesrequested_subject'] = 'Änderungen angefordert: {$a->title} (v{$a->version})';
$string['notifychangesrequested_body'] = 'Dein Entwurf von „{$a->title}“ (v{$a->version}) kam mit folgender Notiz zurück: {$a->note}';
$string['notifyfindingcreated_subject'] = 'Neuer Qualitätsbefund #F-{$a->id}: {$a->type}';
$string['notifyfindingcreated_body'] = 'Ein neuer Qualitätsbefund wurde gemeldet: {$a->summary}';
$string['notifyreviewdue_subject'] = 'Überprüfung fällig: {$a->title}';
$string['notifyreviewdue_body'] = 'Die Seite „{$a->title}“, für die du verantwortlich bist, erreicht ihr Überprüfungsdatum am {$a->reviewdate}. Bitte überprüfe sie und veröffentliche eine aktualisierte Version oder verlängere das Datum.';
$string['task_reviewreminder'] = 'Handbuch: Erinnerungen an Überprüfungstermine';
$string['task_linkchecker'] = 'Handbuch: Link-Prüfung';
$string['brokenlinksummary'] = 'Die Seite „{$a->page}“ verlinkt auf „{$a->target}“ — nicht vorhanden oder nicht veröffentlicht.';
$string['brokenquizsummary'] = 'Ein Lesepfad-Element der Seite „{$a->page}“ verweist auf Test-Kursmodul {$a->cmid}, das nicht mehr existiert.';

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

// Archive and restore (spec 11.3).
$string['archivepage'] = 'Archivieren';
$string['unarchivepage'] = 'Aus dem Archiv holen';
$string['pagearchived'] = 'Seite archiviert. Der Versionsverlauf bleibt erhalten.';
$string['pageunarchived'] = 'Seite aus dem Archiv wiederhergestellt.';
$string['confirmarchive'] = '„{$a}“ archivieren? Lesende sehen sie nicht mehr; Redakteure behalten Zugriff, der gesamte Verlauf bleibt erhalten.';
$string['confirmunarchive'] = '„{$a}“ aus dem Archiv holen? Sie wird wieder für Lesende sichtbar.';
$string['restoreasdraft'] = 'Als Entwurf wiederherstellen';
$string['confirmrestore'] = 'Neuen Arbeitsentwurf auf Basis von v{$a} erstellen? Der spätere Verlauf bleibt erhalten; der Entwurf durchläuft den normalen Prüfworkflow.';
$string['restoredsummary'] = 'Wiederhergestellt aus v{$a}.';
$string['revisionrestored'] = 'v{$a} wurde als neuer Arbeitsentwurf wiederhergestellt.';

// Privacy export paths.
$string['privacy:acknowledgementspath'] = 'Lesebestätigungen';
$string['privacy:authoredpath'] = 'Erstellte Überarbeitungen';
$string['privacy:metadata:local_handbook_finding'] = 'Qualitätsbefunde erfassen, wer sie gemeldet hat, wem sie zugewiesen wurden und wer sie gelöst hat.';

// Privacy API.
$string['privacy:metadata:local_handbook_revision'] = 'Handbuch-Überarbeitungen erfassen, welche Nutzer sie erstellt, geändert, geprüft, freigegeben oder veröffentlicht haben.';
$string['privacy:metadata:local_handbook_revision:createdby'] = 'Nutzer/in, die die Überarbeitung erstellt hat.';
$string['privacy:metadata:local_handbook_revision:modifiedby'] = 'Nutzer/in, die die Überarbeitung zuletzt geändert hat.';
$string['privacy:metadata:local_handbook_revision:publishedby'] = 'Nutzer/in, die die Überarbeitung veröffentlicht hat.';
$string['privacy:metadata:local_handbook_page'] = 'Handbuchseiten erfassen Verantwortliche, Freigebende sowie erstellende und ändernde Nutzer.';
$string['privacy:metadata:local_handbook_page:owneruserid'] = 'Nutzer/in, die für die Richtigkeit der Seite verantwortlich ist.';
$string['privacy:metadata:local_handbook_category'] = 'Handbuchkategorien erfassen die Nutzer, die sie erstellt und geändert haben.';

// Änderungssätze und öffentliche Urheberschaft (Spezifikation 36).
$string['author'] = 'Autor/in';
$string['changesets'] = 'Änderungssätze';
$string['changeset'] = 'Änderungssatz';
$string['changesetdefaultsummary'] = 'Änderungssatz: {$a}';
$string['event_revision_approved'] = 'Handbuchrevision freigegeben';
$string['event_revision_rejected'] = 'Handbuchrevision abgelehnt';
$string['event_changes_requested'] = 'Änderungen am Handbuch angefordert';
$string['event_changeset_created'] = 'Handbuch-Änderungssatz erstellt';
$string['event_changeset_submitted'] = 'Handbuch-Änderungssatz eingereicht';
$string['errorchangesetlocked'] = 'Dieser Änderungssatz ist abgeschlossen oder abgebrochen und kann nicht mehr geändert werden.';
$string['errorchangeitemlocked'] = 'Dieser Eintrag ist in Prüfung, freigegeben oder veröffentlicht und kann nicht aus dem Änderungssatz entfernt werden.';
$string['conflict_humandraft'] = 'Für diese Seite existiert bereits ein Arbeitsentwurf (v{$a}), der nicht zu diesem Änderungssatz gehört; eine Person muss ihn klären, bevor der Änderungssatz hier entwerfen kann.';
$string['conflict_foreignchangeset'] = 'Diese Seite hat bereits einen Arbeitsentwurf (v{$a}) in einem anderen Änderungssatz.';
$string['conflict_inreview'] = 'Der Entwurf dieses Änderungssatzes (v{$a}) ist in Prüfung oder freigegeben; eine Person muss ihn zurückgeben, bevor weiter bearbeitet werden kann.';
$string['conflict_basemismatch'] = 'Die veröffentlichte Revision hat sich seit dem Lesen geändert; aktualisieren Sie die Seite vor dem Entwerfen.';
$string['conflict_concurrency'] = 'Der Entwurf (v{$a}) wurde seit dem letzten Lesen geändert; lesen Sie ihn erneut, bevor Sie aktualisieren.';
$string['changesetstatus_draft'] = 'Entwurf';
$string['changesetstatus_in_review'] = 'In Prüfung';
$string['changesetstatus_partially_completed'] = 'Teilweise abgeschlossen';
$string['changesetstatus_completed'] = 'Abgeschlossen';
$string['changesetstatus_cancelled'] = 'Abgebrochen';
$string['itemstatus_draft'] = 'Entwurf';
$string['itemstatus_conflict'] = 'Konflikt';
$string['itemstatus_in_review'] = 'In Prüfung';
$string['itemstatus_approved'] = 'Freigegeben';
$string['itemstatus_published'] = 'Veröffentlicht';
$string['itemstatus_rejected'] = 'Abgelehnt';
$string['itemstatus_skipped'] = 'Übersprungen';

// Redaktionelle Oberfläche für Änderungssätze (Spezifikation 36, Phase 2).
$string['newchangeset'] = 'Neuer Änderungssatz';
$string['changesetinstructions'] = 'Zusammenfassung der Anweisung';
$string['changesetinstructions_help'] = 'Eine kurze, verständliche Zusammenfassung der Änderung, die dieser Satz umsetzt. Speichern Sie die genehmigte Anweisung, nicht ein vollständiges Chat-Protokoll.';
$string['createchangeset'] = 'Änderungssatz erstellen';
$string['nochangesets'] = 'Noch keine Änderungssätze.';
$string['changesetcreated'] = 'Änderungssatz erstellt.';
$string['changesetdetails'] = 'Details des Änderungssatzes';
$string['changesetitems'] = 'Seiten in diesem Änderungssatz';
$string['nochangesetitems'] = 'Diesem Änderungssatz wurden noch keine Seiten hinzugefügt.';
$string['addpagetochangeset'] = 'Seite hinzufügen';
$string['selectpageadd'] = 'Seite auswählen…';
$string['addpagebutton'] = 'Hinzufügen';
$string['pageaddedtochangeset'] = 'Seite zum Änderungssatz hinzugefügt.';
$string['removeitem'] = 'Entfernen';
$string['itemremoved'] = 'Seite aus dem Änderungssatz entfernt; ihr Entwurf bleibt erhalten.';
$string['confirmremoveitem'] = 'Diese Seite aus dem Änderungssatz entfernen? Ihr Entwurf bleibt erhalten und kann weiterhin normal bearbeitet werden.';
$string['submitchangeset'] = 'Zur Prüfung einreichen';
$string['changesetsubmittednotice'] = 'Geeignete Entwürfe wurden zur Prüfung eingereicht.';
$string['cancelchangeset'] = 'Änderungssatz abbrechen';
$string['confirmcancelchangeset'] = 'Diesen Änderungssatz abbrechen? Entwürfe und Versionsverlauf bleiben erhalten; der Satz wird geschlossen.';
$string['changesetcancelled'] = 'Änderungssatz abgebrochen.';
$string['editdraft'] = 'Entwurf bearbeiten';
$string['reject'] = 'Ablehnen';
$string['revisionrejected'] = 'Überarbeitung abgelehnt.';
$string['changesetsource'] = 'Quelle';
$string['source_human'] = 'Person';
$string['source_ai'] = 'Handbuch-KI';
$string['changesetsponsor'] = 'Auftraggeber';
$string['changesetpreparedby'] = 'Erstellt von';
$string['changesetcreatedon'] = 'Erstellt';
$string['backtochangesets'] = 'Zurück zu den Änderungssätzen';
$string['draftmatchespublished'] = 'Dieser Entwurf entspricht der veröffentlichten Version — noch keine Änderungen.';
$string['changesetnewpage'] = 'Neue Seite (noch nicht veröffentlicht)';
$string['externalreference'] = 'Externe Referenz';

// Metadaten-Vorschläge (Steckbrief) in Change-Sets (Phase 1).
$string['metadatachangesummary'] = 'Metadaten: {$a}';
$string['metadatafield'] = 'Feld';
$string['metadatacurrentvalue'] = 'Aktuell';
$string['metadataproposedvalue'] = 'Vorgeschlagen';
$string['metadatanochanges'] = 'Dieser Vorschlag enthält keine Feldänderungen.';
$string['applychange'] = 'Änderung anwenden';
$string['changeitemapproved'] = 'Änderung freigegeben.';
$string['changeitemapplied'] = 'Änderung angewendet und veröffentlicht.';
$string['changeitemrejected'] = 'Änderung abgelehnt.';
$string['metafield_title'] = 'Titel';
$string['metafield_slug'] = 'Slug';
$string['metafield_summary'] = 'Zusammenfassung';
$string['metafield_contenttype'] = 'Inhaltstyp';
$string['metafield_authoritylevel'] = 'Autoritätsstufe';
$string['metafield_criticality'] = 'Kritikalität';
$string['metafield_responsiblearea'] = 'Verantwortlicher Bereich';
$string['metafield_reviewdate'] = 'Prüfdatum';
$string['metafield_requiredreading'] = 'Pflichtlektüre';
$string['conflict_metadataconcurrency'] = 'Der Seiten-Steckbrief hat sich nach der Erstellung dieses Vorschlags geändert; bitte neu laden und erneut vorschlagen.';
$string['errormetadatafieldunsupported'] = 'Das Metadatenfeld „{$a}" kann nicht über einen Metadaten-Vorschlag geändert werden.';
$string['errormetadatavalue'] = 'Der vorgeschlagene Wert für „{$a}" ist ungültig.';
$string['errormetadatapatchempty'] = 'Ein Metadaten-Vorschlag muss mindestens ein Feld ändern.';
$string['errorunsupportedkind'] = 'Diese Änderungsart („{$a}") kann nicht automatisch angewendet werden.';
$string['errorwrongitemkind'] = 'Diese Aktion gilt nicht für eine Seiteninhalts-Überarbeitung.';

// Neue Seiten, Slug-Aliasse, Beziehungen und Bereiche (Phase 1).
$string['newpagechangesummary'] = 'Neue Seite: {$a}';
$string['newpagesubmitsummary'] = 'Neue Seite über ein Change-Set vorgeschlagen.';
$string['relationchangesummary'] = 'Beziehungen: {$a} Änderung(en)';
$string['itemkindnewpage'] = 'Neue Seite';
$string['relationopcreate'] = 'Hinzufügen';
$string['relationopremove'] = 'Entfernen';
$string['errornewpagecategory'] = 'Eine neue Seite muss auf eine vorhandene Kategorie verweisen.';
$string['errornewpagecontent'] = 'Eine neue Seite muss Inhalt enthalten.';
$string['errorslugtaken'] = 'Der Slug „{$a}" wird bereits verwendet.';
$string['errorrelationop'] = 'Ein Beziehungsvorgang muss „create" oder „remove" sein.';
$string['errorrelationtype'] = 'Unbekannter Beziehungstyp „{$a}".';
$string['errorrelationself'] = 'Eine Seite kann nicht auf sich selbst verweisen.';
$string['errorrelationtarget'] = 'Ein Beziehungsvorgang benötigt eine gültige Zielseite.';
$string['errorrelationempty'] = 'Ein Beziehungsvorschlag muss mindestens einen Vorgang enthalten.';
$string['errorrelationunresolved'] = 'Das Beziehungsziel „{$a}" konnte nicht aufgelöst werden; wenden Sie zuerst die neue Seite an, auf die es verweist.';
$string['errortempkeyrequired'] = 'Ein Vorschlag für eine neue Seite benötigt einen stabilen Tempkey.';
$string['errorunknownarea'] = 'Der verantwortliche Bereich „{$a}" ist nicht im kontrollierten Vokabular enthalten.';

// Verwaltung des Vokabulars verantwortlicher Bereiche (Phase 1).
$string['manageareas'] = 'Verantwortliche Bereiche';
$string['manageareas_help'] = 'Das kontrollierte Vokabular der verantwortlichen Bereiche. Metadaten- und Neue-Seite-Vorschläge müssen einen aktiven Bereich aus dieser Liste verwenden.';
$string['newarea'] = 'Neuer Bereich';
$string['editarea'] = 'Bereich bearbeiten';
$string['areaname'] = 'Name';
$string['areakey'] = 'Schlüssel';
$string['areakey_help'] = 'Ein stabiler Schlüssel (Kleinbuchstaben, Zahlen, Bindestriche). Leer lassen, um ihn aus dem Namen zu erzeugen. Die API kann einen Bereich über den Schlüssel oder den Namen referenzieren.';
$string['areaactive'] = 'Aktiv';
$string['areainactive'] = 'Inaktiv';
$string['areaactivate'] = 'Aktivieren';
$string['areadeactivate'] = 'Deaktivieren';
$string['areasaved'] = 'Verantwortlicher Bereich gespeichert.';
$string['areadeleted'] = 'Verantwortlicher Bereich gelöscht.';
$string['noareas'] = 'Es wurden noch keine verantwortlichen Bereiche definiert.';
$string['confirmdeletearea'] = 'Den verantwortlichen Bereich „{$a}" löschen? Seiten, die diesen Namen bereits verwenden, behalten ihn; nur der Vokabulareintrag wird entfernt.';

// Archivierungs-/Wiederherstellungs-Lebenszyklus (Phase 2).
$string['archivechangesummary'] = 'Archivieren: {$a}';
$string['restorechangesummary'] = 'Wiederherstellen: {$a}';
$string['archiveproposal'] = 'Archivierungsvorschlag';
$string['restoreproposal'] = 'Diese archivierte Seite wiederherstellen';
$string['archivereasonlabel'] = 'Grund';
$string['replacementpage'] = 'Ersatzseite';
$string['redirectmodelabel'] = 'Weiterleitung';
$string['archiveimpact'] = 'Auswirkung: {$a->relations} eingehende Beziehung(en); {$a->paths} aktive(r) Lesepfad(e).';
$string['archivedredirectnotice'] = 'Die Seite „{$a}" wurde archiviert; Sie wurden zur aktuellen Seite geleitet.';
$string['archivedseereplacement'] = 'Aktuelle Seite: {$a}.';
$string['archivereason_obsolete'] = 'Veraltet';
$string['archivereason_superseded'] = 'Ersetzt';
$string['archivereason_duplicate'] = 'Duplikat';
$string['archivereason_merged'] = 'Zusammengeführt';
$string['archivereason_temporary_content_expired'] = 'Temporärer Inhalt abgelaufen';
$string['archivereason_role_no_longer_exists'] = 'Funktion existiert nicht mehr';
$string['archivereason_procedure_no_longer_used'] = 'Verfahren nicht mehr verwendet';
$string['archivereason_incorrect_legacy_import'] = 'Fehlerhafter Altimport';
$string['archivereason_other'] = 'Sonstiges';
$string['redirectmode_notice_only'] = 'Nur Hinweis';
$string['redirectmode_redirect_with_notice'] = 'Weiterleitung mit Hinweis';
$string['redirectmode_automatic_redirect'] = 'Automatische Weiterleitung';
$string['redirectmode_no_redirect'] = 'Keine Weiterleitung';
$string['errorarchivereason'] = 'Ein gültiger Archivierungsgrund ist erforderlich.';
$string['errorarchivenote'] = 'Bei Grund „Sonstiges" ist eine Erklärung erforderlich.';
$string['errorredirectmode'] = 'Ungültiger Weiterleitungsmodus.';
$string['errorreplacementself'] = 'Eine Seite kann nicht ihre eigene Ersatzseite sein.';
$string['errorreplacementinvalid'] = 'Die Ersatzseite existiert nicht oder ist archiviert.';
$string['errorreplacementrequired'] = 'Ein Weiterleitungsmodus benötigt eine Ersatzseite.';
$string['errornotarchived'] = 'Diese Seite ist nicht archiviert.';

// Kategorievorschläge (Phase 2).
$string['categorychangesummary_create'] = 'Neue Kategorie';
$string['categorychangesummary_update'] = 'Kategorie-Aktualisierung';
$string['categorychangesummary_move'] = 'Kategorie verschieben';
$string['categorychangesummary_merge'] = 'Kategorien zusammenführen';
$string['categoryop_create'] = 'Kategorie erstellen';
$string['categoryop_update'] = 'Kategorie aktualisieren';
$string['categoryop_move'] = 'Kategorie verschieben';
$string['categoryop_merge'] = 'Kategorien zusammenführen';
$string['categoryoplabel'] = 'Vorgang';
$string['categorymergesource'] = 'Zusammenführen von';
$string['categorymergetarget'] = 'Zusammenführen in';
$string['itemkindcategory'] = 'Kategorie';
$string['errorcategoryop'] = 'Ungültiger Kategorievorgang.';
$string['errorcategoryname'] = 'Ein gültiger Kategoriename ist erforderlich.';
$string['errorcategoryparent'] = 'Die übergeordnete Kategorie existiert nicht.';
$string['errorcategorynotfound'] = 'Die Kategorie existiert nicht.';
$string['errorcategorynochange'] = 'Eine Kategorie-Aktualisierung muss mindestens ein Feld ändern.';
$string['errorcategorycycle'] = 'Diese Änderung würde einen Kategoriezyklus erzeugen.';
$string['errorcategorymergeself'] = 'Eine Kategorie kann nicht mit sich selbst zusammengeführt werden.';
$string['categoryop_delete_empty'] = 'Leere Kategorie auflösen';
$string['categorychangesummary_delete_empty'] = 'Leere Kategorie auflösen';

// Seitenverschiebungen (Anforderungen der nächsten Version, Taxonomie Phase 1).
$string['pagemovechangesummary'] = 'Verschieben: {$a}';
$string['pagemoveto'] = 'In Kategorie verschieben: {$a}';
$string['conflict_pagemove'] = 'Die Seite wurde nach Erstellung dieses Vorschlags verschoben oder geändert; bitte neu laden und erneut vorschlagen.';
$string['errorpagemovesame'] = 'Die Seite befindet sich bereits in dieser Kategorie.';
$string['event_page_moved'] = 'Handbuchseite verschoben';
$string['errortemprefunresolved'] = 'Die Kategorie „{$a}" wird in diesem Change-Set vorgeschlagen, wurde aber noch nicht erstellt; wenden Sie zuerst ihre Erstellung an.';

// Autorisierung des gesamten Change-Sets (Anforderungen der nächsten Version, Phase 2).
$string['changesetapproved'] = 'Change-Set freigegeben.';
$string['changesetapplied'] = 'Change-Set angewendet.';
$string['approveandapplyset'] = 'Gesamtes Change-Set freigeben &amp; anwenden';
$string['approveset'] = 'Gesamtes Change-Set freigeben';
$string['applyset'] = 'Freigegebenes Change-Set anwenden';
$string['confirmapplyset'] = 'Das gesamte freigegebene Change-Set jetzt anwenden? Alle freigegebenen Elemente werden zusammen in einer Transaktion veröffentlicht; schlägt eines fehl, wird keines angewendet.';

// Lesepfad-Vorschläge (Anforderungen der nächsten Version, Phase 3).
$string['pathchangesummary'] = 'Lesepfad: {$a}';
$string['conflict_pathconcurrency'] = 'Der Lesepfad wurde nach der Vorbereitung dieses Vorschlags geändert; neu laden und erneut vorschlagen.';
$string['errorpathname'] = 'Ein Lesepfad benötigt einen Namen mit höchstens 255 Zeichen.';
$string['errorpathnotfound'] = 'Der Lesepfad existiert nicht.';
$string['errorpathtype'] = 'Dieser Lesepfad-Typ ist unbekannt.';
$string['errorpathslug'] = 'Der Lesepfad benötigt einen gültigen Slug.';
$string['errorpathsectionsempty'] = 'Ein Lesepfad-Vorschlag muss mindestens einen Abschnitt enthalten.';
$string['errorpathitemsempty'] = 'Ein Lesepfad-Vorschlag muss mindestens eine Seite enthalten.';
$string['errorpathpage'] = 'Ein Lesepfad-Element verweist auf eine nicht vorhandene Seite ({$a}).';
$string['errorpathduplicatepage'] = 'Ein Lesepfad darf dieselbe Seite nicht zweimal enthalten.';
$string['errorpathitemtarget'] = 'Jedes Lesepfad-Element benötigt eine Seiten-ID oder einen Seiten-Tempkey.';
$string['itemkindreadingpath'] = 'Lesepfad';
$string['pathnamelabel'] = 'Name';
$string['pathoperation'] = 'Vorgang';
$string['pathcreate'] = 'Lesepfad erstellen';
$string['pathupdate'] = 'Lesepfad aktualisieren';
$string['pathtypelabel'] = 'Typ';
$string['pathschoolyear'] = 'Schuljahr';
$string['pathactive'] = 'Aktiv';
$string['pathestimatedminutes'] = 'Geschätzte Minuten';
$string['pathnewpageitem'] = 'In diesem Set vorgeschlagene neue Seite ({$a})';
$string['pathoptionalsuffix'] = '(optional)';
$string['pathtype_onboarding'] = 'Einarbeitung';
$string['pathtype_calendar_phase'] = 'Kalenderphase';
$string['pathtype_role_based'] = 'Rollenbasiert';
$string['pathtype_situational'] = 'Situativ';
$string['pathtype_refresher'] = 'Auffrischung';
$string['pathtype_compliance'] = 'Compliance';
$string['pathwas'] = '(vorher: {$a})';
$string['pathitemnew'] = 'Neu';
$string['pathitemnowrequired'] = 'Jetzt erforderlich';
$string['pathitemnowoptional'] = 'Jetzt optional';
$string['pathitemmovedsection'] = 'Verschoben aus „{$a}“';
$string['pathnosection'] = '(kein Abschnitt)';
$string['pathremovedheading'] = 'Aus dem Pfad entfernt';
