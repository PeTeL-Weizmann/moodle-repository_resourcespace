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
 * Version details
 *
 * @package    repository_resourcespace
 * @copyright  2018 Anders Jørgensen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class repository_resourcespace extends repository {

    public function __construct($repositoryid, $context, array $options, $readonly) {
        parent::__construct($repositoryid, $context, $options, $readonly);
        $this->config = get_config('resourcespace');
        $this->resourcespace_api_url = get_config('resourcespace', 'resourcespace_api_url');
        $this->api_key = get_config('resourcespace', 'api_key');
        $this->api_user = get_config('resourcespace', 'api_user');
        $this->enable_help = get_config('resourcespace', 'enable_help');
        $this->enable_help_url = get_config('resourcespace', 'enable_help_url');
    }

    public function get_listing($path = '', $page = '') {
        $listArray = array();
        $listArray['list'] = array();
        $listArray['norefresh'] = true;
        $listArray['nologin'] = true;
        if ($this->enable_help == 1) {
            $listArray['help'] = "$this->enable_help_url";
        }
        return $listArray;
    }

    public function print_search() {
        $search = '<input class="form-control" id="reposearch" name="s" placeholder="Search" type="search">';
        return $search;
    }

    public function search($search_text, $page = 0) {
        $search_text = optional_param('s', '*', PARAM_TEXT);

        $search_text = urlencode($search_text);

        // Resourcespace search string.
        $query= "user=" . "$this->api_user" . "&function=search_get_previews&param1=$search_text"
        . "&param2=&param3=&param4=&param5=-1&param6=desc&param7=&param8=thm,scr&param9=";

        // Sign the request with the private key.
        $sign = hash("sha256", $this->api_key . $query);

        // Send request to server.
        $response = (file_get_contents("$this->resourcespace_api_url" . $query . "&sign=" . $sign));

        $jsonArray = json_decode($response);

        $listArray = array();

        // Working around a minor resourcespace bug, where resourcespace returns an error
        // when no files match the search. Afterwards the response is parsed.
        if (is_array($jsonArray)) {
            foreach ($jsonArray as $value) {
                $ref = $value->ref;
                $id = $value->field8;
                $thumbnail = $value->url_thm;
                $src = $value->url_scr;
                $srcExtension = $value->file_extension;
                $modifyDate = $value->file_modified;
                $modifyDate = strtotime($modifyDate);
                // Parsing the resourcespace ref and file extension as the filesource, because the
                // resourcespace api does not return the actual source at this point.
                $list[] = array('title' => "$id",
                                'thumbnail' => $thumbnail,
                                'source' => "$ref,$srcExtension",
                                'datemodified' => "$modifyDate",
                                'author' => 'IA Sprog');
            }
            $listArray['list'] = $list;
        } else {
            $listArray['list'] = array();
        }
        $listArray['norefresh'] = true;
        $listArray['nologin'] = true;
        if ($this->enable_help == 1) {
            $listArray['help'] = "$this->enable_help_url";
        }
        $listArray['issearchresult'] = true;
        return $listArray;
    }

    public function get_file($url, $filename = '') {
        // We have to catch the url, and make an additional request to the resourcespace api,
        // to get the actual filesource.
        $fileInfo = explode(',', $url);
        $subQuery = "user=" . "$this->api_user" . "&function=get_resource_path&param1=" . "$fileInfo[0]" ."&param2&param3=&param4=&param5=" . "$fileInfo[1]" . "&param6=&param7=&param8=";
        $sign = hash("sha256", $this->api_key . $subQuery);
        $fileSource = (file_get_contents("$this->resourcespace_api_url" . $subQuery . "&sign=" . $sign));
        $fileSource = json_decode($fileSource);
        $url = $fileSource;
        $path = $this->prepare_file($filename);
        $c = new curl;
        $result = $c->download_one($url, null, array('filepath' => $path, 'timeout' => self::GETFILE_TIMEOUT));
        if ($result !== true) {
            throw new moodle_exception('errorwhiledownload', 'repository', '', $result);
        }
        return array('path'=>$path, 'url'=>$url);
    }

    public function supported_filetypes() {
        return '*';
    }

    public function global_search() {
        return false;
    }

    public function supported_returntypes() {
        return FILE_INTERNAL;
    }

    public static function get_type_option_names() {
        return array_merge(parent::get_type_option_names(), array('resourcespace_api_url', 'api_user', 'api_key', 'enable_help', 'enable_help_url'));
    }

    public static function type_config_form($mform, $classname = 'repository') {
        parent::type_config_form($mform);

        $mform->addElement('html', '<hr>');
        $mform->addElement('html', '<h2>Server settings</h2>');
        $mform->addElement('text', 'resourcespace_api_url', get_string('resourcespace_api_url', 'repository_resourcespace'));
        $mform->setType('resourcespace_api_url', PARAM_RAW_TRIMMED);
        $mform->addRule('resourcespace_api_url', 'required', 'required', null, 'client');
        $mform->addElement('static', null, '', get_string('resourcespace_api_url_help', 'repository_resourcespace'));

        $mform->addElement('text', 'api_user', get_string('api_user', 'repository_resourcespace'));
        $mform->setType('api_user', PARAM_RAW_TRIMMED);
        $mform->addRule('api_user', '', 'required', null, 'client');
        $mform->addElement('static', null, '', get_string('api_user_help', 'repository_resourcespace'));

        $mform->addElement('password', 'api_key', get_string('api_key', 'repository_resourcespace'));
        $mform->setType('api_key', PARAM_RAW_TRIMMED);
        $mform->addRule('api_key', '', 'required', null, 'client');
        $mform->addElement('static', null, '', get_string('api_key_help', 'repository_resourcespace'));

        $mform->addElement('html', '<hr>');
        $mform->addElement('html', '<h2>Miscellaneous settings</h2>');

        $mform->addElement('checkbox', 'enable_help', get_string('enable_help', 'repository_resourcespace'));
        $mform->addElement('static', null, '', get_string('enable_help_help', 'repository_resourcespace'));

        $mform->addElement('text', 'enable_help_url', get_string('enable_help_url', 'repository_resourcespace'));
        $mform->addElement('static', null, '', get_string('enable_help_url_help', 'repository_resourcespace'));
    }
}
