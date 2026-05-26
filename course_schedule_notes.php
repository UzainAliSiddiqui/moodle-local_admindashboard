<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $OUTPUT;

$canedit = admindash_user_can_edit_course_schedule_notes();

admindash_setup_page(
    '/local/admindashboard/course_schedule_notes.php',
    admindash_get_string_schedule_notes('courseschedulenotes_pagetitle'),
    'platform.schedule_notes'
);
admindash_render_header('platform.schedule_notes');

$tabs = admindash_get_platform_settings_suite_tabs();

if ($canedit && data_submitted() && confirm_sesskey()) {
    $cadencecount = optional_param('cadence_row_count', 3, PARAM_INT);
    $cadencecount = max(1, min(12, $cadencecount));
    $cadencewindows = [];
    for ($i = 0; $i < $cadencecount; $i++) {
        $coursesraw = optional_param('cadence_' . $i . '_courses', '', PARAM_TEXT);
        $courses = preg_split('/\r\n|\r|\n/', $coursesraw) ?: [];
        $cadencewindows[] = [
            'key' => optional_param('cadence_' . $i . '_key', 'cadence_' . $i, PARAM_ALPHANUMEXT),
            'title' => optional_param('cadence_' . $i . '_title', '', PARAM_TEXT),
            'range' => optional_param('cadence_' . $i . '_range', '', PARAM_TEXT),
            'startmonth' => optional_param('cadence_' . $i . '_startmonth', 1, PARAM_INT),
            'startday' => optional_param('cadence_' . $i . '_startday', 1, PARAM_INT),
            'endmonth' => optional_param('cadence_' . $i . '_endmonth', 1, PARAM_INT),
            'endday' => optional_param('cadence_' . $i . '_endday', 1, PARAM_INT),
            'courses' => $courses,
        ];
    }
    admindash_save_course_schedule_cadence_config($cadencewindows);

    $rowcount = optional_param('sticky_row_count', 3, PARAM_INT);
    $rowcount = max(1, min(admindash_course_schedule_sticky_notes_max(), $rowcount));
    $notes = [];
    for ($i = 0; $i < $rowcount; $i++) {
        $notes[] = [
            'title' => optional_param('note_' . $i . '_title', '', PARAM_TEXT),
            'body' => optional_param('note_' . $i . '_body', '', PARAM_TEXT),
            'variant' => optional_param('note_' . $i . '_variant', 'lemon', PARAM_ALPHANUMEXT),
        ];
    }
    admindash_save_course_schedule_sticky_notes($notes);
    redirect(
        new moodle_url('/local/admindashboard/course_schedule_notes.php'),
        get_string('changessaved'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

admindash_render_workspace_header(
    'Platform Settings',
    admindash_get_string_schedule_notes('courseschedulenotes_pagetitle'),
    admindash_get_string_schedule_notes('courseschedulenotes_intro', admindash_course_schedule_sticky_notes_max()),
    'sticky',
    'platform.schedule_notes',
    $tabs,
    [
        [
            'label' => admindash_get_string_schedule_notes('courseschedulenotes_backdash'),
            'url' => new moodle_url('/local/admindashboard/dashboard.php'),
            'primary' => false,
        ],
    ],
    []
);

if (!$canedit) {
    echo $OUTPUT->notification(admindash_get_string_schedule_notes('courseschedulenotes_nocap'), 'notifymessage');
    echo admindash_render_course_schedule_sticky_board(false);
    admindash_render_footer();
    return;
}

$notes = admindash_get_course_schedule_sticky_notes();
$formaction = (new moodle_url('/local/admindashboard/course_schedule_notes.php'))->out(false);

echo html_writer::start_div('admindash-card admindash-schedule-editor');
echo html_writer::tag('h3', admindash_get_string_schedule_notes('courseschedulenotes_formtitle'), ['class' => 'mb-2']);
echo html_writer::div(
    admindash_get_string_schedule_notes('courseschedulenotes_formhelp', admindash_course_schedule_sticky_notes_max()),
    'text-muted small mb-3'
);

$maxnotes = admindash_course_schedule_sticky_notes_max();
$notecount = count($notes);
$shownrows = min($maxnotes, max(3, $notecount + 1));
$formrows = [];
foreach ($notes as $n) {
    $formrows[] = $n;
}
while (count($formrows) < $shownrows) {
    $formrows[] = ['title' => '', 'body' => '', 'variant' => 'lemon'];
}

if ($notecount >= $maxnotes) {
    echo $OUTPUT->notification(
        admindash_get_string_schedule_notes('courseschedulenotes_maxreached', $maxnotes),
        'notifymessage'
    );
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $formaction,
    'class' => 'admindash-schedule-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'sticky_row_count',
    'value' => (string)$shownrows,
]);

$cadencewindows = admindash_get_course_schedule_cadence_config();
$cadencerows = $cadencewindows;
while (count($cadencerows) < count(admindash_default_course_schedule_cadence_config()) + 1) {
    $cadencerows[] = [
        'key' => 'cadence_' . count($cadencerows),
        'title' => '',
        'range' => '',
        'startmonth' => 1,
        'startday' => 1,
        'endmonth' => 1,
        'endday' => 1,
        'courses' => [],
    ];
}
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'cadence_row_count',
    'value' => (string)count($cadencerows),
]);

echo html_writer::tag('h4', 'Course cadence windows', ['class' => 'mt-2 mb-2']);
echo html_writer::div(
    'These rows power the dashboard countdown cards. Leave a title empty to skip that row.',
    'text-muted small mb-3'
);

