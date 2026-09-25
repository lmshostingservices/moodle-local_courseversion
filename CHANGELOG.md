# Changelog - Course Version Control Local Plugin

All notable changes to this plugin will be documented in this file.

## [1.5.14] - 2026-09-25

### Fixed (BUG-CV-HOOK-LIB-NOT-LOADED)

`Exception - Call to undefined function local_courseversion\hook\local_courseversion_check_and_block_edit()`

The hook callback classes (`classes/hook/after_config.php`,
`classes/hook/before_http_headers.php`) called `local_courseversion_check_and_block_edit()`,
which lives in `lib.php`, without loading `lib.php`. The hook manager never
includes a plugin's `lib.php`. It normally got loaded only as a side effect of
core's legacy-callback scan (`get_plugins_with_function()`) that runs just before
the hook is dispatched. That scan returns early when `$CFG->upgraderunning` is set
or during initial install, but hooks are still dispatched then. So during a
plugin or core upgrade, `lib.php` was never loaded and the callback fatalled.

**Fix:** both callbacks now:
- return immediately during install/upgrade (as core does for legacy callbacks);
- `require_once` the plugin's `lib.php` and call the function fully qualified.

### Changed

- `README.md` rewritten: requirements, installation, configuration, capabilities,
  external service disclosure, privacy and support.
- Privacy provider now works: it reports the contexts and users that hold audit
  log data and exports a user's audit entries (previously all stubs). Missing
  `privacy:metadata:*` strings added, and the lms-labs.com licence check is
  declared as an external location. Audit entries are still kept on deletion
  requests, as compliance records.
- Page templates (`archive.php`, `audit.php`, `courses.php`, `edit_version.php`,
  `index.php`, `override.php`, `release.php`, `versions.php`): lines holding
  several `<?php ?>` statements merged into a single statement per line. Output
  HTML is unchanged, apart from escaping `version_status` in the index badge.
- Multi-line function calls reformatted so the opening parenthesis ends the line
  (`archive.php`, `classes/observer.php`, `edit_course.php`, `edit_version.php`,
  `lib.php`, `override.php`, `release.php`).
- Lang: `tasversion` changed to sentence case ("TAS document version").
- Comments: first letter capitalised where a comment block started lowercase.
- 1.5.12 and 1.5.13 were not released; their packages were superseded before promotion.

### Known issue (not changed in this release)

`local_courseversion_before_standard_top_of_body_html_generation()` in `lib.php`
has never been called by Moodle on any version. The legacy callback name is
`before_standard_top_of_body_html` (no `_generation`), and no hook is registered
for it. The locked-course UI injection it contains is dormant code.

## [1.5.11] - 2026-08-21

### Fixed (BUG-CV-ADD-RESOURCE-CROSS-COURSE — recurrence of KB-003)

The v1.5.3 fix for this issue never executed. It guarded on
`empty(optional_param('add', 0, PARAM_INT))`, but `add` carries a module name
(`resource`, `assign`, `quiz`, `label`). `PARAM_INT` is a PHP `(int)` cast, which
reads leading digits only, so every module name became `0`, `empty()` was always
true, and the guard never closed.

`local_courseversion_get_course_id_from_request()` therefore resolved `id` as a
course-module id on every `/course/mod.php` request. Because `id` is in fact the
**course** id when `add` is set, the lookup matched an unrelated module in a
different course and the lock check ran against that course. A teacher adding an
activity in an unlocked course was redirected to whichever course the
coincidental cmid belonged to.

Observed on wombatlms.com.au (Moodle 5.0.1), confirmed server-side as HTTP 303
with `Location: /course/view.php?id=4044`:

| Editing (unlocked) | `id` read as cmid | Module lives in | Redirected to |
|---|---|---|---|
| BSBOPS504 (3258) | 3258 | HLTWHS005 (4044, locked) | HLTWHS005 |
| BSBSUS211 (2261) | 2261 | CHCCCS040 (3092, locked) | CHCCCS040 |

**Fix:** read `add` with `PARAM_ALPHANUMEXT`, and on the add path return `id`
directly as the course id. Locked courses are still blocked when a teacher tries
to add an activity to them — now against the correct course. Merely skipping the
cmid lookup would have left locked courses editable on the add path.

