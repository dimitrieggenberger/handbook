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
 * English strings for local_handbook.
 *
 * @package   local_handbook
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Institutional Handbook';

// Capabilities.
$string['handbook:view'] = 'View published handbook pages';
$string['handbook:viewrestricted'] = 'View restricted-audience handbook pages';
$string['handbook:viewhistory'] = 'View handbook revision history';
$string['handbook:acknowledge'] = 'Record required-reading acknowledgements';
$string['handbook:edit'] = 'Create handbook pages and draft revisions';
$string['handbook:review'] = 'Review handbook drafts and request changes';
$string['handbook:approve'] = 'Approve handbook revisions for publication';
$string['handbook:publish'] = 'Publish, supersede, archive or restore handbook content';
$string['handbook:managecategories'] = 'Manage handbook categories';
$string['handbook:managepaths'] = 'Manage handbook reading paths';
$string['handbook:managefindings'] = 'Manage handbook quality findings';
$string['handbook:managechangesets'] = 'Review and act on handbook change sets';
$string['handbook:viewreports'] = 'View handbook reports';
$string['handbook:manageapi'] = 'Configure handbook external access';
$string['handbook:apiaccess'] = 'Use handbook external-service functions';
$string['handbook:manage'] = 'Administer the handbook plugin';

// Navigation and page titles.
$string['handbookhome'] = 'Handbook';
$string['managetools'] = 'Management';
$string['categories'] = 'Categories';
$string['category'] = 'Category';
$string['reviewqueue'] = 'Review queue';
$string['newpage'] = 'New page';
$string['editpage'] = 'Edit page';
$string['managecategories'] = 'Manage categories';

// Home page.
$string['recentlyupdated'] = 'Recently updated';
$string['nocategoriesyet'] = 'No categories have been created yet.';
$string['nopagesyet'] = 'No published pages yet.';
$string['pagecount'] = '{$a} pages';
$string['pagecountone'] = '1 page';
$string['subcategories'] = 'Subcategories';
$string['pagesincategory'] = 'Pages in this category';
$string['emptycategory'] = 'This category has no published pages yet.';

// Reader view.
$string['summary'] = 'Summary';
$string['effectivedate'] = 'Effective from';
$string['lastupdated'] = 'Last updated';
$string['publishedversion'] = 'Published version';
$string['reviewdate'] = 'Next review';
$string['responsiblearea'] = 'Responsible area';
$string['owner'] = 'Owner';
$string['approver'] = 'Approval';
$string['pagedetails'] = 'Page details';
$string['relatedpages'] = 'Related pages';
$string['contenttype'] = 'Content type';
$string['authoritylevel'] = 'Authority';
$string['scope'] = 'Scope';
$string['audience'] = 'Audience';
$string['languagelabel'] = 'Language';
$string['requiredreading'] = 'Required reading';
$string['notpublished'] = 'This page has no published revision yet.';
$string['draftnotice'] = 'A newer draft revision (v{$a->version}, {$a->status}) exists for this page.';
$string['revisionhistory'] = 'Revision history';
$string['foreditors'] = 'For editors';
$string['viewrevision'] = 'View';
$string['archivedpage'] = 'This page is archived. It is kept for historical reference only.';

// Content types (specification 10.1).
$string['contenttype_policy'] = 'Policy';
$string['contenttype_procedure'] = 'Procedure';
$string['contenttype_standard'] = 'Standard';
$string['contenttype_guideline'] = 'Guideline';
$string['contenttype_quickguide'] = 'Quick guide';
$string['contenttype_template'] = 'Template';
$string['contenttype_example'] = 'Example';
$string['contenttype_roledescription'] = 'Role description';

// Criticality (specification 10.1).
$string['criticality'] = 'Criticality';
$string['criticality_reference'] = 'Reference';
$string['criticality_operational'] = 'Operational';
$string['criticality_mandatory'] = 'Mandatory';
$string['criticality_safetycritical'] = 'Safety-critical';

// AI access (specification 10.1).
$string['aiaccess'] = 'AI access';
$string['aiaccess_full'] = 'Full content';
$string['aiaccess_metadata_only'] = 'Metadata only';
$string['aiaccess_excluded'] = 'Excluded';

