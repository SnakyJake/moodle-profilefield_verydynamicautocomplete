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
 * Dynamic autocomplete profile field definition.
 * Based on moodle menu by Shane Elliot
 *
 * @package   profilefield_verydynamicautocomplete
 * @copyright 2022 Jakob Heinemann
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot."/user/profile/field/verydynamicautocomplete/locallib.php");

/**
 * Class profile_define_verydynamicautocomplete
 *
 * @copyright 2016 onwards Antonello Moro {@link http://treagles.it}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_define_verydynamicautocomplete extends profile_define_base
{
    /**
     * Adds elements to the form for creating/editing this type of profile field.
     *
     * @param moodleform $form
     */
    public function define_form_specific($form) {

        // param1 for menu type contains the options.
        $form->addElement(
            'textarea', 'param1', get_string('param1sqlquery', 'profilefield_verydynamicautocomplete'),
            array('rows' => 6, 'cols' => 40)
        );
        $form->setType('param1', PARAM_TEXT);
        $form->addHelpButton('param1', 'param1sqlqueryhelp', 'profilefield_verydynamicautocomplete');
        // Default data.
        $form->addElement('text', 'defaultdata', get_string('profiledefaultdata', 'admin'), 'size="50"');
        $form->setType('defaultdata', PARAM_TEXT);

        // param2 for csv upload convert
        $form->addElement(
            'textarea', 'param2', get_string('param2sqlqueryconvert', 'profilefield_verydynamicautocomplete'),
            array('rows' => 6, 'cols' => 40)
        );
        $form->setType('param2', PARAM_TEXT);
        $form->addHelpButton('param2', 'param2sqlqueryconverthelp', 'profilefield_verydynamicautocomplete');

        // Let's see if the user can modify the sql.
        $context = context_system::instance();
        $hascap = has_capability('profilefield/verydynamicautocomplete:caneditsql', $context);

        if (!$hascap) {
            $form->hardFreeze('param1');
            $form->hardFreeze('defaultdata');
            $form->hardFreeze('param2');
        }
        $form->addElement('text', 'sql_count_data', get_string('numbersqlvalues', 'profilefield_verydynamicautocomplete'));
        $form->setType('sql_count_data', PARAM_RAW);
        $form->hardFreeze('sql_count_data');
        $form->addElement(
            'textarea', 'sql_sample_data', get_string('samplesqlvalues', 'profilefield_verydynamicautocomplete'),
            array('rows' => 6, 'cols' => 40)
        );
        $form->setType('sql_sample_data', PARAM_RAW);
        $form->hardFreeze('sql_sample_data');
    }

    /**
     * Alter form based on submitted or existing data
     *
     * @param moodleform $mform
     */
    public function define_after_data(&$form) {
        global $DB,$USER;
        try {
            $sql = $form->getElementValue('param1');

            if ($sql) {
                $wants_fullname = verydynamicautocomplete_profilefield_fix_sql($sql,$USER);
                $rs = $DB->get_records_sql($sql);
                $i = 0;
                $defsample = '';
                $countdata = count($rs);
                foreach ($rs as $record) {
                    if ($i == 12) {
                        break;
                    }
                    if($wants_fullname){
                        $record->data = fullname($record);
                    }
                    if (isset($record->data)) {
                        if (strlen($record->data) > 40) {
                            $sampleval = substr(format_string($record->data), 0, 36).'...';
                        } else {
                            $sampleval = format_string($record->data);
                        }
                        $defsample .= 'data: '.$sampleval."\n";
                    }
                }
                $form->setDefault('sql_count_data', $countdata);
                $form->setDefault('sql_sample_data', $defsample);
            } else {
                $form->setDefault('sql_count_data', 0);
                $form->setDefault('sql_sample_data', '');
            }
        } catch (Exception $e) {
            // We don't have to do anything here, since the error shall be handled by define_validate_specific.
            $form->setDefault('sql_count_data', 0);
            $form->setDefault('sql_sample_data', '');
        }
    }

    /**
     * Validates data for the profile field.
     *
     * @param  array $data
     * @param  array $files
     * @return array
     */
    public function define_validate_specific($data, $files) {
        $err = array();

        $data->param1 = str_replace("\r", '', $data->param1);
        // Le'ts try to execute the query.
        $sql = $data->param1;
        global $DB,$USER;
        try {
            if(verydynamicautocomplete_profilefield_fix_sql($sql,$USER)){
                $rs = $DB->get_records_sql($sql);
                foreach($rs as $record){
                    $record->data = fullname($record);
                }
            } else {
                $rs = $DB->get_records_sql($sql);
            }
            if ($rs === False) {
                $err['param1'] = get_string('queryerrorfalse', 'profilefield_verydynamicautocomplete');
            } else {
                if (count($rs) == 0) {
                    //$err['param1'] = get_string('queryerrorempty', 'profilefield_verydynamicautocomplete');
                } else {
                    $firstval = reset($rs);
                    if (!object_property_exists($firstval, 'data')) {
                        $err['param1'] = get_string('queryerrordatamissing', 'profilefield_verydynamicautocomplete');
                    } else if (!empty($data->defaultdata) && !isset($rs[$data->defaultdata])) {
                        // Def missing.
                        $err['defaultdata'] = get_string('queryerrordefaultmissing', 'profilefield_verydynamicautocomplete');
                    }
                }
            }
        } catch (Exception $e) {
            $err['param1'] = get_string('sqlerror', 'profilefield_verydynamicautocomplete') . ': ' .$e->getMessage();
        }
        return $err;
    }

    /**
     * Processes data before it is saved.
     *
     * @param  array|stdClass $data
     * @return array|stdClass
     */
    public function define_save_preprocess($data) {
        $data->param1 = str_replace("\r", '', $data->param1);
        return $data;
    }

}

