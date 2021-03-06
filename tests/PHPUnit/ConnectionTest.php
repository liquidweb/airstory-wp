<?php
/**
 * Tests for the connection functionality.
 *
 * @package Airstory
 */

namespace Airstory\Connection;

use WP_Mock as M;
use Mockery;
use Patchwork;
use WP_Error;
use WP_User_Query;

class ConnectionTest extends \Airstory\TestCase {

	protected $testFiles = array(
		'connection.php',
		'credentials.php',
		'settings.php',
	);

	public function tearDown() {
		Patchwork\undoAll();
		WP_User_Query::tearDown();

		parent::tearDown();
	}

	public function testGetUserProfile() {
		$response = new \stdClass;
		$response->id         = 'uXXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX';
		$response->first_name = 'Leroy';
		$response->last_name  = 'Jenkins';
		$response->email      = 'leroy.jenkins@example.com';
		$response->created    = time();

		Patchwork\replace( 'Airstory\API::get_user', function () use ( $response ) {
			return $response;
		} );

		M::userFunction( 'is_wp_error', array(
			'return' => false,
		) );

		M::passthruFunction( 'sanitize_text_field' );

		$this->assertEquals(
			array(
				'user_id'    => $response->id,
				'first_name' => $response->first_name,
				'last_name'  => $response->last_name,
				'email'      => $response->email,
			),
			get_user_profile(),
			'More data than is required appears to be leaking from the /user endpoint'
		);
	}

	public function testGetUserProfileWithUserId() {
		$token    = uniqid();
		$response = new \stdClass;
		$response->id         = 'uXXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX';
		$response->first_name = 'Leroy';
		$response->last_name  = 'Jenkins';
		$response->email      = 'leroy.jenkins@example.com';
		$response->created    = time();

		Patchwork\replace( 'Airstory\API::set_token', function ( $val ) use ( $token ) {
			if ( $token !== $val ) {
				$this->fail( 'The API token is not being set based on the user ID argument.' );
			}
		} );

		Patchwork\replace( 'Airstory\API::get_user', function () use ( $response ) {
			return $response;
		} );

		M::userFunction( 'Airstory\Credentials\get_token', array(
			'args'   => array( 123 ),
			'return' => $token,
		) );

		M::userFunction( 'is_wp_error', array(
			'return' => false,
		) );

		M::passthruFunction( 'sanitize_text_field' );

		get_user_profile( 123 );
	}

	public function testGetUserProfileReturnsEmptyArrayIfAPIResponseFails() {
		Patchwork\replace( 'Airstory\API::get_user', function () {
			return new WP_Error();
		} );

		M::userFunction( 'is_wp_error', array(
			'return' => true,
		) );

		$this->assertEquals( array(), get_user_profile(), 'WP_Errors should produce empty profile arrays' );
	}

	public function testGetTarget() {
		M::userFunction( 'get_bloginfo', array(
			'args'   => array( 'name' ),
			'return' => 'Example Blog',
		) );

		M::userFunction( __NAMESPACE__ . '\get_webhook_uri', array(
			'return' => 'http://example.com/airstory/v1/webhook'
		) );

		M::passthruFunction( 'esc_url_raw' );

		$response = get_target( 5 );

		$this->assertEquals( '5', $response['identifier'] );
		$this->assertEquals( 'Example Blog', $response['name'] );
		$this->assertEquals( 'http://example.com/airstory/v1/webhook', $response['url'] );
		$this->assertEquals( 'wordpress', $response['type'] );
	}

	public function testGetWebhookUri() {
		$url = 'http://example.com/airstory/v1/webhook';

		M::userFunction( 'get_rest_url', array(
			'args'   => array( null, '/airstory/v1/webhook', 'https' ),
			'return' => $url,
		) );

		M::userFunction( 'wp_cache_get' );

		M::userFunction( 'wp_remote_head', array(
			'times' => 1,
			'args'  => array( $url ),
		) );

		M::userFunction( 'wp_remote_retrieve_header', array(
			'return' => '',
		) );

		M::userFunction( 'wp_cache_set', array(
			'times' => 1,
			'args'  => array( 'airstory_webhook_uri', $url, 'airstory' ),
		) );

		$this->assertEquals( $url, get_webhook_uri() );
	}

	public function testGetWebhookUriChecksForRedirects() {
		$url = 'http://example.com/airstory/v1/webhook';

		M::userFunction( 'get_rest_url', array(
			'return' => $url,
		) );

		M::userFunction( 'wp_parse_url', array(
			'returnUsing' => '/airstory/v1/webhook/',
		) );

		M::userFunction( 'wp_cache_get' );
		M::userFunction( 'wp_remote_head' );
		M::userFunction( 'wp_remote_retrieve_header', array(
			'return' => $url . '/',
		) );

		M::userFunction( 'wp_cache_set', array(
			'times' => 1,
			'args'  => array( 'airstory_webhook_uri', $url . '/', 'airstory' ),
		) );

		$this->assertEquals( $url . '/', get_webhook_uri() );
	}