// Authority levels (specification 10.3).
$string['authority_1'] = 'Level 1 · Institution-wide policy';
$string['authority_2'] = 'Level 2 · Official procedure';
$string['authority_3'] = 'Level 3 · Departmental standard';
$string['authority_4'] = 'Level 4 · Operational guide';
$string['authority_5'] = 'Level 5 · Template';
$string['authority_6'] = 'Level 6 · Example material';

// Revision statuses (specification 11.1).
$string['status_draft'] = 'Draft';
$string['status_in_review'] = 'In review';
$string['status_changes_requested'] = 'Changes requested';
$string['status_approved'] = 'Approved';
$string['status_published'] = 'Published';
$string['status_superseded'] = 'Superseded';
$string['status_rejected'] = 'Rejected';

// Editor and workflow.
$string['pagetitle'] = 'Title';
$string['pageslug'] = 'Slug';
$string['pageslug_help'] = 'Stable URL identifier: lowercase letters, numbers and hyphens. It cannot be changed easily after publication, because links and the external API use it.';
$string['pagecontent'] = 'Page content';
$string['changesummary'] = 'Change summary';
$string['changesummary_help'] = 'Required when submitting for review: a short description of what changed and why.';
$string['savedraft'] = 'Save draft';
$string['submitforreview'] = 'Submit for review';
$string['requestchanges'] = 'Request changes';
$string['approve'] = 'Approve';
$string['publish'] = 'Publish';
$string['reviewnote'] = 'Review note';
$string['version'] = 'Version';
$string['versionnumber'] = 'v{$a}';
$string['basedon'] = 'Based on v{$a}';
$string['draftsaved'] = 'Draft saved.';
$string['draftsubmitted'] = 'Draft submitted for review.';
$string['changesrequested'] = 'Changes requested; the draft was returned to its author.';
$string['revisionapproved'] = 'Revision approved.';
$string['revisionpublished'] = 'Revision published.';
$string['nodraftsinreview'] = 'No drafts are waiting for review.';
$string['submittedby'] = 'Submitted by {$a->name} on {$a->date}';
$string['confirmpublish'] = 'Publish revision v{$a} and supersede the current published revision?';

// Category management.
$string['categoryname'] = 'Category name';
$string['categoryslug'] = 'Slug';
$string['categorydescription'] = 'Description';
$string['categoryparent'] = 'Parent category';
$string['categoryvisible'] = 'Visible';
$string['categoryicon'] = 'Icon';
$string['categoryicon_help'] = 'Font Awesome solid icon class, e.g. fa-children, fa-landmark, fa-sitemap (see fontawesome.com/icons, Free/Solid set). Leave empty for the default folder icon.';
$string['topcategory'] = '(top level)';
$string['newcategory'] = 'New category';
$string['editcategory'] = 'Edit category';
$string['deletecategory'] = 'Delete category';
$string['categorysaved'] = 'Category saved.';
$string['categorydeleted'] = 'Category deleted.';
$string['confirmdeletecategory'] = 'Delete category "{$a}"? This is only possible while it has no pages and no subcategories.';
$string['categorynotempty'] = 'This category still contains pages or subcategories and cannot be deleted.';

// Bootstrap mode and direct publish.
$string['bootstrapmode'] = 'Bootstrap mode';
$string['bootstrapmode_desc'] = 'While enabled, users with the publish capability can publish directly from the editor and imports may publish immediately, skipping the review queue. Revision history is recorded either way. Intended only for the initial population phase — switch it off afterwards to enforce the full editorial workflow.';
$string['saveandpublish'] = 'Save and publish';
$string['bootstrapoffnotice'] = 'Bootstrap mode is off: imported content is created as drafts and goes through the normal review workflow.';

// Seed import.
$string['importseed'] = 'Import content';
$string['importfile'] = 'Seed file (JSON)';
$string['publishonimport'] = 'Publish imported pages immediately';
$string['importcategoriescreated'] = 'Categories created: {$a}';
$string['importcategoriesupdated'] = 'Categories updated: {$a}';
$string['importpagescreated'] = 'Pages created: {$a}';
$string['importpagesupdated'] = 'Pages updated: {$a}';
$string['importpagespublished'] = 'Pages published: {$a}';
$string['importrelationscreated'] = 'Relations created: {$a}';
$string['importerrors'] = 'Import warnings';
$string['errorinvalidjson'] = 'The uploaded file is not valid JSON.';

