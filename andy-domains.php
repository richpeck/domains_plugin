<?php
/*
  Plugin Name: Andy Domains Management Plugin
  Plugin URI: https://github.com/richpeck/4ndy_domains
  Description: Domains plugin for Andy Media. Allows the storing of domains inside Wordpress using a custom CPT + Taxonomy
  Author: Richard Peck
  Author URI: https://github.com/richpeck
  Version: 0.1.0
  Text Domain: andy_domains
  License: GPLv3
 */

///////////////////////////////////////
///////////////////////////////////////

 // RPECK 10/12/2023 - Plugin introduced to help Andy store the domains on his site
 // Plugin creates a CPT and accompanying taxonomy, used to provide the means to populate the site with various domains that used to be hosted on dan.com

///////////////////////////////////////
///////////////////////////////////////

// Exit if accessed directly
if(!defined('ABSPATH')) exit;

// Libraries
use AndyDomains\OptionsPage;
use AndyDomains\AdminColumns;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory; // RPECK 15/11/2023 - PucFactory (used to get updates from Github)

// RPECK 10/12/2023 - Only proceed if the AndyDomains class is present
if (!class_exists('AndyDomains')) {
    
    // RPECK 22/11/2023 - Payment form emeds
    // Allows us to populate the various payment forms as required
    final class AndyDomains {

        /**
         * The only instance of the class
         *
         * @var AndyDomains
         * @since 1.0
         */
        private static $instance;

        /**
         * The Plug-in version.
         *
         * @var string
         * @since 1.0
         */
        public $version = '0.1.0';

        /**
         * The minimal required version of WordPress for this plug-in to function correctly.
         *
         * @var string
         * @since 1.0
         */
        public $wp_version = '4.0';

        /**
         * Class name
         *
         * @var string
         * @since 1.0
         */
        public $class_name;

        /**
         * An array of defined constants names
         *
         * @var array
         * @since 1.0
         */
        public $defined_constants;

        /**
         * Create a new instance of the main class
         *
         * @since 1.0
         * @static
         * @return BoilerPlate
         */
        public static function instance() {
            $class_name = get_class();
            if (!isset(self::$instance) && !(self::$instance instanceof $class_name)) self::$instance = new $class_name;
            return self::$instance;
        }

        /**
         * Construct and start the other plug-in functionality
         *
         * @since 1.0
         * @public
         */
        public function __construct() {
            
            // Save the class name for later use
            $this->class_name = get_class();

            // RPECK 22/11/2023 - Requirements
            if (!$this->check_requirements()) return;

            // RPECK 22/11/2023 - Constants & Dependencies
            $this->load_dependencies();
            $this->define_constants();

            // RPECK 22/11/2023 - Create plugin logic
            add_action('plugins_loaded', array(&$this, 'start'), 0, 100);
            
            // RPECK 12/12/2023 - Check for updates
            add_action('plugins_loaded', array(&$this, 'check_updates'));

        }

        /**
         * Throw error on object clone.
         *
         * Cloning instances of the class is forbidden.
         *
         * @since 1.0
         * @return void
         */
        public function __clone() {
            _doing_it_wrong( __FUNCTION__, __( 'Cloning instances of the class is forbidden.', 'andy_domains' ), '1.0' );
        }

        /**
         * Disable unserializing of the class
         *
         * Unserializing instances of the class is forbidden.
         *
         * @since 1.0
         * @return void
         */
        public function __wakeup() {
            _doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of the class is forbidden.', 'andy_domains' ), '1.0' );
        }

        /**
         * Checks that the WordPress setup meets the plugin requirements
         * @global string $wp_version
         * @return boolean
         */
        private function check_requirements() {
            global $wp_version;
            if (!version_compare( $wp_version, $this->wp_version, '>=')) {
                add_action('admin_notices', array( &$this, 'display_req_notice'));
                return false;
            }
            return true;
        }

        /**
         * Define constants needed across the plug-in.
         */
        private function define_constants() {
            
            // RPECK 10/12/2023 - Define the various constants required throughout the script
            $this->define('FIELDS', array('minimum_bid', 'buy_it_now', 'lease_to_own'));
            $this->define('UPDATE_URL', 'https://github.com/richpeck/domains_plugin');
            
        }

        /**
         * Define constant if not already set
         * @param  string $name
         * @param  string|bool $value
         */
        private function define($name, $value) {
            if (!defined($name)) {
                define($name, $value);
                $this->defined_constants[] = $name;
            }
        }

        /**
         * Loads PHP files that required by the plug-in
         */
        private function load_dependencies() {
            
            // RPECK 10/12/2023 - Load various files for the dependencies
            require_once 'vendor/plugin-update-checker/plugin-update-checker.php';  // RPECK 25/11/2023 - Update the plugin using Github (https://github.com/YahnisElsts/plugin-update-checker#how-to-release-an-update-1)
            require_once 'inc/options_page.php';                                    // RPECK 24/11/2023 - Options pages (used to populate the various inputs required to edit the Stripe license keys etc)
            require_once 'inc/admin_columns.php';                                   // RPECK 24/11/2023 - Admin Columns (used to set up the various items required to get the columns showing in the CPT admin area)            
            
        }

        /**
         * Display the requirement notice
         * @static
         */
        public function display_req_notice() {
            echo '<div id="message" class="error"><p><strong>';
            echo __( 'Sorry, Payment Forms requires WordPress ' . $this->wp_version . ' or higher. Please upgrade your WordPress setup', 'andy_domains' );
            echo '</strong></p></div>';
        }

        /**
         * Adds a separator to the admin menu
         */
        public function admin_menu_separator() {
            global $menu;
            $menu[54] = ['', 'read', '', '', 'wp-menu-separator'];
        }

        /**
         * Sets up the 'domain_category' taxonomy
         */
        public function register_taxonomy() {

            // RPECK 10/12/2023 - Register the taxonomy we can then use in the domains CPT below
            // --
            // https://generatewp.com/taxonomy/
            $labels = array(
                'name'                       => _x( 'Domain Categories', 'Taxonomy General Name', 'andy_domains' ),
                'singular_name'              => _x( 'Domain Category', 'Taxonomy Singular Name', 'andy_domains' ),
                'menu_name'                  => __( 'Domain Categories', 'andy_domains' ),
                'all_items'                  => __( 'All Domain Categories', 'andy_domains' ),
                'parent_item'                => __( 'Parent Domain Category', 'andy_domains' ),
                'parent_item_colon'          => __( 'Parent Domain Category:', 'andy_domains' ),
                'new_item_name'              => __( 'New Domain Category', 'andy_domains' ),
                'add_new_item'               => __( 'Add Domain Category', 'andy_domains' ),
                'edit_item'                  => __( 'Edit Domain Category', 'andy_domains' ),
                'update_item'                => __( 'Update Domain Category', 'andy_domains' ),
                'view_item'                  => __( 'View Domain Category', 'andy_domains' ),
                'separate_items_with_commas' => __( 'Separate items with commas', 'andy_domains' ),
                'add_or_remove_items'        => __( 'Add or remove items', 'andy_domains' ),
                'choose_from_most_used'      => __( 'Choose from the most used', 'andy_domains' ),
                'popular_items'              => __( 'Popular Items', 'andy_domains' ),
                'search_items'               => __( 'Search Items', 'andy_domains' ),
                'not_found'                  => __( 'Not Found', 'andy_domains' ),
                'no_terms'                   => __( 'No items', 'andy_domains' ),
                'items_list'                 => __( 'Items list', 'andy_domains' ),
                'items_list_navigation'      => __( 'Items list navigation', 'andy_domains' ),
            );

            // RPECK 10/12/2023 - Arguments for the taxonomy
            $args = array(
                'labels'                     => $labels,
                'hierarchical'               => false,
                'public'                     => true,
                'show_ui'                    => true,
                'show_admin_column'          => true,
                'show_in_nav_menus'          => true,
                'show_tagcloud'              => false,
                'show_in_rest'               => true,
                'meta_box_cb'                => 'post_categories_meta_box' // RPECK 10/12/2023 - Required to get the checkboxes to show in the taxonomy area (https://wordpress.stackexchange.com/a/229863)
            );

            // RPECK 10/12/2023 - Register taxonomy
	        register_taxonomy('domain_category', array('domain'), $args);

        }

        /**
         * Sets up the 'domain' CPT
         */
        public function register_cpt() {

            // RPECK 10/12/2023 - Get the labels set up
            // --
            // https://generatewp.com/post-type/
            $labels = array(
                'name'                  => _x( 'Domains', 'Post Type General Name', 'andy_domains' ),
                'singular_name'         => _x( 'Domain', 'Post Type Singular Name', 'andy_domains' ),
                'menu_name'             => __( 'Domains', 'andy_domains' ),
                'name_admin_bar'        => __( 'Domain', 'andy_domains' ),
                'archives'              => __( 'Domain Archives', 'andy_domains' ),
                'attributes'            => __( 'Domain Attributes', 'andy_domains' ),
                'parent_item_colon'     => __( 'Parent Domain:', 'andy_domains' ),
                'all_items'             => __( 'All Domains', 'andy_domains' ),
                'add_new_item'          => __( 'Add Domain', 'andy_domains' ),
                'add_new'               => __( 'Add New', 'andy_domains' ),
                'new_item'              => __( 'New Domain', 'andy_domains' ),
                'edit_item'             => __( 'Edit Domain', 'andy_domains' ),
                'update_item'           => __( 'Update Domain', 'andy_domains' ),
                'view_item'             => __( 'View Domain', 'andy_domains' ),
                'view_items'            => __( 'View Domains', 'andy_domains' ),
                'search_items'          => __( 'Search Domain', 'andy_domains' ),
                'not_found'             => __( 'Not found', 'andy_domains' ),
                'not_found_in_trash'    => __( 'Not found in Trash', 'andy_domains' ),
                'featured_image'        => __( 'Featured Image', 'andy_domains' ),
                'set_featured_image'    => __( 'Set featured image', 'andy_domains' ),
                'remove_featured_image' => __( 'Remove featured image', 'andy_domains' ),
                'use_featured_image'    => __( 'Use as featured image', 'andy_domains' ),
                'insert_into_item'      => __( 'Insert into item', 'andy_domains' ),
                'uploaded_to_this_item' => __( 'Uploaded to this item', 'andy_domains' ),
                'items_list'            => __( 'Items list', 'andy_domains' ),
                'items_list_navigation' => __( 'Items list navigation', 'andy_domains' ),
                'filter_items_list'     => __( 'Filter items list', 'andy_domains' ),
            );

            // RPECK 10/12/2023 - Get the arguments set up
            $args = array(
                'label'                 => __( 'Domain', 'text_domain' ),
                'description'           => __( 'Domains Hosted on Andy.co.uk', 'text_domain' ),
                'labels'                => $labels,
                'supports'              => array( 'title' ),
                'taxonomies'            => array( 'domain_category' ),
                'hierarchical'          => false,
                'public'                => true,
                'show_ui'               => true,
                'show_in_menu'          => true,
                'menu_position'         => 55,
                'menu_icon'             => 'dashicons-awards',
                'show_in_admin_bar'     => true,
                'show_in_nav_menus'     => true,
                'can_export'            => true,
                'has_archive'           => true,
                'exclude_from_search'   => false,
                'publicly_queryable'    => true,
                'capability_type'       => 'post',
                'rewrite'               => array('slug' => 'domains'),
                'show_in_rest'          => true
            );

            // RPECK 10/12/2023 - Register the CPT
            register_post_type('domain', $args);

        }
        
        /**
         * Sets up the meta box for the domains
         */
        public function register_meta_box() {
            
            // RPECK 10/12/2023 - Define the meta box here
            add_meta_box(
               'domain_meta_box',                         // $id
               __('Domain Details', 'andy_domains' ), // $title
               array($this, 'domain_meta_box_display'),   // $callback
               'domain',                                  // $page
               'normal',                                  // $context
               'high'                                     // $priority
           );
            
        }
        
         /**
         * Outputs the meta box content to the post edit area
         */
        public function domain_meta_box_display($post) {
            
            // RPECK 10/12/2023 - Create DOMDocument
            $dom = new DOMDocument();
            
            // RPECK 10/12/2023 - Use the DOMDocument to create a new set of fields
            $introduction = $dom->createElement('p', 'Please edit the details of the domain below. These are static values that are held in the database.');
            $introduction->setAttribute('class', 'introduction');
            $introduction->setAttribute('style', 'font-weight: bold; text-decoration: underline');
            
            // RPECK 10/12/2023 - Further information
            $information = $dom->createElement('p', 'If none of the inputs are filled, a generic contact form will display.');
            $information->setAttribute('class', 'information');
            
            // RPECK 10/12/2023 - Horizontal Rule
            // Used to provide a means to break up the content
            $hr = $dom->createElement('hr');
            
            // RPECK 10/12/2023 - Table
            // Used to give us the means to regulate how the various inputs will look
            $inputs_table = $dom->createElement('table');
            
            // RPECK 10/12/1023 - Cycle through the various inputs and outpt them in the table
            // This was done to clean up the code
            foreach(FIELDS as $item) {
                
                // RPECK 10/12/2023 - Vars
                $full_text = str_replace("_"," ", $item);
                $full_text = ucwords($full_text);
                
                // RPECK 10/12/2023 - Get the value from the postmeta
                $value = get_post_meta($post->ID, $item, true);
                
                // RPECK 10/12/2023 - Minimum Offer
                // Provides the means to input a minimum offer value for the domain
                $input = $dom->createElement('input');
                $input->setAttribute('type', 'number');
                $input->setAttribute('min', 0);
                $input->setAttribute('step', 0.01);
                $input->setAttribute('placeholder', '0.00');
                $input->setAttribute('id', $item);
                $input->setAttribute('name', $item);
                $input->setAttribute('value', $value);
                            
                // REPCK 10/12/2023 - Minimum Offer Label
                // Sets the label of the field so we can change it later
                $input_label = $dom->createElement('label', "{$full_text} (Optional):");
                $input_label->setAttribute('for', 'minimum_offer');
                $input_label->setAttribute('style', 'margin-right: 10px; font-weight: bold;');
                
                // RPECK 10/12/2023 - Label column
                $label_column = $dom->createElement('td');
                $label_column->appendChild($input_label);
            
                // RPECK 10/12/2023 - Input column
                $input_column = $dom->createElement('td');
                $input_column->appendChild($input);
                
                // RPECK 10/12/2023 - Create row
                // This allows us to populate the row with columns
                $row = $dom->createElement('tr');
                $row->appendChild($label_column);
                $row->appendChild($input_column);
                
                // RPECK 10/12/2023 - Append row to table
                $inputs_table->appendChild($row);
                
            }
            
            // RPECK 10/12/2023 - Append various elements to main document
            $dom->appendChild($introduction);
            $dom->appendChild($information);
            $dom->appendChild($hr);
            $dom->appendChild($inputs_table);
            
            // RPECK 10/12/2023 - Return the saved HTML
            echo $dom->saveHTML();
            
        }    
        
        /**
         * Save domain data
         */
        public function save_domain_data($post_id) {   
            
            // RPECK 10/12/2023 - Return if the user can't edit posts or the post type is not domain
            if(get_post_type() != 'domain' || !defined('FIELDS')) return;
        
            // RPECK 10/12/2023 - Loop through the types of data
            foreach(FIELDS as $field) {
                
                // RPECK 10/12/2023 - Check to see if the value of the field is present in the $_POST array
                if(array_key_exists($field, $_POST)) {
                    
                    // RPECK 10/12/2023 - Remove meta data if empty
                    if(empty($_POST[$field])) {
                        
                        // RPECK 10/12/2023 - Remove meta data
                        delete_post_meta($post_id, $field);
                        
                    } else {
                        
                        // RPECK 10/12/2023 - Create or update post meta
                        update_post_meta($post_id, $field, $_POST[$field]);
                        
                    }
                    
                }
                
            }
            
        }
        
        /**
         * Yoast SEO Meta Box
         */
        public function remove_yoast_meta_box() {
            
            // RPECK 10/12/2023 - Remove the meta box added by Yoast
            remove_meta_box('wpseo_meta', 'domain', 'normal');
            
        }   


        /**
         * Starts the plug-in main functionality
         */
        public function start() {

            // RPECK 10/12/2023 - Add admin menu separator
            // --
            // https://stackoverflow.com/a/19415984/1143732
            add_action('admin_menu', array($this, 'admin_menu_separator'));

            // RPECK 10/12/2023 - Set up domain-categories taxonomy only for the domains
            // This gives us the means to categorise the domains as required
            add_action('init', array($this, 'register_taxonomy'), 99);

            // RPECK 10/12/2023 - Set up the CPT and Taxonomy for the domain
            // This is done by integrating into the 'init' hook
            // --
            // https://developer.wordpress.org/plugins/post-types/registering-custom-post-types/
            add_action('init', array($this, 'register_cpt'), 100);
            
            // RPECK 10/12/2023 - Add metabox for the new CPT
            // Allows us to define the specific details we wish to capture for each post (minimum value bid, BIN, etc)
            add_action('add_meta_boxes', array($this, 'register_meta_box'));
            
            // RPECK 10/12/2023 - Save the meta box data
            // This needs to hook into the post_save hook to get the updated data saved as postmeta
            add_action('save_post', array($this, 'save_domain_data'));
            
            // RPECK 10/12/1023 - Remove Yoast SEO Metabox for our new CPT (only necessary if Yoast exists)
            // To begin, check the existence of the Yoast plugin (https://stackoverflow.com/a/57669999/1143732)
            if(class_exists('WPSEO_Options')) add_action('add_meta_boxes', array($this, 'remove_yoast_meta_box'), 11);
            
            // RPECK 11/12/2023 - Cycle through the fiels and add them as sortable columns in the new CPT
            // Add a new 'Definition' column to the Acronym post list
            AdminColumns::initialize();

            // RPECK 24/11/2023 - Add admin settings
            // Used custom class to extract code into its own file
            OptionsPage::initialize();

        }
    
        /**
         * Checks the updates using the PucFactory library
         */
        public function check_updates() {
    
            // RPECK 25/11/2023 - If $token is valid, proceed otherwise show a notice
            if(defined('UPDATE_URL')) {

    
                // RPECK 25/11/2023 - Check the update endpoint
                $myUpdateChecker = PucFactory::buildUpdateChecker(UPDATE_URL, __FILE__, 'andy_domains');
    
                // RPECK 25/11/2023 - Set the branch for the repo
                $myUpdateChecker->setBranch('main');
    
            } else {
    
                // RPECK 25/11/2023 - Add admin notice to tell the user that they need to add the token
                add_action('admin_notices', function() {
    
                    echo '<div id="message" class="error"><p><strong>';
                    echo __( 'Updates URL invalid.', 'andy_domains' );
                    echo '</strong></p></div>';
    
                });
    
            }
    
        }
    
    }

}

/*
 * Creates a new instance of the Class
 */
function CreateDomains() {
    return AndyDomains::instance();
}

CreateDomains();
