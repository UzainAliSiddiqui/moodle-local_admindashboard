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
defined('MOODLE_INTERNAL') || die();

function local_admindashboard_user_can_view(): bool {
    if (!isloggedin() || isguestuser()) {
        return false;
    }

    $ctx = context_system::instance();

    // Site admins always have access.
    if (has_capability('moodle/site:config', $ctx)) {
        return true;
    }

    return has_capability('local/admindashboard:view', $ctx);
}

function local_admindashboard_require_view_access(): void {
    if (!local_admindashboard_user_can_view()) {
        throw new required_capability_exception(context_system::instance(), 'local/admindashboard:view', 'nopermissions', '');
    }
}

/**
 * Whether the user may edit course schedule sticky notes (dashboard board).
 */
function local_admindashboard_user_can_edit_course_schedule_notes(): bool {
    $ctx = context_system::instance();
    if (has_capability('moodle/site:config', $ctx)) {
        return true;
    }
    return has_capability('local/admindashboard:editcourseschedulenotes', $ctx);
}

/**
 * Maximum number of schedule sticky notes (editor + dashboard).
 */
function local_admindashboard_course_schedule_sticky_notes_max(): int {
    return 20;
}

/**
 * English fallbacks when Moodle returns [[identifier]] (missing string / stale lang cache after deploy).
 *
 * @return array<string,string>
 */
function local_admindashboard_schedule_notes_lang_fallback_map(): array {
    return [
        'courseschedulenotes_pagetitle' => 'Course schedule notes',
        'courseschedulenotes_intro' => 'Edit the sticky notes shown on the main admin dashboard (course cadence, intakes, and reminders). You can save up to {$a} notes.',
        'courseschedulenotes_nocap' => 'You can view the schedule below. Only users with the edit permission (typically site managers) can change these notes.',
        'courseschedulenotes_backdash' => 'Back to dashboard',
        'courseschedulenotes_formtitle' => 'Sticky note content',
        'courseschedulenotes_formhelp' => 'Plain text only; use line breaks for paragraphs. Colours are cosmetic on the dashboard. Rows with both title and body empty are skipped. One extra blank row appears so you can add another note (up to {$a} total).',
        'courseschedulenotes_untitled' => 'Untitled note',
        'courseschedulenotes_maxreached' => 'You are at the maximum of {$a} notes. Save, then remove or shorten an existing note to add a different one.',
        'courseschedulenotes_notelabel' => 'Note {$a}',
        'courseschedulenotes_field_title' => 'Title',
        'courseschedulenotes_field_body' => 'Body',
        'courseschedulenotes_field_variant' => 'Card colour',
        'courseschedulenotes_variant_lemon' => 'Lemon',
        'courseschedulenotes_variant_mint' => 'Mint',
        'courseschedulenotes_variant_lavender' => 'Lavender',
        'courseschedulenotes_variant_peach' => 'Peach',
        'courseschedulenotes_preview' => 'Live preview',
        'courseschedulenotes_board_eyebrow' => 'Course cadence',
        'courseschedulenotes_board_title' => 'Schedule at a glance',
        'courseschedulenotes_board_edit' => 'Edit notes',
        'courseschedulenotes_board_toggle_hide' => 'Hide schedule notes',
        'courseschedulenotes_board_toggle_show' => 'Show schedule notes',
    ];
}

/**
 * Apply simple {$a} / {$a->name} placeholders (same subset as schedule note lang strings).
 *
 * @param string $template
 * @param null|string|int|float|array|object $a
 */
function local_admindashboard_schedule_notes_lang_format(string $template, $a): string {
    if ($a === null) {
        return str_replace('{$a}', '', $template);
    }
    if (is_scalar($a)) {
        return str_replace('{$a}', (string)$a, $template);
    }
    if (is_object($a)) {
        $a = (array)$a;
    }
    if (!is_array($a)) {
        return $template;
    }
    $out = $template;
    foreach ($a as $key => $value) {
        if (is_string($key) && preg_match('/^[a-z0-9_]+$/i', $key)) {
            $out = str_replace('{$a->' . $key . '}', (string)$value, $out);
        }
    }
    return str_replace('{$a}', '', $out);
}

/**
 * get_string for schedule-notes identifiers, with English fallback if the pack is missing on disk.
 */
function local_admindashboard_get_string_schedule_notes(string $identifier, $a = null): string {
    $resolved = get_string($identifier, 'local_admindashboard', $a);
    $trimmed = trim($resolved);
    if ($trimmed !== '' && str_starts_with($trimmed, '[[') && str_ends_with($trimmed, ']]')) {
        $map = local_admindashboard_schedule_notes_lang_fallback_map();
        if (isset($map[$identifier])) {
            return local_admindashboard_schedule_notes_lang_format($map[$identifier], $a);
        }
    }
    return $resolved;
}

/**
 * Default sticky notes (English); replaced once saved from the editor.
 *
 * @return array<int,array{title:string,body:string,variant:string}>
 */
function local_admindashboard_default_course_schedule_sticky_notes(): array {
    return [
        [
            'title' => 'Basic intakes (Feb–Apr · Aug–Oct)',
            'body' => "Current basic cohorts are planned for February–April and August–October.\nTypical courses: Basics of Pharma, Safe Medication Practice, and Essential & Primary Care.",
            'variant' => 'lemon',
        ],
        [
            'title' => 'Extended window (Apr–Sep)',
            'body' => "April through September carries the longer run for Essential & Primary Care (and aligned basic follow-through), so rotations and clinic commitments stay in sync.",
            'variant' => 'mint',
        ],
        [
            'title' => 'Heads-up',
            'body' => "These windows are communication defaults only — adjust anytime under Platform Settings → Course schedule notes.",
            'variant' => 'lavender',
        ],
    ];
}

/**
 * @return array<int,array{title:string,body:string,variant:string}>
 */
function local_admindashboard_get_course_schedule_sticky_notes(): array {
    $raw = get_config('local_admindashboard', 'course_schedule_sticky_json');
    $defaults = local_admindashboard_default_course_schedule_sticky_notes();
    if ($raw === false || $raw === null || trim((string)$raw) === '') {
        return $defaults;
    }
    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded)) {
        return $defaults;
    }
    if (count($decoded) === 0) {
        return $defaults;
    }
    $allowedvariants = ['lemon' => true, 'mint' => true, 'lavender' => true, 'peach' => true];
    $max = local_admindashboard_course_schedule_sticky_notes_max();
    $out = [];
    foreach ($decoded as $row) {
        if (count($out) >= $max) {
            break;
        }
        if (!is_array($row)) {
            continue;
        }
        $title = isset($row['title']) ? clean_param((string)$row['title'], PARAM_TEXT) : '';
        $body = isset($row['body']) ? clean_param((string)$row['body'], PARAM_TEXT) : '';
        $variant = isset($row['variant']) ? clean_param((string)$row['variant'], PARAM_ALPHANUMEXT) : 'lemon';
        if (!isset($allowedvariants[$variant])) {
            $variant = 'lemon';
        }
        if ($title === '' && trim($body) === '') {
            continue;
        }
        if ($title === '' && trim($body) !== '') {
            $title = local_admindashboard_get_string_schedule_notes('courseschedulenotes_untitled');
        }
        $out[] = [
            'title' => $title,
            'body' => $body,
            'variant' => $variant,
        ];
    }
    return $out !== [] ? $out : $defaults;
}

/**
 * Persist sticky notes from the editor form (any count up to max).
 *
 * @param array<int,array{title:string,body:string,variant:string}> $notes
 */
function local_admindashboard_save_course_schedule_sticky_notes(array $notes): void {
    $allowedvariants = ['lemon' => true, 'mint' => true, 'lavender' => true, 'peach' => true];
    $max = local_admindashboard_course_schedule_sticky_notes_max();
    $clean = [];
    foreach ($notes as $row) {
        if (count($clean) >= $max) {
            break;
        }
        if (!is_array($row)) {
            continue;
        }
        $title = clean_param((string)($row['title'] ?? ''), PARAM_TEXT);
        $body = clean_param((string)($row['body'] ?? ''), PARAM_TEXT);
        $variant = clean_param((string)($row['variant'] ?? 'lemon'), PARAM_ALPHANUMEXT);
        if (!isset($allowedvariants[$variant])) {
            $variant = 'lemon';
        }
        if ($title === '' && trim($body) === '') {
            continue;
        }
        if ($title === '' && trim($body) !== '') {
            $title = local_admindashboard_get_string_schedule_notes('courseschedulenotes_untitled');
        }
        $clean[] = [
            'title' => $title,
            'body' => $body,
            'variant' => $variant,
        ];
    }
    if ($clean === []) {
        $clean = local_admindashboard_default_course_schedule_sticky_notes();
    }
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    set_config(
        'course_schedule_sticky_json',
        json_encode($clean, $flags),
        'local_admindashboard'
    );
}

/**
 * Default editable cadence windows.
 *
 * @return array<int,array{key:string,title:string,range:string,startmonth:int,startday:int,endmonth:int,endday:int,courses:array<int,string>}>
 */
function local_admindashboard_default_course_schedule_cadence_config(): array {
    return [
        [
            'key' => 'basic_first',
            'title' => 'Basic Intake 1',
            'range' => 'Feb-Apr',
            'startmonth' => 2,
            'startday' => 1,
            'endmonth' => 4,
            'endday' => 30,
            'courses' => [
                'Basics of Pharmacology & Drug Dispensing',
                'Safe Medication Administration',
            ],
        ],
        [
            'key' => 'extended',
            'title' => 'Extended Run',
            'range' => 'Apr-Sep',
            'startmonth' => 4,
            'startday' => 1,
            'endmonth' => 9,
            'endday' => 30,
            'courses' => [
                'Essential & Primary Care',
                'Aligned basic follow-through',
            ],
        ],
        [
            'key' => 'basic_second',
            'title' => 'Basic Intake 2',
            'range' => 'Aug-Oct',
            'startmonth' => 8,
            'startday' => 1,
            'endmonth' => 10,
            'endday' => 31,
            'courses' => [
                'Basics of Pharmacology & Drug Dispensing',
                'Safe Medication Administration',
            ],
        ],
    ];
}

/**
 * @return array<int,array{key:string,title:string,range:string,startmonth:int,startday:int,endmonth:int,endday:int,courses:array<int,string>}>
 */
function local_admindashboard_get_course_schedule_cadence_config(): array {
    $raw = get_config('local_admindashboard', 'course_schedule_cadence_json');
    $defaults = local_admindashboard_default_course_schedule_cadence_config();
    if ($raw === false || $raw === null || trim((string)$raw) === '') {
        return $defaults;
    }
    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded) || $decoded === []) {
        return $defaults;
    }

    $out = [];
    foreach ($decoded as $idx => $row) {
        if (!is_array($row)) {
            continue;
        }
        $title = clean_param((string)($row['title'] ?? ''), PARAM_TEXT);
        $range = clean_param((string)($row['range'] ?? ''), PARAM_TEXT);
        $startmonth = max(1, min(12, (int)($row['startmonth'] ?? 1)));
        $startday = max(1, min(31, (int)($row['startday'] ?? 1)));
        $endmonth = max(1, min(12, (int)($row['endmonth'] ?? $startmonth)));
        $endday = max(1, min(31, (int)($row['endday'] ?? $startday)));
        $courses = [];
        foreach ((array)($row['courses'] ?? []) as $course) {
            $course = clean_param((string)$course, PARAM_TEXT);
            if ($course !== '') {
                $courses[] = $course;
            }
        }
        if ($title === '') {
            continue;
        }
        if ($range === '') {
            $range = $startmonth . '/' . $startday . '-' . $endmonth . '/' . $endday;
        }
        $out[] = [
            'key' => clean_param((string)($row['key'] ?? ('cadence_' . $idx)), PARAM_ALPHANUMEXT),
            'title' => $title,
            'range' => $range,
            'startmonth' => $startmonth,
            'startday' => $startday,
            'endmonth' => $endmonth,
            'endday' => $endday,
            'courses' => $courses,
        ];
    }

    return $out !== [] ? $out : $defaults;
}

/**
 * @param array<int,array<string,mixed>> $windows
 */
