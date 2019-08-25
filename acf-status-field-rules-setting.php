<?php 

/* 
		Plugin Name: ACF Status Field Rules Setting
		Plugin URI: https://wordpress.org/plugins/acf-status-field-rules-setting/
		Description: Set status type that should be allowed to show fields
		Version: 1.0
		Author: Sam Cohen
		Author URI: https://github.com/samjco/
		License: GPL
	*/

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

new acf_status_field_rules_setting();

class acf_status_field_rules_setting {

    private $choices = array();
    private $current_post_status = array();
    private $exclude_field_types = array('tab' => 'tab', 'clone' => 'clone');
    private $removed = array();

    public function __construct() {

    // add_action('init', function (){

	// 	if (count($this->choices)) {
	// 	return;
	// 	}


	// 	global $wp_post_statuses;

	// 	// $wp_post_statuses = get_post_stati();
	// 	// echo "<pre>";
	// 	// print_r($wp_post_statuses);
	// 	// echo "</pre>";


	// 	$choices = array('all' => 'All');

	// 	foreach($wp_post_statuses as $status) {

	// 	$choices[$status->name] = $status->label." <span class='acf-status-rules' style='font-size: 10px; text-align:center;padding: 0px 6px;border: 1px solid #ddd;background: #eee;display:block; width:130px; margin-bottom:12px;'>". $status->name."</span>" ;
	// 	}
	// 	$this->choices = $choices;

	// }, PHP_INT_MAX);

        add_action('init', array($this, 'init'), PHP_INT_MAX);
        add_action('acf/init', array($this, 'add_actions'));
        add_action('acf/save_post', array($this, 'save_post'), - 1);
        add_action('after_setup_theme', array($this, 'after_setup_theme'));
        //add_filter('acf/get_field_types', array($this, 'add_actions'), 20, 1);
    } // end public function __construct

    public function after_setup_theme() {
        // check the ACF version
        // if >= 5.5.0 use the acf/prepare_field hook to remove fields
        if (!function_exists('acf_get_setting')) {
            // acf is not installed/active
            return;
        }
        $acf_version = acf_get_setting('version');
        if (version_compare($acf_version, '5.5.0', '>=')) {
            add_filter('acf/prepare_field', array($this, 'prepare_field'), 99);
        } else {
            // if < 5.5.0 user the acf/get_fields hook to remove fields
            add_filter('acf/get_fields', array($this, 'get_fields'), 20, 2);
        }
    } // end public function after_setup_theme

    public function prepare_field($field) {
        $return_field = false;

        // global $post;
        // $current_post_status = $post->post_status;

        $exclude = apply_filters('acf/status_rule_setting/exclude_field_types', $this->exclude_field_types);
        if (in_array($field['type'], $exclude)) {
            $return_field = true;
        }
        if (isset($field['status_rules'])) {
            if (!empty($field['status_rules']) && is_array($field['status_rules'])) {
                foreach($field['status_rules'] as $status) {
                    if ($status == 'all' || $status == get_post_status()) {
                        $return_field = true;
                    }
                }
            } else {
                // no status have been selected for this field
                // it will never be displayed, this is probably an error
            }
        } else {
            // status not set for this field
            // this field was created before this plugin was in use
            // or status is otherwise disabled for this field
            $return_field = true;
        }
        //echo '<pre>'; print_r($field); echo '</pre>';
        if ($return_field) {
            return $field;
        }
        // [
        preg_match('/(\[[^\]]+\])$/', $field['name'], $matches);
        $name = $matches[1];
        if (!in_array($name, $this->removed)) {
            $this->removed[] = $name;
            ?>
<input type="hidden" name="acf_removed<?php echo $name; ?>" value="<?php 
echo $field['name']; ?>" /><?php 
}
return false;
} // end public function prepare_field

public function save_post($post_id = false, $values = array()) {
    if (!isset($_POST['acf'])) {
        return;
    }
    $this->exclude_field_types = apply_filters('acf/status_rule_setting/exclude_field_types', $this->exclude_field_types);
    if (is_array($_POST['acf'])) {
        $_POST['acf'] = $this->filter_post_values($_POST['acf']);
    }
    if (isset($_POST['acf_removed'])) {
        $this->get_removed($post_id);
        $_POST['acf'] = $this->array_merge_recursive_distinct($_POST['acf'], $_POST['acf_removed']);
    }
} // end public function save_post

private function get_removed($post_id) {
    foreach($_POST['acf_removed'] as $field_key => $value) {
        $_POST['acf_removed'][$field_key] = get_field($field_key, $post_id, false);
    }
} // end private function get_removed

private function array_merge_recursive_distinct(array & $array1, array & $array2) {
    $merged = $array1;
    foreach($array2 as $key => & $value) {
        if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
            $merged[$key] = $this->array_merge_recursive_distinct($merged[$key], $value);
        } else {
            // do not overwrite value in first array
            if (!isset($merged[$key])) {
                $merged[$key] = $value;
            }
        }
    }
    return $merged;
} // end private function array_merge_recursive_distinct

