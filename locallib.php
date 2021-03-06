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
// false if no fullname wanted
// true if [fullname] is in sql


function verydynamicautocomplete_profilefield_fix_sql(&$sql,$user){
    global $DB,$USER;
    //on creating a new user, $user will be empty, so set it to $USER
    if(!is_object($user)){
        $user = $USER;
    }
    //profile_get_user_fields_with_data($userid)
    $pfsql = "SELECT shortname,data from {user_info_field} f left join {user_info_data} d on d.userid=:userid and d.fieldid=f.id";
    $profilefields = $DB->get_records_sql_menu($pfsql,array('userid'=>$user->id));

    //@todo: #1 put this on top and check for matches so we dont need the database query above
    preg_match_all('/\[([a-z][a-z0-9_]*)\]/', $sql, $matches);

    $wants_fullname = false;

    foreach($matches[1] as &$field){
        $profile_field_len = strlen("profile_field_");
        if(substr($field,0,$profile_field_len)=="profile_field_"){
            $name = substr($field,$profile_field_len, strlen($field)-$profile_field_len);
            $field = $profilefields[$name];
        } else {
            if($field == "userid"){ //help the sql manager if he uses userid instead of id
                $field = $user->id;
            } elseif($field == "fullname"){
                $wants_fullname = true;
                // before moodle 3.11: 
                // $field = implode(get_all_user_name_fields(),",");
                $field = implode(\core_user\fields::for_name()->get_required_fields(),",");
            } else {
                $field = $user->$field;
            }
        };
    }
    $matches[0] = str_replace(array("[","]"),array("/\[","\]/"),$matches[0]);

    $sql = preg_replace($matches[0],$matches[1],$sql);
    return $wants_fullname;
}