### Changed

- `settings.php`: admin section parameter constrained from `PARAM_RAW` to
  `PARAM_ALPHANUMEXT`.
- `db/upgrade.php`: savepoints reduced to a single 10-digit baseline
  (`2026072300`). The eight previous 13-digit savepoints were all opcache
  invalidation with no schema change, so no upgrade history is lost.
- Removed from the package: `BUILD_INFO.json` (internal build artefact),
  `classes/hook/*.bak_20260811225813`, `version.php.13bak`.

### Known issues, not addressed in this release

- `local_courseversion_get_lock_info()` catches all exceptions and returns
  `null`, which the caller reads as "not locked". A transient database error
  silently permits a structural edit.
- The override check uses `has_capability(..., null, false)`, disabling Moodle's
  "do anything". A site administrator is therefore **not** granted the override
  implicitly; the capability must be assigned explicitly.

## [1.5.3] - 2026-04-10

### Fixed (BUG-CV-ADD-RESOURCE-CROSS-COURSE)

**Root cause:** `local_courseversion_get_course_id_from_request()` Priority 2 resolved
`$_REQUEST['id']` as a course-module ID (CMID) for *any* `/course/mod.php` request —
including requests where `add=TYPE` was also set (teacher adding a NEW resource/activity
via the activity chooser). When adding a new module, `id` in `mod.php` is NOT a CMID;
it is a section reference or `beforemod` pointer. If that integer value coincidentally
matched a CMID belonging to a module in a **locked** course, the resolver returned the
locked course's Moodle ID, the lock check fired, and the teacher was incorrectly
redirected to the locked course's view page — even though they were working in a
completely different, unlocked course.

**Client symptom:** "Adding a text resource in Course 1 (unlocked) redirects me to
Course 2 (locked)" — a recurring issue reported as KB-003.

**Fix (lib.php line ~432):** Added `if (empty($_REQUEST['add']))` guard before the
`$_REQUEST['id']` → cmid resolution block for `/course/mod.php`. Now: when `add` IS
set (adding new module), `id` is skipped as a potential CMID source and the resolver
falls through to other priorities or returns 0 (safe fail — no block). When `add` is
NOT set (existing module operations: moveleft, moveright, setindent, etc.), the `id`
→ CMID resolution is preserved as before.

**No DB schema changes.** version.php → 2026041000153.

## [1.5.2] - 2026-04-10

### Fixed
- unlock_verifier.php switched from raw PHP `curl_init()` to Moodle's `\curl` class
  so unlock API calls succeed on Moodle hosting environments where the raw curl
  bypassed the CA bundle. No DB changes. version.php → 2026041000152.

## [1.3.7] - 2025-12-23

### Changed (Production-Ready - Final ChatGPT Review Fixes)

**Fix #1 - core_update_inplace_editable Smart Detection:**
- No longer blocks this action unconditionally
- New `local_courseversion_is_structural_inplace_edit()` function inspects JSON body
- Inspects `component` and `itemtype` to determine if structural
- BLOCKS: course name, section name, activity name edits
- ALLOWS: grade feedback, inline grading, comments, custom fields

**Fix #2 - Reversed AJAX Logic (Future-Proof):**
- Removed prefix-based allow-list (fragile, breaks on Moodle updates)
- Now: Allow everything by default, block only explicitly destructive actions
- This is the only approach that survives Moodle version upgrades and new plugins
- Blocked actions: `core_course_edit/delete/update/duplicate_module`, `core_course_set_visibility`, `core_courseformat_move_*`

**Fix #3 - Courseformat AJAX Tightened:**
- Removed `core_courseformat_state_set` from blocked list (UI state, not destructive)
- Only block actual move/update actions: `move_section`, `move_cm`, `update_course`
- Read-only courseformat actions now work

**Fix #4 - Web Services Already Allowed:**
- Verified `/webservice/` is in allowed scripts list since v1.3.6
- Moodle Mobile app works correctly on locked courses

**Fix #5 - Improved Log Throttling:**
- Now throttles per: session + action + course (not just script + course)
- Prevents AJAX retry spam from flooding audit table
- Key includes `$_REQUEST['info']` for granular deduplication

