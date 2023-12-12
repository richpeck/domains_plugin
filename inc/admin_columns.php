<?php

// RPECK 24/11/2023 - Options Page
// Various functions used to manage the options page
namespace AndyDomains;

// RPECK 11/12/2023 - Functions/Libraries
use floatval;

// RPECK 24/11/2023 - Allocate the class
final class AdminColumns {

    // RPECK 24/11/2023 - Initialize
    // Used to provide the various ingress points into the class
    public static function initialize() {

        // RPECK 24/11/2023 - Add Options Page
        // Includes the "settings" page which allows us to populate the various Stripe keys
        add_action('admin_init', 'AndyDomains\AdminColumns::setup');

    }
    
    // RPECK 11/12/2023 - Set up the various column hooks
    public static function setup() {
        
        // RPECK 01/12/2023 - Globals
    	global $pagenow;
    	
    	// RPECK 01/12/2023 - Vars
    	$post_type = 'domain';
    
    	if (is_admin() && 'edit.php' == $pagenow && $post_type == $_GET['post_type'] && defined('FIELDS')) {
    	    
    		// manage colunms
    		add_filter( "manage_edit-{$post_type}_columns", "AndyDomains\AdminColumns::manage_{$post_type}_columns" );
    
    		// make columns sortable
    		add_filter( "manage_edit-{$post_type}_sortable_columns", 'AndyDomains\AdminColumns::set_sortable_columns' );
    
    		// populate column cells
    		add_action( "manage_{$post_type}_posts_custom_column", 'AndyDomains\AdminColumns::populate_custom_columns', 10, 2 );
    
    		// set query to sort
    		add_action( 'pre_get_posts', 'AndyDomains\AdminColumns::sort_custom_column_query', 100 );

            
        }
    
    }
    
    // RPECK 11/12/2023 - Add the columns to the admin area
    public static function manage_domain_columns($columns) {
        
        // RPECK 11/12/2023 - Declare the array
        $reordered_columns = array();

        // Inserting columns to a specific location
        foreach($columns as $key => $column){

            $reordered_columns[$key] = $column;
    		
    		// Insert after order date
            if($key ==  'taxonomy-domain_category') {
            
                foreach(FIELDS as $field) {
                    
                    $reordered_columns[$field] = __( ucwords(str_replace("_", " ", $field)) , 'andy_domains');
                    
                }
                
            }
            
        }
        
        // RPECK 11/12/2023 - Return the rendered columns
        return $reordered_columns;
        
    }
    
    // RPECK 11/12/2023 - Set sortable columns
    public static function set_sortable_columns($columns) {
        
        // RPECK 11/12/2023 - Perform the query for each of the fields contained in the FIELDS constant
        foreach(FIELDS as $field) {
            
            $columns[$field] = $field;
            
        }
        
        // RPECK 11/12/2023 - Return
        return $columns;
    }
    
    // RPECK 01/12/2023 - Populate the custom columns
    public static function populate_custom_columns($column, $post_id) {
        
        if(in_array($column, FIELDS)) {

            $my_var_one = 0.00;
			$my_var_one = get_post_meta($post_id, $column, true);
			
            echo( '<span class="' .  strtolower($column). '">£' . number_format( floatval($my_var_one) , 2, '.', ',' ) . '</span>' );

        }
        
    }
    
    // RPECK 11/12/2023 - Sort the data
    public static function sort_custom_column_query($query) {
        $orderby = $query->get('orderby');
    
        if (in_array($orderby, FIELDS)) {
    			
    		$meta_query = array(
    			'relation' => 'OR',
    			'not_exists' => array(
    				'key' => $orderby,
    				'compare' => 'NOT EXISTS',
    				'type' => 'DECIMAL'
    			),
    			'exists' => array(
    				'key' => $orderby,
    				'type' => 'DECIMAL'
    			)
    		);
    
    		$query->set( 'meta_query', $meta_query );
    		$query->set( 'orderby', 'meta_value' );
    		
        }
    
    }

}