import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const p = path.join(fileURLToPath(new URL('..', import.meta.url)), 'functions/team/team-display-template.php');
const head = fs.readFileSync(p, 'utf8').split(/\r?\n/).slice(0, 1194);
const tail = `
/**
 * チーム支援用 JavaScript
 */
function aidunite_team_support_script() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $js = get_stylesheet_directory() . '/assets/js/team/team-support.js';
    if (!is_readable($js)) {
        return;
    }
    wp_enqueue_script(
        'aidunite-team-support',
        get_stylesheet_directory_uri() . '/assets/js/team/team-support.js',
        ['aidunite-confirm-modal', 'aidunite-toast-notification'],
        (string) filemtime($js),
        true
    );
    wp_localize_script('aidunite-team-support', 'aiduniteTeamSupport', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('support_team_nonce'),
    ]);
}
`;
fs.writeFileSync(p, head.join('\n') + tail);
