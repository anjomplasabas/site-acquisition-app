# Site Acquisition Platform — Cycle 8 Fix Changelog

**Draft date:** October 8, 2026  
**Release branch:** `fix/qa-cycle-8-20261006`  
**QA source of truth:** `Site Acquisition Platform_Bug Tracker_Cycle 1.xlsx` → `List of Bug_Cycle 1`  
**Important:** The QA statuses below are the statuses recorded before the Cycle 8 fixes. QA should update the active tracker after retesting.

## Release summary

- **23** tracker entries cross-referenced against the active defect tracker.
- **18** items ready for QA retest, including one item already delivered in earlier commits.
- **2** items require focused verification and must not be closed beyond the implemented scope.
- **1** item requires product clarification and **2** Invalid items have no code change.
- Deployment must include the frontend build and database migrations for the new site-profile fields, Design Plan revision remarks, and updated site-type storage.

## Send-ready message

**Subject:** Site Acquisition Platform — Cycle 8 Fixes for QA Retest

Hi Team,

The Cycle 8 fixes and improvements for the Site Acquisition Platform are ready for QA retesting once the release branch has been deployed to the QA environment.

Please use the Bug Number, QA worksheet row, and Test Case ID in the matrix below when recording retest results in the active defect tracker. For each item, please update the **Retest QA Status** and add the retest date, tester, environment, and supporting screenshot or video in the **QA Comment** field.

Please note the following scope limitations:

- **Bug #136** is only partially addressed. The new site fields and corrected labels are included, but the Surveyor edit permission and final field naming still require business confirmation.
- **Bug #173** includes regression coverage and improved rejection notifications, but the full tracker expectation is not yet confirmed. Completion markers still reset so the affected sections can be resubmitted, which may display a Pending status after rejection.
- **Bug #163** is a behavior clarification and still requires product confirmation; it is not a code fix.
- **Bugs #47 and #80** were marked Invalid in the active tracker and have no code change.

Kindly retest the applicable scenarios and record the results against the exact tracker references below.

Thank you.

## QA retest matrix

### Implemented and ready for retest

| Bug # | QA row | Test case | Type / severity | Pre-fix QA status | Change included | Retest acceptance criteria | Commit |
|---:|---:|---|---|---|---|---|---|
| 88 | 89 | UMTC-06 | Bug / Major | Failed | Corrected the combined User search and Status filter logic. | Searching for a name or email while Status is `Inactive` returns only matching inactive users; active users must not bypass the selected status. | `f1a3748` |
| 117 | 118 | NTC-46 | Bug / Major | Failed | Network Feasibility becomes read-only immediately after completion; the server-side restriction remains enforced. | After completion, users cannot add or edit ISP entries or upload, replace, or remove attachments. | `f1a3748` |
| 167 | 168 | PROJTC-144 | Bug / Major | Failed | Closing-stage completion now validates remarks for RFCO or Acceptance statuses set to `On-hold`. | Saving or completing with either status on hold and blank remarks is blocked with a clear validation message. | `f1a3748` |
| 127 | 128 | SPTC_168 | Bug / Major | No Fix Yet | Added a Request Revision flow for submitted Design Plans, including required remarks and designer notification. | Infra Manager can request revision with remarks; the assigned designer is notified; existing Design Plan data and files remain available for revision. | `4047309` |
| 140 | 141 | — | Bug / Major | No Fix Yet | ISP Status is owned by the Network team and is read-only for Infrastructure users, with UI and server enforcement. | Infra Manager cannot add, edit, or delete ISP Status data; an authorized Network user can still manage it. | `67b392e` |
| 145 | 146 | — | Bug / Major | No Fix Yet | Planning Details are Infrastructure-owned; Network users are blocked from field and document changes. Carried-over MOA/NTP files cannot be deleted. | A Network user cannot edit Contractor or upload, replace, or delete MOA/NTP/BOQ/PR in Project Planning. Retest Bug #183 separately for the newly reported Project Execution scope. | `67b392e` |
| 161 | 162 | PROJTC-95 | Bug / Major | No Fix Yet | PM In-charge is limited to active Surveyors and defaults to the assigned Surveyor. | The dropdown contains only active users with the Surveyor role, rejects an invalid user, and initially selects the assigned Surveyor. | `67b392e` |
| 178 | 179 | — | Bug / Major | No Fix Yet | Restricted unassigned Design Plans so they can be managed only by authorized managers. | An Infrastructure Designer cannot update or complete an unassigned Design Plan; the authorized manager remains able to manage it. | `4047309` |
| 181 | 182 | — | Bug / Minor | No Fix Yet | Removed “Pipeline ID” from the Site Planning search placeholder. | The placeholder no longer claims that Pipeline ID is searchable; supported search behavior remains functional. | `4047309` |
| 147 | 148 | — | Improvement / Major | No Fix Yet | Updated site types to Condominium, School, Dormitory, Apartment, ROW House, and Others with free-text detail; legacy values remain readable. | All new categories can be saved; selecting Others accepts its detail; existing records with legacy types still load correctly. | `5161e0a` |
| 151 | 152 | — | Improvement / Minor | No Fix Yet | Expanded Surveyor assignment notifications in-app and by email. | The assigned Surveyor receives both notifications with survey date, address, tower, and remarks. | `54edf0d` |
| 155 | 156 | — | Improvement / Major | No Fix Yet | Allows the assigned Surveyor or Infra Manager to change the site Type during Site Survey. | Both authorized roles can update and retain the Type; unauthorized users cannot. | `54edf0d` |
| 159 | 160 | — | Improvement / Minor | No Fix Yet | Added an Assigned badge and limited Infrastructure Designers to their assigned Design Plans. | The list shows the badge correctly, and each designer sees only Design Plans assigned to them. | `54edf0d` |
| 168 | 169 | — | Improvement / Major | No Fix Yet | Enabled ZIP archives for Site Planning uploads; the existing maximum upload size is 100 MB. | A supported ZIP file up to 100 MB uploads successfully; a file above the limit is rejected with a user-facing validation message. | `5161e0a` |
| 179 | 180 | — | Improvement / Major | No Fix Yet | Added a `For Approval` Design Plan filter and linked the dashboard counter to the filtered list. | The filter shows only Design Plans awaiting approval, and the counter opens the same filtered results with a matching count. | `54edf0d` |
| 180 | 181 | — | Improvement / Minor | No Fix Yet | Added Pipeline Aging (Days) to the Site Survey Report. | The report contains the new column and calculates elapsed days from endorsement date, falling back to created date when no endorsement date exists. QA/Product should confirm that this matches the tracker term “Pipeline Initiation Date.” | `54edf0d` |
| 182 | 183 | — | Improvement / Minor | No Fix Yet | Added a Tower column to the Site Survey Report. | Each exported row identifies its corresponding tower, including pipelines that contain multiple towers. | `54edf0d` |
| 137 | 138 | — | Improvement / Major | No Fix Yet | The Telco Room Fee transfer and per-tower progress tracking were already implemented in earlier changes. | Confirm Telco Room Fee is on the SAQ side and progress can be identified per tower without regression. | `5974c62`, `2eb9ee1` |

