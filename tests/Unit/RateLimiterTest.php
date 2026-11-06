<?php
/**
 * Tests for RateLimiter.
 *
 * @package PratikLondhe\PratiksLoginShield
 */

namespace PratikLondhe\PratiksLoginShield\Tests\Unit;

use Brain\Monkey\Functions;
use PratikLondhe\PratiksLoginShield\Auth\RateLimiter;
use PratikLondhe\PratiksLoginShield\Settings\SettingsPage;
use WP_Error;

/**
 * Validates failed login tracking, lockout thresholds, and unlocking.
 */
class RateLimiterTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_user' )->returnArg();
		Functions\when( 'wp_parse_args' )->alias(
			function ( $args, $defaults ) {
				return array_merge( $defaults, (array) $args );
			}
		);
		Functions\when( 'wp_generate_password' )->justReturn( 'test-rescue-key-12345' );
	}

	public function test_get_client_ip_returns_valid_ip(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.195';
		$this->assertSame( '203.0.113.195', RateLimiter::get_client_ip() );

		$_SERVER['REMOTE_ADDR'] = 'not-an-ip';
		$this->assertSame( '127.0.0.1', RateLimiter::get_client_ip() );
	}

	public function test_increment_and_clear_attempts(): void {
		$ip = '198.51.100.4';
		$this->assertSame( 0, RateLimiter::get_attempts_count( $ip ) );

		$count1 = RateLimiter::increment_attempts( $ip );
		$this->assertSame( 1, $count1 );
		$this->assertSame( 1, RateLimiter::get_attempts_count( $ip ) );

		$count2 = RateLimiter::increment_attempts( $ip );
		$this->assertSame( 2, $count2 );

		RateLimiter::clear_attempts( $ip );
		$this->assertSame( 0, RateLimiter::get_attempts_count( $ip ) );
	}

	public function test_lock_ip_and_active_lockouts(): void {
		$ip = '192.0.2.1';
		$this->assertFalse( RateLimiter::is_ip_locked( $ip ) );

		RateLimiter::lock_ip( $ip, 'attacker', 4 );

		$this->assertTrue( RateLimiter::is_ip_locked( $ip ) );
		$info = RateLimiter::get_lockout_info( $ip );

		$this->assertNotNull( $info );
		$this->assertSame( 4, $info['attempts'] );
		$this->assertSame( 'attacker', $info['last_user'] );
		$this->assertGreaterThan( time(), $info['locked_until'] );
	}

	public function test_unlock_ip(): void {
		$ip = '192.0.2.2';
		RateLimiter::lock_ip( $ip, 'victim', 4 );
		$this->assertTrue( RateLimiter::is_ip_locked( $ip ) );

		$unlocked = RateLimiter::unlock_ip( $ip );
		$this->assertTrue( $unlocked );
		$this->assertFalse( RateLimiter::is_ip_locked( $ip ) );
	}

	public function test_unlock_all(): void {
		RateLimiter::lock_ip( '1.1.1.1', 'user1', 4 );
		RateLimiter::lock_ip( '2.2.2.2', 'user2', 4 );

		$this->assertCount( 2, RateLimiter::get_active_lockouts() );

		RateLimiter::unlock_all();
		$this->assertEmpty( RateLimiter::get_active_lockouts() );
	}

	public function test_expired_lockouts_are_purged(): void {
		$ip_expired = '192.0.2.10';
		$ip_active  = '192.0.2.20';

		$lockouts = array(
			$ip_expired => array(
				'locked_until' => time() - 100, // in the past
				'attempts'     => 4,
				'last_user'    => 'old',
				'created_at'   => time() - 1000,
			),
			$ip_active  => array(
				'locked_until' => time() + 1000, // in the future
				'attempts'     => 4,
				'last_user'    => 'active',
				'created_at'   => time(),
			),
		);

		update_option( RateLimiter::OPTION_LOCKOUTS, $lockouts );

		$active = RateLimiter::get_active_lockouts();
		$this->assertArrayNotHasKey( $ip_expired, $active );
		$this->assertArrayHasKey( $ip_active, $active );
	}

	public function test_check_lockout_before_auth_blocks_locked_ip(): void {
		$limiter = new RateLimiter();
		$ip      = '192.0.2.99';
		$_SERVER['REMOTE_ADDR'] = $ip;

		RateLimiter::lock_ip( $ip, 'admin', 4 );

		$result = $limiter->check_lockout_before_auth( null, 'admin', 'wrong-pass' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'pratiks_ip_locked', $result->get_error_code() );
	}
}