// Required-reading acknowledgements (spec 16).
$string['acknowledgereading'] = 'Acknowledge reading';
$string['readingconfirmation'] = 'Reading confirmation';
$string['ackpendingnotice'] = 'This page is required reading and you have not yet confirmed the current version (v{$a}).';
$string['ackreconfirmnotice'] = 'This page is required reading and a materially changed version (v{$a}) needs your renewed confirmation.';
$string['ackconfirmednotice'] = 'You confirmed reading the current version (v{$a->version}) on {$a->date}.';
$string['gotoconfirmation'] = 'Go to the reading confirmation';
$string['ackcheckboxlabel'] = 'I have read and understood the current version of "{$a}".';
$string['confirmreading'] = 'Confirm reading';
$string['ackrecorded'] = 'Your reading confirmation was recorded.';
$string['ackconfirmedrecord'] = 'Confirmed on {$a->date} · published version v{$a->version}';
$string['ackconfirmedshort'] = 'Confirmed · {$a}';
$string['ackrecordinfo'] = 'The confirmation is recorded with your user, the published version of the page and the date. It does not replace knowledge assessments in Moodle.';
$string['requiresreack'] = 'Requires renewed acknowledgement when published';
$string['requiresreack_help'] = 'Tick for materially changed versions of required-reading pages: after publication, everyone must confirm reading again. Leave unticked for minor corrections.';
$string['errornotrequiredreading'] = 'This page is not marked as required reading.';

// Reading paths (spec 15).
$string['myreadingpath'] = 'My reading path';
$string['managepaths'] = 'Manage reading paths';
$string['newpath'] = 'New reading path';
$string['editpath'] = 'Edit reading path';
$string['pathname'] = 'Path name';
$string['schoolyear'] = 'School year';
$string['pathitems'] = 'Path items';
$string['sectionname'] = 'Section';
$string['additem'] = 'Add item';
$string['pathsaved'] = 'Reading path saved.';
$string['pathdeleted'] = 'Reading path deleted.';
$string['confirmdeletepath'] = 'Delete the reading path "{$a}" and all its items? Recorded acknowledgements are kept.';
$string['pathitemcount'] = '{$a} items';
$string['nopathsyet'] = 'No active reading paths yet.';
$string['emptypath'] = 'This reading path has no items yet.';
$string['pathprogress'] = '{$a->confirmed} of {$a->total} required pages confirmed';
$string['sectionprogress'] = '{$a->confirmed} of {$a->total} confirmed';
$string['optionalitem'] = 'Optional';
$string['reconfirmitem'] = 'Reconfirm: new version published';
$string['pendingitem'] = 'Pending';
$string['readitem'] = 'Reading';
$string['connectedquiz'] = 'Moodle quiz';
$string['pathcohorts'] = 'Audience cohorts';
$string['pathroles'] = 'Audience roles (system level)';
$string['pathaudience'] = 'Path audience';
$string['pathaudience_help'] = 'Leave both empty to show the path to all handbook viewers. Otherwise the path is visible to members of ANY selected cohort or holders of ANY selected role at system level. Managers always see every path, and the completion report covers exactly this audience.';
$string['errorpathnotvisible'] = 'This reading path is not available for your role or groups.';
$string['importpathscreated'] = 'Reading paths created: {$a}';
$string['importpathsupdated'] = 'Reading paths updated: {$a}';

// Privacy API (acknowledgements).
$string['privacy:metadata:local_handbook_ack'] = 'Required-reading acknowledgements record which user confirmed which published revision and when.';
$string['privacy:metadata:local_handbook_ack:userid'] = 'The user who confirmed reading.';
$string['privacy:metadata:local_handbook_ack:timeacknowledged'] = 'When the confirmation was recorded.';

// Search.
$string['searchhandbook'] = 'Search the handbook';
$string['searchplaceholder'] = 'Search procedures, policies, guides and forms…';
$string['alltypes'] = 'All types';
$string['allcategories'] = 'All categories';
$string['searchresultcount'] = '{$a} pages found';
$string['noresults'] = 'No pages match your search.';

