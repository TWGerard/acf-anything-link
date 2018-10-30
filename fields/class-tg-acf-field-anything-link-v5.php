<?php

// exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;


// check if class already exists
if( !class_exists('tg_acf_field_anything_link') ) :


class tg_acf_field_anything_link extends acf_field_page_link {


  function __construct( $settings ) {
    parent::__construct();
    $this->settings = $settings;
  }

  function initialize() {
    // vars
    $this->name = 'anything_link';
    $this->label = __("Anything Link",'acf');
    $this->category = 'relational';
    $this->defaults = array(
      'post_type'     => array(),
      'taxonomy'      => array(),
      'allow_null'    => 0,
      'multiple'      => 0,
      'allow_archives'  => 1
    );

    // extra
    add_action('wp_ajax_acf/fields/anything_link/query',      array($this, 'ajax_query'));
    add_action('wp_ajax_nopriv_acf/fields/anything_link/query',   array($this, 'ajax_query'));
  }


  function input_admin_enqueue_scripts() {
    $url = $this->settings['url'];
    $version = $this->settings['version'];

    // register & include JS
    wp_register_script('tg-acf-anything-link', "{$url}assets/js/input.js", array('acf-input'), $version);
    wp_enqueue_script('tg-acf-anything-link');

    // register & include CSS
    // wp_register_style('tg-acf-anything-link', "{$url}assets/css/input.css", array('acf-input'), $version);
    // wp_enqueue_style('tg-acf-anything-link');
  }


  function ajax_query() {

    // validate
    if( !acf_verify_ajax() ) die();


    // defaults
      $options = acf_parse_args($_POST, array(
      'post_id'   => 0,
      's'       => '',
      'field_key'   => '',
      'paged'     => 1
    ));


      // vars
      $results = array();
      $args = array();
      $s = false;
      $is_search = false;


    // paged
      $args['posts_per_page'] = 20;
      $args['paged'] = $options['paged'];


      // search
    if( $options['s'] !== '' ) {

      // strip slashes (search may be integer)
      $s = wp_unslash( strval($options['s']) );


      // update vars
      $args['s'] = $s;
      $is_search = true;

    }


    // load field
    $field = acf_get_field( $options['field_key'] );
    if( !$field ) die();


    // update $args
    if( !empty($field['post_type']) ) {

      $args['post_type'] = acf_get_array( $field['post_type'] );

    } else {

      $args['post_type'] = acf_get_post_types();

    }

    // create tax queries
    if( !empty($field['taxonomy']) ) {

      // append to $args
      $args['tax_query'] = array();


      // decode terms
      $taxonomies = acf_decode_taxonomy_terms( $field['taxonomy'] );


      // now create the tax queries
      foreach( $taxonomies as $taxonomy => $terms ) {

        $args['tax_query'][] = array(
          'taxonomy'  => $taxonomy,
          'field'   => 'slug',
          'terms'   => $terms,
        );

      }
    }


    // filters
    $args = apply_filters('acf/fields/page_link/query', $args, $field, $options['post_id']);
    $args = apply_filters('acf/fields/page_link/query/name=' . $field['name'], $args, $field, $options['post_id'] );
    $args = apply_filters('acf/fields/page_link/query/key=' . $field['key'], $args, $field, $options['post_id'] );


    // add archives to $results
    if( $field['allow_archives'] && $args['paged'] == 1 ) {

      $archives = array();
      if (!$is_search) {
        $archives[] = array(
          'id'  => home_url(),
          'text'  => 'Home Page'
        );
      }

      foreach( $args['post_type'] as $post_type ) {
        $post_type_object = get_post_type_object($post_type);

        // vars
        $archive_link = get_post_type_archive_link( $post_type );


        // bail ealry if no link
        if( !$archive_link ) continue;


        // bail early if no search match
        if( $is_search && stripos($archive_link, $s) === false ) continue;


        // append
        $archives[] = array(
          'id'  => $archive_link,
          'text'  => $post_type_object->labels->singular_name . ' Archive'
        );

      }


      // append
      if (count($archives)) {
        $results[] = array(
          'text'    => __('Archives', 'acf'),
          'children'  => $archives
        );
      }


      // Add taxonomy terms to $results
      $taxonomies = get_taxonomies(array(
        'public' => true
      ), 'objects');

      foreach ($taxonomies as $taxonomy) {
        $termArgs = array(
          'taxonomy' => $taxonomy->name,
          'orderby' => 'term_group'
        );
        if ($is_search) {
          $termArgs['search'] = $s;
        }
        $terms = get_terms($termArgs);

        $termArchives = array();
        foreach ($terms as $term) {
          $termArchives[] = array(
            'id' => get_term_link($term, $taxonomy->name),
            'text' => $term->name
          );
        }

        if (count($termArchives)) {
          $results[] = array(
            'text' => __($taxonomy->labels->name, 'acf'),
            'children' => $termArchives
          );
        }
      }

    }


    // get posts grouped by post type
    $groups = acf_get_grouped_posts( $args );


    // loop
    if( !empty($groups) ) {

      foreach( array_keys($groups) as $group_title ) {

        // vars
        $posts = acf_extract_var( $groups, $group_title );


        // data
        $data = array(
          'text'    => $group_title,
          'children'  => array()
        );


        // convert post objects to post titles
        foreach( array_keys($posts) as $post_id ) {

          $posts[ $post_id ] = $this->get_post_title( $posts[ $post_id ], $field, $options['post_id'], $is_search );

        }


        // order posts by search
        if( $is_search && empty($args['orderby']) ) {

          $posts = acf_order_by_search( $posts, $args['s'] );

        }


        // append to $data
        foreach( array_keys($posts) as $post_id ) {

          $data['children'][] = $this->get_post_result( $post_id, $posts[ $post_id ]);

        }


        // append to $results
        $results[] = $data;

      }

    }


    // return
    acf_send_ajax_results(array(
      'results' => $results,
      'limit'   => $args['posts_per_page']
    ));

  }
}


// initialize
new tg_acf_field_anything_link( $this->settings );


// class_exists check
endif;

?>