private function filter_post_values($input) {
    $output = array();
    // global $post;
    // $current_post_status = $post->post_status;
    foreach($input as $index => $value) {
        $keep = true;
        if (substr($index, 0, 6) === 'field_') {
            // check to see if this field can be edited
            $field = get_field_object($index);
            if (in_array($field['type'], $this->exclude_field_types)) {
                $keep = true;
            } else {
                if (isset($field['status_rules'])) {
                    $keep = false;
                    if (!empty($field['status_rules']) && is_array($field['status_rules'])) {

                        foreach($field['status_rules'] as $status) {

                            // var_dump($status);
                            if ($status == 'all' || $status == get_post_status()) {
                                $keep = true;
                                break;
                            }
                        } // end foreach
                    } // end if settings is array
                } // end if setting exists
            } // end if excluded field type else
        } // end if field_
        if ($keep) {
            if (is_array($value)) {
                // recurse nested array
                $output[$index] = $this->filter_post_values($value);
            } else {
                $output[$index] = $value;
            }
        } // end if keep
    } // end foreach input
    return $output;
} // end private function filter_post_values

public function init() {
    $this->get_statuses();
    // $this->current_status();
} // end public function init

public function add_actions() {
    $exclude = apply_filters('acf/status_rule_setting/exclude_field_types', $this->exclude_field_types);
    if (!function_exists('acf_get_setting')) {
        return;
    }
    $acf_version = acf_get_setting('version');
    $sections = acf_get_field_types();
    if ((version_compare($acf_version, '5.5.0', '<') || version_compare($acf_version, '5.6.0', '>=')) && version_compare($acf_version, '5.7.0', '<')) {
        foreach($sections as $section) {
            foreach($section as $type => $label) {
                if (!isset($exclude[$type])) {
                    add_action('acf/render_field_settings/type='.$type, array($this, 'render_field_settings'), 1);
                }
            }
        }
    } else {
        // >= 5.5.0 || < 5.6.0
        foreach($sections as $type => $settings) {
            if (!isset($exclude[$type])) {
                add_action('acf/render_field_settings/type='.$type, array($this, 'render_field_settings'), 1);
            }
        }
    }
} // end public function add_actions

// private function current_status() {
//     // $id = get_the_ID();
//     global $post;
//     // $id = $post->ID;

//     //$wp_post = get_post(660);
//     // $current_post_status = get_post_status($id);
//     $current_post_status = $post->post_status;
//     //$current_post_status = $wp_post->post_status;

//     //var_dump($current_post_status);

//     //if (is_object($current_post_status) && isset($current_post_status)) {
//     $this->current_post_status = $current_post_status;
//     //}

// } // end private function current_post_status_roles

private function get_statuses() {

		if (count($this->choices)) {
		return;
		}

		global $wp_post_statuses;

		$choices = array('all' => 'All');

		foreach($wp_post_statuses as $status):

			$choices[$status->name] = $status->label." <span class='acf-status-rules' style='font-size: 10px; text-align:center;padding: 0px 6px;border: 1px solid #ddd;background: #eee;display:block; width:130px; margin-bottom:12px;'>". $status->name."</span>" ;
		
		endforeach;

		$this->choices = $choices;


} // end private function get_statuses

public function get_fields($fields, $parent) {
    global $post;
    if (is_object($post) && isset($post->ID) && (get_post_type($post->ID) == 'acf-field-group') || (get_post_type($post->ID) == 'acf-field')) {
        // do not alter when editing field or field group
        return $fields;
    }
    $this->exclude_field_types = apply_filters('acf/status_rule_setting/exclude_field_types', $this->exclude_field_types);
    $fields = $this->check_fields($fields);
    return $fields;
} // end public function get_fields

private function check_fields($fields) {
    // recursive function
    // see if field should be kept
    $keep_fields = array();

    if (is_array($fields) && count($fields)) {
        foreach($fields as $field) {
            $keep = false;
            if (in_array($field['type'], $this->exclude_field_types)) {
                $keep = true;
            } else {
                if (isset($field['status_rules'])) {
                    if (!empty($field['status_rules']) && is_array($field['status_rules'])) {


                        foreach($field['status_rules'] as $status) {
                            if ($status == 'all' || $status == get_post_status()) {
                                $keep = true;
                                // already keeping, no point in continuing to check
                                break;
                            }
                        }
                    }
                } else {
                    // field setting is not set
                    // this field was created before this plugin was in use
                    // or this field is not effected, it could be a "layout"
                    // there is currently no way to add field settings to
                    // layouts in ACF
                    // assume 'all'
                    $keep = true;
                }
            } // end if excluded type else
            if ($keep) {
                $sub_fields = false;
                if (isset($field['layouts'])) {
                    $sub_fields = 'layouts';
                }
                if (isset($field['sub_fields'])) {
                    $sub_fields = 'sub_fields';
                }
                if ($sub_fields) {
                    // rucurse sub fields
                    $field[$sub_fields] = $this->check_fields($field[$sub_fields]);
                }
                $keep_fields[] = $field;
            }
        } // end foreach field
    } else {
        return $fields;
    }
    return $keep_fields;
} // end private function check_fields

public function render_field_settings($field) {
    $args = array('type' => 'checkbox', 'label' => 'Status Rules', 'name' => 'status_rules', 'instructions' => 'Select the Status that are allowed to view and edit this field.'.' This field will be removed for any status not selected.', 'required' => 0, 'default_value' => array('all'), 'choices' => $this->choices, 'layout' => 'horizontal');
    acf_render_field_setting($field, $args, false);

} // end public function render_field_settings

} // end class acf_user_type_field_settings

?>