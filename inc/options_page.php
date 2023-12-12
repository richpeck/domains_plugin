<?php

// RPECK 24/11/2023 - Options Page
// Various functions used to manage the options page
namespace AndyDomains;

// RPECK 10/12/2023 - Libraries/Classes
use WP_Query;

// RPECK 24/11/2023 - Allocate the class
final class OptionsPage {

    // RPECK 24/11/2023 - Initialize
    // Used to provide the various ingress points into the class
    public static function initialize() {

        // RPECK 24/11/2023 - Add Options Page
        // Includes the "settings" page which allows us to populate the various Stripe keys
        add_action('admin_menu', 'AndyDomains\OptionsPage::add_menu');

    }

    /**
    * Add menu 
    * @static
    */
    public static function add_menu() {

        // RPECK 24/11/2023 - Create the options page
        add_submenu_page('edit.php?post_type=domain', 'Domain Settings', 'Settings', 'manage_options', 'andy_domains', 'AndyDomains\OptionsPage::populate');

    }     

    // RPECK 24/11/2023 - Static function to process the data
    // --
    // https://developer.wordpress.org/plugins/settings/custom-settings-page/
    public static function populate() {
        
        // check user capabilities
        if(!current_user_can('manage_options')) return;
        
        // RPECK 10/12/2023 - If "Delete" button is clicked
        if(array_key_exists('delete_all_domains', $_POST) && $_POST['delete_all_domains'] == true) {
            
            // RPECK 11/12/2023 - Get the various posts with the type 'domain'
            $allposts = get_posts(array('post_type'=>'domain', 'post_status' => 'any', 'numberposts'=> -1));
             
            // RPECK 11/12/2023 - Cycle through each post and delete them
            foreach ($allposts as $eachpost) {
              wp_delete_post( $eachpost->ID, true );
            }  
            
            // RPECK 11/12/2023 - Explain the job was complete
            add_settings_error('andy_domains', 'andy_domains_message', __( count($allposts) . ' Domains Deleted Successfully', 'andy_domains' ), 'success');
                
        }
        
        // RPECK 10/12/2023 - Check to see if anything is present in the $_FILES array
        if(!empty($_FILES) && array_key_exists('csv', $_FILES) && is_array($_FILES['csv']) && sizeof($_FILES['csv']) > 0) {
            
            // RPECK 10/12/2023 - Extract the first file
            $file = $_FILES['csv'];
            
            // RPECK 10/12/2023 - Get the file and process it (no need to handle via Wordpress, it can be dealt with by PHP)
            // --
            if ($file['error'] == UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) { 
                
                // RPECK 10/12/2023 - Get the CSV file and process it in PHP
                $csv = array_map('str_getcsv', file($file['tmp_name']));
                
                // RPECK 10/12/2023 - Walk through the array and create a new associate array out of it
                // --
                // https://www.php.net/manual/en/function.str-getcsv.php#117692
                array_walk($csv, function(&$a) use ($csv) {
                  $a = array_combine($csv[0], $a);
                });
                
                // RPECK 11/12/2023 - Check to see if the domain column exists in the CSV
                if(!array_key_exists('domain', $csv[0])) {
                    
                    // RPECK 11/12/2023 - Explain the post was inserted
                    add_settings_error('andy_domains', 'andy_domains_message', __( 'Invalid CSV - At Least One Column Needs To Have "domain" As A Header', 'andy_domains' ), 'error');
                    
                } else {
                    
                    // RPECK 10/12/2023 - Remove Column Headers
                    array_shift($csv);
                    
                    // RPECK 10/12/2023 - Check if $csv is an array and, if so, whether it is empty or not
                    if(is_array($csv) && sizeof($csv) > 0) {
                        
                        // RPECK 10/12/2023 - Loop through CSV lines
                        foreach($csv as $row) {
                            
                            // RPECK 10/12/2023 - Check if a post exists based on the domain
                            $query = new WP_Query(
                                array(
                                    'post_type' => 'domain',
                                    's'         => $row['domain'],
                                    'limit'     => 1,
                                    'fields'    => 'ids'
                                )
                            );
                            
                            // RPECK 11/12/2023 - Set up the metadata variable (single array with the values from the row)
                            $metadata = $row;
                            unset($metadata['domain']);
                            
                            // RPECK 10/12/2023 - Check if the $post is present
                            // If it is present, update the post with the data presented by the CSV
                            if($query->found_posts > 0) {
                                
                                // RPECK 10/12/2023 - Get the post object and associate it with the $post variable
                                $post_id = $query->posts[0];
                                
                                // RPECK 10/12/2023 - A valid $post_id means that we have a match on the post, whereby we can update or delete the meta as needed
                                foreach($metadata as $key => $value) {
                                    
                                    // RPECK 11/12/2023 - If the value is empty, delete the meta data
                                    if(empty($value)) {
                                        
                                        // RPECK 11/12/2023 - Delete post meta if not filled with content
                                        delete_post_meta($post_id, $key);
                                        
                                    } else {
                                        
                                        // RPECK 11/12/2023 - Update post meta
                                        update_post_meta($post_id, $key, $value);
                                        
                                    }
                                    
                                }
                                
                            } else {
                                
                                // RPECK 10/12/2023 - If it is not present, create it
                                $post_id = wp_insert_post(
                                    array(
                                        'post_title'  => $row['domain'],
                                        'post_type'   => 'domain',
                                        'post_status' => 'publish',
                                        'meta_input'  => $metadata
                                    )    
                                );
                                
                            }
                            
                        }
                        
                        // RPECK 11/12/2023 - Explain the post was inserted
                        add_settings_error('andy_domains', 'andy_domains_message', __( sizeof($csv) . ' Domains Added Successfully', 'andy_domains' ), 'success');
                        
                    }
                    
                }
                
            }
            
        }

        // show error/update messages
        settings_errors( 'andy_domains' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <hr />
            <p class="intro">Manage the various domains in the system by using the settings/tools on this page.</p>
            <form method="post" enctype="multipart/form-data">
                <label for="upload_csv" style="font-weight: bold; margin-right: 10px;">Upload CSV:</label>
                <input id="upload_csv" name="csv" type="file" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" />
                <hr />
                <label for="delete_all" style="font-weight: bold; margin-right: 10px;">Delete All Domains? (WARNING: Permanent)</label>
                <input id="delete_all" name="delete_all_domains" type="checkbox" value="true" />
                <?php

                    // output save settings button
                    submit_button('Submit');

                ?>
            </form>
        </div>
        <?php

    }

}