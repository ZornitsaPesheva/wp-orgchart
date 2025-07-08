<?php
/*
Plugin Name: OrgChart
Description: An OrgChart plugin using Balkan OrgChart JS with data update.
Version: 1.0.0
Author: BALKAN App
*/

/**
 * Enqueue scripts and styles for the OrgChart plugin.
 *
 * This function loads the OrgChart JS library, our custom JavaScript for chart
 * initialization and AJAX communication, and localizes script data (AJAX URL, nonce,
 * and initial chart data) for use in the frontend JavaScript. It also enqueues
 * our custom CSS file.
 */
function orgchart_enqueue_scripts() {
    // Enqueue the Balkan OrgChart.js library.
    wp_enqueue_script( 'balkan-orgchart', 'https://cdn.balkan.app/orgchart.js', array(), '1.0', true );

    // Enqueue our custom JavaScript for OrgChart initialization and AJAX handling.
    // It depends on 'balkan-orgchart' to ensure it loads after the library.
    wp_enqueue_script( 
        'orgchart-script', 
        plugin_dir_url( __FILE__ ) . 
        'orgchart-script.js', 
        array('balkan-orgchart'), 
        filemtime( plugin_dir_path( __FILE__ ) . 'orgchart-script.js' ), // Update the file
        true 
    );

    // Enqueue our custom CSS file.
    wp_enqueue_style( 'orgchart-style', plugin_dir_url( __FILE__ ) . 'orgchart-style.css', array(), '1.0', 'all' );


    // Localize script: Pass PHP variables to our JavaScript.
    // 'orgchart_ajax' will be a global JavaScript object.
    wp_localize_script( 'orgchart-script', 'orgchart_ajax', array(
        'ajax_url'     => admin_url( 'admin-ajax.php' ), // WordPress AJAX endpoint URL
        'nonce'        => wp_create_nonce( 'orgchart_data_nonce' ), // Security nonce for AJAX requests
        // Get saved chart data from WordPress options, or an empty JSON array if no data exists.
        'initial_data' => get_option( 'orgchart_data', '[]' )
    ) );
}
add_action( 'wp_enqueue_scripts', 'orgchart_enqueue_scripts' );

/**
 * Shortcode to display the OrgChart.
 *
 * This function creates the `[orgchart]` shortcode. When the shortcode is
 * used in a post or page, it outputs the necessary HTML `div` element for the
 * OrgChart. The CSS is now loaded from an external file.
 *
 * @return string The HTML output for the OrgChart.
 */
function orgchart_display_shortcode() {
    // The div element where the OrgChart will be rendered by JavaScript.
    $output = '<div id="tree"></div>';
    return $output;
}
add_shortcode( 'orgchart', 'orgchart_display_shortcode' );

/**
 * AJAX handler for updating OrgChart data.
 *
 * This function processes AJAX requests from the frontend to add, update, or remove
 * nodes in the OrgChart. It saves the updated data to the `orgchart_data`
 * option in the WordPress database.
 */
function orgchart_update_data() {
    // 1. Security Check: Verify the nonce to protect against CSRF attacks.
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'orgchart_data_nonce' ) ) {
        wp_send_json_error( 'Nonce verification failed.' );
        wp_die(); // Terminate AJAX request.
    }

    // Sanitize and retrieve action type (add, update, remove) from POST data.
    $action_type = sanitize_text_field( $_POST['action_type'] );
    // Unslash and decode the JSON string of node data.
    $data_json   = wp_unslash( $_POST['data'] );
    // Sanitize and retrieve node ID for 'remove' action, if present.
    $node_id     = isset( $_POST['node_id'] ) ? sanitize_text_field( $_POST['node_id'] ) : '';

    // Retrieve the current OrgChart data from WordPress options.
    // Decode it from JSON into a PHP array. If no data exists, initialize as an empty array.
    $current_data = json_decode( get_option( 'orgchart_data', '[]' ), true );
    // Ensure $current_data is an array.
    if ( ! is_array( $current_data ) ) {
        $current_data = [];
    }

    // Initialize the response array.
    $response = array( 'success' => false );

    // Process the action based on the 'action_type'.
    switch ( $action_type ) {
        case 'add':
            $new_node = json_decode( $data_json, true );
            if ( $new_node ) {
                $current_data[] = $new_node; // Add the new node to the array.
                // Update the WordPress option with the new JSON-encoded data.
                $response['success'] = update_option( 'orgchart_data', json_encode( $current_data ) );
                $response['message'] = 'Node added successfully.';
                $response['new_data'] = $current_data; // Return the updated data.
            } else {
                $response['message'] = 'Invalid new node data provided.';
            }
            break;

        case 'update':
            $updated_node = json_decode( $data_json, true );
            if ( $updated_node && isset( $updated_node['id'] ) ) {
                $found = false;
                // Iterate through existing nodes to find and update the matching one.
                foreach ( $current_data as &$node ) { // Use reference for direct modification.
                    if ( $node['id'] == $updated_node['id'] ) {
                        // Merge old data with new data to preserve any fields not sent in the update.
                        $node = array_merge($node, $updated_node);
                        $found = true;
                        break;
                    }
                }
                unset( $node ); // Break the reference to avoid unintended side effects.
                if ( $found ) {
                    // Update the WordPress option with the new JSON-encoded data.
                    $response['success'] = update_option( 'orgchart_data', json_encode( $current_data ) );
                    $response['message'] = 'Node updated successfully.';
                    $response['new_data'] = $current_data; // Return the updated data.
                } else {
                    $response['message'] = 'Node not found for update.';
                }
            } else {
                $response['message'] = 'Invalid update node data or missing ID.';
            }
            break;

        case 'remove':
            if ( $node_id ) {
                $original_count = count( $current_data );
                // Filter out the node to be removed.
                $current_data = array_filter( $current_data, function( $node ) use ( $node_id ) {
                    return $node['id'] != $node_id;
                } );
                // Re-index the array to ensure sequential numeric keys after removal.
                $current_data = array_values($current_data);

                if ( count( $current_data ) < $original_count ) {
                    // Update the WordPress option with the new JSON-encoded data.
                    $response['success'] = update_option( 'orgchart_data', json_encode( $current_data ) );
                    $response['message'] = 'Node removed successfully.';
                    $response['new_data'] = $current_data; // Return the updated data.
                } else {
                    $response['message'] = 'Node not found for removal.';
                }
            } else {
                $response['message'] = 'Missing node ID for removal.';
            }
            break;

        default:
            $response['message'] = 'Unknown action type.';
            break;
    }

    // Send the JSON response back to the frontend.
    wp_send_json( $response );
    wp_die(); // Always terminate AJAX requests properly.
}