function local_admindashboard_save_course_schedule_cadence_config(array $windows): void {
    $clean = [];
    foreach ($windows as $idx => $row) {
        if (!is_array($row)) {
            continue;
        }
        $title = clean_param((string)($row['title'] ?? ''), PARAM_TEXT);
        if ($title === '') {
            continue;
        }
        $courses = [];
        foreach ((array)($row['courses'] ?? []) as $course) {
            $course = clean_param((string)$course, PARAM_TEXT);
            if ($course !== '') {
                $courses[] = $course;
            }
        }
        $clean[] = [
            'key' => clean_param((string)($row['key'] ?? ('cadence_' . $idx)), PARAM_ALPHANUMEXT),
            'title' => $title,
            'range' => clean_param((string)($row['range'] ?? ''), PARAM_TEXT),
            'startmonth' => max(1, min(12, (int)($row['startmonth'] ?? 1))),
            'startday' => max(1, min(31, (int)($row['startday'] ?? 1))),
            'endmonth' => max(1, min(12, (int)($row['endmonth'] ?? 1))),
            'endday' => max(1, min(31, (int)($row['endday'] ?? 1))),
            'courses' => $courses,
        ];
    }
    if ($clean === []) {
        $clean = local_admindashboard_default_course_schedule_cadence_config();
    }
    set_config(
        'course_schedule_cadence_json',
        json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'local_admindashboard'
    );
}

/**
 * Build annual dashboard course cadence windows.
 *
 * @return array<int,array{key:string,title:string,range:string,start:int,end:int,courses:array<int,string>}>
 */
function local_admindashboard_course_schedule_cadence_windows(?int $now = null): array {
    $now = $now ?? time();
    $year = (int)date('Y', $now);

    $windows = [];
    foreach (local_admindashboard_get_course_schedule_cadence_config() as $row) {
        $windows[] = [
            'key' => (string)$row['key'],
            'title' => (string)$row['title'],
            'range' => (string)$row['range'],
            'start' => mktime(0, 0, 0, (int)$row['startmonth'], (int)$row['startday'], $year),
            'end' => mktime(23, 59, 59, (int)$row['endmonth'], (int)$row['endday'], $year),
            'courses' => array_values((array)$row['courses']),
        ];
    }
    usort($windows, static function(array $a, array $b): int {
        return (int)$a['start'] <=> (int)$b['start'];
    });
    return $windows;
}

/**
 * @param array<int,array{key:string,title:string,range:string,start:int,end:int,courses:array<int,string>}> $windows
 * @return array{active:?array,next:?array,second:?array}
 */
function local_admindashboard_course_schedule_cadence_state(array $windows, ?int $now = null): array {
    $now = $now ?? time();
    $year = (int)date('Y', $now);
    $active = null;
    foreach ($windows as $window) {
        if ($now >= (int)$window['start'] && $now <= (int)$window['end']) {
            $active = $window;
            break;
        }
    }

    $future = array_values(array_filter($windows, static function(array $window) use ($now): bool {
        return (int)$window['start'] > $now;
    }));
    usort($future, static function(array $a, array $b): int {
        return (int)$a['start'] <=> (int)$b['start'];
    });
    $next = $future[0] ?? null;
    if ($next === null) {
        $nextyearwindows = local_admindashboard_course_schedule_cadence_windows(mktime(0, 0, 0, 1, 1, $year + 1));
        $next = $nextyearwindows[0] ?? null;
    }

    $second = null;
    foreach ($windows as $window) {
        if ((string)$window['key'] === 'basic_second') {
            $second = $window;
            break;
        }
    }
    if ($second !== null && (int)$second['end'] < $now) {
        $nextyearwindows = local_admindashboard_course_schedule_cadence_windows(mktime(0, 0, 0, 1, 1, $year + 1));
        foreach ($nextyearwindows as $window) {
            if ((string)$window['key'] === 'basic_second') {
                $second = $window;
                break;
            }
        }
    }

    return ['active' => $active, 'next' => $next, 'second' => $second];
}

function local_admindashboard_course_schedule_days_between(int $from, int $to): int {
    return $to > $from ? (int)ceil(($to - $from) / DAYSECS) : 0;
}

function local_admindashboard_course_schedule_progress_pct(array $window, ?int $now = null): int {
    $now = $now ?? time();
    $start = (int)($window['start'] ?? 0);
    $end = (int)($window['end'] ?? 0);
    if ($start <= 0 || $end <= $start) {
        return 0;
    }
    if ($now <= $start) {
        return 0;
    }
    if ($now >= $end) {
        return 100;
    }
    return max(0, min(100, (int)round((($now - $start) / ($end - $start)) * 100)));
}

function local_admindashboard_render_course_schedule_window_card(array $window, string $mode, ?int $now = null): string {
    $now = $now ?? time();
    $start = (int)($window['start'] ?? 0);
    $end = (int)($window['end'] ?? 0);
    $isactive = $mode === 'active';
    $iscompleted = $mode === 'completed';
    $days = $isactive ? local_admindashboard_course_schedule_days_between($now, $end) : local_admindashboard_course_schedule_days_between($now, $start);
    $statusmap = [
        'active' => 'Active now',
        'completed' => 'Completed',
        'next' => 'Next up',
        'upcoming' => 'Upcoming',
    ];
    $status = $statusmap[$mode] ?? 'Upcoming';
    $metriclabel = $isactive ? 'days left' : ($iscompleted ? 'cycle closed' : 'days to start');
    $progress = local_admindashboard_course_schedule_progress_pct($window, $now);
    $courses = '';
    foreach (($window['courses'] ?? []) as $course) {
        $courses .= html_writer::tag('li', s((string)$course));
    }
    $metric = $iscompleted ? html_writer::span('Done', 'admindash-cadence-card__done') : html_writer::tag('strong', (string)$days, ['class' => 'admindash-cadence-card__days']);
    $progresslabel = $isactive ? $progress . '% elapsed' : ($iscompleted ? 'Finished for this year' : 'Starts ' . userdate($start, '%d %b'));

    return html_writer::div(
        html_writer::div(
            html_writer::span($status, 'admindash-cadence-card__status')
            . html_writer::span(s((string)($window['range'] ?? '')), 'admindash-cadence-card__range'),
            'admindash-cadence-card__meta'
        )
        . html_writer::tag('h3', s((string)($window['title'] ?? 'Course window')), ['class' => 'admindash-cadence-card__title'])
        . html_writer::div(
            $metric
            . html_writer::span($metriclabel, 'admindash-cadence-card__days-label'),
            'admindash-cadence-card__countdown'
        )
        . html_writer::div(
            html_writer::span('', 'admindash-cadence-card__bar-fill', ['style' => 'width:' . $progress . '%']),
            'admindash-cadence-card__bar',
            ['aria-label' => 'Schedule progress ' . $progress . '%']
        )
        . html_writer::div($progresslabel, 'admindash-cadence-card__progress-label')
        . html_writer::div(userdate($start, '%d %b') . ' - ' . userdate($end, '%d %b %Y'), 'admindash-cadence-card__dates')
        . html_writer::tag('ul', $courses, ['class' => 'admindash-cadence-card__courses']),
        'admindash-cadence-card admindash-cadence-card--' . preg_replace('/[^a-z]/', '', $mode)
    );
}

/**
 * Renders the cork-board sticky notes block for the main dashboard.
 */
function local_admindashboard_render_course_schedule_sticky_board_legacy(bool $showeditlink = true): string {
    $notes = local_admindashboard_get_course_schedule_sticky_notes();
    $canedit = local_admindashboard_user_can_edit_course_schedule_notes();
    $editurl = new moodle_url('/local/admindashboard/course_schedule_notes.php');

    $cards = '';
    $idx = 0;
    foreach ($notes as $note) {
        $idx++;
        $v = preg_replace('/[^a-z]/', '', (string)($note['variant'] ?? 'lemon')) ?: 'lemon';
        $title = format_string($note['title'], true);
        $bodyraw = trim((string)($note['body'] ?? ''));
        $paras = preg_split('/\r\n|\r|\n/', $bodyraw) ?: [];
        $bodyhtml = '';
        foreach ($paras as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            $bodyhtml .= html_writer::tag('p', s($p), ['class' => 'admindash-sticky__p']);
        }
        if ($bodyhtml === '') {
            $bodyhtml = html_writer::tag('p', '—', ['class' => 'admindash-sticky__p admindash-sticky__p--muted']);
        }
        $cards .= html_writer::div(
            html_writer::span('', 'admindash-sticky__pin', ['aria-hidden' => 'true'])
            . html_writer::tag('h3', $title, ['class' => 'admindash-sticky__title'])
            . html_writer::div($bodyhtml, 'admindash-sticky__body'),
            'admindash-sticky admindash-sticky--' . $v . ' admindash-sticky--n' . (($idx % 6) + 1)
        );
    }

    $headinner = html_writer::div(
        html_writer::tag('span', local_admindashboard_get_string_schedule_notes('courseschedulenotes_board_eyebrow'), ['class' => 'admindash-sticky-board__eyebrow'])
        . html_writer::tag('h2', local_admindashboard_get_string_schedule_notes('courseschedulenotes_board_title'), ['class' => 'admindash-sticky-board__title'])
        . ($canedit && $showeditlink
            ? html_writer::link(
                $editurl,
                local_admindashboard_get_string_schedule_notes('courseschedulenotes_board_edit'),
                ['class' => 'admindash-sticky-board__edit btn btn-sm btn-outline-primary']
            )
            : ''),
        'admindash-sticky-board__head'
    );

    $toggleattrs = [
        'type' => 'button',
        'class' => 'admindash-sticky-board__collapse',
        'aria-expanded' => 'true',
        'aria-controls' => 'admindash-sticky-board-panel',
        'data-ad-expand' => local_admindashboard_get_string_schedule_notes('courseschedulenotes_board_toggle_show'),
        'data-ad-collapse' => local_admindashboard_get_string_schedule_notes('courseschedulenotes_board_toggle_hide'),
        'aria-label' => local_admindashboard_get_string_schedule_notes('courseschedulenotes_board_toggle_hide'),
    ];
    $togglebtn = html_writer::tag(
        'button',
        html_writer::span('', 'admindash-sticky-board__chevron', ['aria-hidden' => 'true']),
        $toggleattrs
    );

    $top = html_writer::div($togglebtn . $headinner, 'admindash-sticky-board__top');
    $panel = html_writer::div(
        html_writer::div($cards, 'admindash-sticky-board__grid'),
        'admindash-sticky-board__panel',
        ['id' => 'admindash-sticky-board-panel']
    );

    return html_writer::div(
        $top . $panel,
        'admindash-sticky-board admindash-card admindash-sticky-board-shell'
    );
}

/**
 * Renders the dashboard course cadence block.
 */
