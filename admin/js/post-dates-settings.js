/**
 * 3task Calendar - settings screen "Dates from posts"
 *
 * Applies the settings to existing posts in steps of 50, so large sites do
 * not run into a time-out, and shows the progress.
 */

( function () {
	var root = document.querySelector( '.threecal-post-dates-settings' );
	if ( ! root ) {
		return;
	}

	var i18n = {};
	try {
		i18n = JSON.parse( root.getAttribute( 'data-i18n' ) || '{}' );
	} catch ( e ) {
		i18n = {};
	}

	var queue = [];
	var running = false;

	function find( selector, type ) {
		return root.querySelector( selector + '[data-type="' + type + '"]' );
	}

	function next() {
		if ( running || ! queue.length ) {
			return;
		}
		running = true;
		var type = queue.shift();
		var button = find( '.threecal-sync-btn', type );
		var progress = find( '.threecal-sync-progress', type );
		var count = find( '.threecal-linked-count', type );

		if ( button ) {
			button.disabled = true;
		}

		var finish = function ( text ) {
			if ( progress ) {
				progress.textContent = text;
			}
			if ( button ) {
				button.disabled = false;
			}
			running = false;
			next();
		};

		var step = function ( offset ) {
			var body = new FormData();
			body.append( 'action', 'threecal_post_dates_sync' );
			body.append( 'nonce', root.getAttribute( 'data-nonce' ) );
			body.append( 'post_type', type );
			body.append( 'offset', offset );

			window.fetch( root.getAttribute( 'data-ajax' ), { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( result ) {
					if ( ! result || ! result.success ) {
						finish( i18n.error || '' );
						return;
					}
					if ( count ) {
						count.textContent = result.data.linked;
					}
					if ( result.data.finished ) {
						finish( i18n.done || '' );
						return;
					}
					if ( progress && i18n.progress ) {
						progress.textContent = i18n.progress.replace( '%1$s', result.data.done ).replace( '%2$s', result.data.total );
					}
					step( result.data.done );
				} )
				.catch( function () {
					finish( i18n.error || '' );
				} );
		};

		step( 0 );
	}

	root.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.threecal-sync-btn' );
		if ( ! button ) {
			return;
		}
		event.preventDefault();
		queue.push( button.getAttribute( 'data-type' ) );
		next();
	} );

	// "Another field": show the text field only when it is chosen.
	root.addEventListener( 'change', function ( event ) {
		if ( ! event.target.classList.contains( 'threecal-source-select' ) ) {
			return;
		}
		var custom = event.target.parentNode.querySelector( '.threecal-source-custom' );
		if ( custom ) {
			custom.hidden = '__custom' !== event.target.value;
			if ( ! custom.hidden ) {
				custom.focus();
			}
		}
	} );

	// After saving, the changed post types are checked by themselves.
	( root.getAttribute( 'data-resync' ) || '' ).split( ',' ).forEach( function ( type ) {
		if ( type ) {
			queue.push( type );
		}
	} );
	next();
} )();
