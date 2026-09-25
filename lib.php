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
 * Library functions for local_courseversion.
 *
 * @package   local_courseversion
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Block course editing if version is locked.
 * Legacy callback for Moodle < 4.3 compatibility.
 * New hook system in classes/hook/before_http_headers.php for Moodle 4.3+.
 */
function local_courseversion_before_http_headers() {
    local_courseversion_check_and_block_edit();
}

/**
 * After config hook - fires very early in Moodle bootstrap.
 * This is the earliest point we can intercept requests.
 */
function local_courseversion_after_config() {
    // On Moodle 4.3+ the hook system calls local_courseversion\hook\after_config::callback()
    // instead. Return early to prevent double-execution.
    if (class_exists('\core\hook\after_config')) {
        return;
    }
    local_courseversion_check_and_block_edit();
}

/**
 * Shared logic to check and block course editing.
 * Called by both legacy callback and new hook system.
 * 
 * DESIGN PRINCIPLES (ChatGPT audit fixes v1.3.6):
 * - Only block STRUCTURAL changes (add/edit/delete activities, sections, settings)
 * - ALWAYS allow learning interactions (submissions, forums, quizzes, completion, grading)
 * - Never use wildcards like 'mod_' - use explicit allow/block lists
 * - Allow web services for Moodle Mobile app
 */
function local_courseversion_check_and_block_edit() {
    global $PAGE, $DB, $USER, $CFG;

    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $script = $_SERVER['SCRIPT_NAME'] ?? '';

    // BUG-CV-DOUBLE-READ FIX: Buffer php://input exactly once here so both
    // local_courseversion_is_structural_inplace_edit() and the AJAX Priority 6
    // course-ID resolver can read the same body. php://input is a non-seekable
    // stream on many PHP/FPM configurations — a second file_get_contents() call
    // returns an empty string, silently killing course-ID resolution and causing
    // the $PAGE->context fallback to fire with a potentially wrong course context.
    $GLOBALS['local_cv_raw_body'] = null; // reset each request
    if (strpos($script, '/lib/ajax/') !== false) {
        $GLOBALS['local_cv_raw_body'] = file_get_contents('php://input');
    }

    // ========== ALWAYS ALLOWED PAGES (never block) ==========
    // These are pages where teachers/students need to interact even in locked courses
    $allowedscripts = [
        // Grading (ChatGPT fix #1)
        '/grade/',                    // All grading pages
        '/grade/report/',             // Grade reports
        '/grade/edit/',               // Grade editing
        
        // Assignment (ChatGPT fix #3)
        '/mod/assign/view.php',       // Assignment viewing/grading
        '/mod/assign/grader.php',     // Assignment grader
        '/mod/assign/submission.php', // Assignment submissions
        
        // Quiz
        '/mod/quiz/review.php',       // Quiz review
        '/mod/quiz/attempt.php',      // Quiz attempts
        '/mod/quiz/summary.php',      // Quiz summary
        '/mod/quiz/startattempt.php', // Start quiz attempt
        
        // Forum (ChatGPT fix #2)
        '/mod/forum/post.php',        // Forum posting
        '/mod/forum/discuss.php',     // Forum discussions
        '/mod/forum/view.php',        // Forum view
        
        // Completion (ChatGPT fix #4)
        '/course/completion.php',     // Completion management
        '/course/togglecompletion.php', // Toggle completion
        
        // User management
        '/user/',                     // User pages
        '/enrol/',                    // Enrollment pages
        '/group/',                    // Group pages
        '/cohort/',                   // Cohort pages
        '/admin/roles/',              // Role assignment
        
        // Reports & communication
        '/report/',                   // Reports
        '/calendar/',                 // Calendar
        '/message/',                  // Messaging
        '/badges/',                   // Badges
        '/comment/',                  // Comments
        
        // Web services for Moodle Mobile (ChatGPT fix #5)
        '/webservice/',               // All web service calls
        '/webservice/rest/server.php', // REST API
        '/webservice/xmlrpc/server.php', // XMLRPC API
        
        // Other activity viewing
        '/mod/resource/',             // Resource viewing
        '/mod/page/',                 // Page viewing
        '/mod/url/',                  // URL viewing
        '/mod/folder/',               // Folder viewing
        '/mod/book/',                 // Book viewing
        '/mod/scorm/',                // SCORM attempts
        '/mod/h5pactivity/',          // H5P activity
        '/mod/lesson/',               // Lesson attempts
        '/mod/workshop/',             // Workshop submissions
        '/mod/glossary/',             // Glossary entries
        '/mod/wiki/',                 // Wiki editing (student content)
        '/mod/data/',                 // Database entries
        '/mod/chat/',                 // Chat
        '/mod/choice/',               // Choice voting
        '/mod/feedback/',             // Feedback responses
        '/mod/survey/',               // Survey responses
    ];
    
    foreach ($allowedscripts as $allowed) {
        if (strpos($script, $allowed) !== false) {
            return; // Always allow these pages
        }
    }

    // ========== BLOCKED SCRIPTS (structure changes only) ==========
    $blockedscripts = [
        '/course/edit.php',           // Course settings
        '/course/modedit.php',        // Activity editing
        '/course/mod.php',            // Activity add/delete/move
        '/course/editsection.php',    // Section editing
        '/course/delete.php',         // Course deletion
        '/course/rest.php',           // REST API for course changes
        '/course/dndupload.php',      // Drag-drop uploads
        '/lib/ajax/service.php',      // AJAX service (filtered below)
        '/course/format/',            // Format changes
        '/backup/backup.php',         // Backup
        '/backup/restorefile.php',    // Restore
        '/course/reset.php',          // Course reset
        '/course/changenumsections.php', // Section count
        '/course/bulkactivity.php',   // Bulk activity actions
        '/admin/tool/recyclebin/',    // Recycle bin restore
    ];

    $shouldcheck = false;
    foreach ($blockedscripts as $blocked) {
        if (strpos($script, $blocked) !== false) {
            $shouldcheck = true;
            break;
        }
    }

    // ========== AJAX FILTER (v1.3.7 - REVERSED LOGIC) ==========
    // DESIGN: Allow everything by default, block only explicitly destructive actions
    // This is future-proof as new Moodle/plugin actions are automatically allowed
    
    // Use AJAX_SCRIPT constant for reliable detection
    $isajaxrequest = (defined('AJAX_SCRIPT') && AJAX_SCRIPT) || 
                     strpos($script, '/lib/ajax/') !== false ||
                     (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    
    if (strpos($script, '/lib/ajax/service.php') !== false) {
        $info = optional_param('info', '', PARAM_ALPHANUMEXT);
        
        // 🔴 EXPLICITLY BLOCKED AJAX actions (structural changes ONLY)
        // Everything else is ALLOWED by default - this is the future-proof approach
        $blockedajaxactions = [
            // Course structure mutations
            'core_course_edit_module',
            'core_course_delete_module',
            'core_course_set_visibility',
            'core_course_update_module',
            'core_course_duplicate_module',
            
            // Section/activity movement (v1.3.7 fix #3 - only block destructive actions)
            'core_courseformat_move_section',
            'core_courseformat_move_cm',
            'core_courseformat_update_course',
        ];
        
        // Check if action is in the blocked list
        $isblocked = false;
        foreach ($blockedajaxactions as $action) {
            if (strpos($info, $action) !== false) {
                $isblocked = true;
                break;
            }
        }
        
        // Special handling for core_update_inplace_editable (v1.3.7 fix #1)
        // This is used for BOTH structural edits AND non-structural edits (grading, comments)
        // We must inspect the component/itemtype to decide
        if (strpos($info, 'core_update_inplace_editable') !== false) {
            $isblocked = local_courseversion_is_structural_inplace_edit();
        }
        
        // If not blocked, allow everything (reversed logic)
        if (!$isblocked) {
            return;
        }
    }

    if (!$shouldcheck) {
        return;
    }

    // ========== COURSE ID RESOLUTION (ChatGPT fix #7) ==========
    $courseid = local_courseversion_get_course_id_from_request($script);

    if (!$courseid || (defined('SITEID') && $courseid == SITEID)) {
        return;
    }

    $lockinfo = local_courseversion_get_lock_info($courseid);
    if (!$lockinfo) {
        return;
    }

    // Check override capability
    try {
        require_once($CFG->libdir . '/accesslib.php');
        $context = context_course::instance($courseid, IGNORE_MISSING);
        if ($context && has_capability('local/courseversion:override', $context, null, false)) {
            return;
        }
    } catch (Exception $e) {
        // Proceed with lock
    }

    // ========== LOG BLOCKED ATTEMPT (with throttling - ChatGPT fix #10) ==========
    local_courseversion_log_blocked_edit_throttled($courseid, $lockinfo, $script);

    // ========== RETURN ERROR ==========
    if ($isajaxrequest) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode([
            'error' => true,
            'errorcode' => 'courselocked',
            'message' => 'This course is locked (Version ' . $lockinfo->version . '). Structural editing is disabled.',
            'exception' => [
                'errorcode' => 'courselocked',
                'message' => 'Course locked - Version ' . $lockinfo->version
            ]
        ]);
        die();
    }

    // Standard redirect for non-AJAX
    require_once($CFG->libdir . '/weblib.php');
    $url = new moodle_url('/course/view.php', ['id' => $courseid]);
    $message = get_string('courselocked', 'local_courseversion', $lockinfo);
    redirect($url, $message, null, \core\output\notification::NOTIFY_ERROR);
}