// Revision history and comparison.
$string['comparerevisions'] = 'Compare revisions';
$string['comparingversions'] = 'Comparing v{$a->from} → v{$a->to}';
$string['difflegend'] = 'Additions are highlighted, removals struck through.';
$string['comparewithpublished'] = 'Compare with the published version';
$string['comparewithprevious'] = 'Compare with its base version';
$string['viewchanges'] = 'View changes';
$string['nocontentdiff'] = 'No text changes between these versions.';
$string['createdby'] = 'Created by';
$string['backtopage'] = 'Back to the page';

// Home personalization and reader polish (spec 12.1, 12.2).
$string['pendingreadingcard'] = 'Pending required reading';
$string['noackpending'] = 'You are up to date with all required reading.';
$string['continuepath'] = 'Continue the path';
$string['continuereading'] = 'Read and confirm';
$string['currentsection'] = 'Current section';
$string['editorialwork'] = 'Editorial work';
$string['draftsawaiting'] = 'Drafts awaiting review: {$a}';
$string['changesrequestedcount'] = 'Changes requested: {$a}';
$string['overduereviewcount'] = 'Reviews overdue: {$a}';
$string['safetycriticalpages'] = 'Safety-critical pages';
$string['quickguides'] = 'Quick guides';
$string['formstemplates'] = 'Forms and templates';
$string['viewall'] = 'View all';
$string['onthispage'] = 'On this page';
$string['printpage'] = 'Print';
$string['printfooter'] = 'Printed on {$a->date}. Printed copies age: the authoritative version lives at {$a->url}';
$string['authoritynote'] = 'This guide summarises {$a}. If they differ, the full procedure prevails.';
$string['partofpath'] = 'Part of the reading path: {$a}';

// Relation type labels (spec 9.2): forward and reverse.
$string['relation_relatedto'] = 'Related to';
$string['relationrev_relatedto'] = 'Related to';
$string['relation_dependson'] = 'Depends on';
$string['relationrev_dependson'] = 'Required by';
$string['relation_implements'] = 'Implements';
$string['relationrev_implements'] = 'Implemented by';
$string['relation_replaces'] = 'Replaces';
$string['relationrev_replaces'] = 'Replaced by';
$string['relation_supersedes'] = 'Supersedes';
$string['relationrev_supersedes'] = 'Superseded by';
$string['relation_exceptionto'] = 'Exception to';
$string['relationrev_exceptionto'] = 'Exception defined in';
$string['relation_procedurefor'] = 'Procedure for';
$string['relationrev_procedurefor'] = 'Connected procedure';
$string['relation_quickguidefor'] = 'Quick guide for';
$string['relationrev_quickguidefor'] = 'Quick guide';
$string['relation_templatefor'] = 'Template for';
$string['relationrev_templatefor'] = 'Template';
$string['relation_assessmentfor'] = 'Assessment for';
$string['relationrev_assessmentfor'] = 'Connected assessment';
$string['relation_translationof'] = 'Translation of';
$string['relationrev_translationof'] = 'Translated as';

// Quality findings (spec 19).
$string['reportproblem'] = 'Report a problem';
$string['reportintro'] = 'Describe what you found. Your report creates a quality finding, recorded with your user and the published version; the editorial team triages it and records the resolution.';
$string['problemtype'] = 'Problem type';
$string['affectedsection'] = 'Affected section (optional)';
$string['problemdescription'] = 'Description';
$string['reportplaceholder'] = 'What did you find and, if you know, how should it be?';
$string['sendreport'] = 'Send report';
$string['reportthanks'] = 'Thank you. Finding #F-{$a} was created and the editorial team was notified.';
$string['managefindings'] = 'Quality findings';
$string['nofindings'] = 'No findings match this filter.';
$string['findingupdated'] = 'Finding updated.';
$string['resolutionnote'] = 'Resolution note';
$string['filteropenish'] = 'Open + under review';
$string['findingtype_contradiction'] = 'Possible contradiction';
$string['findingtype_duplicate'] = 'Duplicate or overlapping content';
$string['findingtype_ambiguous_responsibility'] = 'Ambiguous responsibility';
$string['findingtype_missing_escalation'] = 'Missing escalation route';
$string['findingtype_missing_record'] = 'Missing required record or form';
$string['findingtype_outdated_reference'] = 'Outdated role, date or system reference';
$string['findingtype_incorrect_content'] = 'Incorrect information';
$string['findingtype_inconsistent_terminology'] = 'Inconsistent terminology';
$string['findingtype_broken_link'] = 'Broken internal link';
$string['findingtype_missing_owner'] = 'Missing owner or approver';
$string['findingtype_review_overdue'] = 'Review date exceeded';
$string['findingtype_procedure_without_policy'] = 'Procedure without a connected policy';
$string['findingtype_policy_without_procedure'] = 'Policy without an implementable procedure';
$string['findingtype_modality_difference'] = 'Unexplained difference across modalities';
$string['findingtype_assessment_outdated'] = 'Connected assessment may be outdated';
$string['findingtype_accessibility'] = 'Accessibility or readability concern';
$string['findingtype_other'] = 'Other';
$string['findingstatus_open'] = 'Open';
$string['findingstatus_under_review'] = 'Under review';
$string['findingstatus_accepted'] = 'Accepted';
$string['findingstatus_dismissed'] = 'Dismissed';
$string['findingstatus_resolved'] = 'Resolved';
$string['findingstatus_intentional_difference'] = 'Intentional difference';
$string['scale_low'] = 'Low';
$string['scale_medium'] = 'Medium';
$string['scale_high'] = 'High';

