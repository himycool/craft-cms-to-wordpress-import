<?php

require_once('data-migration/DataMigrationHelper.php');

//require_once('data-migration/BlogDataMigrationJson.php');
require_once('data-migration/ResourceDataMigrationJson.php');


add_action('admin_menu', 'hbwp_add_admin_menu');
add_action('admin_init', 'hbwp_settings_init');


function hbwp_add_admin_menu()
{

    add_menu_page('Post Migration to WordPress', 'Post Migration to WordPress', 'manage_options', 'post_to_wordpress', 'hbwp_options_page');
}


function hbwp_settings_init()
{

    register_setting('pluginPage', 'hbwp_settings');

    add_settings_section(
        'hbwp_pluginPage_section',
        __('', 'hbwp'),
        'hbwp_settings_section_callback',
        'pluginPage'
    );

    add_settings_field(
        'hbwp_text_field_0',
        __('Please mention the starting row', 'hbwp'),
        'hbwp_text_field_0_render',
        'pluginPage',
        'hbwp_pluginPage_section'
    );
    add_settings_field(
        'hbwp_text_field_1',
        __('Please enter total number of posts you need to import', 'hbwp'),
        'hbwp_text_field_1_render',
        'pluginPage',
        'hbwp_pluginPage_section'
    );
}


function hbwp_text_field_0_render()
{

    $options = get_option('hbwp_settings');
?>
    <input type='text' id="starting_row" name='hbwp_settings[hbwp_text_field_0]' value='<?php echo isset($options['hbwp_text_field_0']) ? $options['hbwp_text_field_0'] : 0; ?>'>
<?php

}

function hbwp_text_field_1_render()
{

    $options = get_option('hbwp_settings');
?>
    <input type='text' id="total_posts" name='hbwp_settings[hbwp_text_field_1]' value='<?php echo isset($options['hbwp_text_field_1']) ? $options['hbwp_text_field_1'] : 5; ?>'>
<?php

}


function hbwp_settings_section_callback()
{

    //	echo __( 'Please get your API key of your hubspot account from <a href="https://app.hubspot.com/keys/get" target="_blank">API key section</a>', 'hbwp' );

}

function hbwp_import_ajax_request()
{

    // The $_REQUEST contains all the data sent via ajax
    if (isset($_REQUEST)) {

        $offset = $_REQUEST['starting_row'] ? $_REQUEST['starting_row'] : 0;
        $totalposts = $_REQUEST['total_posts'] ? $_REQUEST['total_posts'] : 5;
        
        // $blog_migration =  new BlogDataMigration();
        // $inputFileNameBlog = CBXPHPSPREADSHEET_ROOT_PATH . 'data-migration/json/blog-09-01-2023.json';
        // $message = $blog_migration->importDataFromFile($inputFileNameBlog, $offset, $totalposts);

        $resource_migration =  new ResourceDataMigration();
        $inputFileNameResource = CBXPHPSPREADSHEET_ROOT_PATH . 'data-migration/json/resources-09-01-2023.json';
        $message = $resource_migration->importDataFromFile($inputFileNameResource, $offset, $totalposts);

    }
    echo $message;
    // Always die in functions echoing ajax content
    wp_die();
}

add_action('wp_ajax_hbwp_import_ajax_request', 'hbwp_import_ajax_request');
add_action('wp_ajax_nopriv_hbwp_import_ajax_request', 'hbwp_import_ajax_request');

function hbwp_options_page()
{

?>
    <script>
        jQuery(document).ready(function($) {

            var isREquestEnable = true;
        

            $('#submit').on('click', function(event) {

                if(isREquestEnable) {
                
                $(".results-here").html('Importing please wait... Check the log file for more information').show();

                $.ajax({
                    url: ajaxurl,
                    data: {
                        'action': 'hbwp_import_ajax_request',
                        'starting_row': $("#starting_row").val(),
                        'total_posts': $("#total_posts").val()
                    },
                    beforeSend: function() {
                        isREquestEnable = false;
                    },
                    success: function(data) {
                        $(".results-here").html(data);
                        isREquestEnable = true;
                    },
                    error: function(errorThrown) {
                        console.log(errorThrown);
                        isREquestEnable = true;
                    }
                });

            }
            });
        });
    </script>
    <form action='options.php' method='post' id="hubto_wp">

        <h1>Post Migration to WordPress</h1>
        <?php
        settings_fields('pluginPage');
        do_settings_sections('pluginPage');
        ?>
        <p class="submit"><input type="button" name="submit" id="submit" class="button button-primary" value="Start import now"></p>
        <div class="results-here" style="display:none;">
            Importing please wait... Check the log file for more information
        </div>
    </form>
<?php

}
?>