function local_admindashboard_render_course_schedule_sticky_board(bool $showeditlink = true): string {
    $now = time();
    $windows = local_admindashboard_course_schedule_cadence_windows($now);
    $state = local_admindashboard_course_schedule_cadence_state($windows, $now);

    $cards = '';
    $nextkey = $state['next'] !== null ? (string)$state['next']['key'] : '';
    foreach ($windows as $window) {
        $start = (int)($window['start'] ?? 0);
        $end = (int)($window['end'] ?? 0);
        $mode = 'upcoming';
        if ($now >= $start && $now <= $end) {
            $mode = 'active';
        } else if ($end < $now) {
            $mode = 'completed';
        } else if ((string)($window['key'] ?? '') === $nextkey) {
            $mode = 'next';
        }
        $cards .= local_admindashboard_render_course_schedule_window_card($window, $mode, $now);
    }

    $activecopy = $state['active'] !== null
        ? 'Now running: ' . (string)$state['active']['title'] . ' until ' . userdate((int)$state['active']['end'], '%d %b')
        : 'No active window today';
    $nextcopy = $state['next'] !== null
        ? 'Next start: ' . (string)$state['next']['title'] . ' on ' . userdate((int)$state['next']['start'], '%d %b')
        : 'Next start date pending';

    $summarychips = html_writer::span($activecopy, 'admindash-cadence-board__chip admindash-cadence-board__chip--active')
        . html_writer::span($nextcopy, 'admindash-cadence-board__chip');
    if (count($windows) > 1) {
        $summarychips .= html_writer::span(count($windows) . ' editable windows', 'admindash-cadence-board__chip');
    }
    $summary = html_writer::div($summarychips, 'admindash-cadence-board__chips');

    $canedit = local_admindashboard_user_can_edit_course_schedule_notes();
    $editurl = new moodle_url('/local/admindashboard/course_schedule_notes.php');
    $editlink = ($canedit && $showeditlink)
        ? html_writer::link(
            $editurl,
            'Edit cadence',
            ['class' => 'admindash-sticky-board__edit btn btn-sm btn-outline-primary']
        )
        : '';

    $headinner = html_writer::div(
        html_writer::tag('span', local_admindashboard_get_string_schedule_notes('courseschedulenotes_board_eyebrow'), ['class' => 'admindash-sticky-board__eyebrow'])
        . html_writer::tag('h2', local_admindashboard_get_string_schedule_notes('courseschedulenotes_board_title'), ['class' => 'admindash-sticky-board__title'])
        . html_writer::div('Annual training windows with live countdowns for active and upcoming cycles.', 'admindash-cadence-board__subtitle')
        . $summary
        . $editlink,
        'admindash-sticky-board__head'
    );

    $toggleattrs = [
        'type' => 'button',
        'class' => 'admindash-sticky-board__collapse',
        'aria-expanded' => 'true',
        'aria-controls' => 'admindash-sticky-board-panel',
        'data-ad-expand' => local_admindashboard_get_string_schedule_notes('courseschedulenotes_board_toggle_show'),
        'data-ad-collapse' => local_admindashboard_get_string_schedule_notes('courseschedulenotes_board_toggle_hide'),
        'aria-label' => local_admindashboard_get_string_schedule_notes('courseschedulenotes_board_toggle_hide'),
    ];
    $togglebtn = html_writer::tag(
        'button',
        html_writer::span('', 'admindash-sticky-board__chevron', ['aria-hidden' => 'true']),
        $toggleattrs
    );

    $top = html_writer::div($togglebtn . $headinner, 'admindash-sticky-board__top');
    $panel = html_writer::div(
        html_writer::div($cards, 'admindash-cadence-board__grid'),
        'admindash-sticky-board__panel',
        ['id' => 'admindash-sticky-board-panel']
    );

    return html_writer::div(
        $top . $panel,
        'admindash-sticky-board admindash-card admindash-sticky-board-shell admindash-cadence-board'
    );
}

/**
 * Common setup for admin dashboard pages.
 */
