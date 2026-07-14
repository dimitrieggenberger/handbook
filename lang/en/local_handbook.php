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
$string['topcategory'] = '(top level)';
$string['newcategory'] = 'New category';
$string['editcategory'] = 'Edit category';
$string['deletecategory'] = 'Delete category';
$string['categorysaved'] = 'Category saved.';
$string['categorydeleted'] = 'Category deleted.';
$string['confirmdeletecategory'] = 'Delete category "{$a}"? This is only possible while it has no pages and no subcategories.';
$string['categorynotempty'] = 'This category still contains pages or subcategories and cannot be deleted.';

// Errors.
$string['errorpagenotfound'] = 'Handbook page not found.';
$string['errorcategorynotfound'] = 'Handbook category not found.';
$string['errorslugexists'] = 'This slug is already in use.';
$string['errordraftexists'] = 'An unpublished draft already exists for this page. Edit that draft instead of creating a new one.';
$string['errornodraft'] = 'There is no editable draft revision for this page.';
$string['errorrevisionconflict'] = 'The revision was modified by someone else while you were editing. Review the newer version before saving again.';
$string['errorworkflowstate'] = 'This action is not allowed in the revision\'s current workflow state.';

// Privacy API.
$string['privacy:metadata:local_handbook_revision'] = 'Handbook revisions record which user created, modified, reviewed, approved or published them.';
$string['privacy:metadata:local_handbook_revision:createdby'] = 'The user who created the revision.';
$string['privacy:metadata:local_handbook_revision:modifiedby'] = 'The user who last modified the revision.';
$string['privacy:metadata:local_handbook_revision:publishedby'] = 'The user who published the revision.';
$string['privacy:metadata:local_handbook_page'] = 'Handbook pages record their owner, approver and the users who created and modified them.';
$string['privacy:metadata:local_handbook_page:owneruserid'] = 'The user responsible for keeping the page accurate.';
$string['privacy:metadata:local_handbook_category'] = 'Handbook categories record the users who created and modified them.';