foreach ($cadencerows as $i => $window) {
    $coursesvalue = implode("\n", array_values((array)($window['courses'] ?? [])));
    echo html_writer::start_div('admindash-schedule-form__card');
    echo html_writer::tag('h4', 'Cadence window ' . ($i + 1), ['class' => 'admindash-schedule-form__card-title']);
    echo html_writer::empty_tag('input', [
        'type' => 'hidden',
        'name' => 'cadence_' . $i . '_key',
        'value' => (string)($window['key'] ?? ('cadence_' . $i)),
    ]);

    echo html_writer::start_div('row g-2');
    echo html_writer::start_div('col-md-6');
    echo html_writer::tag('label', 'Title', ['for' => 'cadence_' . $i . '_title', 'class' => 'form-label']);
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'cadence_' . $i . '_title',
        'id' => 'cadence_' . $i . '_title',
        'class' => 'form-control',
        'value' => (string)($window['title'] ?? ''),
        'maxlength' => 255,
    ]);
    echo html_writer::end_div();
    echo html_writer::start_div('col-md-6');
    echo html_writer::tag('label', 'Range label', ['for' => 'cadence_' . $i . '_range', 'class' => 'form-label']);
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'cadence_' . $i . '_range',
        'id' => 'cadence_' . $i . '_range',
        'class' => 'form-control',
        'value' => (string)($window['range'] ?? ''),
        'maxlength' => 80,
    ]);
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('row g-2 mt-1');
    foreach ([
        'startmonth' => 'Start month',
        'startday' => 'Start day',
        'endmonth' => 'End month',
        'endday' => 'End day',
    ] as $field => $label) {
        echo html_writer::start_div('col-6 col-md-3');
        echo html_writer::tag('label', $label, ['for' => 'cadence_' . $i . '_' . $field, 'class' => 'form-label']);
        echo html_writer::empty_tag('input', [
            'type' => 'number',
            'name' => 'cadence_' . $i . '_' . $field,
            'id' => 'cadence_' . $i . '_' . $field,
            'class' => 'form-control',
            'value' => (string)($window[$field] ?? 1),
            'min' => '1',
            'max' => strpos($field, 'month') !== false ? '12' : '31',
        ]);
        echo html_writer::end_div();
    }
    echo html_writer::end_div();

    echo html_writer::start_div('mt-2');
    echo html_writer::tag('label', 'Courses / notes shown on card', ['for' => 'cadence_' . $i . '_courses', 'class' => 'form-label']);
    echo '<textarea name="cadence_' . $i . '_courses" id="cadence_' . $i . '_courses" class="form-control" rows="3" spellcheck="true">' . s($coursesvalue) . '</textarea>';
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::tag('h4', admindash_get_string_schedule_notes('courseschedulenotes_formtitle'), ['class' => 'mt-4 mb-2']);

$variants = [
    'lemon' => admindash_get_string_schedule_notes('courseschedulenotes_variant_lemon'),
    'mint' => admindash_get_string_schedule_notes('courseschedulenotes_variant_mint'),
    'lavender' => admindash_get_string_schedule_notes('courseschedulenotes_variant_lavender'),
    'peach' => admindash_get_string_schedule_notes('courseschedulenotes_variant_peach'),
];

foreach ($formrows as $i => $note) {
    echo html_writer::start_div('admindash-schedule-form__card');
    echo html_writer::tag('h4', admindash_get_string_schedule_notes('courseschedulenotes_notelabel', $i + 1), ['class' => 'admindash-schedule-form__card-title']);

    echo html_writer::start_div('mb-2');
    echo html_writer::tag('label', admindash_get_string_schedule_notes('courseschedulenotes_field_title'), ['for' => 'note_' . $i . '_title', 'class' => 'form-label']);
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'note_' . $i . '_title',
        'id' => 'note_' . $i . '_title',
        'class' => 'form-control',
        'value' => $note['title'],
        'maxlength' => 255,
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('mb-2');
    echo html_writer::tag('label', admindash_get_string_schedule_notes('courseschedulenotes_field_body'), ['for' => 'note_' . $i . '_body', 'class' => 'form-label']);
    echo '<textarea name="note_' . $i . '_body" id="note_' . $i . '_body" class="form-control" rows="5" spellcheck="true">' . s($note['body']) . '</textarea>';
    echo html_writer::end_div();

    echo html_writer::start_div('mb-0');
    echo html_writer::tag('label', admindash_get_string_schedule_notes('courseschedulenotes_field_variant'), ['for' => 'note_' . $i . '_variant', 'class' => 'form-label']);
    echo html_writer::start_tag('select', [
        'name' => 'note_' . $i . '_variant',
        'id' => 'note_' . $i . '_variant',
        'class' => 'form-select admindash-schedule-form__select',
    ]);
    foreach ($variants as $val => $label) {
        $attrs = ['value' => $val];
        if ($note['variant'] === $val) {
            $attrs['selected'] = 'selected';
        }
        echo html_writer::tag('option', s($label), $attrs);
    }
    echo html_writer::end_tag('select');
    echo html_writer::end_div();

    echo html_writer::end_div();
}

echo html_writer::tag(
    'button',
    get_string('savechanges'),
    ['type' => 'submit', 'class' => 'btn btn-primary mt-3']
);
echo html_writer::end_tag('form');
echo html_writer::end_div();

echo html_writer::div(
    html_writer::tag('h3', admindash_get_string_schedule_notes('courseschedulenotes_preview'), ['class' => 'mt-4 mb-2'])
    . admindash_render_course_schedule_sticky_board(false),
    'admindash-schedule-preview-wrap mt-2'
);

admindash_render_footer();