function local_admindashboard_setup_page(string $path, string $title, string $active): void {
    global $PAGE;

    require_login();
    local_admindashboard_require_view_access();

    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url($path));
    // Use a minimal layout so the custom dashboard can occupy the full window.
    $PAGE->set_pagelayout('popup');
    $PAGE->add_body_class('admindash-fullscreen');
    $pagestring = 'page_' . str_replace(['.', '-'], '_', $active);
    $resolvedtitle = get_string_manager()->string_exists($pagestring, 'local_admindashboard')
        ? get_string($pagestring, 'local_admindashboard')
        : $title;
    $PAGE->set_title($resolvedtitle);
    $PAGE->set_heading($resolvedtitle);
    $PAGE->requires->strings_for_js([
        'theme_day_mode',
        'theme_dark_mode',
        'sticky_schedule_show',
        'sticky_schedule_hide',
        'table_search_placeholder',
        'table_export_excel',
        'table_export_pdf',
        'table_report_title',
    ], 'local_admindashboard');

    // Sidebar submenu toggle + theme toggle + simple report table helpers.
    $PAGE->requires->js_init_code(<<<'JS'
(() => {
    function safeGet(key) {
        try { return window.localStorage?.getItem(key); } catch (e) { return null; }
    }

    function safeSet(key, value) {
        try { window.localStorage?.setItem(key, value); } catch (e) { /* ignore */ }
    }

    const THEME_KEY = 'local_admindashboard_theme';
    const STICKY_SCHEDULE_COLLAPSED_KEY = 'local_admindashboard_sticky_schedule_collapsed';

    function getCurrentTheme() {
        return document.body.classList.contains('admindash-theme-dark') ? 'dark' : 'light';
    }

    function updateThemeToggle() {
        const btn = document.querySelector('.admindash-theme-toggle');
        if (!btn) return;
        const theme = getCurrentTheme();
        const isDark = theme === 'dark';
        btn.textContent = isDark
            ? M.util.get_string('theme_day_mode', 'local_admindashboard')
            : M.util.get_string('theme_dark_mode', 'local_admindashboard');
        btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
    }

    function applyTheme(theme, persist) {
        const next = (theme === 'dark') ? 'dark' : 'light';
        document.body.classList.toggle('admindash-theme-dark', next === 'dark');
        document.body.classList.toggle('admindash-theme-light', next === 'light');
        if (persist) {
            safeSet(THEME_KEY, next);
        }
        updateThemeToggle();
    }

    function initTheme() {
        const stored = safeGet(THEME_KEY);
        if (stored === 'dark' || stored === 'light') {
            applyTheme(stored, false);
            return;
        }
        const prefersDark = !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        applyTheme(prefersDark ? 'dark' : 'light', false);
    }

    function getNavGroupParts(group) {
        if (!group) return { button: null, panel: null };
        return {
            button: group.querySelector('.admindash-nav-toggle'),
            panel: group.querySelector('.admindash-subnav-wrap')
        };
    }

    function openNavGroup(group, immediate) {
        const parts = getNavGroupParts(group);
        if (!parts.button || !parts.panel) return;

        group.classList.add('open');
        parts.button.setAttribute('aria-expanded', 'true');

        if (immediate) {
            parts.panel.style.height = 'auto';
            return;
        }

        parts.panel.style.height = parts.panel.offsetHeight + 'px';
        requestAnimationFrame(() => {
            parts.panel.style.height = parts.panel.scrollHeight + 'px';
        });

        parts.panel.addEventListener('transitionend', function handleOpen(event) {
            if (event.propertyName !== 'height') return;
            if (group.classList.contains('open')) {
                parts.panel.style.height = 'auto';
            }
            parts.panel.removeEventListener('transitionend', handleOpen);
        });
    }

    function closeNavGroup(group, immediate) {
        const parts = getNavGroupParts(group);
        if (!parts.button || !parts.panel) return;

        parts.button.setAttribute('aria-expanded', 'false');

        if (immediate) {
            group.classList.remove('open');
            parts.panel.style.height = '0px';
            return;
        }

        parts.panel.style.height = parts.panel.scrollHeight + 'px';
        requestAnimationFrame(() => {
            group.classList.remove('open');
            parts.panel.style.height = '0px';
        });
    }

    function initNavAccordions() {
        document.querySelectorAll('.admindash-nav-group.has-children').forEach((group) => {
            if (group.classList.contains('open')) {
                openNavGroup(group, true);
            } else {
                closeNavGroup(group, true);
            }
        });
    }

    function setSidebarOpen(open) {
        const next = !!open;
        document.body.classList.toggle('admindash-sidebar-open', next);
        const toggle = document.querySelector('.admindash-sidebar-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', next ? 'true' : 'false');
        }
    }

    function init() {
        initTheme();
        initNavAccordions();
        setSidebarOpen(false);

        // Click handlers.
        document.addEventListener('click', (e) => {
            const themeBtn = e.target.closest?.('.admindash-theme-toggle');
            if (themeBtn) {
                e.preventDefault();
                applyTheme(getCurrentTheme() === 'dark' ? 'light' : 'dark', true);
                return;
            }

            const sidebarBtn = e.target.closest?.('.admindash-sidebar-toggle');
            if (sidebarBtn) {
                e.preventDefault();
                setSidebarOpen(!document.body.classList.contains('admindash-sidebar-open'));
                return;
            }

            const backdrop = e.target.closest?.('.admindash-sidebar-backdrop');
            if (backdrop) {
                e.preventDefault();
                setSidebarOpen(false);
                return;
            }

            // Sidebar: toggle submenus.
            const btn = e.target.closest?.('.admindash-nav-toggle');
            if (!btn) return;
            e.preventDefault();
            const group = btn.closest('.admindash-nav-group');
            if (!group) return;
            const isOpen = group.classList.contains('open');
            const section = group.closest('.admindash-nav-section');
            (section || document).querySelectorAll?.('.admindash-nav-group.has-children.open').forEach((other) => {
                if (other !== group) {
                    closeNavGroup(other, false);
                }
            });

            if (isOpen) {
                closeNavGroup(group, false);
            } else {
                openNavGroup(group, false);
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 900) {
                setSidebarOpen(false);
            }
            document.querySelectorAll('.admindash-nav-group.has-children.open').forEach((group) => {
                const parts = getNavGroupParts(group);
                if (parts.panel) {
                    parts.panel.style.height = 'auto';
                }
            });
        });

        initStickyScheduleToggle();
    }

    function syncStickyScheduleShell(shell, collapsed) {
        const btn = shell.querySelector('.admindash-sticky-board__collapse');
        if (!btn) {
            return;
        }
        shell.classList.toggle('is-collapsed', collapsed);
        btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        const expand = btn.getAttribute('data-ad-expand') || M.util.get_string('sticky_schedule_show', 'local_admindashboard');
        const collapse = btn.getAttribute('data-ad-collapse') || M.util.get_string('sticky_schedule_hide', 'local_admindashboard');
        btn.setAttribute('aria-label', collapsed ? expand : collapse);
    }

    function initStickyScheduleToggle() {
        const shell = document.querySelector('.admindash-sticky-board-shell');
        if (!shell) {
            return;
        }
        const btn = shell.querySelector('.admindash-sticky-board__collapse');
        if (!btn) {
            return;
        }
        const stored = safeGet(STICKY_SCHEDULE_COLLAPSED_KEY);
        syncStickyScheduleShell(shell, stored === '1');

        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const collapsed = !shell.classList.contains('is-collapsed');
            syncStickyScheduleShell(shell, collapsed);
            safeSet(STICKY_SCHEDULE_COLLAPSED_KEY, collapsed ? '1' : '0');
        });
    }

    // Tables: add search + export controls for report tables.
    function tableToXlsHtml(table) {
        const html = `<!doctype html><html><head><meta charset="utf-8"></head><body>${table.outerHTML}</body></html>`;
        return html;
    }

    function downloadBlob(filename, mime, content) {
        const blob = new Blob([content], { type: mime });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 250);
    }

    function attachReportTable(table) {
        if (table.dataset.admindashTableAttached === '1') return;
        table.dataset.admindashTableAttached = '1';

        const wrapper = document.createElement('div');
        wrapper.className = 'admindash-tablewrap';
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);

        const toolbar = document.createElement('div');
        toolbar.className = 'admindash-tabletools';

        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'form-control';
        search.placeholder = M.util.get_string('table_search_placeholder', 'local_admindashboard');
        search.style.maxWidth = '280px';

        const btnExcel = document.createElement('button');
        btnExcel.type = 'button';
        btnExcel.className = 'btn btn-outline-primary';
        btnExcel.textContent = M.util.get_string('table_export_excel', 'local_admindashboard');

        const btnPdf = document.createElement('button');
        btnPdf.type = 'button';
        btnPdf.className = 'btn btn-outline-primary';
        btnPdf.textContent = M.util.get_string('table_export_pdf', 'local_admindashboard');

        toolbar.appendChild(search);
        toolbar.appendChild(btnExcel);
        toolbar.appendChild(btnPdf);
        wrapper.insertBefore(toolbar, table);

        search.addEventListener('input', () => {
            const q = (search.value || '').trim().toLowerCase();
            const rows = Array.from(table.tBodies[0]?.rows || []);
            rows.forEach((tr) => {
                const text = tr.textContent?.toLowerCase() || '';
                tr.style.display = (!q || text.includes(q)) ? '' : 'none';
            });
        });

        btnExcel.addEventListener('click', () => {
            const html = tableToXlsHtml(table);
            downloadBlob('report.xls', 'application/vnd.ms-excel;charset=utf-8', html);
        });

        btnPdf.addEventListener('click', () => {
            const win = window.open('', '_blank');
            if (!win) return;
            win.document.open();
            const reportTitle = M.util.get_string('table_report_title', 'local_admindashboard');
            win.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${reportTitle}</title>`);
            win.document.write('<style>body{font-family:Arial, sans-serif;margin:16px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ddd;padding:8px}th{background:#f5f5f5}</style>');
            win.document.write('</head><body>');
            win.document.write(table.outerHTML);
            win.document.write('</body></html>');
            win.document.close();
            win.focus();
            win.print();
        });

        // Sorting: click headers to sort asc/desc.
        const thead = table.tHead;
        const tbody = table.tBodies[0];
        if (thead && tbody) {
            Array.from(thead.rows[0]?.cells || []).forEach((th, idx) => {
                th.classList.add('admindash-sortable');
                th.tabIndex = 0;
                th.setAttribute('role', 'button');
                th.dataset.sortDir = 'none';

                function getCellValue(tr) {
                    const cell = tr.cells[idx];
                    return (cell?.textContent || '').trim();
                }

                function parseNumber(text) {
                    const cleaned = (text || '').replace(/[%,$,]/g, '').trim();
                    const n = Number(cleaned);
                    return Number.isFinite(n) ? n : null;
                }

                function sortRows(dir) {
                    const rows = Array.from(tbody.rows);
                    const visibleRows = rows.filter(r => r.style.display !== 'none');

                    visibleRows.sort((a, b) => {
                        const av = getCellValue(a);
                        const bv = getCellValue(b);
                        const an = parseNumber(av);
                        const bn = parseNumber(bv);
                        let cmp = 0;
                        if (an !== null && bn !== null) {
                            cmp = an - bn;
                        } else {
                            cmp = av.localeCompare(bv, undefined, { numeric: true, sensitivity: 'base' });
                        }
                        return dir === 'desc' ? -cmp : cmp;
                    });

                    visibleRows.forEach(r => tbody.appendChild(r));
                }

                function toggleSort() {
                    // Reset other headers.
                    Array.from(thead.rows[0]?.cells || []).forEach((oth) => {
                        if (oth !== th) oth.dataset.sortDir = 'none';
                    });

                    const current = th.dataset.sortDir || 'none';
                    const next = current === 'asc' ? 'desc' : 'asc';
                    th.dataset.sortDir = next;
                    sortRows(next);
                }

                th.addEventListener('click', toggleSort);
                th.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggleSort();
                    }
                });
            });
        }
    }

    window.addEventListener('load', () => {
        document.querySelectorAll('table.admindash-report-table').forEach(attachReportTable);
        updateThemeToggle();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
JS
    );

}

function local_admindashboard_get_nav_icon_svg(string $icon): string {
    $icons = [
        'brand' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 3l1.8 3.7L18 8.5l-3 2.9.7 4.1L12 13.7 8.3 15.5 9 11.4 6 8.5l4.2-.8L12 3z" fill="currentColor"/></svg>',
        'home' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 10.5L12 4l8 6.5V20a1 1 0 0 1-1 1h-4.5v-6h-5v6H5a1 1 0 0 1-1-1v-9.5z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16 19v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M9.5 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zm8.5 9v-1a4 4 0 0 0-3-3.87M14 3.13a3.5 3.5 0 0 1 0 6.74" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'courses' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4H20v13.5A2.5 2.5 0 0 0 17.5 15H4V6.5zm0 8.5h13.5A2.5 2.5 0 0 1 20 17.5V20H6.5A2.5 2.5 0 0 1 4 17.5V15z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
        'department' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 7h16M4 12h10M4 17h16M17 9.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'analytics' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 18V9M12 18V5M19 18v-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M3 20h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'report' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 4h10l3 3v13H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M14 4v4h4" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
        'compliance' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 21c4.97 0 9-4.03 9-9s-4.03-9-9-9-9 4.03-9 9 4.03 9 9 9z" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'certification' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 3l7 4v10l-7 4-7-4V7l7-4z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9.5 12l1.7 1.7L15 10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'export' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 16V4M12 16l-4-4M12 16l4-4M4 20h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'announcement' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 18l-2 2V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
        'discussion' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 10h8M8 14h5M7 4h10a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3h-4l-4 4v-4H7a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'message' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
        'integration' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16 8a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM3 20a7 7 0 0 1 14 0M17 11l2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'config' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm8 4l-2.1.8a7.9 7.9 0 0 1-.6 1.5l1 2-2 2-2-1a7.9 7.9 0 0 1-1.5.6L12 20l-.8-2.1a7.9 7.9 0 0 1-1.5-.6l-2 1-2-2 1-2a7.9 7.9 0 0 1-.6-1.5L4 12l2.1-.8a7.9 7.9 0 0 1 .6-1.5l-1-2 2-2 2 1a7.9 7.9 0 0 1 1.5-.6L12 4l.8 2.1a7.9 7.9 0 0 1 1.5.6l2-1 2 2-1 2a7.9 7.9 0 0 1 .6 1.5L20 12z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>',
        'branding' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 6h16v12H4zM8 10h8M8 14h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'support' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 20h.01M8.5 9a3.5 3.5 0 1 1 6 2.4c-.9.8-1.7 1.4-1.7 2.6v.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z" stroke="currentColor" stroke-width="1.8"/></svg>',
        'help' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 3l9 4.5-9 4.5-9-4.5L12 3zm0 9l9-4.5V16.5L12 21l-9-4.5V7.5L12 12z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>',
        'profile' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 20a8 8 0 1 1 16 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm8 4l-2.1.8a7.9 7.9 0 0 1-.6 1.5l1 2-2 2-2-1a7.9 7.9 0 0 1-1.5.6L12 20l-.8-2.1a7.9 7.9 0 0 1-1.5-.6l-2 1-2-2 1-2a7.9 7.9 0 0 1-.6-1.5L4 12l2.1-.8a7.9 7.9 0 0 1 .6-1.5l-1-2 2-2 2 1a7.9 7.9 0 0 1 1.5-.6L12 4l.8 2.1a7.9 7.9 0 0 1 1.5.6l2-1 2 2-1 2a7.9 7.9 0 0 1 .6 1.5L20 12z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>',
        'sticky' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 4h10l2 3v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7l2-3z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M9 9h6M9 13h6M9 17h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M12 4v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
        'menu' => '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
        'caret' => '<svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 4l6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
    ];

    return $icons[$icon] ?? $icons['report'];
}

function local_admindashboard_get_nav_items(): array {
    return [
        [
            'heading' => get_string('nav_heading_admintools', 'local_admindashboard'),
            'items' => [
                [
                    'key' => 'admintools.users',
                    'label' => get_string('nav_admintools_users', 'local_admindashboard'),
                    'icon' => 'users',
                    'children' => [
                        ['key' => 'admintools.users.list', 'label' => get_string('nav_admintools_users_list', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/manage_users.php')],
                        ['key' => 'admintools.users.add', 'label' => get_string('nav_admintools_users_add', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/add_user.php')],
                        ['key' => 'admintools.users.roles', 'label' => get_string('nav_admintools_users_roles', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/define_roles.php')],
                    ],
                ],
                [
                    'key' => 'admintools.courses',
                    'label' => get_string('nav_admintools_courses', 'local_admindashboard'),
                    'icon' => 'courses',
                    'children' => [
                        ['key' => 'admintools.courses.list', 'label' => get_string('nav_admintools_courses_list', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/course_list.php')],
                        ['key' => 'admintools.courses.create', 'label' => get_string('nav_admintools_courses_create', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/create_course.php')],
                        ['key' => 'admintools.courses.templates', 'label' => get_string('nav_admintools_courses_templates', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/course_templates.php')],
                    ],
                ],
                [
                    'key' => 'admintools.groups',
                    'label' => get_string('nav_admintools_groups', 'local_admindashboard'),
                    'icon' => 'department',
                    'url' => new moodle_url('/local/admindashboard/group_department_setup.php'),
                ],
            ],
        ],
        [
            'heading' => get_string('nav_heading_reportsanalytics', 'local_admindashboard'),
            'items' => [
                [
                    'key' => 'courseanalytics',
                    'label' => get_string('nav_courseanalytics', 'local_admindashboard'),
                    'icon' => 'analytics',
                    'children' => [
                        ['key' => 'courseanalytics.overview', 'label' => get_string('nav_courseanalytics_overview', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/course_analytics.php')],
                        ['key' => 'courseanalytics.modules', 'label' => get_string('nav_courseanalytics_modules', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/course_analytics_modules.php')],
                        ['key' => 'courseanalytics.sentiment', 'label' => get_string('nav_courseanalytics_sentiment', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/sentiment_analyzer.php')],
                    ],
                ],
                [
                    'key' => 'reports',
                    'label' => get_string('nav_reports', 'local_admindashboard'),
                    'icon' => 'report',
                    'children' => [
                        ['key' => 'reports.passfail', 'label' => get_string('nav_reports_passfail', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/passfail_report.php')],
                        ['key' => 'reports.ticks', 'label' => get_string('nav_reports_ticks', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/progress_ticks_report.php')],
                        ['key' => 'reports.departmentcompletion', 'label' => get_string('nav_reports_departmentcompletion', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/department_reports.php')],
                        ['key' => 'reports.departmentengagement', 'label' => get_string('nav_reports_departmentengagement', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/department_reports_engagement.php')],
                        ['key' => 'reports.userprogress', 'label' => get_string('nav_reports_userprogress', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/user_progress.php')],
                        ['key' => 'reports.useractivity', 'label' => get_string('nav_reports_useractivity', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/user_progress_activity.php')],
                    ],
                ],
                [
                    'key' => 'compliance',
                    'label' => get_string('nav_compliance', 'local_admindashboard'),
                    'icon' => 'compliance',
                    'children' => [
                        ['key' => 'compliance.expiry', 'label' => get_string('nav_compliance_expiry', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/license_expiry.php')],
                        ['key' => 'compliance.mandatory', 'label' => get_string('nav_compliance_mandatory', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/mandatory_training.php')],
                        ['key' => 'compliance.dashboard', 'label' => get_string('nav_compliance_dashboard', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/compliance_dashboard.php')],
                    ],
                ],
                [
                    'key' => 'skills',
                    'label' => get_string('nav_skills', 'local_admindashboard'),
                    'icon' => 'certification',
                    'children' => [
                        ['key' => 'skills.gap', 'label' => get_string('nav_skills_gap', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/skill_gap_matrix.php')],
                        ['key' => 'skills.certificates', 'label' => get_string('nav_skills_certificates', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/certificate_status.php')],
                        ['key' => 'skills.renewals', 'label' => get_string('nav_skills_renewals', 'local_admindashboard'), 'url' => new moodle_url('/local/admindashboard/renewal_readiness.php')],
                    ],
                ],
                [
                    'key' => 'exportcenter',
                    'label' => get_string('nav_exportcenter', 'local_admindashboard'),
                    'icon' => 'export',
                    'url' => new moodle_url('/local/admindashboard/export_center.php'),
                ],
            ],
        ],
        [
            'heading' => get_string('nav_heading_communication', 'local_admindashboard'),
            'items' => [
                ['key' => 'communication.announcements', 'label' => get_string('nav_communication_announcements', 'local_admindashboard'), 'icon' => 'announcement', 'url' => new moodle_url('/local/admindashboard/announcements.php')],
                ['key' => 'communication.discussions', 'label' => get_string('nav_communication_discussions', 'local_admindashboard'), 'icon' => 'discussion', 'url' => new moodle_url('/local/admindashboard/forums_discussions.php')],
                ['key' => 'communication.messaging', 'label' => get_string('nav_communication_messaging', 'local_admindashboard'), 'icon' => 'message', 'url' => new moodle_url('/local/admindashboard/direct_messaging.php')],
            ],
        ],
        [
            'heading' => get_string('nav_heading_platformsettings', 'local_admindashboard'),
            'items' => [
                ['key' => 'platform.integrations', 'label' => get_string('nav_platform_integrations', 'local_admindashboard'), 'icon' => 'integration', 'url' => new moodle_url('/local/admindashboard/integrations.php')],
                ['key' => 'platform.config', 'label' => get_string('nav_platform_config', 'local_admindashboard'), 'icon' => 'config', 'url' => new moodle_url('/local/admindashboard/system_config.php')],
                ['key' => 'platform.branding', 'label' => get_string('nav_platform_branding', 'local_admindashboard'), 'icon' => 'branding', 'url' => new moodle_url('/local/admindashboard/platform_branding.php')],
                ['key' => 'platform.schedule_notes', 'label' => get_string('nav_platform_schedule_notes', 'local_admindashboard'), 'icon' => 'sticky', 'url' => new moodle_url('/local/admindashboard/course_schedule_notes.php'), 'require_edit_notes' => true],
            ],
        ],
        [
            'heading' => get_string('nav_heading_supportaccount', 'local_admindashboard'),
            'items' => [
                ['key' => 'support.tickets', 'label' => get_string('nav_support_tickets', 'local_admindashboard'), 'icon' => 'support', 'url' => new moodle_url('/local/admindashboard/support_tickets.php')],
                ['key' => 'support.help', 'label' => get_string('nav_support_help', 'local_admindashboard'), 'icon' => 'help', 'url' => new moodle_url('/local/admindashboard/help_center.php')],
                ['key' => 'support.profile', 'label' => get_string('nav_support_profile', 'local_admindashboard'), 'icon' => 'profile', 'url' => new moodle_url('/local/admindashboard/my_profile.php')],
                ['key' => 'support.settings', 'label' => get_string('nav_support_settings', 'local_admindashboard'), 'icon' => 'settings', 'url' => new moodle_url('/local/admindashboard/account_settings.php')],
            ],
        ],
    ];
}

function local_admindashboard_module_action(string $label, moodle_url $url, bool $primary = false): array {
    return [
        'label' => $label,
        'url' => $url,
        'primary' => $primary,
    ];
}

function local_admindashboard_module_page_definition(
    string $script,
    string $active,
    string $eyebrow,
    string $title,
    string $icon,
    string $summary,
    array $actions,
    array $scope,
    array $roadmap,
    array $notes = []
): array {
    return [
        'script' => $script,
        'active' => $active,
        'eyebrow' => $eyebrow,
        'title' => $title,
        'icon' => $icon,
        'summary' => $summary,
        'actions' => $actions,
        'scope' => $scope,
        'roadmap' => $roadmap,
        'notes' => $notes,
    ];
}

function local_admindashboard_get_module_page_definitions(): array {
    return [
        'admintools.users.list' => local_admindashboard_module_page_definition(
            '/local/admindashboard/manage_users.php',
            'admintools.users.list',
            'Admin Tools',
            'Manage Users',
            'users',
            'Central workspace for account lifecycle, onboarding hygiene, and role review across the LMS.',
            [
                local_admindashboard_module_action('Open core user admin', new moodle_url('/admin/user.php'), true),
                local_admindashboard_module_action('Open Add User module', new moodle_url('/local/admindashboard/add_user.php')),
                local_admindashboard_module_action('View user progress', new moodle_url('/local/admindashboard/user_progress.php')),
            ],
            [
                'Review user rosters by department, status, or role.',
                'Spot incomplete profiles and permission drift before it becomes an access issue.',
                'Prepare this module for bulk reminders, activation, and export actions.',
            ],
            [
                'Connect a paginated user table with department and capability filters.',
                'Add account-state chips for active, suspended, pending, and incomplete records.',
                'Introduce batch operations with sesskey protection and capability checks.',
            ],
            [
                'This page is scaffolded and already linked from the sidebar.',
                'Core Moodle account administration remains one click away while custom workflows are added.',
            ]
        ),
        'admintools.users.add' => local_admindashboard_module_page_definition(
            '/local/admindashboard/add_user.php',
            'admintools.users.add',
            'Admin Tools',
            'Add User',
            'users',
            'Launchpad for single-user onboarding, role defaults, and department-aware provisioning.',
            [
                local_admindashboard_module_action('Open core add-user form', new moodle_url('/user/editadvanced.php', ['id' => -1]), true),
                local_admindashboard_module_action('Open Manage Users module', new moodle_url('/local/admindashboard/manage_users.php')),
                local_admindashboard_module_action('Open Group setup', new moodle_url('/local/admindashboard/group_department_setup.php')),
            ],
            [
                'Capture onboarding requirements before redirecting to final account creation.',
                'Standardize role, cohort, and department defaults for new staff.',
                'Document approval and verification steps for regulated environments.',
            ],
            [
                'Add a preflight checklist for required profile fields and department mappings.',
                'Embed quick links to create the user, assign roles, and place them into groups.',
                'Support future bulk onboarding flows from CSV or HR feeds.',
            ],
            [
                'Best next step is a guided onboarding form that feeds Moodle core user creation.',
            ]
        ),
        'admintools.users.roles' => local_admindashboard_module_page_definition(
            '/local/admindashboard/define_roles.php',
            'admintools.users.roles',
            'Admin Tools',
            'Define Roles',
            'users',
            'Keep permission models understandable by pairing role governance with dashboard-facing guidance.',
            [
                local_admindashboard_module_action('Open role manager', new moodle_url('/admin/roles/manage.php'), true),
                local_admindashboard_module_action('Open Manage Users module', new moodle_url('/local/admindashboard/manage_users.php')),
                local_admindashboard_module_action('Open System Config module', new moodle_url('/local/admindashboard/system_config.php')),
            ],
            [
                'Track which operational roles are in use and where elevated access exists.',
                'Document role purpose, risk level, and expected owners.',
                'Provide a clean handoff into Moodle core capability editing.',
            ],
            [
                'Add a role inventory table with last-reviewed dates.',
                'Highlight privileged roles that should trigger periodic audits.',
                'Support change logs for custom role edits and exceptions.',
            ]
        ),
        'admintools.courses.list' => local_admindashboard_module_page_definition(
            '/local/admindashboard/course_list.php',
            'admintools.courses.list',
            'Admin Tools',
            'Course List',
            'courses',
            'Operational view for course catalog hygiene, ownership, and lifecycle status.',
            [
                local_admindashboard_module_action('Open core course management', new moodle_url('/course/management.php'), true),
                local_admindashboard_module_action('Open Course Analytics', new moodle_url('/local/admindashboard/course_analytics.php')),
                local_admindashboard_module_action('Open Create Course module', new moodle_url('/local/admindashboard/create_course.php')),
            ],
            [
                'Surface course owner, status, enrolment volume, and compliance relevance.',
                'Separate live, draft, archived, and review-needed courses.',
                'Prepare bulk actions for archive, hide, and audit workflows.',
            ],
            [
                'Wire a course inventory table with category and department filters.',
                'Expose completion and engagement indicators from existing metrics helpers.',
                'Add quick actions for reporting, templates, and course governance.',
            ]
        ),
        'admintools.courses.create' => local_admindashboard_module_page_definition(
            '/local/admindashboard/create_course.php',
            'admintools.courses.create',
            'Admin Tools',
            'Create New Course',
            'courses',
            'Structured starting point for course intake, approvals, and build standards.',
            [
                local_admindashboard_module_action('Open core course management', new moodle_url('/course/management.php'), true),
                local_admindashboard_module_action('Open Course Templates module', new moodle_url('/local/admindashboard/course_templates.php')),
                local_admindashboard_module_action('Open Platform Branding module', new moodle_url('/local/admindashboard/platform_branding.php')),
            ],
            [
                'Capture course intent, owner, audience, and compliance impact before build.',
                'Standardize naming, visibility, and department tagging.',
                'Reduce one-off course creation by steering admins toward templates.',
            ],
            [
                'Introduce a pre-creation checklist and request summary card.',
                'Connect approval states for regulated or mandatory learning content.',
                'Add template and branding presets to speed up course rollout.',
            ]
        ),
        'admintools.courses.templates' => local_admindashboard_module_page_definition(
            '/local/admindashboard/course_templates.php',
            'admintools.courses.templates',
            'Admin Tools',
            'Course Templates',
            'courses',
            'Template control room for repeatable course structures, compliance packs, and content standards.',
            [
                local_admindashboard_module_action('Open Modules Report', new moodle_url('/local/admindashboard/course_analytics_modules.php'), true),
                local_admindashboard_module_action('Open Create Course module', new moodle_url('/local/admindashboard/create_course.php')),
                local_admindashboard_module_action('Open Course List module', new moodle_url('/local/admindashboard/course_list.php')),
            ],
            [
                'Define standard module stacks for onboarding, mandatory training, and certifications.',
                'Track which templates are active, retired, or due for review.',
                'Align templates with brand, assessment, and completion expectations.',
            ],
            [
                'Add a template registry with versioning and ownership.',
                'Support cloning workflows into live courses with clear audit trails.',
                'Map template usage back into analytics so duplicates and drift are visible.',
            ]
        ),
        'admintools.groups' => local_admindashboard_module_page_definition(
            '/local/admindashboard/group_department_setup.php',
            'admintools.groups',
            'Admin Tools',
            'Group & Department Setup',
            'department',
            'Base for managing departments, cohorts, and reporting segments used across the dashboard.',
            [
                local_admindashboard_module_action('Open core profile fields', new moodle_url('/user/profile/index.php'), true),
                local_admindashboard_module_action('Open Manage Users module', new moodle_url('/local/admindashboard/manage_users.php')),
                local_admindashboard_module_action('Open Department reports', new moodle_url('/local/admindashboard/department_reports.php')),
            ],
            [
                'Define organizational structures used by compliance and analytics screens.',
                'Make department mapping visible before custom filters are introduced everywhere.',
                'Prepare for future syncs from HR, ERP, or identity systems.',
            ],
            [
                'Add reference tables for department, location, and group taxonomies.',
                'Validate which user records are missing mandatory organizational tags.',
                'Expose quick links into affected reports and user management flows.',
            ]
        ),
        'compliance.expiry' => local_admindashboard_module_page_definition(
            '/local/admindashboard/license_expiry.php',
            'compliance.expiry',
            'Reports & Analytics',
            'License Expiry',
            'compliance',
            'Monitoring surface for expiring certifications, recency thresholds, and renewal queues.',
            [
                local_admindashboard_module_action('Open Compliance Dashboard', new moodle_url('/local/admindashboard/compliance_dashboard.php'), true),
                local_admindashboard_module_action('Open Renewal Readiness module', new moodle_url('/local/admindashboard/renewal_readiness.php')),
                local_admindashboard_module_action('Open Export Center', new moodle_url('/local/admindashboard/export_center.php')),
            ],
            [
                'Prioritize users and departments with the nearest renewal deadlines.',
                'Separate urgent expiries from upcoming review windows.',
                'Prepare exportable outreach lists for compliance teams.',
            ],
            [
                'Connect certification and completion dates into a renewal queue.',
                'Add threshold filters such as expiring in 7, 30, or 60 days.',
                'Support reminder campaigns and escalation summaries.',
            ]
        ),
        'compliance.mandatory' => local_admindashboard_module_page_definition(
            '/local/admindashboard/mandatory_training.php',
            'compliance.mandatory',
            'Reports & Analytics',
            'Mandatory Training',
            'compliance',
            'Focused workspace for mandated learning programs and overdue completion follow-up.',
            [
                local_admindashboard_module_action('Open Pass/Fail report', new moodle_url('/local/admindashboard/passfail_report.php'), true),
                local_admindashboard_module_action('Open Compliance Dashboard', new moodle_url('/local/admindashboard/compliance_dashboard.php')),
                local_admindashboard_module_action('Open Send KPI reminders', new moodle_url('/local/admindashboard/send_kpi_reminders.php')),
            ],
            [
                'Identify required courses that are incomplete, failed, or overdue.',
                'Summarize mandatory-learning exposure by department and role.',
                'Prepare follow-up actions for reminder or manager escalation flows.',
            ],
            [
                'Combine course requirement rules with current completion metrics.',
                'Add filters for criticality, due window, and owner department.',
                'Surface action lists for reminders, exports, and progress tracking.',
            ]
        ),
        'compliance.dashboard' => local_admindashboard_module_page_definition(
            '/local/admindashboard/compliance_dashboard.php',
            'compliance.dashboard',
            'Reports & Analytics',
            'Compliance Dashboard',
            'compliance',
            'Executive compliance hub for readiness, expiry exposure, and department-level risk signals.',
            [
                local_admindashboard_module_action('Open License Expiry module', new moodle_url('/local/admindashboard/license_expiry.php'), true),
                local_admindashboard_module_action('Open Mandatory Training module', new moodle_url('/local/admindashboard/mandatory_training.php')),
                local_admindashboard_module_action('Open Department Engagement report', new moodle_url('/local/admindashboard/department_reports_engagement.php')),
            ],
            [
                'Bring expiry, overdue learning, and completion health into one screen.',
                'Highlight at-risk departments and learners with actionable drill-downs.',
                'Create a strong handoff into exports and reminders.',
            ],
            [
                'Compose top-level KPI cards from existing reporting helpers.',
                'Introduce trend blocks for expiring records and overdue cohorts.',
                'Add direct navigation to intervention workflows and detailed reports.',
            ],
            [
                'This page should become the default control room for compliance operations.',
            ]
        ),
        'skills.gap' => local_admindashboard_module_page_definition(
            '/local/admindashboard/skill_gap_matrix.php',
            'skills.gap',
            'Reports & Analytics',
            'Skill Gap Matrix',
            'certification',
            'Matrix-style workspace for comparing current completion, competency coverage, and target skill expectations.',
            [
                local_admindashboard_module_action('Open Course Analytics', new moodle_url('/local/admindashboard/course_analytics.php'), true),
                local_admindashboard_module_action('Open Certificate Status module', new moodle_url('/local/admindashboard/certificate_status.php')),
                local_admindashboard_module_action('Open Department Completion report', new moodle_url('/local/admindashboard/department_reports.php')),
            ],
            [
                'Model target skills against actual completions and assessment outcomes.',
                'Compare departments, teams, and learning tracks side by side.',
                'Expose the highest-impact capability gaps for intervention planning.',
            ],
            [
                'Define target-skill sources and map them to courses or certifications.',
                'Render a score matrix with completion and assessment overlays.',
                'Add exportable gap summaries for management reporting.',
            ]
        ),
        'skills.certificates' => local_admindashboard_module_page_definition(
            '/local/admindashboard/certificate_status.php',
            'skills.certificates',
            'Reports & Analytics',
            'Certificate Status',
            'certification',
            'Status board for active certifications, earned credentials, and missing evidence.',
            [
                local_admindashboard_module_action('Open Renewal Readiness module', new moodle_url('/local/admindashboard/renewal_readiness.php'), true),
                local_admindashboard_module_action('Open License Expiry module', new moodle_url('/local/admindashboard/license_expiry.php')),
                local_admindashboard_module_action('Open Export Center', new moodle_url('/local/admindashboard/export_center.php')),
            ],
            [
                'Track which certifications are current, missing, expired, or awaiting verification.',
                'Segment by department, certification family, or credential owner.',
                'Prepare data for downstream compliance and renewal reporting.',
            ],
            [
                'Connect certificate issue and expiry data into a unified table.',
                'Add evidence status and exception handling for manual certifications.',
                'Expose renewal-related drill-downs and outbound exports.',
            ]
        ),
        'skills.renewals' => local_admindashboard_module_page_definition(
            '/local/admindashboard/renewal_readiness.php',
            'skills.renewals',
            'Reports & Analytics',
            'Renewal Readiness',
            'certification',
            'Forward-looking view of who is ready, blocked, or at risk for credential renewal.',
            [
                local_admindashboard_module_action('Open Certificate Status module', new moodle_url('/local/admindashboard/certificate_status.php'), true),
                local_admindashboard_module_action('Open License Expiry module', new moodle_url('/local/admindashboard/license_expiry.php')),
                local_admindashboard_module_action('Open Support Tickets module', new moodle_url('/local/admindashboard/support_tickets.php')),
            ],
            [
                'Separate renewal-ready learners from those missing prerequisites or evidence.',
                'Create queues for follow-up by department or certificate owner.',
                'Support early intervention before hard expiry dates are reached.',
            ],
            [
                'Map prerequisite completion rules for each renewal pathway.',
                'Add readiness statuses and blocker summaries.',
                'Integrate export and ticketing hooks for manual follow-up.',
            ]
        ),
        'communication.announcements' => local_admindashboard_module_page_definition(
            '/local/admindashboard/announcements.php',
            'communication.announcements',
            'Communication',
            'Announcements',
            'announcement',
            'Operational surface for LMS-wide notices, department updates, and compliance communications.',
            [
                local_admindashboard_module_action('Open notifications center', new moodle_url('/message/output/popup/notifications.php'), true),
                local_admindashboard_module_action('Open Direct Messaging module', new moodle_url('/local/admindashboard/direct_messaging.php')),
                local_admindashboard_module_action('Open Help Center module', new moodle_url('/local/admindashboard/help_center.php')),
            ],
            [
                'Plan targeted announcements by audience, urgency, and business area.',
                'Keep major operational or compliance notices easy to review.',
                'Lay the groundwork for reusable communication templates.',
            ],
            [
                'Add announcement drafting, approval, and scheduling states.',
                'Connect audience filters such as department, course, or risk segment.',
                'Track sent, scheduled, and archived communications.',
            ]
        ),
        'communication.discussions' => local_admindashboard_module_page_definition(
            '/local/admindashboard/forums_discussions.php',
            'communication.discussions',
            'Communication',
            'Forums & Discussions',
            'discussion',
            'Collaboration hub for forum health, response activity, and unresolved discussion queues.',
            [
                local_admindashboard_module_action('Open forum index', new moodle_url('/mod/forum/index.php'), true),
                local_admindashboard_module_action('Open Sentiment Analyzer', new moodle_url('/local/admindashboard/sentiment_analyzer.php')),
                local_admindashboard_module_action('Open Announcements module', new moodle_url('/local/admindashboard/announcements.php')),
            ],
            [
                'Review which course discussions are active, stale, or unmoderated.',
                'Prepare moderation and response-SLA views for admins.',
                'Connect community health with sentiment and engagement signals.',
            ],
            [
                'Add forum inventory and activity summaries.',
                'Highlight posts needing response or escalation.',
                'Support links into sentiment, engagement, and course analytics pages.',
            ]
        ),
        'communication.messaging' => local_admindashboard_module_page_definition(
            '/local/admindashboard/direct_messaging.php',
            'communication.messaging',
            'Communication',
            'Direct Messaging',
            'message',
            'Admin-oriented messaging workspace for outreach, follow-ups, and targeted nudges.',
            [
                local_admindashboard_module_action('Open core messaging', new moodle_url('/message/index.php'), true),
                local_admindashboard_module_action('Open at-risk reminders', new moodle_url('/local/admindashboard/send_at_risk_reminders.php')),
                local_admindashboard_module_action('Open Announcements module', new moodle_url('/local/admindashboard/announcements.php')),
            ],
            [
                'Coordinate direct outreach to at-risk, overdue, or renewal-pending learners.',
                'Keep messaging templates tied to dashboard-driven interventions.',
                'Provide operational links to existing reminder workflows.',
            ],
            [
                'Add audience lists sourced from risk and compliance reports.',
                'Introduce reusable message templates with audit-friendly labels.',
                'Track send intent and delivery summaries for follow-up actions.',
            ]
        ),
        'platform.integrations' => local_admindashboard_module_page_definition(
            '/local/admindashboard/integrations.php',
            'platform.integrations',
            'Platform Settings',
            'Integrations',
            'integration',
            'Command point for identity, HR, reporting, and third-party system touchpoints around the LMS.',
            [
                local_admindashboard_module_action('Open plugins overview', new moodle_url('/admin/plugins.php'), true),
                local_admindashboard_module_action('Open System Config module', new moodle_url('/local/admindashboard/system_config.php')),
                local_admindashboard_module_action('Open Export Center', new moodle_url('/local/admindashboard/export_center.php')),
            ],
            [
                'Document active integrations, owners, and failure sensitivity.',
                'Expose the most important admin destinations for integration support.',
                'Prepare for health checks, credential rotation, and data sync monitoring.',
            ],
            [
                'Add an integration registry with status, owner, and last-check timestamps.',
                'Track inbound and outbound data flows relevant to departments and compliance.',
                'Surface warnings and support actions when dependencies fail.',
            ]
        ),
        'platform.config' => local_admindashboard_module_page_definition(
            '/local/admindashboard/system_config.php',
            'platform.config',
            'Platform Settings',
            'System Config',
            'config',
            'Configuration workspace for operational settings, policy-sensitive changes, and admin checklists.',
            [
                local_admindashboard_module_action('Open site administration search', new moodle_url('/admin/search.php'), true),
                local_admindashboard_module_action('Open Integrations module', new moodle_url('/local/admindashboard/integrations.php')),
                local_admindashboard_module_action('Open Platform Branding module', new moodle_url('/local/admindashboard/platform_branding.php')),
            ],
            [
                'Centralize references to the highest-value configuration areas.',
                'Guide admins through controlled changes instead of broad admin searching.',
                'Support audit notes and operational ownership for sensitive settings.',
            ],
            [
                'Add categorized settings shortcuts and review checklists.',
                'Track config areas that impact analytics, compliance, or communication.',
                'Prepare for change logs and environment validation notes.',
            ]
        ),
        'platform.branding' => local_admindashboard_module_page_definition(
            '/local/admindashboard/platform_branding.php',
            'platform.branding',
            'Platform Settings',
            'Platform Branding',
            'branding',
            'Visual and messaging control point for branded experiences across admin flows and learner touchpoints.',
            [
                local_admindashboard_module_action('Open mobile branding tools', new moodle_url('/admin/tool/mobile/launch.php'), true),
                local_admindashboard_module_action('Open Announcements module', new moodle_url('/local/admindashboard/announcements.php')),
                local_admindashboard_module_action('Open Create Course module', new moodle_url('/local/admindashboard/create_course.php')),
            ],
            [
                'Keep logos, UI language, and campaign styling consistent.',
                'Support branded course-template and communication patterns.',
                'Prepare for future asset governance and preview flows.',
            ],
            [
                'Add branding presets and current-asset summaries.',
                'Connect templates, announcements, and support content with shared visuals.',
                'Expose links to the relevant Moodle configuration screens.',
            ]
        ),
        'support.tickets' => local_admindashboard_module_page_definition(
            '/local/admindashboard/support_tickets.php',
            'support.tickets',
            'Support & Account',
            'Support Tickets',
            'support',
            'Support operations surface for triage, learning blockers, and routed admin issues.',
            [
                local_admindashboard_module_action('Open inbound message settings', new moodle_url('/admin/tool/messageinbound/index.php'), true),
                local_admindashboard_module_action('Open Help Center module', new moodle_url('/local/admindashboard/help_center.php')),
                local_admindashboard_module_action('Open Direct Messaging module', new moodle_url('/local/admindashboard/direct_messaging.php')),
            ],
            [
                'Create a clean place for learning-support and admin-escalation queues.',
                'Differentiate technical, content, access, and compliance blockers.',
                'Prepare integrations with messaging and help content.',
            ],
            [
                'Add ticket statuses, priority badges, and route owners.',
                'Support quick links into affected user, course, or compliance records.',
                'Expose a queue summary for operations and service-level follow-up.',
            ]
        ),
        'support.help' => local_admindashboard_module_page_definition(
            '/local/admindashboard/help_center.php',
            'support.help',
            'Support & Account',
            'Help Center',
            'help',
            'Knowledge base landing page for admin playbooks, common fixes, and guided support routes.',
            [
                local_admindashboard_module_action('Open Moodle help', new moodle_url('/help.php'), true),
                local_admindashboard_module_action('Open Support Tickets module', new moodle_url('/local/admindashboard/support_tickets.php')),
                local_admindashboard_module_action('Open System Config module', new moodle_url('/local/admindashboard/system_config.php')),
            ],
            [
                'Collect high-value admin help topics in one place.',
                'Point common issues toward the right operational owner quickly.',
                'Lay the groundwork for searchable internal runbooks.',
            ],
            [
                'Add curated help topics grouped by user, course, compliance, and system areas.',
                'Track frequently used support routes and escalation contacts.',
                'Support future embedded documentation or internal SOP links.',
            ]
        ),
        'support.profile' => local_admindashboard_module_page_definition(
            '/local/admindashboard/my_profile.php',
            'support.profile',
            'Support & Account',
            'My Profile',
            'profile',
            'Profile workspace for admin identity details, account ownership, and workflow shortcuts.',
            [
                local_admindashboard_module_action('Open core profile', new moodle_url('/user/profile.php'), true),
                local_admindashboard_module_action('Open Settings module', new moodle_url('/local/admindashboard/account_settings.php')),
                local_admindashboard_module_action('Open Help Center module', new moodle_url('/local/admindashboard/help_center.php')),
            ],
            [
                'Keep profile maintenance and admin-facing shortcuts together.',
                'Guide users toward account-related settings without leaving the dashboard context.',
                'Prepare for richer personal productivity widgets later.',
            ],
            [
                'Add identity summary, key account links, and profile completeness indicators.',
                'Expose quick actions for password, notification, and preference updates.',
                'Support future personal task snapshots or pinned modules.',
            ]
        ),
        'support.settings' => local_admindashboard_module_page_definition(
            '/local/admindashboard/account_settings.php',
            'support.settings',
            'Support & Account',
            'Settings',
            'settings',
            'Account-settings hub for preferences, notification controls, and personal admin defaults.',
            [
                local_admindashboard_module_action('Open user preferences', new moodle_url('/user/preferences.php'), true),
                local_admindashboard_module_action('Open My Profile module', new moodle_url('/local/admindashboard/my_profile.php')),
                local_admindashboard_module_action('Open Direct Messaging module', new moodle_url('/local/admindashboard/direct_messaging.php')),
            ],
            [
                'Gather the most-used preference destinations in one dashboard-aligned screen.',
                'Reduce friction for notification and account-level changes.',
                'Prepare for admin-specific preference presets in future iterations.',
            ],
            [
                'Add grouped shortcuts for messaging, profile, and interface preferences.',
                'Highlight settings that impact dashboard usage and communications.',
                'Support future preference snapshots or recommended defaults.',
            ]
        ),
    ];
}

function local_admindashboard_render_module_page(string $pagekey): void {
    $pages = local_admindashboard_get_module_page_definitions();
    if (!array_key_exists($pagekey, $pages)) {
        throw new moodle_exception('invalidpage');
    }

    $page = $pages[$pagekey];
    local_admindashboard_setup_page($page['script'], $page['title'], $page['active']);
    local_admindashboard_render_header($page['active']);

    $summary = html_writer::tag('p', s($page['summary']), ['class' => 'admindash-module-hero__summary']);

    $badges = [];
    $badges[] = html_writer::tag('span', 'Scaffold live', ['class' => 'admindash-module-chip']);
    $badges[] = html_writer::tag('span', 'Sidebar linked', ['class' => 'admindash-module-chip']);
    $badges[] = html_writer::tag('span', 'Ready for data wiring', ['class' => 'admindash-module-chip']);

    $actionbuttons = [];
    foreach ($page['actions'] as $action) {
        $class = !empty($action['primary']) ? 'btn btn-primary' : 'btn btn-outline-secondary';
        $actionbuttons[] = html_writer::link($action['url'], s($action['label']), ['class' => $class]);
    }

    $scopeitems = [];
    foreach ($page['scope'] as $item) {
        $scopeitems[] = html_writer::tag('li', s($item));
    }

    $roadmapitems = [];
    foreach ($page['roadmap'] as $item) {
        $roadmapitems[] = html_writer::tag('li', s($item));
    }

    $noteblock = '';
    if (!empty($page['notes'])) {
        $noteitems = [];
        foreach ($page['notes'] as $item) {
            $noteitems[] = html_writer::tag('li', s($item));
        }
        $noteblock = html_writer::div(
            html_writer::tag('h3', 'Implementation Notes', ['class' => 'admindash-module-card__title'])
                . html_writer::tag('ul', implode('', $noteitems), ['class' => 'admindash-module-list']),
            'admindash-card admindash-module-card'
        );
    }

    echo html_writer::start_div('admindash-module-shell');
    echo html_writer::start_div('admindash-card admindash-module-hero');
    echo html_writer::tag('div', s($page['eyebrow']), ['class' => 'admindash-module-hero__eyebrow']);
    echo html_writer::start_div('admindash-module-hero__titleline');
    echo html_writer::tag('div', local_admindashboard_get_nav_icon_svg($page['icon']), ['class' => 'admindash-module-hero__icon', 'aria-hidden' => 'true']);
    echo html_writer::tag('h2', s($page['title']), ['class' => 'admindash-module-hero__title']);
    echo html_writer::end_div();
    echo $summary;
    echo html_writer::div(implode('', $badges), 'admindash-module-chiprow');
    echo html_writer::div(implode('', $actionbuttons), 'admindash-module-actions');
    echo html_writer::end_div();

    echo html_writer::start_div('admindash-module-stats');
    echo html_writer::div(
        html_writer::tag('div', 'Now', ['class' => 'admindash-module-stat__label'])
            . html_writer::tag('div', 'Local page is live', ['class' => 'admindash-module-stat__value'])
            . html_writer::tag('div', 'Users can reach this module directly from the new sidebar.', ['class' => 'admindash-module-stat__meta']),
        'admindash-card admindash-module-stat'
    );
    echo html_writer::div(
        html_writer::tag('div', 'Focus', ['class' => 'admindash-module-stat__label'])
            . html_writer::tag('div', 'Operations-first scaffold', ['class' => 'admindash-module-stat__value'])
            . html_writer::tag('div', 'The page is ready for forms, tables, and report widgets next.', ['class' => 'admindash-module-stat__meta']),
        'admindash-card admindash-module-stat'
    );
    echo html_writer::div(
        html_writer::tag('div', 'Navigation', ['class' => 'admindash-module-stat__label'])
            . html_writer::tag('div', 'Sidebar route migrated', ['class' => 'admindash-module-stat__value'])
            . html_writer::tag('div', 'This module now replaces the temporary generic target URL.', ['class' => 'admindash-module-stat__meta']),
        'admindash-card admindash-module-stat'
    );
    echo html_writer::end_div();

    echo html_writer::start_div('admindash-module-grid');
    echo html_writer::div(
        html_writer::tag('h3', 'Initial Scope', ['class' => 'admindash-module-card__title'])
            . html_writer::tag('ul', implode('', $scopeitems), ['class' => 'admindash-module-list']),
        'admindash-card admindash-module-card'
    );
    echo html_writer::div(
        html_writer::tag('h3', 'Build Roadmap', ['class' => 'admindash-module-card__title'])
            . html_writer::tag('ul', implode('', $roadmapitems), ['class' => 'admindash-module-list']),
        'admindash-card admindash-module-card'
    );
    echo $noteblock;
    echo html_writer::end_div();
    echo html_writer::end_div();

    local_admindashboard_render_footer();
}

function local_admindashboard_get_manage_users_suite_tabs(): array {
    return [
        [
            'key' => 'admintools.users.list',
            'label' => 'Users List',
            'url' => new moodle_url('/local/admindashboard/manage_users.php'),
        ],
        [
            'key' => 'admintools.users.add',
            'label' => 'Add User',
            'url' => new moodle_url('/local/admindashboard/add_user.php'),
        ],
        [
            'key' => 'admintools.users.roles',
            'label' => 'Define Roles',
            'url' => new moodle_url('/local/admindashboard/define_roles.php'),
        ],
    ];
}

function local_admindashboard_get_manage_courses_suite_tabs(): array {
    return [
        [
            'key' => 'admintools.courses.list',
            'label' => 'Course List',
            'url' => new moodle_url('/local/admindashboard/course_list.php'),
        ],
        [
            'key' => 'admintools.courses.create',
            'label' => 'Create New Course',
            'url' => new moodle_url('/local/admindashboard/create_course.php'),
        ],
        [
            'key' => 'admintools.courses.templates',
            'label' => 'Course Templates',
            'url' => new moodle_url('/local/admindashboard/course_templates.php'),
        ],
    ];
}

function local_admindashboard_get_skill_certifications_suite_tabs(): array {
    return [
        [
            'key' => 'skills.gap',
            'label' => 'Skill Gap Matrix',
            'url' => new moodle_url('/local/admindashboard/skill_gap_matrix.php'),
        ],
        [
            'key' => 'skills.certificates',
            'label' => 'Certificate Status',
            'url' => new moodle_url('/local/admindashboard/certificate_status.php'),
        ],
        [
            'key' => 'skills.renewals',
            'label' => 'Renewal Readiness',
            'url' => new moodle_url('/local/admindashboard/renewal_readiness.php'),
        ],
    ];
}

function local_admindashboard_get_communication_suite_tabs(): array {
    return [
        [
            'key' => 'communication.announcements',
            'label' => 'Announcements',
            'url' => new moodle_url('/local/admindashboard/announcements.php'),
        ],
        [
            'key' => 'communication.discussions',
            'label' => 'Forums & Discussions',
            'url' => new moodle_url('/local/admindashboard/forums_discussions.php'),
        ],
        [
            'key' => 'communication.messaging',
            'label' => 'Direct Messaging',
            'url' => new moodle_url('/local/admindashboard/direct_messaging.php'),
        ],
    ];
}

function local_admindashboard_get_compliance_suite_tabs(): array {
    return [
        [
            'key' => 'compliance.dashboard',
            'label' => 'Compliance Dashboard',
            'url' => new moodle_url('/local/admindashboard/compliance_dashboard.php'),
        ],
        [
            'key' => 'compliance.expiry',
            'label' => 'License Expiry',
            'url' => new moodle_url('/local/admindashboard/license_expiry.php'),
        ],
        [
            'key' => 'compliance.mandatory',
            'label' => 'Mandatory Training',
            'url' => new moodle_url('/local/admindashboard/mandatory_training.php'),
        ],
    ];
}

function local_admindashboard_get_platform_settings_suite_tabs(): array {
    return [
        [
            'key' => 'platform.config',
            'label' => 'System Config',
            'url' => new moodle_url('/local/admindashboard/system_config.php'),
        ],
        [
            'key' => 'platform.integrations',
            'label' => 'Integrations',
            'url' => new moodle_url('/local/admindashboard/integrations.php'),
        ],
        [
            'key' => 'platform.branding',
            'label' => 'Platform Branding',
            'url' => new moodle_url('/local/admindashboard/platform_branding.php'),
        ],
        [
            'key' => 'platform.schedule_notes',
            'label' => 'Course schedule notes',
            'url' => new moodle_url('/local/admindashboard/course_schedule_notes.php'),
        ],
    ];
}

function local_admindashboard_get_support_account_suite_tabs(): array {
    return [
        [
            'key' => 'support.tickets',
            'label' => 'Support Tickets',
            'url' => new moodle_url('/local/admindashboard/support_tickets.php'),
        ],
        [
            'key' => 'support.help',
            'label' => 'Help Center',
            'url' => new moodle_url('/local/admindashboard/help_center.php'),
        ],
        [
            'key' => 'support.profile',
            'label' => 'My Profile',
            'url' => new moodle_url('/local/admindashboard/my_profile.php'),
        ],
        [
            'key' => 'support.settings',
            'label' => 'Settings',
            'url' => new moodle_url('/local/admindashboard/account_settings.php'),
        ],
    ];
}

function local_admindashboard_get_certificate_issue_union_sql(): array {
    global $CFG, $DB;

    require_once($CFG->libdir . '/xmldb/xmldb_table.php');
    require_once($CFG->libdir . '/xmldb/xmldb_field.php');

    $manager = $DB->get_manager();
    $parts = [];
    $hastimestamps = false;

    if ($manager->table_exists(new xmldb_table('customcert'))
            && $manager->table_exists(new xmldb_table('customcert_issues'))) {
        $customissues = new xmldb_table('customcert_issues');
        $timeexpr = '0';
        if ($manager->field_exists($customissues, new xmldb_field('timecreated'))) {
            $timeexpr = 'ci.timecreated';
            $hastimestamps = true;
        }
        $parts[] = "SELECT ci.userid AS userid,
                            ccert.course AS courseid,
                            {$timeexpr} AS issuedat,
                            'customcert' AS source
                       FROM {customcert_issues} ci
                       JOIN {customcert} ccert ON ccert.id = ci.customcertid";
    }

    if ($manager->table_exists(new xmldb_table('certificate'))
            && $manager->table_exists(new xmldb_table('certificate_issues'))) {
        $certificateissues = new xmldb_table('certificate_issues');
        $timeexpr = '0';
        if ($manager->field_exists($certificateissues, new xmldb_field('timecreated'))) {
            $timeexpr = 'ci.timecreated';
            $hastimestamps = true;
        }
        $parts[] = "SELECT ci.userid AS userid,
                            cert.course AS courseid,
                            {$timeexpr} AS issuedat,
                            'certificate' AS source
                       FROM {certificate_issues} ci
                       JOIN {certificate} cert ON cert.id = ci.certificateid";
    }

    if ($manager->table_exists(new xmldb_table('tool_certificate_templates'))
            && $manager->table_exists(new xmldb_table('tool_certificate_issues'))) {
        $issues = new xmldb_table('tool_certificate_issues');
        $templates = new xmldb_table('tool_certificate_templates');
        $timeexpr = '0';
        if ($manager->field_exists($issues, new xmldb_field('timecreated'))) {
            $timeexpr = 'tci.timecreated';
            $hastimestamps = true;
        }

        $issueshascourseid = $manager->field_exists($issues, new xmldb_field('courseid'));
        $issueshascontextid = $manager->field_exists($issues, new xmldb_field('contextid'));
        $issueshastemplateid = $manager->field_exists($issues, new xmldb_field('templateid'));
        $templateshascourseid = $manager->field_exists($templates, new xmldb_field('courseid'));
        $templateshascontextid = $manager->field_exists($templates, new xmldb_field('contextid'));

        if ($issueshascourseid) {
            $parts[] = "SELECT tci.userid AS userid,
                                tci.courseid AS courseid,
                                {$timeexpr} AS issuedat,
                                'tool_certificate' AS source
                           FROM {tool_certificate_issues} tci";
        } else if ($issueshascontextid) {
            $parts[] = "SELECT tci.userid AS userid,
                                ctx.instanceid AS courseid,
                                {$timeexpr} AS issuedat,
                                'tool_certificate' AS source
                           FROM {tool_certificate_issues} tci
                           JOIN {context} ctx ON ctx.id = tci.contextid
                          WHERE ctx.contextlevel = " . CONTEXT_COURSE;
        } else if ($issueshastemplateid && $templateshascourseid) {
            $parts[] = "SELECT tci.userid AS userid,
                                tct.courseid AS courseid,
                                {$timeexpr} AS issuedat,
                                'tool_certificate' AS source
                           FROM {tool_certificate_issues} tci
                           JOIN {tool_certificate_templates} tct ON tct.id = tci.templateid";
        } else if ($issueshastemplateid && $templateshascontextid) {
            $parts[] = "SELECT tci.userid AS userid,
                                ctx.instanceid AS courseid,
                                {$timeexpr} AS issuedat,
                                'tool_certificate' AS source
                           FROM {tool_certificate_issues} tci
                           JOIN {tool_certificate_templates} tct ON tct.id = tci.templateid
                           JOIN {context} ctx ON ctx.id = tct.contextid
                          WHERE ctx.contextlevel = " . CONTEXT_COURSE;
        }
    }

    if (empty($parts)) {
        return [
            'available' => false,
            'hastimestamps' => false,
            'sql' => "SELECT 0 AS userid, 0 AS courseid, 0 AS issuedat, '' AS source WHERE 1=0",
        ];
    }

    return [
        'available' => true,
        'hastimestamps' => $hastimestamps,
        'sql' => implode(' UNION ALL ', $parts),
    ];
}

function local_admindashboard_render_workspace_header(
    string $eyebrow,
    string $title,
    string $summary,
    string $icon,
    string $active,
    array $tabs,
    array $actions = [],
    array $chips = []
): void {
    echo html_writer::start_div('admindash-card admindash-module-hero');
    echo html_writer::tag('div', s($eyebrow), ['class' => 'admindash-module-hero__eyebrow']);
    echo html_writer::start_div('admindash-module-hero__titleline');
    echo html_writer::tag('div', local_admindashboard_get_nav_icon_svg($icon), ['class' => 'admindash-module-hero__icon', 'aria-hidden' => 'true']);
    echo html_writer::tag('h2', s($title), ['class' => 'admindash-module-hero__title']);
    echo html_writer::end_div();

    if (!empty($actions)) {
        $actionmarkup = [];
        foreach ($actions as $action) {
            $class = !empty($action['primary']) ? 'btn btn-primary' : 'btn btn-outline-secondary';
            $actionmarkup[] = html_writer::link($action['url'], s($action['label']), ['class' => $class]);
        }
        echo html_writer::div(implode('', $actionmarkup), 'admindash-module-actions');
    }

    if (!empty($tabs)) {
        $tabmarkup = [];
        foreach ($tabs as $tab) {
            $class = 'admindash-workspace-tab';
            $attrs = [];
            if (($tab['key'] ?? '') === $active) {
                $class .= ' active';
                $attrs['aria-current'] = 'page';
            }
            $attrs['class'] = $class;
            $tabmarkup[] = html_writer::link($tab['url'], s($tab['label']), $attrs);
        }
        echo html_writer::div(implode('', $tabmarkup), 'admindash-workspace-tabs');
    }

    echo html_writer::end_div();
}

function local_admindashboard_render_header(string $active): void {
    global $OUTPUT, $SITE, $PAGE;

    $PAGE->add_body_class('admindash-page');

    echo $OUTPUT->header();

    $logourl = $OUTPUT->get_compact_logo_url(220, 220);
    if (empty($logourl)) {
        $logourl = $OUTPUT->get_logo_url(null, 220);
    }
    $sitename = format_string($SITE->fullname);

    $navitems = local_admindashboard_get_nav_items();

    echo html_writer::start_div('admindash-layout');

    echo html_writer::start_div('admindash-sidebar');
    echo html_writer::start_div('admindash-brand');
    if (!empty($logourl)) {
        echo html_writer::empty_tag('img', ['src' => $logourl, 'alt' => $sitename, 'class' => 'admindash-brand__logo']);
    } else {
        echo html_writer::tag('div', s($sitename), ['class' => 'admindash-brand__fallback']);
    }
    echo html_writer::end_div();

    $homeclass = 'admindash-home-link';
    if ($active === 'dashboard') {
        $homeclass .= ' active';
    }
    echo html_writer::link(
        new moodle_url('/local/admindashboard/dashboard.php'),
        html_writer::tag('span', local_admindashboard_get_nav_icon_svg('home'), ['class' => 'admindash-nav-icon', 'aria-hidden' => 'true'])
            . html_writer::tag('span', 'Dashboard', ['class' => 'admindash-nav-label']),
        ['class' => $homeclass]
    );

    echo html_writer::start_div('admindash-nav');
    foreach ($navitems as $sectionindex => $section) {
        echo html_writer::start_div('admindash-nav-section');
        echo html_writer::tag('div', s($section['heading']), ['class' => 'admindash-nav-heading']);
        echo html_writer::start_div('admindash-nav-list');
        foreach ($section['items'] as $itemindex => $item) {
            if (!empty($item['require_edit_notes']) && !local_admindashboard_user_can_edit_course_schedule_notes()) {
                continue;
            }
            $itemkey = (string)($item['key'] ?? '');
            $haschildren = !empty($item['children']);
            $isparentactive = $itemkey !== '' && ($itemkey === $active || str_starts_with($active, $itemkey . '.'));
            $panelid = 'admindash-subnav-' . $sectionindex . '-' . $itemindex;
            $groupclass = 'admindash-nav-group';
            if ($haschildren) {
                $groupclass .= ' has-children';
            }
            if ($haschildren && $isparentactive) {
                $groupclass .= ' open';
            }
            echo html_writer::start_div($groupclass);

            if ($haschildren) {
                $toggleclass = 'admindash-nav-toggle';
                if ($isparentactive) {
                    $toggleclass .= ' active';
                }
                echo html_writer::tag(
                    'button',
                    html_writer::tag('span', local_admindashboard_get_nav_icon_svg((string)($item['icon'] ?? 'report')), ['class' => 'admindash-nav-icon', 'aria-hidden' => 'true'])
                        . html_writer::tag('span', s($item['label']), ['class' => 'admindash-nav-label'])
                        . html_writer::tag('span', local_admindashboard_get_nav_icon_svg('caret'), ['class' => 'admindash-nav-caret', 'aria-hidden' => 'true']),
                    [
                        'type' => 'button',
                        'class' => $toggleclass,
                        'aria-expanded' => $isparentactive ? 'true' : 'false',
                        'aria-controls' => $panelid,
                    ]
                );

                echo html_writer::start_div('admindash-subnav-wrap', ['id' => $panelid]);
                echo html_writer::start_div('admindash-subnav');
                foreach ($item['children'] as $child) {
                    $childkey = (string)($child['key'] ?? '');
                    $childclass = '';
                    $attrs = [];
                    if ($childkey === $active) {
                        $childclass = 'active';
                        $attrs['aria-current'] = 'page';
                    }
                    if ($childclass !== '') {
                        $attrs['class'] = $childclass;
                    }
                    echo html_writer::link($child['url'], s($child['label']), $attrs);
                }
                echo html_writer::end_div();
                echo html_writer::end_div();
            } else {
                $linkclass = 'admindash-nav-link';
                if ($isparentactive) {
                    $linkclass .= ' active';
                }
                $attrs = ['class' => $linkclass];
                if ($isparentactive) {
                    $attrs['aria-current'] = 'page';
                }
                echo html_writer::link(
                    $item['url'],
                    html_writer::tag('span', local_admindashboard_get_nav_icon_svg((string)($item['icon'] ?? 'report')), ['class' => 'admindash-nav-icon', 'aria-hidden' => 'true'])
                        . html_writer::tag('span', s($item['label']), ['class' => 'admindash-nav-label']),
                    $attrs
                );
            }

            echo html_writer::end_div();
        }
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::div('', 'admindash-sidebar-backdrop', ['aria-hidden' => 'true']);

    echo html_writer::start_div('admindash-main');

    echo html_writer::start_div('admindash-topbar');
    echo html_writer::tag('button', local_admindashboard_get_nav_icon_svg('menu'), [
        'type' => 'button',
        'class' => 'btn btn-outline-secondary admindash-sidebar-toggle',
        'aria-expanded' => 'false',
        'aria-label' => 'Toggle navigation',
    ]);
    echo html_writer::tag('button', 'Dark mode', [
        'type' => 'button',
        'class' => 'btn btn-outline-secondary admindash-theme-toggle',
        'aria-pressed' => 'false',
    ]);
    echo html_writer::link(new moodle_url('/'), 'Back to LMS', [
        'class' => 'btn btn-outline-secondary',
    ]);
    echo html_writer::end_div();
}

/**
 * Add dashboard link to global navigation for admins.
 */
function local_admindashboard_extend_navigation(global_navigation $navigation): void {
    if (!isloggedin() || isguestuser()) {
        return;
    }
    if (!local_admindashboard_user_can_view()) {
        return;
    }

    if ($navigation->find('local_admindashboard', navigation_node::TYPE_CUSTOM)) {
        return;
    }

    global $CFG;
    $navigation->add(
        get_string('pluginname', 'local_admindashboard'),
        new moodle_url($CFG->wwwroot . '/local/admindashboard/dashboard.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_admindashboard',
        new pix_icon('i/report', '', 'core')
    );
}

/**
 * Add dashboard link on the front page navigation.
 */
function local_admindashboard_extend_navigation_frontpage(navigation_node $frontpage): void {
    if (!isloggedin() || isguestuser()) {
        return;
    }
    if (!local_admindashboard_user_can_view()) {
        return;
    }

    if ($frontpage->find('local_admindashboard', navigation_node::TYPE_CUSTOM)) {
        return;
    }

    global $CFG;
    $frontpage->add(
        get_string('pluginname', 'local_admindashboard'),
        new moodle_url($CFG->wwwroot . '/local/admindashboard/dashboard.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_admindashboard',
        new pix_icon('i/report', '', 'core')
    );
}

function local_admindashboard_render_footer(): void {
    global $OUTPUT;
    echo html_writer::end_div(); // main
    echo html_writer::end_div(); // layout
    echo $OUTPUT->footer();
}

/**
 * Primary navigation tabs mein link add karne ke liye.
 */
/**
 * Top menu (Home, Dashboard) mein link add karne ke liye.
 */
function local_admindashboard_extend_navigation_primary(core\navigation\views\primary $navigation): void {
    if (!isloggedin() || isguestuser()) {
        return;
    }

    // Only show to users who can view the dashboard.
    if (!local_admindashboard_user_can_view()) {
        return;
    }

    // Prevent duplicates.
    $key = 'local_admindashboard';
    if ($navigation->find($key, navigation_node::TYPE_CUSTOM)) {
        return;
    }

    $url = new moodle_url('/local/admindashboard/dashboard.php');
    $navigation->add(
        get_string('pluginname', 'local_admindashboard'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        $key,
        new pix_icon('i/report', '', 'core')
    );
}
