<?php
/**
 * 大会・イベント 公開 LP（OGP・表示用 payload 組立）
 *
 * メタ read/write は competition-persist-read.php / competition-persist.php 正本。
 *
 * @package AidUnite
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param string $slug
 * @return string
 */
function aidunite_competition_public_lp_url($slug) {
    $slug = sanitize_title((string) $slug);
    if ($slug === '') {
        return home_url('/competition-event/');
    }

    return home_url('/competition-event/?slug=' . rawurlencode($slug));
}

/**
 * @param int $event_id
 * @return array<string, mixed>
 */
function aidunite_competition_read_public_lp_payload($event_id) {
    $event_id = (int) $event_id;
    if ($event_id < 1) {
        return [];
    }

    $raw = aidunite_competition_read_event_meta_raw($event_id);
    if (empty($raw)) {
        return [];
    }

    if (aidunite_competition_normalize_visibility($raw['visibility'] ?? 'admin_only') !== 'public') {
        return ['error' => 'forbidden'];
    }

    if (!in_array(aidunite_competition_normalize_event_status($raw['status'] ?? 'draft'), ['inviting', 'locked', 'in_progress'], true)) {
        return ['error' => 'not_available'];
    }

    $marketing = aidunite_competition_normalize_marketing_input(is_array($raw['marketing'] ?? null) ? $raw['marketing'] : []);
    $kind_labels = aidunite_competition_get_event_kind_labels();
    $event_kind = aidunite_competition_normalize_event_kind($raw['event_kind'] ?? 'tournament');
    $capacity = (int) ($raw['capacity_teams'] ?? 0);
    $confirmed = aidunite_competition_count_entries_by_status($event_id, 'confirmed');
    $remaining = $capacity > 0 ? max(0, $capacity - $confirmed) : null;
    $recruitment_open = !aidunite_competition_is_recruitment_closed($event_id);
    $refund = aidunite_competition_read_refund_policy_payload($event_id);

    $date_start = (string) ($raw['date_start'] ?? '');
    $venue_name = (string) ($raw['venue_name'] ?? '');
    $summary = (string) ($marketing['summary'] ?? '');
    if ($summary === '') {
        $summary = $date_start !== ''
            ? date('Y年n月j日', strtotime($date_start)) . '開催'
            : '大会・イベント';
        if ($venue_name !== '') {
            $summary .= ' @ ' . $venue_name;
        }
    }

    $og_title = (string) ($raw['title'] ?? get_the_title($event_id));
    $og_description = $summary;
    $og_image = (string) ($marketing['cover_image'] ?? '');
    $og_url = aidunite_competition_public_lp_url((string) ($marketing['slug'] ?? ''));

    return [
        'id' => $event_id,
        'title' => (string) ($raw['title'] ?? get_the_title($event_id)),
        'event_kind' => $event_kind,
        'event_kind_label' => $kind_labels[$event_kind] ?? $event_kind,
        'date_start' => $date_start,
        'date_end' => (string) ($raw['date_end'] ?: $raw['date_start']),
        'venue' => [
            'name' => $venue_name,
            'address' => (string) ($raw['venue_address'] ?? ''),
        ],
        'application_deadline' => (string) ($raw['application_deadline'] ?? ''),
        'recruitment_open' => $recruitment_open,
        'capacity' => [
            'teams' => $capacity,
            'confirmed' => $confirmed,
            'remaining' => $remaining,
        ],
        'entry_fee' => [
            'required' => !empty($raw['entry_fee_required']),
            'amount' => (int) ($raw['entry_fee_amount'] ?? 0),
            'currency' => (string) ($raw['entry_fee_currency'] ?: 'JPY'),
        ],
        'marketing' => [
            'slug' => (string) ($marketing['slug'] ?? ''),
            'cover_image' => $og_image,
            'summary' => $summary,
            'tags' => is_array($marketing['tags'] ?? null) ? $marketing['tags'] : [],
            'public_url' => $og_url,
        ],
        'refund_policy' => $refund,
        'capabilities' => aidunite_competition_read_event_capabilities(
            $event_kind,
            is_array($raw['capabilities_override'] ?? null) ? $raw['capabilities_override'] : [],
            $raw
        ),
        'og' => [
            'title' => $og_title,
            'description' => $og_description,
            'image' => $og_image,
            'url' => $og_url,
            'type' => 'website',
        ],
        'cta' => [
            'login_url' => wp_login_url($og_url),
            'mypage_url' => is_user_logged_in() ? home_url('/mypage/') : wp_login_url(home_url('/mypage/')),
        ],
    ];
}

/**
 * @param int $event_id
 * @param array<string, mixed> $og
 */
function aidunite_competition_render_public_lp_og_tags($event_id, array $og = []) {
    if ($og === []) {
        $payload = aidunite_competition_read_public_lp_payload($event_id);
        $og = is_array($payload['og'] ?? null) ? $payload['og'] : [];
    }

    if ($og === []) {
        return;
    }

    add_action('wp_head', static function () use ($og) {
        echo '<meta property="og:title" content="' . esc_attr((string) ($og['title'] ?? '')) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr((string) ($og['description'] ?? '')) . '">' . "\n";
        echo '<meta property="og:type" content="' . esc_attr((string) ($og['type'] ?? 'website')) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url((string) ($og['url'] ?? '')) . '">' . "\n";
        if (!empty($og['image'])) {
            echo '<meta property="og:image" content="' . esc_url((string) $og['image']) . '">' . "\n";
        }
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    }, 1);
}

/**
 * @param int $event_id
 */
function aidunite_competition_public_lp_enqueue_assets($event_id = 0) {
    wp_enqueue_style(
        'competition-event-public',
        get_stylesheet_directory_uri() . '/assets/css/pages/competition-event-public.css',
        [],
        '1.0.0'
    );
}