/**
 * Check if an inplace edit targets structural course elements (v1.3.7 fix #1).
 * 
 * core_update_inplace_editable is used for both:
 * - Structural edits: course name, section name, activity name (BLOCK)
 * - Non-structural edits: grade feedback, comments, inline grading (ALLOW)
 * 
 * We inspect the component and itemtype to determine which.
 * 
 * @return bool True if this is a structural edit that should be blocked
 */
function local_courseversion_is_structural_inplace_edit() {
    // Use pre-buffered body (BUG-CV-DOUBLE-READ FIX): php://input is a non-seekable
    // stream; reading it twice returns empty string on the second call. The outer
    // function local_courseversion_check_and_block_edit() buffers it once into
    // $GLOBALS['local_cv_raw_body'] so both this function and Priority 6 of the
    // course-ID resolver operate on the same data.
    $rawbody = $GLOBALS['local_cv_raw_body'] ?? null;
    if (!$rawbody) {
        return false; // Can't determine, allow by default
    }
    
    $data = json_decode($rawbody, true);
    if (!is_array($data)) {
        return false;
    }
    
    // Structural components/itemtypes that should be blocked
    $structuraltargets = [
        // Course-level edits
        'core_course' => ['coursename', 'activityname', 'sectionname'],
        'format_topics' => ['sectionname'],
        'format_weeks' => ['sectionname'],
        'format_tiles' => ['sectionname'],
        'core_courseformat' => ['sectionname', 'activityname'],
        // Module name edits
        'mod_' => ['name'], // Any module name edit
    ];
    
    foreach ($data as $call) {
        if (!isset($call['methodname']) || $call['methodname'] !== 'core_update_inplace_editable') {
            continue;
        }
        
        $component = $call['args']['component'] ?? '';
        $itemtype = $call['args']['itemtype'] ?? '';
        
        // Check if this is a structural target
        foreach ($structuraltargets as $targetcomponent => $itemtypes) {
            // Exact match or prefix match for mod_
            $matches = ($component === $targetcomponent) || 
                       ($targetcomponent === 'mod_' && strpos($component, 'mod_') === 0);
            
            if ($matches && in_array($itemtype, $itemtypes, true)) {
                return true; // This is a structural edit, block it
            }
        }
        
        // Specific checks for section name edits
        if ($itemtype === 'sectionname' || $itemtype === 'activityname') {
            return true; // Block section/activity name edits
        }
    }
    
    return false; // Not a structural edit, allow it
}