// Reports (spec 12.5, 15.3).
$string['reports'] = 'Reports';
$string['reporthealth'] = 'Editorial health';
$string['reportpaths'] = 'Path completion';
$string['reportpageacks'] = 'Page acknowledgements';
$string['reportoverdue'] = 'Review date exceeded';
$string['reportmissingowner'] = 'No owner assigned';
$string['reportneverpublished'] = 'Never published';
$string['reportagingdrafts'] = 'Oldest drafts in review';
$string['openfindingscount'] = 'Open quality findings: {$a}';
$string['reportpathintro'] = 'Confirmed required pages per staff member ({$a} required pages in this path). Staff = users holding the handbook view capability.';
$string['pathprogressshort'] = 'Confirmed';
$string['reportconfirmed'] = 'Confirmed';
$string['reportpending'] = 'Pending';
$string['norequiredpages'] = 'No published required-reading pages yet.';

// Notifications and scheduled tasks (spec 21).
$string['messageprovider:draftsubmitted'] = 'Handbook draft submitted for review';
$string['messageprovider:changesrequested'] = 'Changes requested on your handbook draft';
$string['messageprovider:findingcreated'] = 'New handbook quality finding';
$string['messageprovider:reviewdue'] = 'Handbook page review due';
$string['notifydraftsubmitted_subject'] = 'Draft for review: {$a->title} (v{$a->version})';
$string['notifydraftsubmitted_body'] = 'A draft revision of "{$a->title}" (v{$a->version}) was submitted for review. Change summary: {$a->summary}';
$string['notifychangesrequested_subject'] = 'Changes requested: {$a->title} (v{$a->version})';
$string['notifychangesrequested_body'] = 'Your draft of "{$a->title}" (v{$a->version}) was returned with the note: {$a->note}';
$string['notifyfindingcreated_subject'] = 'New quality finding #F-{$a->id}: {$a->type}';
$string['notifyfindingcreated_body'] = 'A new quality finding was reported: {$a->summary}';
$string['notifyreviewdue_subject'] = 'Review due: {$a->title}';
$string['notifyreviewdue_body'] = 'The page "{$a->title}" you own reaches its review date on {$a->reviewdate}. Please review it and publish an updated revision or extend the review date.';
$string['task_reviewreminder'] = 'Handbook review-date reminders';
$string['task_linkchecker'] = 'Handbook link checker';
$string['brokenlinksummary'] = 'Page "{$a->page}" links to "{$a->target}", which does not exist or is not published.';
$string['brokenquizsummary'] = 'Reading-path item for page "{$a->page}" points to quiz course module {$a->cmid}, which no longer exists.';

// External API.
$string['errorexcludedpage'] = 'This page is excluded from external and AI access.';
$string['errormetadataonly'] = 'This page is metadata-only for external and AI access; its content cannot be read or drafted through the API.';
$string['errorbasemismatch'] = 'The published revision has changed since it was read. Fetch the current version before drafting.';

