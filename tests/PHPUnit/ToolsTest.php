<?php
/**
 * Tests for the plugin tools.
 *
 * @package Airstory
 */

namespace Airstory\Tools;

use WP_Mock as M;
use Mockery;

class ToolsTest extends \Airstory\TestCase {

	protected $testFiles = array(
		'tools.php',
	);

	public function testRegisterMenuPage() {
		M::userFunction( 'add_submenu_page', array(
			'times'  => 1,
			'args'   => array( 'tools.php', '*', '*', 'manage_options', 'airstory', __NAMESPACE__ . '\render_tools_page' ),
		) );

		register_menu_page();
	}

	public function testRenderToolsPage() {
		$this->markTestIncomplete();

		M::passthruFunction( 'esc_html' );

		render_tools_page();
	}

	/**
 	 * @requires extension dom
 	 * @requires extension mcrypt
 	 * @requires extension openssl
 	 */
	public function testCheckCompatibility() {
		$compatibility = check_compatibility();

		$this->assertTrue( $compatibility['compatible'], 'The compatibility array should include a single go/no-go for compatibility' );
		$this->assertArrayHasKey( 'details', $compatibility, 'The compatibility array should include details for each dependency' );
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testCheckCompatibilityWithDom() {
		M::userFunction( __NAMESPACE__ . '\extension_loaded', array(
			'return' => false,
		) );

		$compatibility = check_compatibility();

		$this->assertFalse( $compatibility['compatible'] );
		$this->assertFalse( $compatibility['details']['dom']['compatible'] );
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testCheckCompatibilityWithMcrypt() {
		M::userFunction( __NAMESPACE__ . '\extension_loaded', array(
			'return' => false,
		) );

		$compatibility = check_compatibility();

		$this->assertFalse( $compatibility['compatible'] );
		$this->assertFalse( $compatibility['details']['mcrypt']['compatible'] );
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testCheckCompatibilityWithOpenSSL() {
		M::userFunction( __NAMESPACE__ . '\extension_loaded', array(
			'return' => false,
		) );

		$compatibility = check_compatibility();

		$this->assertFalse( $compatibility['compatible'] );
		$this->assertFalse( $compatibility['details']['openssl']['compatible'] );
	}
}

/**
 * Test double for extension_loaded() within the Airstory\Tools namespace.
 *
 * @param string $ext The extension to check whether or not it's loaded.
 * @return bool True if the extension is loaded, false otherwise.
 */
function extension_loaded( $ext ) {
	return \extension_loaded( $ext );
}
