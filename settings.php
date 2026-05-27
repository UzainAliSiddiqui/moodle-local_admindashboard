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
