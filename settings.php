<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category('local_admindashboard', get_string('pluginname', 'local_admindashboard')));
    $ADMIN->add('local_admindashboard', new admin_externalpage(
        'local_admindashboard_dashboard',
        get_string('pluginname', 'local_admindashboard'),
        new moodle_url('/local/admindashboard/dashboard.php'),
        'moodle/site:config'
    ));

    $settings = new admin_settingpage('local_admindashboard_settings', get_string('settingsheading', 'local_admindashboard'));
    $settings->add(new admin_setting_configpasswordunmask(
        'local_admindashboard/groq_apikey',
        get_string('groqapikey', 'local_admindashboard'),
        get_string('groqapikey_desc', 'local_admindashboard'),
        ''
    ));
    $settings->add(new admin_setting_configtext(
        'local_admindashboard/groq_model',
        get_string('groqmodel', 'local_admindashboard'),
        get_string('groqmodel_desc', 'local_admindashboard'),
        'llama-3.3-70b-versatile',
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtext(
        'local_admindashboard/groq_endpoint',
        get_string('groqendpoint', 'local_admindashboard'),
        get_string('groqendpoint_desc', 'local_admindashboard'),
        'https://api.groq.com/openai/v1/chat/completions',
        PARAM_URL
    ));
    $ADMIN->add('local_admindashboard', $settings);
}