	public function testGetWebhookUriCreatesAbsoluteRedirectUris() {
		$url = 'http://example.com/airstory/v1/webhook';

		M::userFunction( 'get_rest_url', array(
			'return' => $url,
		) );

		M::userFunction( 'wp_parse_url', array(
			'return' => '/airstory/v1/webhook/',
		) );

		M::userFunction( 'site_url', array(
			'return' => $url . '/',
		) );

		M::userFunction( 'wp_cache_get' );
		M::userFunction( 'wp_remote_head' );
		M::userFunction( 'wp_remote_retrieve_header', array(
			'return' => '/airstory/v1/webhook/',
		) );

		M::userFunction( 'wp_cache_set', array(
			'times' => 1,
			'args'  => array( 'airstory_webhook_uri', $url . '/', 'airstory' ),
		) );

		$this->assertEquals( $url . '/', get_webhook_uri() );
	}

	public function testGetWebhookUriReturnsFromCache() {
		M::userFunction( 'wp_cache_get', array(
			'return' => 'http://example.com/foo',
		) );

		M::userFunction( 'get_rest_url', array(
			'times' => 0,
		) );

		$this->assertEquals( 'http://example.com/foo', get_webhook_uri() );
	}

	public function testHasConnection() {
		M::userFunction( 'get_user_option', array(
			'args'            => array( '_airstory_target', 5 ),
			'return_in_order' => array( uniqid(), '', false ),
		) );

		$this->assertTrue( has_connection( 5 ) );
		$this->assertFalse( has_connection( 5 ) );
		$this->assertFalse( has_connection( 5 ) );
	}

	public function testRegisterConnection() {
		Patchwork\replace( 'Airstory\API::post_target', function () {
			return 'connection-id';
		} );

		M::userFunction( __NAMESPACE__ . '\has_connection', array(
			'return' => false,
		) );

		M::userFunction( __NAMESPACE__ . '\get_user_profile', array(
			'return' => array(
				'email' => 'test@example.com',
			),
		) );

		M::userFunction( __NAMESPACE__ . '\get_target', array(
			'return' => array(
				'identifier' => '123',
				'name'       => 'My site',
				'url'        => 'https://example.com/webhook',
			),
		) );

		M::userFunction( 'is_wp_error', array(
			'return' => false,
		) );

		M::userFunction( 'Airstory\Settings\set_user_data', array(
			'times'  => 1,
			'args'   => array( 123, 'profile', array( 'email' => 'test@example.com' ) ),
		) );

		M::userFunction( 'update_user_option', array(
			'times'  => 1,
			'args'   => array( 123, '_airstory_target', 'connection-id' ),
		) );

		M::expectAction( 'airstory_register_connection', 123, 'connection-id', array(
			'identifier' => '123',
			'name'       => 'My site',
			'url'        => 'https://example.com/webhook',
		) );

		M::passthruFunction( 'sanitize_text_field' );

		$this->assertEquals( 'connection-id', register_connection( 123 ) );
	}

	public function testRegisterConnectionReturnsEarlyIfNoProfileDataFound() {
		M::userFunction( __NAMESPACE__ . '\has_connection', array(
			'return' => false,
		) );

		M::userFunction( __NAMESPACE__ . '\get_user_profile', array(
			'return' => array(),
		) );

		M::expectActionAdded( 'user_profile_update_errors', 'Airstory\Settings\profile_error_save_token' );

		$this->assertInstanceOf( 'WP_Error', register_connection( 123 ) );
	}

	public function testRegisterConnectionHandlesWPErrors() {
		$response = new WP_Error();

		Patchwork\replace( 'Airstory\API::post_target', function () use ( $response ) {
			return $response;
		} );

		M::userFunction( __NAMESPACE__ . '\has_connection', array(
			'return' => false,
		) );

		M::userFunction( __NAMESPACE__ . '\get_user_profile', array(
			'return' => array(
				'email' => 'test@example.com',
			),
		) );

		M::userFunction( 'get_bloginfo', array(
			'return' => 'My site',
		) );

		M::userFunction( 'get_rest_url', array(
			'return' => 'https://example.com/webhook',
		) );

		M::userFunction( 'is_wp_error', array(
			'return' => true,
		) );

		M::expectActionAdded( 'user_profile_update_errors', 'Airstory\Settings\profile_error_save_token' );

		$this->assertInstanceOf( 'WP_Error', register_connection( 123 ) );
	}

	public function testRegisterConnectionChecksForExistingConnectionFirst() {
		M::userFunction( __NAMESPACE__ . '\has_connection', array(
			'return' => true,
		) );

		M::userFunction( __NAMESPACE__ . '\get_user_profile', array(
			'times'  => 0,
		) );

		register_connection( 123 );
	}

