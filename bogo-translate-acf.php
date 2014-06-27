<?php
/*
    Plugin Name: Bogo translate ACF
    Description: This plugins works like a bridge between Bogo and Advanced Custom Fields to allow for translation of your Advanced Custom Fields with Bogo.
    Plugin URI: http://jensnilsson.nu
    Author: Jens Nilsson
    Author URI: http://jensnilsson.nu
    Text Domain: bogo-acf
    Domain Path: /languages/
    Version: 1.0
*/


class Bogo_Acf {

    private $bogo_acf_getting_field = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action( 'plugins_loaded', array( $this, 'bogo_acf_plugins_loaded' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'bogo_acf_select_default_template' ) );
        add_filter( 'acf/load_field', array( $this, 'bogo_acf_load_original_post_values' ) );
        add_filter( 'page_attributes_dropdown_pages_args', array( $this, 'bogo_acf_select_parent_page' ), 10, 2 );
    }

    /**
     * Load pluin text-domain.
     */
    function bogo_acf_plugins_loaded() {
        load_plugin_textdomain( 'bogo-acf', 'wp-content/plugins/bogo-acf/languages', 'bogo-acf/languages' );
    }

    /**
     * Fetch data and enqueue a script that will select the correct template for the page based on the original post
     */
    function bogo_acf_select_default_template() {
        // only use select default template and load original post values if this is a page thats adds a translation
        $locale = $this->get_get_or_referrer_key('locale');
        $original_post = $this->get_get_or_referrer_key('original_post');
        if( $locale && $original_post ) {
            $original_post_template = get_post_meta( $original_post, '_wp_page_template', true );

            wp_enqueue_script( 'bogo_acf_js', plugin_dir_url( __FILE__ ) . '/bogo-translate-acf.js', 'jquery', '2013-09-11' );

            wp_localize_script( 'bogo_acf_js', 'bogo_acf_js', array( 'parents_default_template' => $original_post_template ) );
        }
    }

    /**
     * Resolves which parent this translation should have and pre-selects it in the parent dropdown
     */
    function bogo_acf_select_parent_page( $dropdown_args, $post ) {

        // check if this is a page where we should be doing stuff
        $locale = $this->get_get_or_referrer_key('locale');
        $original_post = $this->get_get_or_referrer_key('original_post');
        if( $locale && $original_post ) {

            $original_post = get_post( $original_post );

            // check if the original post has a parent
            if( $original_post->post_parent != 0 ) {

                $parent_translations = bogo_get_post_translations( $original_post->post_parent );

                if(isset($parent_translations[$locale])){
                    // pre-select in the parent-dropdown
                    $dropdown_args['selected'] = $parent_translations[$locale]->ID;
                } else {
                    error_log("handle this case");
                }
            }
        }

        return $dropdown_args;
    }

    // Gets key_name from GET or HTTP_REFERER
    // returns false if not found
    function get_get_or_referrer_key($key_name){

        $original_post = false;
        if(isset($_GET[$key_name])){
            $original_post = $_GET[$key_name];
        } else {
            $args = ( array_key_exists( 'HTTP_REFERER', $_SERVER ) ) ? wp_parse_args( $_SERVER['HTTP_REFERER'] ) : array();
            if( array_key_exists($key_name, $args)){
                $original_post = $args[$key_name];
            }
        }
        return $original_post;
    }

    function bogo_acf_load_original_post_values( $field ) {

        $original_post = $this->get_get_or_referrer_key('original_post');
        $locale = $this->get_get_or_referrer_key('locale');
        $original_post_field = array();
        if( $locale && $original_post && !$this->bogo_acf_getting_field ) {
            $this->bogo_acf_getting_field = true;

            $original_post_field = get_field_object( $field['key'], $original_post, array( 'load_value' => true, 'format_value' => false ));

            switch( $original_post_field['type'] ) {
                case 'repeater':
                    $original_post_field['value'] = acf_field_repeater::format_value( $original_post_field['value'], $original_post, $original_post_field );
                    break;
                case 'flexible_content':
                    $original_post_field['value'] = acf_field_flexible_content::format_value( $original_post_field['value'], $original_post, $original_post_field );
                    break;
                default:
                    break;
            }

            $this->bogo_acf_getting_field = false;
        }
        $field = array_merge($field, $original_post_field);

        return $field;
    }

    /**
     * Returns as singleton
     *
     * @return bogo_acf
     */
    public static function Instance() {
        static $inst = null;
        if ($inst === null) {
            $inst = new bogo_acf();
        }
        return $inst;
    }
}

$bogo_acf = Bogo_Acf::Instance();

?>
