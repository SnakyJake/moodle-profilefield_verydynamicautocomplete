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
 * Dynamic menu profile field definition.
 *
 * @package    profilefield_verydynamicmenu
 * @copyright  2016 onwards Antonello Moro {@link http://treagles.it}, 2022 Jakob Heinemann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 require_once($CFG->dirroot."/user/profile/field/verydynamicautocomplete/locallib.php");

/**
 * Class profile_field_verydynamicautocomplete
 *
 * @copyright  2016 onwards Antonello Moro {@link http://treagles.it}, 2022 Jakob Heinemann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_field_verydynamicautocomplete extends profile_field_base {
    /** @var array $autocomplete */
    public $autocomplete;

    /** @var  array @calls array indexed by @fieldid-$userid. It keeps track of recordset,
     * so that we don't do the query twice for the same field */
    private static $acalls = array();
    /**
     * Constructor method.
     *
     * Pulls out the autocomplete for the menu from the database and sets the the corresponding key for the data if it exists.
     *
     * @param int $fieldid
     * @param int $userid
     */
    public function __construct($fieldid = 0, $userid = 0, $fielddata = null){
        // First call parent constructor.
        parent::__construct($fieldid, $userid, $fielddata);
        // Only if we actually need data.
        if ($fieldid !== 0 && $userid !== 0) {
            $mykey = $fieldid.','.$userid; // It will always work because they are number, so no chance of ambiguity.
            if (array_key_exists($mykey , self::$acalls)) {
                $rs = self::$acalls[$mykey];
            } else {
                $sql = $this->field->param1;

                global $DB;
                if(verydynamicautocomplete_profilefield_fix_sql($sql, \core_user::get_user($userid))){
                    $rs = $DB->get_records_sql($sql);
                    foreach($rs as $record){
                        $record->data = fullname($record);
                    }
                } else {
                    $rs = $DB->get_records_sql($sql);
                }

                self::$acalls[$mykey] = $rs;
            }
            $this->autocomplete = array();
            /*
            if ($this->field->required) {
                $this->autocomplete[''] = get_string('choose').'...';
            }
            */
            foreach ($rs as $option) {
                $data = json_decode($option->data);
                if(is_array($data)){
                    foreach($data as $value){
                        if(!empty($value)){
                            $this->autocomplete[$value] = $value;
                        }
                    }
                } else {
                    if(!empty($data)){
                        $this->autocomplete[$data] = $data;
                    }
                }
            }
        }
    }

    /**
     * Sets user id and user data for the field
     *
     * @param mixed $data
     * @param int $dataformat
     */
    /*
    public function set_user_data($data, $dataformat) {
        $this->data = json_decode($data);
        $this->dataformat = $dataformat;
    }
    */

    /**
     * Old syntax of class constructor. Deprecated in PHP7.
     *
     * @deprecated since Moodle 3.1
     */
    public function profile_field_verydynamicautocomplete($fieldid=0, $userid=0,$fielddata = null) {
        self::__construct($fieldid, $userid,$fielddata);
    }

    /**
     * Create the code snippet for this field instance
     * Overwrites the base class method
     * @param MoodleQuickForm $mform instance of the moodleform class
     */
    public function edit_field_add($mform) {
        global $USER;
        $haswritecap = is_siteadmin();
        if(!$haswritecap){
            $writable_roles = explode(",",$this->field->param2);
            $userroles = get_user_roles(context_system::instance(),$USER->id);
            foreach($userroles as $role){
                if(in_array($role->roleid,$writable_roles)){
                    $haswritecap = true;
                    break;
                }
            }
        }
        $options = [
            'tags' => $haswritecap,
            'multiple' => true,
        ];
        $mform->addElement('autocomplete', $this->inputname, format_string($this->field->name), $this->autocomplete, $options);
    }

    /**
     * The data from the form returns the key. This should be converted to the
     * respective option string to be saved in database
     * Overwrites base class accessor method.
     *
     * @param mixed    $data       - the key returned from the select input in the form
     * @param stdClass $datarecord The object that will be used to save the record
     */
    public function edit_save_data_preprocess($data, $datarecord)
    {
        $data = array_map("strval",array_values(array_filter($data)));
        if(empty($data)){
            return "";
        } else {
            sort($data);
            return json_encode($data,JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * When passing the user object to the form class for the edit profile page
     * we should load the key for the saved data
     * Overwrites the base class method.
     *
     * @param   object   user object
     */
    public function edit_load_user_data($user)
    {
        if(!empty($this->data)){
            $result = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            if(end($result)["function"] == "download_data"){
                $user->{$this->inputname} = implode("\n",json_decode($this->data));
                return;
            }
        }
        $user->{$this->inputname} = is_string($this->data)?json_decode($this->data):$this->data;
    }

    /**
     * HardFreeze the field if locked.
     * @param MoodleQuickForm $mform instance of the moodleform class
     */
    public function edit_field_set_locked($mform) {
        if (!$mform->elementExists($this->inputname)) {
            return;
        }
        if ($this->is_locked() and !has_capability('moodle/user:update', context_system::instance())) {
            $mform->hardFreeze($this->inputname);
            // this line messes all things up
            // $mform->setConstant($this->inputname, format_string($this->data));
        }
    }

    /**
     * Convert external data (csv file) from value to key for processing later by edit_save_data_preprocess
     *
     * @param string $value one of the values in menu options.
     * @return array options key for the menu
     */
    public function convert_external_data($value) {
        if(is_array($value)) {
            return $value;
        } else {
            $data = explode(';', str_replace(["\r\n","\r","\n",'\r\n','\r','\n'], ';', $value));
            $data = array_map('trim', $data);
            return $data;
        }
    }

    /**
     * Display the data for this field.
     */
    public function display_data() {
        if(empty($this->data)){
            return get_string("none");
        }
        $data = json_decode($this->data,true);
        sort($data);

        $string = '';
        foreach($data as $value) {
            $string .= ($string?"<br>":"").$value;
        }
        return $string?format_text($string):get_string("none");
    }
}
