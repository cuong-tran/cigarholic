/**
 * Progressive enhancement for cigarholic/contact-form: submit through the REST API, keep the plain
 * form POST as the fallback without JavaScript. (CNT-03)
 */
( function () {
	document.querySelectorAll( '.cigarholic-contact__form[data-endpoint]' ).forEach( ( form ) => {
		const status = form.querySelector( '.cigarholic-contact__status' );
		const button = form.querySelector( 'button[type="submit"]' );
		const show = ( text, state ) => {
			if ( status ) {
				status.textContent = text;
				status.dataset.state = state;
			}
		};
		form.addEventListener( 'submit', async ( event ) => {
			if ( ! window.fetch ) {
				return;
			}
			event.preventDefault();
			const data = new FormData( form );
			data.delete( 'action' );
			if ( button ) {
				button.disabled = true;
			}
			try {
				const response = await fetch( form.dataset.endpoint, {
					method: 'POST',
					body: data,
					headers: { Accept: 'application/json' },
					credentials: 'same-origin',
				} );
				const json = await response.json();
				const ok = response.ok && json && json.ok;
				show( ok ? json.message : ( json && json.message ) || 'Something went wrong. Please try again.', ok ? 'ok' : 'error' );
				if ( ok ) {
					form.reset();
					if ( window.turnstile ) {
						window.turnstile.reset();
					}
				}
			} catch {
				show( 'Something went wrong. Please try again.', 'error' );
			} finally {
				if ( button ) {
					button.disabled = false;
				}
			}
		} );
	} );
} )();
