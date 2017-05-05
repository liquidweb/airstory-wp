<?php
/**
 * User settings for connecting to Airstory.
 *
 * @package Airstory
 */

namespace Airstory\Settings;

use Airstory\Connection as Connection;
use Airstory\Credentials as Credentials;

/**
 * Render the "Airstory" settings section on the user profile page.
 *
 * @param WP_User $user The current user object.
 */
function render_profile_settings( $user ) {
	$profile = get_user_option( '_airstory_profile', $user->ID );
?>

	<h2><?php esc_html_e( 'Airstory Configuration', 'airstory' ); ?></h2>
	<table class="form-table">
		<tbody>
			<tr>
				<th><label for="airstory-token"><?php esc_html_e( 'User Token', 'airstory' ); ?></label></th>
				<td>
					<?php if ( ! empty( $profile['email'] ) ) : ?>

						<input name="airstory-disconnect" type="submit" class="button" value="<?php esc_attr_e( 'Disconnect from Airstory', '' ); ?>" />
						<p class="description">
							<?php echo wp_kses_post( sprintf( __( 'Currently authenticated as <strong>%s</strong>', 'airstory' ), $profile['email'] ) ); ?>
						</p>

					<?php else : ?>

						<input name="airstory-token" id="airstory-token" type="password" class="regular-text" />
						<p class="description"><?php echo wp_kses_post( __( 'You can retrieve your user token from <a href="https://app.airstory.co/projects?overlay=account" target="_blank">your Airstory account settings</a>.', 'airstory' ) ); ?></p>

					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>

<?php
	wp_nonce_field( 'airstory-profile', '_airstory_nonce' );
}
add_action( 'show_user_profile', __NAMESPACE__ . '\render_profile_settings' );

/**
 * Save the user's profile settings.
 *
 * @param int $user_id The user ID.
 * @return bool Whether or not the user meta was updated successfully.
 */
function save_profile_settings( $user_id ) {
	if ( ! isset( $_POST['_airstory_nonce'] )
		|| ! wp_verify_nonce( $_POST['_airstory_nonce'], 'airstory-profile' )
		|| ! current_user_can( 'edit_user', $user_id )
	) {
		return false;
	}

	$token = get_user_option( '_airstory_token', $user_id );

	// The user is disconnecting.
	if ( $token && isset( $_POST['airstory-disconnect'] ) ) {

		/**
		 * A user has disconnected their account from Airstory.
		 *
		 * @param int $user_id The ID of the user that just disconnected.
		 */
		do_action( 'airstory_user_disconnect', $user_id );

		return Credentials\clear_token( $user_id );

	} elseif ( empty( $_POST['airstory-token'] ) ) {

		// No disconnection, but no token value, either.
		return false;
	}

	// Store the user meta.
	$new_token = sanitize_text_field( $_POST['airstory-token'] );
	$result    = Credentials\set_token( $user_id, $new_token );

	/**
	 * A user has connected their account to Airstory.
	 *
	 * @param int $user_id The ID of the user that just connected.
	 */
	do_action( 'airstory_user_connect', $user_id );

	return (bool) $result;
}
add_action( 'personal_options_update', __NAMESPACE__ . '\save_profile_settings' );

/**
 * Generate a list of blogs that $user_id is a member of *and* can publish to.
 *
 * This is only used in WordPress Multisite, but will allow users to manage their connections with
 * each site in a network from their profile page.
 *
 * @param int $user_id The WordPress user ID.
 * @return array {
 *   An array of blogs the user is able to publish to. This will be an array of arrays.
 *
 *   @var int    $id    The WordPress blog ID.
 *   @var string $title The WordPress blog name.
 *   @var bool   $connected Whether or not there's an active connection with the blog.
 * }
 */
function get_available_blogs( $user_id ) {
	$all_blogs = get_blogs_of_user( $user_id );
	$blogs     = array();

	/*
	 * Go through each blog this user is a member of and determine if the user:
	 * - Can at least create (if not publish) new posts, making them at least a Contributor.
	 * - Already has an active Airstory connection for the blog.
	 *
	 * This is a rather intensive process, and may take a while for users that are members of many
	 * blogs in the network.
	 */
	foreach ( $all_blogs as $blog_id => $blog ) {
		switch_to_blog( $blog_id );

		// Don't bother checking tokens if the user can't publish.
		if ( user_can( $user_id, 'edit_posts' ) ) {

			$blogs[] = array(
				'id'        => (int) $blog_id,
				'title'     => $blog->blogname,
				'connected' => Connection\has_connection( $user_id ),
			);
		}

		restore_current_blog();
	}

	return $blogs;
}
