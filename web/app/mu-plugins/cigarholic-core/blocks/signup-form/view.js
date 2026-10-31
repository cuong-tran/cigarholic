/**
 * Progressive enhancement for cigarholic/signup-form: submit through the REST API without a page
 * reload, keep the no-JavaScript form POST as the fallback. (CNT-04)
 */
( function () {
	const forms = document.querySelectorAll( '.cigarholic-signup__form[data-endpoint]' );
	forms.forEach( ( form ) => {
		const status = form.querySelector( '.cigarholic-signup__status' );
		const button = form.querySelector( 'button[type="submit"]' );
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
				if ( status ) {
					status.textContent = ok ? json.message : ( json && json.message ) || form.dataset.errorMessage || 'Something went wrong. Please try again.';
					status.dataset.state = ok ? 'ok' : 'error';
				}
				if ( ok ) {
					form.reset();
					if ( window.turnstile ) {
						window.turnstile.reset();
					}
				}
			} catch {
				if ( status ) {
					status.textContent = 'Something went wrong. Please try again.';
					status.dataset.state = 'error';
				}
			} finally {
				if ( button ) {
					button.disabled = false;
				}
			}
		} );
	} );
} )();