// Errors.
$string['errorbootstrapoff'] = 'Direct publishing requires bootstrap mode (see plugin settings).';
$string['errorpagenotfound'] = 'Handbook page not found.';
$string['errorcategorynotfound'] = 'Handbook category not found.';
$string['errorslugexists'] = 'This slug is already in use.';
$string['errordraftexists'] = 'An unpublished draft already exists for this page. Edit that draft instead of creating a new one.';
$string['errornodraft'] = 'There is no editable draft revision for this page.';
$string['errorrevisionconflict'] = 'The revision was modified by someone else while you were editing. Review the newer version before saving again.';
$string['errorworkflowstate'] = 'This action is not allowed in the revision\'s current workflow state.';

// Archive and restore (spec 11.3).
$string['archivepage'] = 'Archive';
$string['unarchivepage'] = 'Unarchive';
$string['pagearchived'] = 'Page archived. Its revision history is preserved.';
$string['pageunarchived'] = 'Page restored from the archive.';
$string['confirmarchive'] = 'Archive "{$a}"? Readers will no longer see it; editors keep access and the full history is preserved.';
$string['confirmunarchive'] = 'Restore "{$a}" from the archive? It becomes visible to readers again.';
$string['restoreasdraft'] = 'Restore as draft';
$string['confirmrestore'] = 'Create a new working draft based on v{$a}? Later history is kept; the draft goes through the normal review workflow.';
$string['restoredsummary'] = 'Restored from v{$a}.';
$string['revisionrestored'] = 'v{$a} was restored as a new working draft.';

// Privacy export paths.
$string['privacy:acknowledgementspath'] = 'Reading acknowledgements';
$string['privacy:authoredpath'] = 'Authored revisions';
$string['privacy:metadata:local_handbook_finding'] = 'Quality findings record who reported, was assigned to and resolved them.';

// Privacy API.
$string['privacy:metadata:local_handbook_revision'] = 'Handbook revisions record which user created, modified, reviewed, approved or published them.';
$string['privacy:metadata:local_handbook_revision:createdby'] = 'The user who created the revision.';
$string['privacy:metadata:local_handbook_revision:modifiedby'] = 'The user who last modified the revision.';
$string['privacy:metadata:local_handbook_revision:publishedby'] = 'The user who published the revision.';
$string['privacy:metadata:local_handbook_page'] = 'Handbook pages record their owner, approver and the users who created and modified them.';
$string['privacy:metadata:local_handbook_page:owneruserid'] = 'The user responsible for keeping the page accurate.';
$string['privacy:metadata:local_handbook_category'] = 'Handbook categories record the users who created and modified them.';

// Change sets and public authorship (specification 36).
$string['author'] = 'Author';
$string['changesets'] = 'Change sets';
$string['changeset'] = 'Change set';
$string['changesetdefaultsummary'] = 'Change set: {$a}';
$string['event_revision_approved'] = 'Handbook revision approved';
$string['event_revision_rejected'] = 'Handbook revision rejected';
$string['event_changes_requested'] = 'Handbook changes requested';
$string['event_changeset_created'] = 'Handbook change set created';
$string['event_changeset_submitted'] = 'Handbook change set submitted';
$string['errorchangesetlocked'] = 'This change set is completed or cancelled and can no longer be modified.';
$string['errorchangeitemlocked'] = 'This item is in review, approved or published and cannot be removed from the change set.';
$string['conflict_humandraft'] = 'A working draft (v{$a}) that is not part of this change set already exists for this page; a human must resolve it before the change set can draft here.';
$string['conflict_foreignchangeset'] = 'This page already has a working draft (v{$a}) in another change set.';
$string['conflict_inreview'] = 'This change set\'s draft (v{$a}) is in review or approved; a human must return it before further edits.';
$string['conflict_basemismatch'] = 'The published revision changed since it was read; refresh the page before drafting.';
$string['conflict_concurrency'] = 'The draft (v{$a}) was modified since it was last read; re-read it before updating.';
$string['changesetstatus_draft'] = 'Draft';
$string['changesetstatus_in_review'] = 'In review';
$string['changesetstatus_partially_completed'] = 'Partially completed';
$string['changesetstatus_completed'] = 'Completed';
$string['changesetstatus_cancelled'] = 'Cancelled';
$string['itemstatus_draft'] = 'Draft';
$string['itemstatus_conflict'] = 'Conflict';
$string['itemstatus_in_review'] = 'In review';
$string['itemstatus_approved'] = 'Approved';
$string['itemstatus_published'] = 'Published';
$string['itemstatus_rejected'] = 'Rejected';
$string['itemstatus_skipped'] = 'Skipped';