	public function testUpdateConnection() {
		$phpunit = $this;
		$target  = uniqid();

		Patchwork\replace( 'Airstory\API::put_target', function ( $email, $connection_id, $target_arr ) use ( $phpunit, $target ) {
			if ( 'test@example.com' !== $email ) {
				$phpunit->fail( 'The expected email address was not passed' );
			} elseif ( $target !== $connection_id ) {
				$phpunit->fail( 'The expected connection ID was not passed' );
			} elseif ( array( 'identifier' => '5' ) !== $target_arr ) {
				$phpunit->fail( 'The expected target was not passed' );
			}
		} );

		M::userFunction( __NAMESPACE__ . '\get_user_profile', array(
			'args'   => array( 5 ),
			'return' => array( 'email' => 'test@example.com' ),
		) );

		M::userFunction( 'get_user_option', array(
			'args'   => array( '_airstory_target', 5 ),
			'return' => $target,
		) );

		M::userFunction( __NAMESPACE__ . '\get_target', array(
			'return' => array( 'identifier' => '5' ),
		) );

		M::expectAction( 'airstory_update_connection', 5, $target, array( 'identifier' => '5' ) );

		$this->assertEquals( $target, update_connection( 5 ) );
	}

	public function testRemoveConnection() {
		$connection_id = uniqid();
		$profile       = array(
			'email' => 'test@example.com',
		);

		Patchwork\replace( 'Airstory\API::delete_target', function () use ( $connection_id ) {
			return $connection_id;
		} );

		M::userFunction( 'Airstory\Settings\get_user_data', array(
			'args'   => array( 123, 'profile', array() ),
			'return' => $profile,
		) );

		M::userFunction( 'get_user_option', array(
			'args'   => array( '_airstory_target', 123 ),
			'return' => $connection_id,
		) );

		M::userFunction( 'delete_user_option', array(
			'times'  => 1,
			'args'   => array( 123, '_airstory_target' ),
		) );

		M::expectAction( 'airstory_remove_connection', 123, $connection_id );

		remove_connection( 123 );
	}

	public function testRemoveConnectionOnlyDeletesIfItHasTheUserEmail() {
		Patchwork\replace( 'Airstory\API::delete_target', function () {
			$this->fail( 'This should never be called without the user email address' );
		} );

		M::userFunction( 'Airstory\Settings\get_user_data', array(
			'args'   => array( 123, 'profile', array() ),
			'return' => array( 'email' => '' )
		) );

		M::userFunction( 'get_user_option', array(
			'args'   => array( '_airstory_target', 123 ),
			'return' => uniqid(),
		) );

		M::userFunction( 'delete_user_option', array(
			'times'  => 1,
		) );

		remove_connection( 123 );
	}

	public function testRemoveConnectionOnlyDeletesIfItHasTheConnectionID() {
		Patchwork\replace( 'Airstory\API::delete_target', function () {
			$this->fail( 'This should never be called without the connection ID' );
		} );

		M::userFunction( 'Airstory\Settings\get_user_data', array(
			'args'   => array( 123, 'profile', array() ),
			'return' => array( 'email' => 'test@example.com' ),
		) );

		M::userFunction( 'get_user_option', array(
			'args'   => array( '_airstory_target', 123 ),
			'return' => null,
		) );

		M::userFunction( 'delete_user_option', array(
			'times'  => 1,
		) );

		remove_connection( 123 );
	}

	public function testSetConnectedSites() {
		M::userFunction( 'is_multisite', array(
			'return' => true,
		) );

		M::userFunction( 'Airstory\Settings\get_available_blogs', array(
			'return' => array(
				array( 'id' => 1 ),
				array( 'id' => 2 ),
				array( 'id' => 3 ),
				array( 'id' => 4 ),
				array( 'id' => 5 ),
			),
		) );

		M::userFunction( 'switch_to_blog', array(
			'times' => 5,
		) );

		M::userFunction( __NAMESPACE__ . '\register_connection', array(
			'times' => 3,
		) );

		M::userFunction( __NAMESPACE__ . '\remove_connection', array(
			'times' => 2,
		) );

		M::userFunction( 'restore_current_blog', array(
			'times' => 5,
		) );

		M::passthruFunction( 'absint' );

		set_connected_blogs( 5, array( 1, 2, 3 ) );
	}

	public function testSetConnectedSitesReturnsEarlyIfNotMultisite() {
		M::userFunction( 'is_multisite', array(
			'return' => false,
		) );

		M::userFunction( 'Airstory\Settings\get_available_blogs', array(
			'times'  => 0,
		) );

		set_connected_blogs( 5, array() );
	}

	public function testTriggerConnectionRefresh() {
		M::userFunction( 'wp_cache_delete', array(
			'times' => 1,
			'args'  => array( 'airstory_webhook_uri', 'airstory' ),
		) );

		M::expectAction( 'airstory_update_all_connections' );

		trigger_connection_refresh( 'old', 'new' );
	}

	public function testTriggerConnectionRefreshOnlyFiresIfValuesChanged() {
		M::userFunction( 'wp_cache_delete', array(
			'times' => 0,
		) );

		trigger_connection_refresh( 'same', 'same' );
	}
}

/**
 * Mock representation of the API class.
 */
class API {
	public static $response;

	public function __call( $name, $args ) {
		return self::$response;
	}
}
