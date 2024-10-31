<?php
/*
Plugin Name: Objectives Key Results - OKR
Plugin URI: https://mkaion.com/wordpress-okr/
Description: WordPress OKR Plugin to manage objectives and key results.
Version: 1.02
Author: Mainul Kabir Aion
Author URI: https://mkaion.com/
License: GPL3
*/

/*
Thanks to Daryl L. L. Houston for the initial plugin.
TODO

* Percent complete per KR
* Weight of KR toward Objective completion
* Calculate/display completion
* Localize
* End date for O and KR
* Basic CSS, especially for labels in the shortcode.
* Make lots of stuff filterable.

*/

add_action('wp_enqueue_scripts', 'okr_init');

function okr_init()
{
    wp_enqueue_script('okr', plugins_url('assets/js/script.js', __FILE__));
    wp_enqueue_script('okr', plugins_url('assets/css/style.css', __FILE__));
}

class OKR
{

    public function __construct()
    {
        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'add_meta_boxes', array($this, 'add_meta_boxes' ) );
        add_action( 'wp_insert_post_data', array($this, 'save_key_result_data'), 10, 2 );
        add_shortcode('okr', array( $this, 'shortcode' ) );
    }

    public function add_meta_boxes()
    {
        add_meta_box('okr_key_result_parent', 'Key Result Data', array($this, 'add_key_result_meta_box'), 'key_result');
    }


    public function add_key_result_meta_box()
    {
        global $post;

        wp_nonce_field('okr_key_result_data', 'okr_key_result_data');
        $objectives = get_posts(array('post_type' => 'objective'));

        $okr_data = array();

        if ( metadata_exists( 'post', $post->ID, 'okr_key_result_meta' ) ) {
            $okr_data = get_post_meta($post->ID, 'okr_key_result_meta', true);
        } else {
            $okr_data = array_fill_keys( array( 'due_date', 'percent_complete', 'weight' ), '' );
        }

        echo '<label> Select Objective </label>';
        echo '<select style="margin-left: 20px" name="key_result_parent">';
        echo '<option value="">Choose an Objective</option>';
        foreach ($objectives as $objective) {
            echo '<option value="' . (int)$objective->ID . '"' . selected($objective->ID, $post->post_parent) . '>' . esc_html($objective->post_title) . '</option>';
        }
        echo '</select>';
        echo '<br />';

        echo '<label> Due Date:</label>';
        echo '<input style="margin-left: 60px" name="key_result_due_date" type="date" value="' . esc_attr($okr_data['due_date']) . '" />';
        echo '<br />';

        echo '<label> Percent Complete: </label>';
        echo '<input style="margin-left: 10px" name="key_result_percent_complete" type="number" min="0" max="100" value="' . (int)$okr_data['percent_complete'] . '" />';
        echo '  ';

        echo '<div class="range-slider">';
        echo ' <input name="key_result_percent_complete" class="range-slider__range" type="range" min="0" max="100" value="' . (int)$okr_data['percent_complete'] . '">';
        echo '<span class="range-slider__value">' . $okr_data['percent_complete'] . '</span>';
        echo '</div>';

        //echo '<input name="key_result_percent_complete" type="range" min="0" max="100" class="slider" value="' . (int) $okr_data['percent_complete'] . '" />';

        echo '<br />';

        echo '<label>Weight:</label>';
        echo '<input style="margin-left: 65px" name="key_result_weight" type="number" min="0" max="100" value="' . (int)$okr_data['weight'] . '" />';
        echo '<br />';
        ?>
        <script type="text/javascript">
            var rangeSlider = function(){
                var slider = $('.range-slider'),
                    range = $('.range-slider__range'),
                    value = $('.range-slider__value');

                slider.each(function(){
                    value.each(function(){
                        var value = " <?php echo $okr_data['percent_complete'] ?>" + " %" ;
                        $(this).html(value);
                    });
                    range.on('input', function(){
                        // $('input[name=key_result_percent_complete]').val(this.value);
                        $(this).next(value).html(this.value + '%');
                    });
                });
            };
            rangeSlider();
        </script>
        <?php

    }

    public function save_key_result_data( $data, $post_array )
    {
        global $post;

        if ( ( isset( $_POST['okr_key_result_data'] ) && !wp_verify_nonce($_POST['okr_key_result_data'], 'okr_key_result_data' ) ) || !isset( $post->ID ) ) {
            return $data;
        }

        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
            return $data;
        }


        if ('key_result' == $post->post_type) {
            $data['post_parent'] = (int)$post_array['key_result_parent'];

            if (!isset($this->key_result_meta)) {
                $this->key_result_meta = array(
                    'due_date' => '',
                    'percent_complete' => 0,
                    'weight' => 1,
                );
            }
            if (isset($post_array['key_result_due_date']) && preg_match('/\d\d\d\d-\d\d-\d\d/', $post_array['key_result_due_date'])) {
                $this->key_result_meta['due_date'] = sanitize_text_field($post_array['key_result_due_date']);
            }

            if (isset($post_array['key_result_percent_complete']) && is_numeric($post_array['key_result_percent_complete'])) {
                $this->key_result_meta['percent_complete'] = (int)$post_array['key_result_percent_complete'];
            }

            if (isset($post_array['key_result_weight']) && is_numeric($post_array['key_result_weight'])) {
                $this->key_result_meta['weight'] = (int)$post_array['key_result_weight'];
            }

            update_post_meta($post->ID, 'okr_key_result_meta', $this->key_result_meta);
        }

        return $data;
    }

    public function shortcode($attributes)
    {
        $out = '';

        // Parse the shortcode for an ids or a slugs attribute and fetch the matching Objective ids.
        $ids = array();
        if (isset($attributes['slugs'])) {
            $slugs = explode(',', $attributes['slugs']);
            foreach ($slugs as $slug) {
                $page = get_page_by_path(trim($slug), OBJECT, 'objective');
                if ($page) {
                    $ids[] = (int)$page->ID;
                }
            }
        } else if (isset($attributes['ids'])) {
            $ids = explode(',', $attributes['ids']);
            array_walk($ids, 'trim');
            array_walk($ids, 'intval');
        } else {
            return '';
        }

        $objectives = get_posts(array(
            'post_type' => 'objective',
            'include' => $ids
        ));


        foreach ($objectives as $objective) {
            $key_results = get_posts(array(
                'post_type' => 'key_result',
                'post_parent' => $objective->ID
            ));
            $out .= '<div class="objective">';
            $out .= '<h3>' . esc_html($objective->post_title) . '</h3>';
            $out .= '<div class="objective-content">' . esc_html($objective->post_content) . '</div>';

            $weight = 0;
            $percent_complete = 0;

            foreach ($key_results as $kr) {
                $kr_meta = get_post_meta($kr->ID, 'okr_key_result_meta', true);
                $out .= '<div class="key-result">';
                $out .= '<h4>' . esc_html($kr->post_title) . '</h4>';
                $out .= '<div class="key-result-content">' . esc_html($kr->post_content) . '</div>';
                $out .= '<ul class="key-result-meta">';
                if (isset($kr_meta['due_date'])) {
                    $out .= '<li class="key-result-due-date"><span class="label">' . __('Due Date') . '</span>' . esc_html($kr_meta['due_date']) . '</li>';
                }

                if (isset($kr_meta['percent_complete'])) {
                    $out .= '<li class="key-result-percent-complete"><span class="label">' . __('Percent Complete') . '</span>' . esc_html($kr_meta['percent_complete']) . '</li>';
                    $percent_complete += (int)$kr_meta['percent_complete'];
                }

                if (isset($kr_meta['weight'])) {
                    $out .= '<li class="key-result-weight"><span class="label">' . __('Weight') . '</span>' . esc_html($kr_meta['weight']) . '</li>';
                    $weight += (int)$kr_meta['weight'];
                } else {
                    $weight += 1;
                }
                $out .= '</ul>';
                $out .= '</div>'; // .key-result
            }

            $out .= 'Objective Percent Complete: ' . round($percent_complete / $weight) . '%';
            $out .= '</div>'; // .objective
        }

        return $out;
    }

    public function register_post_types() {

        $labels = array(
            'name'                  => 'Objectives',
            'singular_name'         => 'Objective',
            'menu_name'             => 'Objectives',
            'name_admin_bar'        => 'Objective',
            'archives'              => 'Objective Archives',
            'attributes'            => 'Objective Attributes',
            'parent_item_colon'     => 'Parent Objective:',
            'all_items'             => 'All Objectives',
            'add_new_item'          => 'Add New Objective',
            'add_new'               => 'Add New',
            'new_item'              => 'New Objective',
            'edit_item'             => 'Edit Objective',
            'update_item'           => 'Update Objective',
            'view_item'             => 'View Objective',
            'view_items'            => 'View Objectives',
            'search_items'          => 'Search Objective',
            'not_found'             => 'Not found',
            'not_found_in_trash'    => 'Not found in Trash',
            'featured_image'        => 'Featured Image',
            'set_featured_image'    => 'Set featured image',
            'remove_featured_image' => 'Remove featured image',
            'use_featured_image'    => 'Use as featured image',
            'uploaded_to_this_item' => 'Uploaded to this item',
            'items_list'            => 'Objectives list',
            'items_list_navigation' => 'Objectives list navigation',
            'filter_items_list'     => 'Filter Objectives list',
        );
        $args = array(
            'label'                 => 'Objective',
            'description'           => 'Post Type Description',
            'labels'                => $labels,
            'supports'              => array( 'title', 'editor', 'author', 'thumbnail', 'comments' ),
            'taxonomies'            => array( 'category' ),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 5,
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'page',
        );
        register_post_type( 'objective', $args );

        $labels = array(
            'name'                  => 'Key Results',
            'singular_name'         => 'Key Result',
            'menu_name'             => 'Key Results',
            'name_admin_bar'        => 'Key Result',
            'archives'              => 'Key Result Archives',
            'attributes'            => 'Key Result Attributes',
            'parent_item_colon'     => 'Parent Key Result:',
            'all_items'             => 'All Key Results',
            'add_new_item'          => 'Add New Key Result',
            'add_new'               => 'Add New',
            'new_item'              => 'New Key Result',
            'edit_item'             => 'Edit Key Result',
            'update_item'           => 'Update Key Result',
            'view_item'             => 'View Key Result',
            'view_items'            => 'View Key Results',
            'search_items'          => 'Search Key Result',
            'not_found'             => 'Not found',
            'not_found_in_trash'    => 'Not found in Trash',
            'featured_image'        => 'Featured Image',
            'set_featured_image'    => 'Set featured image',
            'remove_featured_image' => 'Remove featured image',
            'use_featured_image'    => 'Use as featured image',
            'uploaded_to_this_item' => 'Uploaded to this item',
            'items_list'            => 'Key Results list',
            'items_list_navigation' => 'Key Results list navigation',
            'filter_items_list'     => 'Filter Key Results list',
        );

        $args = array(
            'label'                 => 'Key Result',
            'description'           => 'Post Type Description',
            'labels'                => $labels,
            'supports'              => array( 'title', 'editor', 'author', 'thumbnail', 'comments' ),
            'taxonomies'            => array( 'category' ),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 6,
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'page',
        );
        register_post_type( 'key_result', $args );

    }

}

new OKR();



