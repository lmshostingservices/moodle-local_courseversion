<?php
/**
 * Plugin unlock verification for AI Grader Time Saving Plugins.
 * Auto-unlocks when valid credentials and sufficient credits are available.
 *
 * @package    local_courseversion
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseversion;

defined('MOODLE_INTERNAL') || die();

class unlock_verifier {
    
    const PLUGIN_ID = 'courseversion';
    const CREDITS_REQUIRED = 1000;
    const API_URL = 'https://lms-labs.com/api/plugin-unlock/verify';
    const UNLOCK_URL = 'https://lms-labs.com/api/plugin-unlock';
    const CACHE_KEY = 'local_courseversion_unlocked';
    const CACHE_DURATION = 86400; // 24 hours
    // DB config key — written once on first confirmed unlock, survives all cache purges and upgrades.
    const PERM_KEY = 'unlocked_permanently';
    
    public static function is_unlocked(): bool {
        // ── TIER 1: Permanent DB flag (written once on first confirmed unlock, never expires) ──
        // get_config() reads from the mdl_config_plugins table directly — it is NOT a cache and
        // it is NOT purged by "Purge all caches". Once set, this check short-circuits everything.
        $permanent = get_config('local_courseversion', self::PERM_KEY);
        if ($permanent === '1') {
            return true;
        }

        // ── TIER 2: Application cache — ONLY used to short-circuit confirmed POSITIVE results ──
        // Negative results are deliberately NOT cached. This ensures that as soon as a site admin
        // unlocks the plugin via the dashboard, the very next Moodle page load picks up the new
        // unlocked status without needing a manual cache purge.
        $cache = \cache::make('core', 'config');
        $cached = $cache->get(self::CACHE_KEY);
        
        if ($cached !== false && is_array($cached)) {
            if (!empty($cached['unlocked']) && isset($cached['expires']) && $cached['expires'] > time()) {
                // Upgrade to permanent flag so future checks skip the API entirely.
                set_config(self::PERM_KEY, '1', 'local_courseversion');
                return true;
            }
        }
        
        // ── TIER 3: Live API check (runs on every request until unlock is confirmed) ──
        $result = self::verify_with_api();
        
        // If not unlocked but has valid credentials and enough credits, auto-unlock now.
        if (!$result['unlocked'] && $result['has_credentials'] && $result['has_credits']) {
            $autoUnlockResult = self::auto_unlock();
            if ($autoUnlockResult) {
                $result['unlocked'] = true;
            }
        }
        
        if ($result['unlocked']) {
            // Cache the positive result for 24 hours to avoid unnecessary API calls.
            $cache->set(self::CACHE_KEY, [
                'unlocked' => true,
                'expires' => time() + self::CACHE_DURATION,
            ]);
            // Write the permanent DB flag so this check never fires again.
            set_config(self::PERM_KEY, '1', 'local_courseversion');
        }
        // Do NOT cache a negative result — next page load will re-check the API so the
        // unlock is picked up immediately after the admin pays for it.
        
        return $result['unlocked'];
    }
    
    public static function clear_cache(): void {
        $cache = \cache::make('core', 'config');
        $cache->delete(self::CACHE_KEY);
        // NOTE: We deliberately do NOT clear the permanent DB flag here.
        // Once a site has paid to unlock the plugin it should remain unlocked forever.
        // If an admin genuinely needs to reset the unlock state they must do so from
        // the plugin settings page or directly in the database.
    }
    
    private static function verify_with_api(): array {
        global $CFG;
        $credentials = self::get_credentials();
        
        if (empty($credentials['siteid']) || empty($credentials['apikey'])) {
            return ['unlocked' => false, 'has_credentials' => false, 'has_credits' => false];
        }
        
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => 10, 'CURLOPT_CONNECTTIMEOUT' => 5]);

        $response = $curl->get(self::API_URL, [
            'pluginId' => self::PLUGIN_ID,
            'siteId'   => $credentials['siteid'],
            'apiKey'   => $credentials['apikey'],
        ]);
        $httpcode = (int)($curl->info['http_code'] ?? 0);
        
        if ($httpcode !== 200 || empty($response)) {
            // FAIL-OPEN: If the API is unreachable or returns an unexpected response,
            // assume the plugin is unlocked so that transient network issues do not
            // block legitimate paying customers from using the plugin.
            return ['unlocked' => true, 'has_credentials' => true, 'has_credits' => true];
        }
        
        $data = json_decode($response, true);
        $credits = isset($data['credits']) ? (int)$data['credits'] : 0;
        
        return [
            'unlocked' => !empty($data['unlocked']),
            'has_credentials' => true,
            'has_credits' => ($credits >= self::CREDITS_REQUIRED) || ($credits === -1),
        ];
    }
    
    private static function auto_unlock(): bool {
        global $CFG;
        $credentials = self::get_credentials();
        
        if (empty($credentials['siteid']) || empty($credentials['apikey'])) {
            return false;
        }
        
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT'        => 15,
            'CURLOPT_CONNECTTIMEOUT' => 5,
            'CURLOPT_HTTPHEADER'     => ['Content-Type: application/json', 'Accept: application/json'],
        ]);

        $postdata = json_encode([
            'pluginId' => self::PLUGIN_ID,
            'siteId'   => $credentials['siteid'],
            'apiKey'   => $credentials['apikey'],
        ]);

        $response = $curl->post(self::UNLOCK_URL, $postdata);
        $httpcode  = (int)($curl->info['http_code'] ?? 0);
        
        if ($httpcode === 200 && !empty($response)) {
            $data = json_decode($response, true);
            return !empty($data['success']);
        }
        
        return false;
    }
    
    private static function get_credentials(): array {
        $siteid = '';
        $apikey = '';
        
        if (class_exists('\\local_aiconfig\\config')) {
            $siteid = \local_aiconfig\config::get_site_id();
            $apikey = \local_aiconfig\config::get_api_key();
        }
        
        if (empty($siteid)) {
            $siteid = get_config('local_courseversion', 'siteid');
        }
        if (empty($apikey)) {
            $apikey = get_config('local_courseversion', 'apikey');
        }
        
        return ['siteid' => $siteid ?: '', 'apikey' => $apikey ?: ''];
    }
    
    public static function show_unlock_notice(): void {
        $message = get_string('unlock_required', 'local_courseversion');
        if (empty($message) || $message === '[[unlock_required]]') {
            $message = 'This plugin requires ' . self::CREDITS_REQUIRED . ' AI credits to unlock. ' .
                       'Please visit your AI Grader dashboard at lms-labs.com to unlock this plugin.';
        }
        \core\notification::warning($message);
    }
    
    public static function check_and_notify(): bool {
        if (self::is_unlocked()) {
            return true;
        }
        self::show_unlock_notice();
        return false;
    }
}
