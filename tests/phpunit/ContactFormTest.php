<?php
/**
 * Tests for the contact form.
 *
 * @package Cigarholic
 */

namespace Cigarholic\Tests;

use Cigarholic\Core\Contact\ContactForm;
use Cigarholic\Core\Plugin;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Feature IDs: CNT-03.
 */
final class ContactFormTest extends WP_UnitTestCase {

	/**
	 * The feature under test.
	 *
	 * @var ContactForm
	 */
	private ContactForm $form;

	/**
	 * Fresh state for every test.
	 */
	public function set_up(): void {
		parent::set_up();
		$feature = Plugin::instance()->features()->get( 'contact-form' );
		$this->assertInstanceOf( ContactForm::class, $feature );
		$this->form             = $feature;
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		delete_transient( 'cigarholic_contact_' . md5( '127.0.0.1' ) );
		delete_option( ContactForm::OPTION );
		add_filter( 'cigarholic_turnstile_verified', '__return_true' );
		reset_phpmailer_instance();
	}

	/**
	 * Fields fixture.
	 *
	 * @param array<string, string> $overrides Overrides.
	 * @return array{name: string, email: string, order_number: string, message: string}
	 */
	private function fields( array $overrides = array() ): array {
		return array_merge(
			array(
				'name'         => 'Pat Example',
				'email'        => 'pat@example.test',
				'order_number' => '#1042',
				'message'      => "Does the desktop humidor fit 50 Churchills?\nThanks.",
			),
			$overrides
		);
	}

	/**
	 * A valid message is emailed to the configured recipient with the visitor as Reply-To.
	 */
	public function test_message_is_emailed(): void {
		update_option(
			ContactForm::OPTION,
			array(
				'recipient'      => 'help@cigarholic.test',
				'subject_prefix' => '[Test]',
			)
		);

		$this->assertTrue( $this->form->send( $this->fields(), 'token', '' ) );

		$mailer = tests_retrieve_phpmailer_instance();
		$sent   = $mailer->get_sent();
		$this->assertSame( 'help@cigarholic.test', $sent->to[0][0] );
		$this->assertStringContainsString( '[Test] Contact from Pat Example (order #1042)', $sent->subject );
		$this->assertStringContainsString( 'Does the desktop humidor fit 50 Churchills?', $sent->body );
		$this->assertStringContainsString( 'Reply-To: Pat Example <pat@example.test>', $sent->header );
	}

	/**
	 * Validation errors carry their own codes and send nothing.
	 */
	public function test_validation(): void {
		$this->assertSame( 'invalid_name', $this->form->send( $this->fields( array( 'name' => ' ' ) ), 't', '' )->get_error_code() );
		$this->assertSame( 'invalid_email', $this->form->send( $this->fields( array( 'email' => 'nope' ) ), 't', '' )->get_error_code() );
		$this->assertSame( 'invalid_message', $this->form->send( $this->fields( array( 'message' => '' ) ), 't', '' )->get_error_code() );
		$this->assertSame( 'invalid_message', $this->form->send( $this->fields( array( 'message' => str_repeat( 'x', ContactForm::MAX_MESSAGE + 1 ) ) ), 't', '' )->get_error_code() );
		$this->assertTrue( $this->form->send( $this->fields(), 't', 'https://spam.example' ), 'honeypot: silent success' );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	/**
	 * Turnstile failure and the rate limit are refused.
	 */
	public function test_turnstile_and_rate_limit(): void {
		remove_filter( 'cigarholic_turnstile_verified', '__return_true' );
		add_filter( 'cigarholic_turnstile_verified', '__return_false' );
		$this->assertSame( 'verification_failed', $this->form->send( $this->fields(), 'bad', '' )->get_error_code() );
		remove_filter( 'cigarholic_turnstile_verified', '__return_false' );
		add_filter( 'cigarholic_turnstile_verified', '__return_true' );

		// The refused attempt above counted; start the rate-limit window fresh.
		delete_transient( 'cigarholic_contact_' . md5( '127.0.0.1' ) );
		for ( $i = 0; $i < ContactForm::RATE_LIMIT; $i++ ) {
			$this->assertTrue( $this->form->send( $this->fields(), 't', '' ) );
		}
		$this->assertSame( 'rate_limited', $this->form->send( $this->fields(), 't', '' )->get_error_code() );
	}

	/**
	 * Header injection through the name is neutralised.
	 */
	public function test_header_injection_is_neutralised(): void {
		$this->assertTrue( $this->form->send( $this->fields( array( 'name' => "Eve\r\nBcc: victim@example.test" ) ), 't', '' ) );
		$sent = tests_retrieve_phpmailer_instance()->get_sent();
		$this->assertSame( 0, preg_match( '/^Bcc:/mi', $sent->header ), 'no injected header line' );
		$this->assertSame( array(), $sent->bcc ?? array() );
		$this->assertMatchesRegularExpression( '/^Reply-To: .*<pat@example\.test>\r?$/m', $sent->header );
	}

	/**
	 * REST endpoint answers 200 for a valid message and 400 for an invalid one.
	 */
	public function test_rest_endpoint(): void {
		$request = new WP_REST_Request( 'POST', '/cigarholic/v1/contact' );
		foreach ( $this->fields() as $key => $value ) {
			$request->set_param( $key, $value );
		}
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['ok'] );

		$bad = new WP_REST_Request( 'POST', '/cigarholic/v1/contact' );
		foreach ( $this->fields( array( 'email' => 'nope' ) ) as $key => $value ) {
			$bad->set_param( $key, $value );
		}
		$this->assertSame( 400, rest_get_server()->dispatch( $bad )->get_status() );
	}

	/**
	 * The block renders a labelled form with the REST endpoint and the optional order field.
	 */
	public function test_block_renders(): void {
		$html = do_blocks( '<!-- wp:cigarholic/contact-form {"heading":"Write to us"} /-->' );
		$this->assertStringContainsString( 'Write to us', $html );
		$this->assertStringContainsString( 'name="message"', $html );
		$this->assertStringContainsString( 'name="order_number"', $html );
		$this->assertStringContainsString( 'cigarholic/v1/contact', $html );
		$this->assertStringContainsString( 'cf-turnstile', $html );

		$without = do_blocks( '<!-- wp:cigarholic/contact-form {"showOrderNumber":false} /-->' );
		$this->assertStringNotContainsString( 'name="order_number"', $without );
	}
}