/**
 * Log blocked edit with throttling to prevent spam (v1.3.7 fix #5).
 * Throttles per: session + action + course to prevent audit table bloat.
 */
function local_courseversion_log_blocked_edit_throttled($courseid, $lockinfo, $script) {
    global $SESSION;
    
    // Get the AJAX action if present for more granular throttling
    $action = optional_param('info', 'page', PARAM_ALPHANUMEXT);
    
    // Create a unique key for this specific blocked action
    // Includes: session (implicit), script, action, and course
    $key = 'cv_logged_' . md5($script . '_' . $action . '_' . $courseid);
    
    // Check if we've already logged this in this session
    if (!empty($SESSION->$key)) {
        return; // Already logged this specific block in this session
    }
    
    // Mark as logged for this session
    $SESSION->$key = time();
    
    // Now do the actual logging
    local_courseversion_log_blocked_edit($courseid, $lockinfo, $script);
}

/**
 * Get course ID from request parameters (ChatGPT fix #7 - comprehensive detection).
 * 
 * Handles all common request patterns:
 * - Direct course/courseid parameters
 * - Course module ID (cmid, update, delete, duplicate, hide, show)
 * - Forum discussion ID
 * - Quiz attempt ID
 * - Section ID
 * - AJAX service JSON body
 */