// Hook our AJAX handler to WordPress AJAX actions.
// 'wp_ajax_' handles requests from logged-in users.
add_action( 'wp_ajax_orgchart_update', 'orgchart_update_data' );
// 'wp_ajax_nopriv_' handles requests from non-logged-in users.
// You might remove this if only logged-in users should be able to modify the chart.
add_action( 'wp_ajax_nopriv_orgchart_update', 'orgchart_update_data' );

add_action('wp_ajax_upload_image_to_media', 'handle_image_upload');
add_action('wp_ajax_nopriv_upload_image_to_media', 'handle_image_upload');

function handle_image_upload() {
    if ( ! function_exists('wp_handle_upload') ) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    $uploadedfile = $_FILES['file'];

    $upload_overrides = array('test_form' => false);

    $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

    if ($movefile && !isset($movefile['error'])) {
        // Optionally, add to media library
        $attachment = array(
            'post_mime_type' => $movefile['type'],
            'post_title'     => sanitize_file_name($uploadedfile['name']),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        $attach_id = wp_insert_attachment($attachment, $movefile['file']);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        echo json_encode(array(
            'success' => true,
            'url'     => wp_get_attachment_url($attach_id),
            'id'      => $attach_id
        ));
    } else {
        echo json_encode(array(
            'success' => false,
            'error'   => $movefile['error']
        ));
    }

    wp_die(); // required to end AJAX call
}

/**
 * Plugin activation hook.
 *
 * This function runs when the plugin is activated. It checks if the OrgChart data
 * option already exists. If not, it sets up some default initial data for the chart.
 */
function orgchart_activate() {
    // Check if the option 'orgchart_data' does not exist (false return).
    if ( get_option( 'orgchart_data' ) === false ) {
        // Define some default organizational chart data.
        $default_data = [
            [ 'id' => '1', 'pid' => '', 'EmployeeName' => 'Jack Hill', 'Title' => 'Chairman and CEO', 'Email' => 'jack@example.com', 'ImgUrl' => 'https://cdn.balkan.app/shared/16.jpg', 'tags' => ['orange'] ],
            [ 'id' => '2', 'pid' => '1', 'EmployeeName' => 'Ann Smith', 'Title' => 'CTO', 'Email' => 'ann@example.com', 'ImgUrl' => 'https://cdn.balkan.app/shared/1.jpg' ],
            [ 'id' => '3', 'pid' => '1', 'EmployeeName' => 'Joe Brown', 'Title' => 'CFO', 'Email' => 'joe@example.com', 'ImgUrl' => 'https://cdn.balkan.app/shared/2.jpg' ]
        ];
        // Save the default data, JSON-encoded, to the WordPress options table.
        update_option( 'orgchart_data', json_encode( $default_data ) );
    }
}
// Register the activation hook.
register_activation_hook( __FILE__, 'orgchart_activate' );

/**
 * Plugin deactivation hook.
 *
 * This function runs when the plugin is deactivated. It cleans up by deleting
 * the saved OrgChart data from the WordPress options table.
 */
function orgchart_deactivate() {
    // Delete the option that stores the OrgChart data.
    delete_option( 'orgchart_data' );
}
// Register the deactivation hook.
register_deactivation_hook( __FILE__, 'orgchart_deactivate' );

