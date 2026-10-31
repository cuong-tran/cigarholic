<?php
/**
 * Tests for the signup feature.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use Cigarholic\Core\Plugin;
use Cigarholic\Core\Signups\Repository;
use Cigarholic\Core\Signups\Signups;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Feature IDs: CNT-04, MKT-01.
 */
final class SignupsTest extends WP_UnitTestCase {

	/**
	 * The feature under test.
	 *
	 * @var Signups
	 */
	private Signups $signups;

	/**
	 * Fresh table for every test.
	 */
	public function set_up(): void {
		parent::set_up();
		$feature = Plugin::instance()->features()->get( 'signups' );
		$this->assertInstanceOf( Signups::class, $feature );
		$this->signups = $feature;
		Repository::install();
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . Repository::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- test isolation on our own table.
		delete_transient( 'cigarholic_signup_' . md5( '127.0.0.1' ) );
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		// No calls to Cloudflare from unit tests; the Turnstile outcome is decided per test.
		add_filter( 'cigarholic_turnstile_verified', '__return_true' );
	}

	/**
	 * A failed Turnstile check is refused with its own error code.
	 */
	public function test_turnstile_failure_is_refused(): void {
		remove_filter( 'cigarholic_turnstile_verified', '__return_true' );
		add_filter( 'cigarholic_turnstile_verified', '__return_false' );
		$result = $this->signups->subscribe( 'human@example.test', 'coming-soon', 'token', '' );
		$this->assertWPError( $result );
		$this->assertSame( 'verification_failed', $result->get_error_code() );
		$this->assertSame( 0, Repository::count() );
	}

	/**
	 * A valid email is stored once; the same email again is accepted without a duplicate row.
	 */
	public function test_subscribe_is_idempotent(): void {
		$this->assertTrue( $this->signups->subscribe( 'Owner@Example.test', 'coming-soon', '', '' ) );
		$this->assertTrue( $this->signups->subscribe( 'owner@example.test', 'footer', '', '' ) );
		$this->assertSame( 1, Repository::count() );
		$rows = Repository::all();
		$this->assertSame( 'owner@example.test', $rows[0]['email'] );
		$this->assertSame( 'coming-soon', $rows[0]['source'], 'the first source is kept' );
	}

	/**
	 * Invalid addresses and filled honeypots never reach the table.
	 */
	public function test_validation_and_honeypot(): void {
		$result = $this->signups->subscribe( 'not-an-email', 'coming-soon', '', '' );
		$this->assertWPError( $result );
		$this->assertSame( 'invalid_email', $result->get_error_code() );

		$this->assertTrue( $this->signups->subscribe( 'bot@example.test', 'coming-soon', '', 'http://spam.example' ), 'bots get a silent success' );
		$this->assertSame( 0, Repository::count() );
	}

	/**
	 * More than RATE_LIMIT attempts a minute from one IP are refused.
	 */
	public function test_rate_limit(): void {
		for ( $i = 1; $i <= Signups::RATE_LIMIT; $i++ ) {
			$this->assertTrue( $this->signups->subscribe( "user{$i}@example.test", 'coming-soon', '', '' ) );
		}
		$result = $this->signups->subscribe( 'one-too-many@example.test', 'coming-soon', '', '' );
		$this->assertWPError( $result );
		$this->assertSame( 'rate_limited', $result->get_error_code() );
	}

	/**
	 * The REST endpoint stores and answers; bad input gets 400.
	 */
	public function test_rest_endpoint(): void {
		$request = new WP_REST_Request( 'POST', '/' . Signups::REST_NAMESPACE . '/signups' );
		$request->set_param( 'email', 'rest@example.test' );
		$request->set_param( 'source', 'coming-soon' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['ok'] );
		$this->assertSame( 1, Repository::count() );

		$bad = new WP_REST_Request( 'POST', '/' . Signups::REST_NAMESPACE . '/signups' );
		$bad->set_param( 'email', 'nope' );
		$this->assertSame( 400, rest_get_server()->dispatch( $bad )->get_status() );
	}

	/**
	 * CSV export has a header and one line per signup, with formula injection neutralised.
	 */
	public function test_csv_export(): void {
		Repository::subscribe( 'a@example.test', 'coming-soon' );
		$csv   = Signups::csv( Repository::all() );
		$lines = explode( "\n", trim( $csv ) );
		$this->assertSame( 'email,source,created_at_utc,synced_at_utc', $lines[0] );
		$this->assertCount( 2, $lines );
		$this->assertStringContainsString( 'a@example.test,coming-soon,', $lines[1] );

		$this->assertStringContainsString(
			"'=cmd",
			Signups::csv(
				array(
					array(
						'id'         => 1,
						'email'      => '=cmd()@x.test',
						'source'     => 's',
						'created_at' => '2026-01-01 00:00:00',
						'synced_at'  => null,
					),
				)
			)
		);
	}

	/**
	 * The block renders a labelled form posting to admin-post with the REST endpoint as data attribute.
	 */
	public function test_block_renders(): void {
		$html = do_blocks( '<!-- wp:cigarholic/signup-form {"heading":"Hello","buttonLabel":"Go","source":"footer"} /-->' );
		$this->assertStringContainsString( '<h2 class="cigarholic-signup__heading"', $html );
		$this->assertStringContainsString( 'Hello', $html );
		$this->assertStringContainsString( 'name="email"', $html );
		$this->assertStringContainsString( 'value="footer"', $html );
		$this->assertStringContainsString( 'admin-post.php', $html );
		$this->assertStringContainsString( 'cigarholic/v1/signups', $html );
		$this->assertStringContainsString( 'cf-turnstile', $html, 'Turnstile test keys are configured in wp-tests-config.php' );
	}
}