function local_courseversion_get_course_id_from_request($script) {
    global $PAGE, $DB;

    $courseid = 0;

    // === BUG-CV-AJAX-PRIORITY-SKIP FIX (v1.5.0) ===
    // For AJAX service calls, Priority 1 (GET/POST course/courseid params) MUST be skipped.
    // Moodle's JavaScript course editor appends the current page's course context to the
    // AJAX query string (e.g. /lib/ajax/service.php?course=3092&info=core_courseformat_update_course).
    // If the user previously visited a locked course (3092), this stale GET param fires
    // Priority 1 and returns 3092 — even when the user is actually working in an unlocked
    // course (2280). Priority 1 short-circuits before the v1.4.9 BATCH-CROSS-COURSE FIX
    // at Priority 6 ever runs, so the JSON body (the authoritative source for AJAX) is
    // never consulted. Fix: for /lib/ajax/service.php, jump directly to Priority 6.
    $isajaxservice = strpos($script, '/lib/ajax/service.php') !== false;

    if (!$isajaxservice) {
        // === PRIORITY 1: Direct course parameters (non-AJAX only) ===
        if (($v = optional_param('courseid', 0, PARAM_INT)) > 0) {
            return $v;
        }
        if (($v = optional_param('course', 0, PARAM_INT)) > 0) {
            return $v;
        }

        // Course edit page uses 'id' for course ID
        if ((($reqid = optional_param('id', 0, PARAM_INT)) > 0) && strpos($script, '/course/edit.php') !== false) {
            return $reqid;
        }
    }
    
    // === PRIORITY 2: Course module ID (ChatGPT fix #7) ===
    // cmid parameter is common across many pages
    $cmid = 0;
    if (!empty(optional_param('cmid', 0, PARAM_INT))) {
        $cmid = (int)optional_param('cmid', 0, PARAM_INT);
    } else if (!empty(optional_param('update', 0, PARAM_INT))) {
        $cmid = (int)optional_param('update', 0, PARAM_INT);
    } else if (!empty(optional_param('delete', 0, PARAM_INT))) {
        $cmid = (int)optional_param('delete', 0, PARAM_INT);
    } else if (!empty(optional_param('duplicate', 0, PARAM_INT))) {
        $cmid = (int)optional_param('duplicate', 0, PARAM_INT);
    } else if (!empty(optional_param('hide', 0, PARAM_INT))) {
        $cmid = (int)optional_param('hide', 0, PARAM_INT);
    } else if (!empty(optional_param('show', 0, PARAM_INT))) {
        $cmid = (int)optional_param('show', 0, PARAM_INT);
    } else if ((($reqid = optional_param('id', 0, PARAM_INT)) > 0) && strpos($script, '/course/mod.php') !== false) {
        // === BUG-CV-ADD-RESOURCE-CROSS-COURSE FIX (v1.5.11) ===
        // In course/mod.php the 'id' parameter has TWO meanings:
        //   - When 'add' is present, 'id' is the COURSE id. Moodle core reads it as
        //     $id = required_param('id', PARAM_INT) and passes it straight through as
        //     'course' => $id when redirecting to modedit.php.
        //   - When 'add' is absent, 'id' is a course-module id (update/move/delete).
        //
        // v1.5.3 attempted this distinction but tested 'add' with PARAM_INT. The value
        // is a module name such as 'resource' or 'assign', so PARAM_INT reduced it to 0
        // and empty() was always true — the guard never closed. 'id' was then resolved
        // as a cmid, matching an unrelated module in a DIFFERENT course, and the lock
        // check fired against that course. Teachers adding an activity in an unlocked
        // course were redirected to whichever course that coincidental cmid belonged to.
        //
        // Fix: read 'add' as text, and on the add path return 'id' as the course id so
        // the lock check still runs — against the correct course.
        if (optional_param('add', '', PARAM_ALPHANUMEXT) !== '') {
            return $reqid;
        }
        $cmid = $reqid;
    }
    
    if ($cmid) {
        $cm = get_coursemodule_from_id(null, $cmid, 0, false, IGNORE_MISSING);
        if ($cm) {
            return $cm->course;
        }
        // Fallback to direct DB query
        $cmrecord = $DB->get_record('course_modules', ['id' => $cmid], 'course', IGNORE_MISSING);
        if ($cmrecord) {
            return $cmrecord->course;
        }
    }
    
    // === PRIORITY 3: Forum discussion ID (ChatGPT fix #7) ===
    if (!empty(optional_param('discussion', 0, PARAM_INT)) || !empty(optional_param('d', 0, PARAM_INT))) {
        $discussionid = (int)(optional_param('discussion', 0, PARAM_INT) ?? optional_param('d', 0, PARAM_INT));
        if ($discussionid) {
            $course = $DB->get_field('forum_discussions', 'course', ['id' => $discussionid], IGNORE_MISSING);
            if ($course) {
                return $course;
            }
        }
    }
    
    // === PRIORITY 4: Quiz attempt ID (ChatGPT fix #7) ===
    if (!empty(optional_param('attemptid', 0, PARAM_INT)) || !empty(optional_param('attempt', 0, PARAM_INT))) {
        $attemptid = (int)(optional_param('attemptid', 0, PARAM_INT) ?? optional_param('attempt', 0, PARAM_INT));
        if ($attemptid) {
            $course = $DB->get_field_sql(
                "SELECT q.course FROM {quiz_attempts} qa
                 JOIN {quiz} q ON q.id = qa.quiz
                 WHERE qa.id = ?",
                [$attemptid],
                IGNORE_MISSING
            );
            if ($course) {
                return $course;
            }
        }
    }
    
    // === PRIORITY 5: Section ID ===
    if ((($reqid = optional_param('id', 0, PARAM_INT)) > 0) && strpos($script, '/course/editsection.php') !== false) {
        $sectionid = $reqid;
        $section = $DB->get_record('course_sections', ['id' => $sectionid], 'course', IGNORE_MISSING);
        if ($section) {
            return $section->course;
        }
    }
    
    // Move activity
    if (!empty(optional_param('moveto', 0, PARAM_INT)) || !empty(optional_param('move', 0, PARAM_INT))) {
        $cmid = (int)(optional_param('id', 0, PARAM_INT) ?? 0);
        if ($cmid) {
            $cm = $DB->get_record('course_modules', ['id' => $cmid], 'course', IGNORE_MISSING);
            if ($cm) {
                return $cm->course;
            }
        }
    }
    
    // REST API calls
    if (strpos($script, '/course/rest.php') !== false) {
        if (!empty(optional_param('id', 0, PARAM_INT))) {
            return $reqid;
        }
    }
    
    // Backup/restore
    if (strpos($script, '/backup/') !== false && !empty(optional_param('id', 0, PARAM_INT))) {
        return $reqid;
    }
    
    // Course reset
    if (strpos($script, '/course/reset.php') !== false && !empty(optional_param('id', 0, PARAM_INT))) {
        return $reqid;
    }
    
    // DND upload
    if (strpos($script, '/course/dndupload.php') !== false && !empty(optional_param('course', 0, PARAM_INT))) {
        return (int)optional_param('course', 0, PARAM_INT);
    }
    
    // === PRIORITY 6: AJAX service calls - parse JSON body ===
    // BUG-CV-DOUBLE-READ FIX: Use the pre-buffered body from $GLOBALS['local_cv_raw_body']
    // instead of re-calling file_get_contents('php://input'), which returns empty string
    // on the second read on most PHP/FPM configurations.
    // BUG-CV-AJAX-PARAM FIX: Check both 'courseid' AND 'course' in args — different
    // Moodle versions (4.1 vs 4.4) use different parameter names for core_courseformat_update_course.
    // BUG-CV-BATCH-CROSS-COURSE FIX (v1.4.9): Moodle 4.4+ often sends batched AJAX requests
    // containing multiple service calls in a single POST. The previous code iterated ALL
    // calls and returned the FIRST courseid found — even if that courseid came from a
    // completely unrelated, non-blocked call (e.g. a navigation or context call that still
    // carried a stale courseid=3092 from a previous page visit). The actual blocked call
    // (e.g. core_courseformat_update_course) with the correct courseid=2280 was later in
    // the batch and was never reached. Fix: only extract the course ID from calls whose
    // methodname is explicitly in the blocked actions list. Other batched calls are ignored.
    if (strpos($script, '/lib/ajax/service.php') !== false) {
        $rawbody = $GLOBALS['local_cv_raw_body'] ?? null;
        if ($rawbody) {
            $data = json_decode($rawbody, true);
            if (is_array($data)) {
                // BATCH CROSS-COURSE FIX: mirror the same blocked-method list used by the
                // AJAX filter above. Only resolve course ID from calls that are actually
                // blocked — never from unrelated calls that happen to share the batch.
                $blockedmethods = [
                    'core_course_edit_module',
                    'core_course_delete_module',
                    'core_course_set_visibility',
                    'core_course_update_module',
                    'core_course_duplicate_module',
                    'core_courseformat_move_section',
                    'core_courseformat_move_cm',
                    'core_courseformat_update_course',
                    'core_update_inplace_editable',
                ];
                foreach ($data as $call) {
                    // Skip calls that are not in the blocked list — their args may carry
                    // stale courseids from unrelated contexts (the cross-course bug).
                    $methodname = $call['methodname'] ?? '';
                    $isblocked = false;
                    foreach ($blockedmethods as $bm) {
                        if ($methodname === $bm) {
                            $isblocked = true;
                            break;
                        }
                    }
                    if (!$isblocked) {
                        continue;
                    }

                    // Direct courseid — check both 'courseid' (Moodle 4.4+) and 'course' (Moodle 4.1+)
                    if (!empty($call['args']['courseid'])) {
                        return (int)$call['args']['courseid'];
                    }
                    if (!empty($call['args']['course'])) {
                        return (int)$call['args']['course'];
                    }
                    // Course module ID in AJAX
                    if (!empty($call['args']['cmid'])) {
                        $cmid = (int)$call['args']['cmid'];
                        $cm = $DB->get_record('course_modules', ['id' => $cmid], 'course', IGNORE_MISSING);
                        if ($cm) {
                            return $cm->course;
                        }
                    }
                    // Section ID in AJAX
                    if (!empty($call['args']['sectionid'])) {
                        $sectionid = (int)$call['args']['sectionid'];
                        $section = $DB->get_record('course_sections', ['id' => $sectionid], 'course', IGNORE_MISSING);
                        if ($section) {
                            return $section->course;
                        }
                    }
                    // Forum discussion in AJAX
                    if (!empty($call['args']['discussionid'])) {
                        $discussionid = (int)$call['args']['discussionid'];
                        $course = $DB->get_field('forum_discussions', 'course', ['id' => $discussionid], IGNORE_MISSING);
                        if ($course) {
                            return $course;
                        }
                    }
                }
            }
        }
    }

    // NOTE: $PAGE->context fallback deliberately removed (BUG-CV-DOUBLE-READ FIX).
    // During local_courseversion_after_config(), $PAGE->context is not yet set, making
    // the fallback always return 0. During before_http_headers, $PAGE->context can be
    // set to a DIFFERENT course's context (e.g., if Moodle's AJAX service receives a
    // stale contextid from a previous Course 1 page visit), returning Course 1's ID
    // while the user is actually working in Course 2. This is the root cause of
    // BUG-CV-DOUBLE-READ — cross-course lock contamination. Fail-safe: return 0 (no
    // block) when course ID cannot be determined from request parameters.
    return 0;
}

