<?php
/**
 * Plugin Name:     Ultimate Member - Whole Word Search
 * Description:     Extension to Ultimate Member for whole word search in the Members Directory.
 * Version:         1.0.0
 * Requires PHP:    7.4
 * Author:          Miss Veronica
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/MissVeronica
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; 
if ( ! class_exists( 'UM' ) ) return;

class UM_Whole_Word_Search {

    function __construct( ) {

        add_filter( 'um_member_directory_general_search_meta_query', array( $this, 'um_member_directory_whole_word_search_meta_query' ), 10, 2 );
        add_filter( 'get_meta_sql',                                  array( $this, 'change_meta_sql_whole_word_search' ), 9, 6 );
        add_action( 'um_user_after_query',                           array( $this, 'um_user_after_query_whole_word_search' ), 10, 2 );
    }

    public function um_user_after_query_whole_word_search( $query_args, $user_query ) {

        remove_filter( 'get_meta_sql', array( $this, 'change_meta_sql_whole_word_search' ), 9, 6 );
    }

    public function um_member_directory_whole_word_search_meta_query( $meta_query, $search ) {

        $meta_query = array(
            'relation' => 'OR',
                array(
                    'value'   => '([[:blank:]]|[[:punct:]]|^)' . $search . '([[:punct:]}|[[:blank:]]|$)',
                    'compare' => 'REGEXP',
                ),
        );

        return $meta_query;
    }

    public function change_meta_sql_whole_word_search( $sql, $queries, $type, $primary_table, $primary_id_column, $context ) {
        
        if ( ! empty( $_POST['search'] ) ) {

            global $wpdb;

            remove_filter( 'get_meta_sql', array( UM()->member_directory(), 'change_meta_sql' ), 10, 6 );

            $search = trim( stripslashes( sanitize_text_field( $_POST['search'] ) ) );
            if ( ! empty( $search ) ) {

                preg_match(
                    '/^(.*).meta_value REGEXP[^\)]/im',
                    $sql['where'],
                    $join_matches
                );

                if ( isset( $join_matches[1] ) ) {
                    $meta_join_for_search = trim( $join_matches[1] );

                    // skip private invisible fields
                    $custom_fields = array();
                    foreach ( array_keys( UM()->builtin()->all_user_fields ) as $field_key ) {
                        $data = UM()->fields()->get_field( $field_key );
                        if ( ! um_can_view_field( $data ) ) {
                            continue;
                        }

                        $custom_fields[] = $field_key;
                    }

                    $custom_fields = apply_filters( 'um_general_search_custom_fields', $custom_fields );

                    if ( ! empty( $custom_fields ) ) {
                        $sql['join'] = preg_replace(
                            '/(' . $meta_join_for_search . ' ON \( ' . $wpdb->users . '\.ID = ' . $meta_join_for_search . '\.user_id )(\))/im',
                            "$1 AND " . $meta_join_for_search . ".meta_key IN( '" . implode( "','", $custom_fields ) . "' ) $2",
                            $sql['join']
                        );
                    }

                    // Add OR instead AND to search in WP core fields user_email, user_login, user_display_name
                    $search_where = $context->get_search_sql( $search, UM()->member_directory()->core_search_fields, 'both' );

                    $search_where = preg_replace( '/ AND \((.*?)\)/im', "$1 OR", $search_where );

                    $sql['where'] = preg_replace(
                        '/(' . $meta_join_for_search . '.meta_value = \'' . esc_attr( $search ) . '\')/im',
                        trim( $search_where ) . " $1",
                        $sql['where'],
                        1
                    );
                }
            }
        }

        return $sql;
    }
}

new UM_Whole_Word_Search();
