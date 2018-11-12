<?php
/*
Plugin Name: Embed iSpring
Plugin URI: http://theblackchimp.com
description: Plugin to embed iSpring software generated HTML5 PPT's
Version: 1.0
Author: Harsh
Author URI: http://theblackchimp.com
License: GPL2
*/

defined('ABSPATH') or die('No script kiddies please!');
register_activation_hook(__FILE__, 'tbc_ispring_on_EI_activate');
function tbc_ispring_on_EI_activate()
{
    global $wpdb;
    $create_table_query = "
        CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}ispring_data` (
          `id` bigint(20) NOT NULL AUTO_INCREMENT,
          `file_name` varchar(100) NOT NULL,
          `file_path` text NOT NULL,
           PRIMARY KEY  (id)
        );";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($create_table_query);
}

function tbc_ispring_embedder_settings_page()
{
    add_menu_page('Upload iSpring PPT', 'iSpring Embedder', 'manage_options', 'ispring-embedder', 'tbc_ispring_option_page_content');
}

add_action('admin_menu', 'tbc_ispring_embedder_settings_page');
function tbc_ispring_option_page_content()
{
    ?>
    <style>
        table {
            border-collapse: collapse;
            width: 90%;
            text-align: center;
        }

        th, td {
            text-align: left;
            padding: 8px;
        }

        tr:nth-child(even) {
            background-color: #fff
        }

        th {
            background-color: #4dcadd;
            color: white;
        }

        .ispring_form div {
            padding-top: 10px;
        }

        .notice-error, .notice-updated {
            margin-left: 0px !important;
        }
    </style>
    <div>
        <h2>Upload iSpring converted file </h2>
        <?php
        $notices = tbc_ispring_save_user_data();
        tbc_ispring_display_Notices($notices);
        ?>
        <form class="ispring_form" method="post" enctype="multipart/form-data">
            <div>
                <span><input type="text" id="file_title_name" required="" name="file_name" value=""
                             placeholder="Title"/></span>
            </div>
            <div>
                <span><input type="file" id="ispring_file" required="" name="zip_file" value=""/></span>
            </div>
            </table>
            <?php
            submit_button('Upload File', 'primary', 'submit_ispring_form');
            ?>
        </form>
    </div>
    <?php
    tbc_ispring_display_uploaded_files_list();
}

function tbc_ispring_save_user_data()
{
    if (!isset($_POST['submit_ispring_form']) && !empty($_GET['delete_ppt'])) {
        if (is_numeric($_GET['delete_ppt'])) {

            $notices = tbc_ispring_delete_ppt_by_id($_GET['delete_ppt']);
            return $notices;
        }
        return;
    }
    if (isset($_POST['submit_ispring_form']) && !empty($_POST['submit_ispring_form'])) {
        $error = new WP_Error();
        if ($_FILES["zip_file"]["name"]) {
            $filename = sanitize_file_name($_FILES["zip_file"]["name"]);
            $source = $_FILES["zip_file"]["tmp_name"];
            $type = sanitize_mime_type($_FILES["zip_file"]["type"]);
            $title = $_POST['file_name'];
            $name = explode(".", $filename);
            $accepted_types = array(
                'application/zip',
                'application/x-zip-compressed',
                'multipart/x-zip',
                'application/x-compressed'
            );
            if (empty($title)) {
                $error->add('error', 'Title Field cannot be empty.');
                return $error;
            }

            foreach ($accepted_types as $mime_type) {
                if ($mime_type == $type) {
                    $type_okay = true;
                    break;
                }
            }

            if (!$type_okay) {
                $error->add('error', 'The file you are trying to upload is not a .zip file type. Please try again.');
                return $error;
            }

            $continue = strtolower($name[1]) == 'zip' ? true : false;
            if (!$continue) {
                $error->add('error', 'The file you are trying to upload is not a .zip file. Please try again.');
                return $error;
            }
            $uploads = wp_upload_dir();
            $target_path = $uploads['basedir'] . "/iSpring_embedder";
            if (!is_dir($target_path))
                wp_mkdir_p($target_path);
            $target_file = $target_path . '/' . $filename;
            if (move_uploaded_file($source, $target_file)) {
                $zip = new ZipArchive;
                if ($zip->open($target_file) == TRUE) {
                    $ext_folder = $ext_folder = explode("/",$zip->getNameIndex(0))[0];
                    $zip->extractTo($target_path);
                    $zip->close();
                    unlink($target_file);
                }
                $folder_name = '/ppt_' . time();
                $folderUpdated = rename($target_path . '/' . $ext_folder, $target_path . $folder_name);
                if ($folderUpdated) {
                    $path = 'iSpring_embedder' . $folder_name;
                    tbc_ispring_save_data_to_db($title, $path);
                    $error->add('success', 'File Uploaded Successfully.');
                    return $error;
                }
            } else {
                $error->add('error', 'There was a with upload. Please try again.');
                return $error;
            }
        }
    }
}

function tbc_ispring_save_data_to_db($title, $path)
{
    global $wpdb;
    $title = sanitize_text_field($title);
    $id = $wpdb->insert($wpdb->prefix . 'ispring_data', array(
        'file_name' => $title,
        'file_path' => $path
    ), array(
        '%s',
        '%s'
    ));
    return $id;
}

function tbc_ispring_display_uploaded_files_list()
{
    $data = tbc_ispring_get_ppt_data();
    if (empty($data)) {
        echo "No Files to display. Please Upload files to display here";
    } else {
        echo "<table><tr><th>Sr. No.</th><th>Name</th><th>Shortcode</th><th>Action</th></tr>";
        $i = 1;
        foreach ($data as $file):
            $onClickJs = 'return confirm("Are you sure you want to delete?")';
            ?>
            <tr>
                <td><?php echo $i; ?></td>
                <td><?php echo esc_html($file->file_name); ?></td>
                <td>[ppt_embedder id="<?php echo $file->id; ?>"]</td>
                <td><a href=" <?php menu_page_url('ispring-embedder'); ?>&delete_ppt=<?php echo esc_html($file->id); ?>"
                       Onclick="<?php echo esc_js($onClickJs); ?>">Delete</a></td>
            </tr>
            <?php
            $i++;
        endforeach;
        ?>
        </table>
        </br>
        <div>
            <p>You can set dimensions of the <i>iframe</i> by adding width and height parameters to shortcode. </p>
            <p>For Example : <strong>[ppt_embedder id='1' width='800px' height='300px']</strong></p>
        </div>
        <?php
    }
}

add_shortcode('ppt_embedder', 'tbc_ispring_process_ppt_embedder_shortcode');
function tbc_ispring_process_ppt_embedder_shortcode($atts)
{
    $atts = shortcode_atts(array(
        'id' => '',
        'width' => '100%',
        'height' => '555px'
    ), $atts);
    $uploads = wp_upload_dir();
    if (!empty($atts['id']) && is_numeric($atts['id'])) {
        $data = tbc_ispring_get_ppt_data($atts['id']);
        if (count($data) >= 1) {
            return '<iframe style="width:' . esc_attr($atts['width']) . '; height:' . esc_attr($atts['height']) . '"; src="' . $uploads['baseurl'] . '/' . $data[0]->file_path . '/index.html"> </iframe>';
        } else {
            return "Oops !! the files have been deleted.";
        }
    }
}

function tbc_ispring_get_ppt_data($id = '')
{
    global $wpdb;
    $id = esc_sql($id);
    $query = 'SELECT * FROM ' . $wpdb->prefix . 'ispring_data';

    if (!empty($id))
        $query = 'SELECT * FROM ' . $wpdb->prefix . 'ispring_data where id=' . $id;
    $data = $wpdb->get_results($query);
    return $data;
}

function tbc_ispring_display_Notices($error)
{
    if (is_wp_error($error)) {
        $class = 'notice notice-error';
        if (isset($error->errors['success']))
            $class = 'updated notice-updated';
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($error->get_error_message()));
    }
}

function tbc_ispring_delete_ppt_by_id($id = '')
{
    if (isset($id)) {
        $id = esc_sql($id);
        $data = tbc_ispring_get_ppt_data($id);
        if (count($data) >= 1) {
            global $wpdb;
            $deleted = $wpdb->delete($wpdb->prefix . 'ispring_data', array(
                'id' => $id
            ));
            if ($deleted) {
                $basedir = wp_upload_dir();
                $path = $basedir['basedir'] . '/' . $data[0]->file_path;
                tbc_ispring_removeDirectory($path);
                $notice = new WP_Error('success', 'Deleted file Successfully.');
                return $notice;
            }
        }
    }
}

function tbc_ispring_removeDirectory($path)
{

    $files = glob($path . '/*');
    foreach ($files as $file) {
        is_dir($file) ? tbc_ispring_removeDirectory($file) : unlink($file);
    }
    rmdir($path);
    return;
}

?>