/**
 * Check if a Moodle course is locked via version control.
 *
 * @param int $moodlecourseid The Moodle course ID
 * @return object|null Lock info or null if not locked
 */
function local_courseversion_get_lock_info($moodlecourseid) {
    global $DB;

    try {
        // Use get_records_sql with LIMIT 1 to handle duplicate course records
        $cvcourses = $DB->get_records_sql(
            "SELECT * FROM {local_cv_courses} 
             WHERE moodle_course_id = ? 
             ORDER BY id DESC LIMIT 1",
            [$moodlecourseid]
        );
        
        if (empty($cvcourses)) {
            return null;
        }
        
        $cvcourse = reset($cvcourses);

        // Use get_records with LIMIT 1 to avoid error if multiple active versions exist
        $activeversions = $DB->get_records_sql(
            "SELECT * FROM {local_cv_versions} 
             WHERE courseid = ? AND status = 'active' AND locked = 1 
             ORDER BY timecreated DESC LIMIT 1",
            [$cvcourse->id]
        );

        if (empty($activeversions)) {
            return null;
        }

        $activeversion = reset($activeversions);

        return (object)[
            'version' => $activeversion->version_number,
            'reason' => $activeversion->lock_reason ?? '',
            'cvcourse_id' => $cvcourse->id
        ];
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Log a blocked edit attempt to the audit trail.
 */
function local_courseversion_log_blocked_edit($courseid, $lockinfo, $script) {
    global $DB, $USER;

    try {
        $record = new stdClass();
        $record->courseid = $lockinfo->cvcourse_id;
        $record->versionid = null;
        $record->action = 'blocked_edit';
        $record->userid = isset($USER->id) ? $USER->id : 0;
        $record->timecreated = time();
        $record->reason = 'Attempted: ' . basename($script);
        $record->details = json_encode([
            'moodle_course_id' => $courseid,
            'script' => $script,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'locked_version' => $lockinfo->version
        ]);
        $DB->insert_record('local_cv_audit', $record);
    } catch (Exception $e) {
        // Don't fail if logging fails
    }
}

/**
 * Inject CSS/JS to disable editing UI on locked courses.
 * This is a belt-and-suspenders approach.
 * IMPORTANT: Only applies to course content pages, NOT enrollment/participants pages.
 */
function local_courseversion_before_standard_top_of_body_html_generation() {
    global $PAGE, $DB, $OUTPUT;
    
    try {
        if (!$PAGE->context) {
            return '';
        }
        
        $coursecontext = $PAGE->context->get_course_context(false);
        if (!$coursecontext) {
            return '';
        }
        
        $courseid = $coursecontext->instanceid;
        if (!$courseid || (defined('SITEID') && $courseid == SITEID)) {
            return '';
        }
        
        // IMPORTANT: Do NOT apply lock CSS on enrollment/participants/user pages
        // These pages need full functionality for role assignment
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $excludedpages = [
            '/user/index.php',        // Participants page
            '/user/view.php',         // User profile
            '/enrol/',                // All enrollment pages
            '/group/',                // Groups management
            '/cohort/',               // Cohort management
            '/admin/roles/',          // Role assignment pages
        ];
        foreach ($excludedpages as $excluded) {
            if (strpos($script, $excluded) !== false) {
                return '';
            }
        }
        
        $lockinfo = local_courseversion_get_lock_info($courseid);
        if (!$lockinfo) {
            return '';
        }
        
        // Check override
        if (has_capability('local/courseversion:override', $coursecontext, null, false)) {
            return '';
        }
        
        // Inject CSS to hide editing controls and JS to prevent actions
        // ONLY for course content editing - not enrollment/role management
        $html = '<style id="cv-lock-styles">
            /* Hide all editing controls - COURSE CONTENT ONLY */
            .editing .activity-actions,
            .editing .section-actions,
            .editing .course-content-item-content .activity-basis,
            .editing [data-action="cmDuplicate"],
            .editing [data-action="cmDelete"],
            .editing [data-action="sectionDelete"],
            .editing .activity-item .actions,
            .editing .section .actions,
            .editing .editmode-switch-form,
            .editing #changenumsections,
            .editing .add-activity-or-resource,
            .editing .activity-add,
            .editing .section-add-menus,
            .activity-actions .dropdown,
            .editing .activity-moveicon,
            .editing .section-moveicon,
            [data-for="section_cmlist"] .activity-actions,
            .btn-outline-secondary[data-action="togglecoursecontentcollapsed"],
            .activity-item .dropdown-toggle:not([data-action="completion-toggle"]) {
                display: none !important;
            }
            /* Hide inline edit pencil icons for activity names and section names ONLY */
            /* Use specific selectors to avoid affecting role editing on participants page */
            .inplaceeditable[data-component="core_course"] .quickediticon,
            .inplaceeditable[data-component="format_topics"] .quickediticon,
            .inplaceeditable[data-component="format_weeks"] .quickediticon,
            #page-course-view-topics .inplaceeditable .quickediticon,
            #page-course-view-weeks .inplaceeditable .quickediticon,
            .course-content .inplaceeditable .quickediticon,
            .course-content a.quickeditlink,
            .editing .course-content .quickediticon,
            .course-content .editsection,
            .section-modchooser,
            .course-content [data-action="edit"],
            .course-content .action-edit,
            .editing_title .iconsmall,
            .activityinstance .editing_title,
            .course-content .inplaceeditable .aalink.quickeditlink {
                display: none !important;
                visibility: hidden !important;
                pointer-events: none !important;
            }
            /* Disable clicking on inplace editable elements - COURSE CONTENT ONLY */
            .course-content .inplaceeditable {
                pointer-events: none !important;
            }
            /* But allow clicking on the activity link itself */
            .course-content .inplaceeditable .aalink:not(.quickeditlink),
            .activityname .aalink,
            .activity-instance a.aalink {
                pointer-events: auto !important;
            }
            .course-locked-banner {
                background: #dc3545;
                color: white;
                padding: 10px 20px;
                text-align: center;
                font-weight: bold;
                margin-bottom: 10px;
            }
        </style>';
        
        $html .= '<script>
            document.addEventListener("DOMContentLoaded", function () {
                // Disable editing mode toggle
                var editBtn = document.querySelector(".editmode-switch-form input[type=submit]");
                if (editBtn) {
                    editBtn.disabled = true;
                    editBtn.title = "Course is locked - Version ' . $lockinfo->version . '";
                }
                
                // Disable all action dropdowns
                document.querySelectorAll(".activity-actions, .section-actions").forEach(function (el) {
                    el.style.display = "none";
                });
                
                // Intercept form submissions
                document.querySelectorAll("form").forEach(function (form) {
                    var action = form.getAttribute("action") || "";
                    if (action.includes("/course/mod.php") || 
                        action.includes("/course/modedit.php") ||
                        action.includes("/course/editsection.php")) {
                        form.addEventListener("submit", function (e) {
                            e.preventDefault();
                            alert("This course is locked (Version ' . $lockinfo->version . '). Editing is disabled.");
                            return false;
                        });
                    }
                });
            });
        </script>';
        
        return $html;
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Extend navigation.
 */
function local_courseversion_extend_navigation(global_navigation $navigation) {
    global $PAGE;
    
    if (has_capability('local/courseversion:manage', context_system::instance()) ||
        has_capability('local/courseversion:create', context_system::instance())) {
        // Add to navigation if user has access
    }
}

/**
 * Extend course navigation to show lock status and inject lock CSS.
 * IMPORTANT: Only applies to course content pages, NOT enrollment/participants pages.
 */
function local_courseversion_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    global $DB, $PAGE;
    
    // IMPORTANT: Do NOT apply lock CSS on enrollment/participants/user pages
    // These pages need full functionality for role assignment
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $excludedpages = [
        '/user/index.php',        // Participants page
        '/user/view.php',         // User profile
        '/enrol/',                // All enrollment pages
        '/group/',                // Groups management
        '/cohort/',               // Cohort management
        '/admin/roles/',          // Role assignment pages
    ];
    foreach ($excludedpages as $excluded) {
        if (strpos($script, $excluded) !== false) {
            return;
        }
    }
    
    $lockinfo = local_courseversion_get_lock_info($course->id);
    if (!$lockinfo) {
        return;
    }
    
    // Check override capability
    if (has_capability('local/courseversion:override', $context, null, false)) {
        return;
    }
    
    // Inject CSS to hide ONLY the pencil edit icons for COURSE CONTENT, not enrollment/role editing
    $css = '
        /* Hide only the pencil icon link - COURSE CONTENT ONLY */
        .course-content a.quickeditlink,
        .course-content .quickediticon,
        .course-content .inplaceeditable a[data-inplaceeditablelink="1"],
        #page-course-view-topics a[data-inplaceeditablelink="1"],
        #page-course-view-weeks a[data-inplaceeditablelink="1"] {
            display: none !important;
        }
        /* Hide all editing controls - COURSE CONTENT ONLY */
        .editing .activity-actions,
        .editing .section-actions,
        .editing [data-action="cmDuplicate"],
        .editing [data-action="cmDelete"],
        .editing [data-action="sectionDelete"],
        .editing .activity-item .actions,
        .editing .section .actions,
        .editing .editmode-switch-form,
        .editing #changenumsections,
        .editing .add-activity-or-resource,
        .editing .activity-add,
        .editing .section-add-menus,
        .activity-actions .dropdown,
        .editing .activity-moveicon,
        .editing .section-moveicon,
        .editsection,
        .section-modchooser {
            display: none !important;
        }
    ';
    
    $PAGE->requires->css('/local/courseversion/styles.css');
    
    // Use JavaScript to inject the CSS and hide pencil icons only
    $jscode = 'var cvStyle = document.createElement("style");
        cvStyle.id = "cv-lock-styles";
        cvStyle.textContent = ' . json_encode($css) . ';
        document.head.appendChild(cvStyle);
        // Hide only the pencil icon anchors, do NOT remove them
        document.querySelectorAll("a.quickeditlink, .quickediticon, a[data-inplaceeditablelink]").forEach(function (el) {
            el.style.display = "none";
        });';
    
    $PAGE->requires->js_init_code($jscode, true);
}

/**
 * Add links to site administration.
 */
function local_courseversion_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    global $PAGE;
    
    if ($settingsnav->get('siteadministration')) {
        $node = $settingsnav->get('siteadministration');
        if ($node && has_capability('local/courseversion:manage', context_system::instance())) {
            // Admin links are handled via settings.php
        }
    }
}

/**
 * Check if ASQA guidance is enabled.
 */
function local_courseversion_asqa_enabled() {
    return get_config('local_courseversion', 'enableasqaguidance');
}

/**
 * Get status badge class.
 */
function local_courseversion_get_status_class($status) {
    $classes = [
        'draft' => 'cv-badge cv-badge-draft',
        'active' => 'cv-badge cv-badge-active',
        'superseded' => 'cv-badge cv-badge-superseded',
        'archived' => 'cv-badge cv-badge-archived',
    ];
    return $classes[$status] ?? 'cv-badge';
}

/**
 * Log an audit action.
 */
function local_courseversion_log_action($action, $versionid = null, $courseid = null, $reason = '', $details = []) {
    global $DB, $USER;
    
    $record = new stdClass();
    $record->action = $action;
    $record->versionid = $versionid;
    $record->courseid = $courseid;
    $record->userid = $USER->id;
    $record->reason = $reason;
    $record->details = json_encode($details);
    $record->ipaddress = getremoteaddr();
    $record->timecreated = time();
    
    return $DB->insert_record('local_cv_audit_log', $record);
}

/**
 * Check if version should be auto-locked.
 */
function local_courseversion_check_auto_lock($versionid) {
    global $DB;
    
    $state = $DB->get_record('local_cv_assessment_state', ['versionid' => $versionid]);
    if (!$state) {
        return false;
    }
    
    return ($state->has_enrolments || $state->has_attempts);
}

/**
 * Sync assessment state from Moodle course.
 */
function local_courseversion_sync_assessment_state($versionid) {
    global $DB;
    
    $version = $DB->get_record('local_cv_versions', ['id' => $versionid]);
    if (!$version) {
        return false;
    }
    
    $course = $DB->get_record('local_cv_courses', ['id' => $version->courseid]);
    if (!$course || !$course->moodle_course_id) {
        return false;
    }
    
    $moodlecourseid = $course->moodle_course_id;
    
    // Count enrolments
    $enrolcount = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT ue.userid) 
         FROM {user_enrolments} ue
         JOIN {enrol} e ON e.id = ue.enrolid
         WHERE e.courseid = ? AND ue.status = 0",
        [$moodlecourseid]
    );
    
    // Count quiz attempts
    $attemptcount = $DB->count_records_sql(
        "SELECT COUNT(*) 
         FROM {quiz_attempts} qa
         JOIN {quiz} q ON q.id = qa.quiz
         WHERE q.course = ?",
        [$moodlecourseid]
    );
    
    // Get last attempt date
    $lastattempt = $DB->get_field_sql(
        "SELECT MAX(qa.timefinish) 
         FROM {quiz_attempts} qa
         JOIN {quiz} q ON q.id = qa.quiz
         WHERE q.course = ?",
        [$moodlecourseid]
    );
    
    $now = time();
    $state = $DB->get_record('local_cv_assessment_state', ['versionid' => $versionid]);
    
    if ($state) {
        $state->has_enrolments = $enrolcount > 0 ? 1 : 0;
        $state->has_attempts = $attemptcount > 0 ? 1 : 0;
        $state->enrolment_count = $enrolcount;
        $state->attempt_count = $attemptcount;
        $state->last_attempt_date = $lastattempt ?: null;
        $state->last_sync = $now;
        $state->timemodified = $now;
        $DB->update_record('local_cv_assessment_state', $state);
    } else {
        $state = new stdClass();
        $state->versionid = $versionid;
        $state->has_enrolments = $enrolcount > 0 ? 1 : 0;
        $state->has_attempts = $attemptcount > 0 ? 1 : 0;
        $state->enrolment_count = $enrolcount;
        $state->attempt_count = $attemptcount;
        $state->last_attempt_date = $lastattempt ?: null;
        $state->last_sync = $now;
        $state->timecreated = $now;
        $state->timemodified = $now;
        $DB->insert_record('local_cv_assessment_state', $state);
    }
    
    // Auto-lock if has enrolments or attempts
    if (($enrolcount > 0 || $attemptcount > 0) && !$version->locked) {
        $version->locked = 1;
        $version->lock_reason = get_string('autolockedmessage', 'local_courseversion', 
            (object)['enrolments' => $enrolcount, 'attempts' => $attemptcount]);
        $version->timemodified = $now;
        $DB->update_record('local_cv_versions', $version);
        
        local_courseversion_log_action('auto_lock', $versionid, $course->id, 
            'Automatically locked due to enrolments/attempts', 
            ['enrolments' => $enrolcount, 'attempts' => $attemptcount]);
    }
    
    return $state;
}

