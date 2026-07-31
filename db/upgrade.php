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
 * Upgrade steps for local_courseversion.
 *
 * @package    local_courseversion
 * @copyright  2026 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_courseversion_upgrade($oldversion) {

    // v1.5.2: FIX — unlock_verifier.php switched from raw PHP curl_init() to Moodle's \curl
    // class (require_once $CFG->libdir/filelib.php). Raw curl_init() bypassed Moodle's SSL
    // cert bundle, causing silent API call failures on Moodle hosting environments.
    // Moodle \curl uses the correct CA bundle and respects proxy settings.
    // No DB schema changes. version.php → 2026041000152.
    if ($oldversion < 2026041000152) {
        upgrade_plugin_savepoint(true, 2026041000152, 'local', 'courseversion');
    }

    // v1.5.3: BUG FIX — course ID resolver in local_courseversion_get_course_id_from_request()
    // was treating $_REQUEST['id'] as a course-module ID (CMID) for any /course/mod.php request,
    // including requests where 'add' is also set (teacher adding a new resource/activity).
    // When 'add' is set, 'id' is NOT a CMID — it is a section reference or beforemod pointer.
    // If that value coincidentally matched a CMID belonging to a module in a LOCKED course,
    // the resolver returned the locked course's Moodle ID, triggered the lock check, and
    // incorrectly redirected the teacher to the locked course's view page, even though they
    // were working in a completely different, unlocked course.
    // Fix: only resolve 'id' as a CMID for mod.php when 'add' is NOT set in the request.
    // No DB schema changes. version.php → 2026041000153.
    if ($oldversion < 2026041000153) {
        upgrade_plugin_savepoint(true, 2026041000153, 'local', 'courseversion');
    }

    // v1.5.4 - BUG FIX (BUG-CV-UNLOCK-FLAP):
    //   The 1-hour application cache (core/config) is purged by "Purge all caches", Moodle
    //   upgrades, and any site-admin cache-clear action. After every purge, is_unlocked() had
    //   to re-call the API. If credentials had drifted (e.g. after a Moodle upgrade reset
    //   local_aiconfig settings) the API would return unlocked:false for the already-paid-for
    //   site, and the "1000 credits to unlock" notice would reappear.
    //   Fix: three-tier unlock check:
    //     TIER 1 — Permanent DB flag (get_config / set_config on mdl_config_plugins).
    //              Written once the first time the API confirms unlocked=true. Survives ALL
    //              cache purges and upgrades indefinitely.
    //     TIER 2 — 24-hour application cache (extended from 1 h so API is called at most
    //              once per day on sites that haven't yet been permanently flagged).
    //     TIER 3 — Live API call + auto-unlock (unchanged from before).
    //   Once TIER 1 is set, the function returns immediately without any cache or network
    //   access, so the notice can never reappear on an already-unlocked site.
    //   File changed: classes/unlock_verifier.php. No DB schema changes. version.php → 2026042000154.
    if ($oldversion < 2026042000154) {
        upgrade_plugin_savepoint(true, 2026042000154, 'local', 'courseversion');
    }

    // v1.5.5 - BUG FIX (BUG-CV-UNLOCK-CACHE):
    //   v1.5.4 introduced a 24-hour application cache for the unlock result, but cached
    //   BOTH positive (unlocked) and negative (not unlocked) results. If a site loaded
    //   Course Version Control before purchasing the unlock, the cache stored unlocked:false
    //   for 24 hours. When the admin then purchased the unlock via the dashboard, the
    //   cached negative result prevented Moodle from picking up the new unlock status —
    //   the "1000 credits to unlock" notice continued to appear for up to 24 hours.
    //   Fix: negative (unlocked:false) results are deliberately NOT written to the cache.
    //   The live API is called on every page load until the unlock is confirmed. Once
    //   confirmed, the 24-hour cache and permanent DB flag are written as before, so
    //   subsequent checks are instant and never hit the network again.
    //   File changed: classes/unlock_verifier.php. No DB schema changes. version.php → 2026042000155.
    if ($oldversion < 2026042000155) {
        upgrade_plugin_savepoint(true, 2026042000155, 'local', 'courseversion');
    }

    // v1.5.6 - HOOK-MIGRATION (core\hook\after_config):
    //   Migrated legacy local_courseversion_after_config() lib.php callback to the
    //   Moodle 4.3+ hook system. Added classes/hook/after_config.php and registered
    //   it in db/hooks.php. The legacy function now returns early when
    //   \core\hook\after_config exists (Moodle 4.3+) to prevent double-execution.
    //   No DB schema changes.
    if ($oldversion < 2026071500156) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'version.php', 'db/upgrade.php', 'db/hooks.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026071500156, 'local', 'courseversion');
    }

    if ($oldversion < 2026072300210) {
        // FIX-API-DOMAIN: Updated all API endpoint URLs from lms-labs.com to lms-labs.com.
        // lms-labs.com has no DNS resolution from Moodle server side; lms-labs.com is the
        // correct working domain. All ajax.php, api_client, unlock_verifier, lib.php calls updated.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026072300210, 'local', 'courseversion');
    }

    if ($oldversion < 2026072300211) {
        // FIX-API-DOMAIN: Reverted API endpoint to lms-labs.com (correct domain).
        // essaygraderai.app was the original single-plugin domain; lms-labs.com is correct.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_plugin_savepoint(true, 2026072300211, 'local', 'courseversion');
    }

    if ($oldversion < 2026072300212) {
        // Domain update: lms-labs.com → lms-labs.com
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'lib.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_plugin_savepoint(true, 2026072300212, 'local', 'courseversion');
    }

    return true;
}