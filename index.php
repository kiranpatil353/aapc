<?php
/* Plugin Name: Auto Assign Post Category 
 * Description: Auto assign post category based on tags mapped by user in plugin setting. Single tag can be mapped to multiple categories. 
 * Version: 1.0
 * Author: kiranpatil353, clarionwpdeveloper
 * License: GPLv2
 */
// loading js files 
function auto_apc_load_scripts() {
    wp_enqueue_script('slider-validation-js', plugins_url('js/validation.js', __FILE__));
}
add_action('admin_init', 'auto_apc_load_scripts');
// If user has submitted forms 
if (isset($_POST) && !empty($_POST)) {
    auto_apc_post_tag_form();
}
//custom PHP function
function auto_apc_in_arrayi($needle, $haystack)
{
	return in_array(strtolower($needle), array_map('strtolower', $haystack));
}

function auto_apc_post_tag_form() {
	// extract to variables
    extract($_POST);
	
    if (isset($cat)) {
		
        $serialized_Array = serialize($cat);
        if (!isset($wpdb))
            $wpdb = $GLOBALS['wpdb'];
        $wpdb->insert($wpdb->prefix . 'tag_category_mapping', array('tag_name' => sanitize_text_field($tag_name), 'category_list' => sanitize_text_field($serialized_Array)), array('%s', '%s'));
    }
	// only if numeric values 
    if (isset($_REQUEST['deleteval']) && is_numeric($_REQUEST['deleteval'])) {
        $id = $_REQUEST['deleteval'];
        if (!isset($wpdb))
            $wpdb = $GLOBALS['wpdb'];
        $auto_apc_table_name = $wpdb->prefix . 'tag_category_mapping';
        $wpdb->query("DELETE FROM $auto_apc_table_name WHERE ID = $id ");
    }
}

function auto_apc_add_category($post_id = 0) {
    if (!$post_id)
        return;
    if (!isset($wpdb))
        $wpdb = $GLOBALS['wpdb'];
    $all_tags = $wpdb->get_results("SELECT id, tag_name, category_list FROM " . $wpdb->prefix . "tag_category_mapping");

    $catArray = array();
    $finalArray = array();
    $post_tags = wp_get_post_tags($post_id, array('fields' => 'names'));
	
    foreach ($all_tags as $tag) {
        if ($tag->tag_name && auto_apc_in_arrayi($tag->tag_name, $post_tags)) {
            $catArray = unserialize($tag->category_list);
            $finalArray = array_merge($finalArray, $catArray);
            wp_set_post_categories($post_id, $finalArray, $append = false);
        }
    }
}

add_action('publish_post', 'auto_apc_add_category');

//

function auto_apc_admin_menu() {

    add_menu_page('Auto Category Tag', 'Auto Category Tag', 'manage_options', 'category-mapping', 'auto_apc_menu_plugin_options');
}

//
add_action('admin_menu', 'auto_apc_admin_menu');

//

function auto_apc_add_submenu_page() {
    add_submenu_page(
            'category-mapping', 'Assign New', 'Assign New', 'manage_options', 'mapnew_categories', 'auto_apc_add_options_function'
    );
}

add_action('admin_menu', 'auto_apc_add_submenu_page');

function auto_apc_add_options_function() {
    ?>
    <div class="wrap">
        <h2><?php echo esc_html('Assign Tag to Categories '); ?></h2>
        <form method="post" name="tag_form" id="tag_form" action="" >
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php echo esc_html('Enter Tag Name'); ?></th>
                    <td><input required type="text" name="tag_name" id="tag_name" class="" value="" /></td>
                </tr>	
                <tr valign="top">
                    <th scope="row"><?php echo esc_html('Map Available Categories:'); ?></th>
                    <td>
                        <?php
                        $select_cats = wp_dropdown_categories(array('echo' => 0, 'hide_empty' => 0));

                        $select_cats = str_replace('id=', 'multiple="multiple" required id=', $select_cats);
                        $select_cats = preg_replace('/\bcat\b/', 'cat[]', $select_cats);
                        echo $select_cats;
                        ?>
                    </td>
                </tr>

            </table>

    <?php submit_button(); ?>

        </form>

    </div>
    <?php
}
// display tag list 
function auto_apc_menu_plugin_options() {
    $cat_string = '';
    if (!isset($wpdb))
        $wpdb = $GLOBALS['wpdb'];
// Validate user role/permissions
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html('Auto Assign Post Category '); ?>
            <a class="page-title-action" href="<?php echo admin_url(); ?>admin.php?page=mapnew_categories"><?php echo esc_html('Assign New'); ?></a>
        </h1>
    </div>
    <table class="wp-list-table widefat fixed striped pages">
        <thead>
            <tr >
                <th class="manage-column column-author" id="author" scope="col"><?php echo esc_html('Tag Name'); ?></th>
                <th class="manage-column column-author" id="author" scope="col"><?php echo esc_html('Categories'); ?></th>
                <th class="manage-column column-author" id="author" scope="col"><?php echo esc_html('Action'); ?></th>
            </tr>
        </thead> 
            <?php
            $all_tags = $wpdb->get_results("SELECT id, tag_name, category_list FROM " . $wpdb->prefix . "tag_category_mapping");

            foreach ($all_tags as $tag) {
                $cat_string = '';
                ?>
            <tr class="row-title">
                <th><?php  echo esc_html($tag->tag_name); ?></th>
                    <?php $categories = unserialize($tag->category_list);
                    ?>
                <th>	
                    <?php
                    if (isset($categories)) {
                        foreach ($categories as $only_cat) {
                            $cat_string .= get_cat_name($only_cat) . ", ";
                        }
                         echo esc_html(rtrim($cat_string, ' , '));
                    }
                    ?>
                </th>
                <th>
            <form action="" id="delfrm<?php echo $tag->id; ?>" name="delfrm<?php echo $tag->id; ?>" method="post">
                <a href="javascript:;"onclick="javascript:confirm('Do you really want to delete') ? validate(event, <?php echo $tag->id; ?>) : 0"  /><?php echo esc_html('Delete'); ?> </a>
                <input type="hidden" name="deleteval" id="deleteval" value="<?php echo esc_html($tag->id); ?>" />
			</form>
        </th>

        <tr>
    <?php }
    ?>

    </tr>

    <tbody id="the-list">

    </tbody>
    </table>
    <?php
}

/* Plugin Activation Hook
 * 
 */
					
function auto_apc_plugin_options_install() {
    if (!isset($wpdb))
    $wpdb = $GLOBALS['wpdb'];
    $auto_apc_table_name = $wpdb->prefix . 'tag_category_mapping';

    if ($wpdb->get_var("show tables like '$auto_apc_table_name'") != $auto_apc_table_name) {
        $sql = "CREATE TABLE " . $auto_apc_table_name . " (
		id INT NOT NULL AUTO_INCREMENT,
		tag_name TEXT NOT NULL,
		category_list TEXT NOT NULL,
		PRIMARY KEY (id)
		);";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

register_activation_hook(__FILE__, 'auto_apc_plugin_options_install');

// Plugin deactivation hook
function auto_apc_hook_deactivate() {
    if (!isset($wpdb))
    $wpdb = $GLOBALS['wpdb'];
    $auto_apc_table_name = $wpdb->prefix . 'tag_category_mapping';
    $wpdb->query("DROP TABLE IF EXISTS $auto_apc_table_name");
}

register_deactivation_hook(__FILE__, 'auto_apc_hook_deactivate');

?>