/**
 * Create a new version from existing.
 */
function local_courseversion_create_version_from($baseversionid, $newversionnumber) {
    global $DB, $USER;
    
    $base = $DB->get_record('local_cv_versions', ['id' => $baseversionid], '*', MUST_EXIST);
    $now = time();
    
    $newversion = new stdClass();
    $newversion->courseid = $base->courseid;
    $newversion->version_number = $newversionnumber;
    $newversion->release_year = get_config('local_courseversion', 'defaultreleaseyear') ?: date('Y');
    $newversion->status = 'draft';
    $newversion->change_summary = '';
    $newversion->tas_version = $base->tas_version;
    $newversion->validation_date = null;
    $newversion->validation_notes = '';
    $newversion->locked = 0;
    $newversion->lock_reason = null;
    $newversion->supersedes_version_id = $baseversionid;
    $newversion->releasedby = null;
    $newversion->releasedat = null;
    $newversion->createdby = $USER->id;
    $newversion->timecreated = $now;
    $newversion->timemodified = $now;
    
    $newid = $DB->insert_record('local_cv_versions', $newversion);
    
    // Create empty assessment state
    $state = new stdClass();
    $state->versionid = $newid;
    $state->has_enrolments = 0;
    $state->has_attempts = 0;
    $state->enrolment_count = 0;
    $state->attempt_count = 0;
    $state->last_attempt_date = null;
    $state->last_sync = null;
    $state->timecreated = $now;
    $state->timemodified = $now;
    $DB->insert_record('local_cv_assessment_state', $state);
    
    $course = $DB->get_record('local_cv_courses', ['id' => $base->courseid]);
    local_courseversion_log_action('create', $newid, $base->courseid, 
        "Created from version {$base->version_number}",
        ['base_version' => $base->version_number, 'new_version' => $newversionnumber]);
    
    return $newid;
}