### Security
- Allow-by-default, block-only-destructive design pattern
- No prefix matching - explicit action names only
- Structural vs non-structural detection for inplace edits

## [1.3.6] - 2025-12-23

### Changed (ChatGPT Security Audit - All 10 Fixes Implemented)

**Fix #1 - Gradebook Access:**
- Added `/grade/`, `/grade/report/`, `/grade/edit/` to always-allowed pages
- Teachers can now grade, edit feedback, override grades on locked courses

**Fix #2 - Forum Posting AJAX:**
- Replaced dangerous `mod_` wildcard with explicit `mod_forum_` allow prefix
- Forum posts and replies now work via AJAX on locked courses

**Fix #3 - Assignment Grading AJAX:**
- Added `mod_assign_` to allowed AJAX prefixes
- Assignment submissions, grading, feedback all work on locked courses

**Fix #4 - Course Completion Tracking:**
- Added `core_completion_` to allowed AJAX prefixes
- Student activity completion, teacher overrides now work on locked courses
- Added `/course/togglecompletion.php` to allowed pages

**Fix #5 - Mobile App & Web Services:**
- Added `/webservice/`, `/webservice/rest/server.php`, `/webservice/xmlrpc/server.php` to allowed pages
- Moodle Mobile app now works fully on locked courses

**Fix #6 - Section Drag-and-Drop:**
- Blocked only structural mutations: `core_courseformat_state_set`, `core_courseformat_move_section`, `core_courseformat_move_cm`
- Allow read-only courseformat calls

**Fix #7 - Course ID Resolution (Complete Rewrite):**
- Now detects course from: `cmid`, `discussion`, `attemptid`, `sectionid`
- Uses `get_coursemodule_from_id()` for reliable cmid resolution
- Forum discussion ID resolves to course via `forum_discussions` table
- Quiz attempt ID resolves via `quiz_attempts` + `quiz` tables join
- AJAX JSON body parsing for `discussionid`

**Fix #8 - AJAX Detection:**
- Now uses `AJAX_SCRIPT` constant as primary detection method
- Fallback to `HTTP_X_REQUESTED_WITH` header and script path

**Fix #10 - Audit Log Throttling:**
- New `local_courseversion_log_blocked_edit_throttled()` function
- Logs once per script type per session to prevent table bloat
- Uses session-based deduplication with md5 key

### Security
- Replaced all wildcard patterns with explicit allow/block lists
- Never blocks: learning activities, grading, forums, quizzes, completion, web services
- Only blocks: course structure mutations (add/edit/delete activities, sections, settings)

## [1.3.5] - 2025-12-23

### Fixed
- Added explicit allow-list for pages that should NEVER be blocked: grading, assignments, quizzes, forums, completion, reports, badges, calendar, messaging
- Fixed AJAX filter - now only blocks course structure modifications, not student/teacher activity interactions
- Students can now: complete activities, submit assignments, attempt quizzes, post in forums on locked courses
- Teachers can now: grade assignments, review quizzes, manage completion on locked courses

## [1.3.4] - 2025-12-23

### Fixed
- **CRITICAL**: Fixed role assignment bug on locked courses - users can now be enrolled and assigned roles
- CSS lock styles now only apply to course content pages, NOT enrollment/participants pages
- Excluded pages from lock CSS: /user/index.php, /user/view.php, /enrol/, /group/, /cohort/, /admin/roles/
- Made CSS selectors more specific to `.course-content` to avoid affecting role editing UI

## [1.3.2] - 2025-12-22

### Changed
- Added official Moodle 5.x compatibility declaration (`$plugin->supported = [400, 500]`)
- Verified all navigation callbacks are compatible with Moodle 5

## [1.3.1] - 2025-12-20

### Changed
- Migrated to centralized download architecture
- Updated versioned ZIP filename

## [1.3.0] - 2025-12-01

### Added
- Enhanced version tracking
- Diff comparison
- Rollback functionality

## [1.0.0] - 2025-06-01

### Added
- Initial release
- Course version control
- Moodle 4.0+ compatibility