### Partial implementation or focused verification

| Bug # | QA row | Test case | Type / severity | Pre-fix QA status | Implemented scope | Remaining concern / QA action | Commit |
|---:|---:|---|---|---|---|---|---|
| 136 | 137 | — | Improvement / Major | Failed | Added Total No. of Towers/Buildings, Residential Unit Starting Floor, and Floor Not in Plan; exposed site metric fields on the pipeline site form; clarified Total Units/Floors per Tower labels. | Retest only the implemented fields and labels. Do not close the full item until the requested Surveyor edit permission and exact business-approved field names are confirmed. Bug #184 in the active tracker is a separate open issue concerning per-tower values not appearing in the Site Survey Form. | `f1a3748` |
| 173 | 174 | — | Bug / Major | No Fix Yet | Added regression coverage for retained Network and Design Plan data and added rejection-reason notifications for assigned Surveyors, the Designer, and the Network team. | Verify data/files are retained and recipients can see the rejection reason. The full original expectation remains subject to confirmation because completion markers reset for resubmission and the status may appear Pending. | `4047309` |

### Clarification or no code change

| Bug # | QA row | Test case | Type / severity | Active QA status | Disposition | Required action |
|---:|---:|---|---|---|---|---|
| 163 | 164 | — | Bug / Minor | No Fix Yet | The Monitoring percentage is the average of weighted Infrastructure Deployment progress and Network Deployment progress; it is not the Share Connect 82.5% figure. | Product/QA must confirm whether this behavior is accepted and whether percentages are also required on other stages. |
| 47 | 48 | — | Bug / Major | Invalid | Adding an activity or site visit to an inactive site is expected behavior per the tracker discussion. | Close or retain as Invalid; no retest of a code change is required. |
| 80 | 81 | — | Bug / Minor | Invalid | The two Preview Import buttons intentionally expose the same action at the top and bottom of a long mapping form. | Close or retain as Invalid unless QA requests removal of one button. |

## Active tracker reconciliation

The 23 Cycle 8 entries above were cross-checked against the active tracker. Their Bug Number, worksheet row, Test Case ID, Type, Severity, and pre-fix QA status all match.

The active tracker is newer than the tracker copy embedded in the Cycle 1–8 workbook. The following updates are outside the 23-item Cycle 8 changelog and should not be represented as fixes from this release branch:

- **Passed:** Bug #134, Bug #135, Bug #143, Bug #149 (`PROJTC-101`), and Bug #169 (`PROJTC-170`).
- **Failed:** Bug #141 and Bug #142, both with QA comments dated October 7, 2026.
- **New / No Fix Yet:** Bug #183 (Network access to Project Planning and Project Execution) and Bug #184 (`SPTC_246`, per-tower values not reflected in the Site Survey Form).

Bug #183 partially overlaps Bug #145, but its Project Execution scope is not covered by the Cycle 8 fix and must remain open. Bug #184 is related to the broader multi-tower work but is not resolved by the field and label changes listed under Bug #136.

## Release verification note

The listed commits exist on `origin/fix/qa-cycle-8-20261006`. As of October 8, 2026, that branch is not contained in `origin/staging`, so this changelog should say **ready for QA retest after deployment** rather than **deployed** or **QA passed**.
