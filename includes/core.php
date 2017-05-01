<?php
/**
 * Core functionality for Airstory.
 *
 * @package Airstory.
 */

namespace Airstory\Core;

use Airstory;
use WP_Query;

/**
 * Retrieve an existing post based on Airstory project and document IDs.
 *
 * Since we're tracking the original project and document IDs, we can prevent multiple copies of
 * the same post from being made by cross-referencing these values with post meta.
 *
 * @param string $project_id  The Airstory project UUID.
 * @param string $document_id The Airstory document UUID.
 * @return int Either the ID of a NON-published post with those IDs or 0 if no such post exists.
 */
function get_current_draft( $project_id, $document_id ) {
	$query_args = array(
		'post_type'              => 'post',
		'post_status'            => array( 'draft', 'pending' ),
		'no_found_rows'          => true,
		'update_term_meta_cache' => false,
		'fields'                 => 'ids',
		'posts_per_page'         => 1,
		'meta_query'             => array(
			array(
				'key'   => '_airstory_project_id',
				'value' => $project_id,
			),
			array(
				'key'   => '_airstory_document_id',
				'value' => $document_id,
			),
		),
	);

	/**
	 * Filters the WP_Query arguments used to determine if a post has already been imported (as a
	 * draft) into WordPress.
	 *
	 * @param array $query_args WP_Query arguments to find a matching draft.
	 */
	$query_args = apply_filters( 'airstory_get_current_draft', $query_args );
	$query      = new WP_Query( $query_args );

	return empty( $query->posts ) ? 0 : (int) current( $query->posts );
}

/**
 * Given an Airstory project and document UUIDs, call out to the Airstory API and assemble a
 * WordPress post.
 *
 * @param Airstory\API $api         An instance of the Airstory API class.
 * @param string       $project_id  The Airstory project UUID.
 * @param string       $document_id The Airstory document UUID.
 * @return int|WP_Error The ID of the newly-created post or a WP_Error object if anything went
 *                      wrong during the creation of the post.
 */
function import_document( Airstory\API $api, $project_id, $document_id ) {
	$document = $api->get_document( $project_id, $document_id );

	// Something went wrong getting metadata about the document.
	if ( is_wp_error( $document ) ) {
		return $document;
	}

	// Begin assembling the post.
	$post = array(
		'post_title'   => sanitize_text_field( $document->title ),
		'post_status'  => 'draft',
		'post_type'    => 'post',
		'post_content' => '',
	);

	// Next, retrieve the post content.
	$contents = $api->get_document_content( $project_id, $document_id );

	// Unable to retrieve the rendered content.
	if ( is_wp_error( $contents ) ) {
		return $contents;
	}

	/**
	 * Filters the Airstory document content before inserting it into the wp_insert_post() array.
	 *
	 * @param string $document The compiled, HTML response from Airstory.
	 */
	$contents = apply_filters( 'airstory_before_insert_content', $contents );
	$post['post_content'] = wp_kses_post( $contents );

	/**
	 * Filter arguments for new posts from Airstory before they're inserted into the database.
	 *
	 * @see wp_insert_post()
	 *
	 * @param array $post An array of arguments for wp_insert_post().
	 */
	$post = apply_filters( 'airstory_before_insert_post', $post );

	// Finally, insert the post.
	$post_id = wp_insert_post( $post );

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	// Store the Airstory project and document IDs in post meta.
	add_post_meta( $post_id, '_airstory_project_id', sanitize_text_field( $project_id ), true );
	add_post_meta( $post_id, '_airstory_document_id', sanitize_text_field( $document_id ), true );

	/**
	 * Fires after an Airstory post has been successfully inserted into WordPress.
	 *
	 * @param int $post_id The ID of the newly-created post.
	 */
	do_action( 'airstory_import_post', $post_id );

	return $post_id;
}

/**
 * Given an Airstory project and document UUIDs, call out to the Airstory API and assemble a
 * WordPress post.
 *
 * @param Airstory\API $api         An instance of the Airstory API class.
 * @param string       $project_id  The Airstory project UUID.
 * @param string       $document_id The Airstory document UUID.
 * @param int          $post_id     The ID of the post that already exists within WordPress.
 * @return int|WP_Error The ID of the newly-created post or a WP_Error object if anything went
 *                      wrong during the updating of the post.
 */
function update_document( Airstory\API $api, $project_id, $document_id, $post_id ) {
	$document = $api->get_document( $project_id, $document_id );

	// Something went wrong getting metadata about the document.
	if ( is_wp_error( $document ) ) {
		return $document;
	}

	// Next, retrieve the post content.
	$contents = $api->get_document_content( $project_id, $document_id );

	// Unable to retrieve the rendered content.
	if ( is_wp_error( $contents ) ) {
		return $contents;
	}

	/** This filter is defined in includes/core.php. */
	$contents = apply_filters( 'airstory_before_insert_content', $contents );
	$post     = array(
		'ID'           => $post_id,
		'post_content' => wp_kses_post( $contents ),
	);

	/** This filter is defined in includes/core.php. */
	$post = apply_filters( 'airstory_before_insert_post', $post );

	// Finally, insert the post.
	$post_id = wp_update_post( $post );

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	/**
	 * Fires after an Airstory post has been successfully updated within WordPress.
	 *
	 * @param int $post_id The ID of the updated post.
	 */
	do_action( 'airstory_update_post', $post_id );

	return $post_id;
}
