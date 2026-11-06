<?php
/**
 * Tests for XmlRpcBlocker.
 *
 * @package PratikLondhe\PratiksLoginShield
 */

namespace PratikLondhe\PratiksLoginShield\Tests\Unit;

use PratikLondhe\PratiksLoginShield\Auth\XmlRpcBlocker;

/**
 * Validates XML-RPC filtering behavior.
 */
class XmlRpcBlockerTest extends TestCase {

	public function test_filter_xmlrpc_methods_returns_empty_array(): void {
		$blocker = new XmlRpcBlocker();
		$methods = array(
			'system.listMethods' => 'this:system_listMethods',
			'wp.getPosts'        => 'this:wp_getPosts',
		);

		$this->assertEmpty( $blocker->filter_xmlrpc_methods( $methods ) );
	}

	public function test_filter_wp_headers_removes_x_pingback(): void {
		$blocker = new XmlRpcBlocker();
		$headers = array(
			'Content-Type' => 'text/html',
			'X-Pingback'   => 'http://example.com/xmlrpc.php',
		);

		$filtered = $blocker->filter_wp_headers( $headers );
		$this->assertArrayNotHasKey( 'X-Pingback', $filtered );
		$this->assertArrayHasKey( 'Content-Type', $filtered );
	}
}
