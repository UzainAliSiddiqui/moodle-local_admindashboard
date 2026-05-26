<?php
// This file is part of Moodle - http://moodle.org/
//
// push_cron.php — Server-side cron that detects KPI changes and sends
// push notifications to registered devices via the Expo Push API.
//
// Run via Moodle cron or a scheduled system task:
//   php /var/www/html/local/admindashboard/push_cron.php
//
// Or add to crontab (runs every hour):
//   0 * * * * www-data php /var/www/html/local/admindashboard/push_cron.php >> /var/log/admindash_push.log 2>&1

define('CLI_SCRIPT', true);

// Bootstrap Moodle — adjust path if needed.
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/metricslib.php');

// ── Config ────────────────────────────────────────────────────────────
const EXPO_PUSH_URL        = 'https://exp.host/--/api/v2/push/send';
const SNAPSHOT_CACHE_TABLE = 'local_admindash_push_snap';
const HIGH_RISK_THRESHOLD  = 70; // risk score to consider "high risk"
const MAX_BATCH_SIZE       = 100; // Expo allows up to 100 per request

// ── Ensure snapshot table exists ──────────────────────────────────────
function admindash_ensure_snapshot_table(): void {
    global $DB;
    $dbman = $DB->get_manager();
    if (!$dbman->table_exists('local_admindash_push_snap')) {
        $DB->execute("
            CREATE TABLE {local_admindash_push_snap} (
                id            BIGINT(10)   NOT NULL AUTO_INCREMENT,
                cache_key     VARCHAR(128) NOT NULL,
                payload       LONGTEXT     NOT NULL,
                updated_at    BIGINT(10)   NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_cache_key (cache_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}

// ── Snapshot helpers ──────────────────────────────────────────────────
function admindash_load_snapshot(string $key): ?array {
    global $DB;
    $row = $DB->get_record('local_admindash_push_snap', ['cache_key' => $key]);
    if (!$row) return null;
    $decoded = json_decode($row->payload, true);
    return is_array($decoded) ? $decoded : null;
}

function admindash_save_snapshot(string $key, array $data): void {
    global $DB;
    $existing = $DB->get_record('local_admindash_push_snap', ['cache_key' => $key]);
    $payload = json_encode($data);
    if ($existing) {
        $DB->update_record('local_admindash_push_snap', (object)[
            'id'         => $existing->id,
            'payload'    => $payload,
            'updated_at' => time(),
        ]);
    } else {
        $DB->insert_record('local_admindash_push_snap', (object)[
            'cache_key'  => $key,
            'payload'    => $payload,
            'updated_at' => time(),
        ]);
    }
}

// ── Expo push sender ──────────────────────────────────────────────────
function admindash_send_expo_notifications(array $messages): void {
    $batches = array_chunk($messages, MAX_BATCH_SIZE);
    foreach ($batches as $batch) {
        $ch = curl_init(EXPO_PUSH_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Accept-Encoding: gzip, deflate',
            ],
            CURLOPT_POSTFIELDS     => json_encode($batch),
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            mtrace('[push_cron] Expo API error ' . $httpCode . ': ' . $response);
        } else {
            $decoded = json_decode($response, true);
            $errors = array_filter($decoded['data'] ?? [], fn($r) => ($r['status'] ?? '') === 'error');
            foreach ($errors as $err) {
                mtrace('[push_cron] Token error: ' . json_encode($err));
            }
        }
    }
}

// ── Remove invalid / expired tokens ──────────────────────────────────
function admindash_prune_invalid_token(string $token): void {
    global $DB;
    $DB->delete_records('local_admindash_push_tokens', ['push_token' => $token]);
}

// ── Main ──────────────────────────────────────────────────────────────
mtrace('[push_cron] Starting at ' . date('Y-m-d H:i:s'));

admindash_ensure_snapshot_table();

// Fetch all registered push tokens.
$tokenRows = $DB->get_records('local_admindash_push_tokens');
if (empty($tokenRows)) {
    mtrace('[push_cron] No registered push tokens. Exiting.');
    exit(0);
}

// Get global KPI overview (courseid=0, all departments).
$metrics = admindash_get_metrics(0, '');
$highRiskRows = admindash_get_at_risk_participants(0, '', 1000);
$highRiskCount = count(array_filter($highRiskRows, fn($r) => (int)($r['risk_score'] ?? 0) >= HIGH_RISK_THRESHOLD));

$currentSnap = [
    'highRiskCount' => $highRiskCount,
    'failed'        => (int)($metrics['failed']       ?? 0),
    'droppedMidway' => (int)($metrics['dropped_midway'] ?? 0),
    'attempted'     => (int)($metrics['attempted']    ?? 0),
    'participants'  => (int)($metrics['total']         ?? 0),
    'checkedAt'     => time(),
];

$prevSnap = admindash_load_snapshot('global_kpi');

// Build notification messages.
$messages = [];

foreach ($tokenRows as $tokenRow) {
    $pushToken = trim($tokenRow->push_token ?? '');
    if (empty($pushToken)) continue;

    $notifs = [];

    if ($prevSnap !== null) {
        // New high-risk participants.
        if ($currentSnap['highRiskCount'] > (int)($prevSnap['highRiskCount'] ?? 0)) {
            $diff = $currentSnap['highRiskCount'] - (int)$prevSnap['highRiskCount'];
            $notifs[] = [
                'title' => "⚠️ {$diff} New High-Risk Participant" . ($diff > 1 ? 's' : ''),
                'body'  => "{$currentSnap['highRiskCount']} participant" . ($currentSnap['highRiskCount'] > 1 ? 's are' : ' is') . ' now flagged as high risk.',
                'sound' => 'default',
                'data'  => ['type' => 'high_risk', 'count' => $currentSnap['highRiskCount']],
                'badge' => $currentSnap['highRiskCount'],
            ];
        }

        // New failures.
        if ($currentSnap['failed'] > (int)($prevSnap['failed'] ?? 0)) {
            $diff = $currentSnap['failed'] - (int)$prevSnap['failed'];
            $notifs[] = [
                'title' => "❌ {$diff} New Failure" . ($diff > 1 ? 's' : ''),
                'body'  => "{$currentSnap['failed']} participant" . ($currentSnap['failed'] > 1 ? 's have' : ' has') . ' failed the assessment.',
                'sound' => 'default',
                'data'  => ['type' => 'failed', 'count' => $currentSnap['failed']],
            ];
        }

        // Hourly nudge when dropped-midway count is still high (> 0 and 8+ hours since last nudge).
        $hoursSince = ($currentSnap['checkedAt'] - (int)($prevSnap['checkedAt'] ?? 0)) / 3600;
        if ($hoursSince >= 8 && $currentSnap['droppedMidway'] > 0) {
            $notifs[] = [
                'title' => "⏰ {$currentSnap['droppedMidway']} Pending Participant" . ($currentSnap['droppedMidway'] > 1 ? 's' : ''),
                'body'  => "{$currentSnap['droppedMidway']} enrolled participant" . ($currentSnap['droppedMidway'] > 1 ? 's have' : ' has') . ' not yet attempted the assessment.',
                'sound' => 'default',
                'data'  => ['type' => 'pending', 'count' => $currentSnap['droppedMidway']],
            ];
        }
    }

    foreach ($notifs as $notif) {
        $messages[] = array_merge(['to' => $pushToken], $notif);
    }
}

if (empty($messages)) {
    mtrace('[push_cron] No new notifications to send.');
} else {
    mtrace('[push_cron] Sending ' . count($messages) . ' notification(s) to ' . count($tokenRows) . ' device(s)...');
    admindash_send_expo_notifications($messages);
    mtrace('[push_cron] Done.');
}

// Always save latest snapshot so 8-hour nudge timer resets correctly.
admindash_save_snapshot('global_kpi', $currentSnap);

mtrace('[push_cron] Finished at ' . date('Y-m-d H:i:s'));
exit(0);