/**
 * Release a version.
 */
function local_courseversion_release_version($versionid) {
    global $DB, $USER;
    
    $version = $DB->get_record('local_cv_versions', ['id' => $versionid], '*', MUST_EXIST);
    $now = time();
    
    // Supersede current active version
    $DB->execute(
        "UPDATE {local_cv_versions} SET status = 'superseded', timemodified = ? WHERE courseid = ? AND status = 'active'",
        [$now, $version->courseid]
    );
    
    // Release this version
    $version->status = 'active';
    $version->locked = 1;
    $version->lock_reason = 'Released version - automatically locked';
    $version->releasedby = $USER->id;
    $version->releasedat = $now;
    $version->timemodified = $now;
    $DB->update_record('local_cv_versions', $version);
    
    local_courseversion_log_action('release', $versionid, $version->courseid, 
        "Released version {$version->version_number}",
        ['change_summary' => $version->change_summary]);
    
    return true;
}

/**
 * Archive a version.
 */
function local_courseversion_archive_version($versionid, $reason = '') {
    global $DB, $USER;
    
    $version = $DB->get_record('local_cv_versions', ['id' => $versionid], '*', MUST_EXIST);
    $now = time();
    
    $version->status = 'archived';
    $version->locked = 1;
    $version->lock_reason = 'Archived - permanently locked';
    $version->timemodified = $now;
    $DB->update_record('local_cv_versions', $version);
    
    local_courseversion_log_action('archive', $versionid, $version->courseid, $reason);
    
    return true;
}

/**
 * Override a version lock.
 */
function local_courseversion_override_lock($versionid, $reason) {
    global $DB, $USER;
    
    $version = $DB->get_record('local_cv_versions', ['id' => $versionid], '*', MUST_EXIST);
    $now = time();
    
    $version->locked = 0;
    $version->lock_reason = null;
    $version->timemodified = $now;
    $DB->update_record('local_cv_versions', $version);
    
    local_courseversion_log_action('override', $versionid, $version->courseid, $reason);
    
    return true;
}
