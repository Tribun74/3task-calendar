<?php
/**
 * 3task Calendar: visitors submit events, you publish them.
 *
 * Shortcode [threecal_submit] prints a form. A submission becomes a draft event with the
 * entered data, so nothing appears on the site before you have looked at it. Spam protection
 * without any external service: a hidden field that humans do not fill, a minimum time between
 * showing and sending the form, a nonce, length limits and at most ten submissions per hour
 * from one connection. The connection is only hashed with the site's salt for that hour and is
 * never stored with the event.
 *
 * @package ThreeCal
 * @since 1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Submit class.
 */
class ThreeCal_Submit {

	/**
	 * Key in the event settings that holds the submission.
	 */
	const KEY = 'submission';

	/**
	 * Seconds a human needs at least to fill the form.
	 */
	const MIN_SECONDS = 4;

	/**
	 * Submissions per connection and hour.
	 */
	const PER_HOUR = 10;

	/**
	 * Result of the last handled request, shown by the shortcode.
	 *
	 * @var array|null
	 */
	private static $result = null;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_shortcode( 'threecal_submit', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'maybe_handle' ), 20 );
	}

	/**
	 * Signed timestamp for the form: time and a hash, so a bot cannot forge an older one.
	 *
	 * @param int|null $time Unix time, default now.
	 * @return string
	 */
	public static function time_token( $time = null ) {
		$time = null === $time ? time() : (int) $time;
		return $time . '.' . substr( wp_hash( 'threecal_submit|' . $time ), 0, 20 );
	}

	/**
	 * Check a timestamp token: valid signature and at least MIN_SECONDS old, at most a day.
	 *
	 * @param string $token Token from the form.
	 * @return bool
	 */
	public static function time_token_ok( $token ) {
		if ( ! is_string( $token ) || ! preg_match( '/^(\d{9,11})\.([a-f0-9]{20})$/', $token, $m ) ) {
			return false;
		}
		if ( ! hash_equals( substr( wp_hash( 'threecal_submit|' . $m[1] ), 0, 20 ), $m[2] ) ) {
			return false;
		}
		$age = time() - (int) $m[1];
		return $age >= self::MIN_SECONDS && $age <= DAY_IN_SECONDS;
	}

	/**
	 * Handle a posted form on normal page loads.
	 */
	public static function maybe_handle() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- only looks whether our form was sent; handle() verifies the nonce.
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) || ! isset( $_POST['threecal_submit_nonce'] ) ) {
			return;
		}
		self::$result = self::handle( true );
		if ( self::$result['ok'] && ! headers_sent() ) {
			wp_safe_redirect( self::success_url() );
			exit;
		}
	}

	/**
	 * Address the form was sent to, with ?tc_submitted=1 (the form posts to its own page,
	 * so the referer is useless there: WordPress drops a referer equal to the request).
	 *
	 * @return string
	 */
	public static function success_url() {
		$request = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw below.
		$path    = (string) wp_parse_url( $request, PHP_URL_PATH );
		$query   = (string) wp_parse_url( $request, PHP_URL_QUERY );
		$url     = home_url( '' !== $path ? $path : '/' );
		if ( '' !== $query ) {
			$url .= '?' . $query;
		}
		return esc_url_raw( add_query_arg( 'tc_submitted', '1', remove_query_arg( 'tc_submitted', $url ) ) );
	}

	/**
	 * Validate and store a submission.
	 *
	 * @param bool $redirect Unused here, kept for symmetry with maybe_handle().
	 * @return array ok (bool), errors (field => message), message, event_id
	 */
	public static function handle( $redirect = true ) {
		$errors = array();
		$out    = array( 'ok' => false, 'errors' => array(), 'message' => '', 'event_id' => 0 );
		$get    = function ( $key, $max = 200 ) {
			return isset( $_POST[ $key ] ) ? mb_substr( trim( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) ), 0, $max ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked below.
		};

		// Spam protection first: quiet refusal, no hint for bots.
		$spam = __( 'That did not work. Please reload the page and try again.', '3task-calendar' );
		if ( ! isset( $_POST['threecal_submit_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['threecal_submit_nonce'] ) ), 'threecal_submit' ) ) {
			$out['message'] = $spam;
			return $out;
		}
		if ( '' !== $get( 'tc_website' ) || ! self::time_token_ok( isset( $_POST['tc_t'] ) ? sanitize_text_field( wp_unslash( $_POST['tc_t'] ) ) : '' ) ) {
			$out['message'] = $spam;
			return $out;
		}
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'threecal_submit_' . substr( wp_hash( 'rate|' . $ip ), 0, 24 );
		$n   = (int) get_transient( $key );
		if ( $n >= self::PER_HOUR ) {
			$out['message'] = __( 'Too many submissions from your connection. Please try again later.', '3task-calendar' );
			return $out;
		}

		// Fields.
		$title = $get( 'tc_title', 256 );
		if ( '' === $title ) {
			$errors['tc_title'] = __( 'Please enter a title.', '3task-calendar' );
		} elseif ( mb_strlen( $title ) > 255 ) {
			$errors['tc_title'] = __( 'The title is too long.', '3task-calendar' );
		}
		$start_date = $get( 'tc_start_date', 10 );
		$end_date   = $get( 'tc_end_date', 10 );
		$start_time = $get( 'tc_start_time', 5 );
		$end_time   = $get( 'tc_end_time', 5 );
		$all_day    = '' !== $get( 'tc_all_day', 2 );
		$valid_date = function ( $d ) {
			return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) && checkdate( (int) substr( $d, 5, 2 ), (int) substr( $d, 8, 2 ), (int) substr( $d, 0, 4 ) );
		};
		$valid_time = function ( $t ) {
			return '' === $t || (bool) preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $t );
		};
		if ( ! $valid_date( $start_date ) ) {
			$errors['tc_start_date'] = __( 'Please enter a valid start date.', '3task-calendar' );
		}
		if ( '' !== $end_date && ! $valid_date( $end_date ) ) {
			$errors['tc_end_date'] = __( 'Please enter a valid end date.', '3task-calendar' );
		}
		if ( ! $valid_time( $start_time ) || ! $valid_time( $end_time ) ) {
			$errors['tc_start_time'] = __( 'Please enter times as HH:MM.', '3task-calendar' );
		}
		$url = $get( 'tc_url', 500 );
		if ( '' !== $url ) {
			$clean = esc_url_raw( $url, array( 'http', 'https' ) );
			if ( ! $clean || ! wp_http_validate_url( $clean ) ) {
				$errors['tc_url'] = __( 'The link must start with http:// or https://.', '3task-calendar' );
			}
			$url = $clean;
		}
		$email = $get( 'tc_email', 200 );
		if ( '' !== $email && ! is_email( $email ) ) {
			$errors['tc_email'] = __( 'Please check the e-mail address.', '3task-calendar' );
		}
		$description = isset( $_POST['tc_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tc_description'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked above.
		$description = mb_substr( trim( $description ), 0, 3000 );
		$location    = $get( 'tc_location', 300 );
		$organizer   = $get( 'tc_organizer', 200 );

		if ( $errors ) {
			$out['errors']  = $errors;
			$out['message'] = __( 'Please check the marked fields.', '3task-calendar' );
			return $out;
		}

		// Dates.
		if ( '' === $end_date ) {
			$end_date = $start_date;
		}
		if ( $all_day || '' === $start_time ) {
			$all_day = true;
			$start   = $start_date . ' 00:00:00';
			$end     = $end_date . ' 23:59:59';
		} else {
			$start = $start_date . ' ' . $start_time . ':00';
			$end   = $end_date . ' ' . ( '' !== $end_time ? $end_time : $start_time ) . ':00';
			if ( strtotime( $end ) <= strtotime( $start ) ) {
				$end = $end_date === $start_date ? gmdate( 'Y-m-d H:i:s', strtotime( $start ) + 2 * HOUR_IN_SECONDS ) : $end;
			}
		}

		$event              = new ThreeCal_Event();
		$event->title       = $title;
		$event->description = $description;
		$event->start_date  = $start;
		$event->end_date    = $end;
		$event->all_day     = $all_day ? 1 : 0;
		$event->url         = $url;
		$event->status      = 'draft';
		$event->color       = ThreeCal_Themes::LEGACY_EVENT_COLOR;
		$event->settings    = array();
		$event->location_id = self::match_location( $location );
		if ( '' !== $organizer ) {
			$event->set_organizer( $organizer, '' );
		}
		$event->set_setting( self::KEY, array(
			'at'            => current_time( 'mysql' ),
			'email'         => $email,
			'location_text' => $location,
		) );
		if ( ! $event->save() ) {
			$out['message'] = __( 'The event could not be saved. Please try again later.', '3task-calendar' );
			return $out;
		}
		$settings = get_option( 'threecal_settings', array() );
		$cat_id   = isset( $settings['submit_category'] ) ? absint( $settings['submit_category'] ) : 0;
		if ( $cat_id && ThreeCal_Category::get( $cat_id ) ) {
			$event->set_categories( array( $cat_id ) );
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );

		if ( ! isset( $settings['submit_notify'] ) || $settings['submit_notify'] ) {
			self::notify( $event, $location, $email );
		}

		/**
		 * Fires after a visitor submitted an event (stored as draft). Since 1.7.0
		 *
		 * @param ThreeCal_Event $event The draft event.
		 */
		do_action( 'threecal_event_submitted', $event );

		$out['ok']       = true;
		$out['event_id'] = (int) $event->id;
		$out['message']  = __( 'Thank you! We have received your event and will check it before it appears in the calendar.', '3task-calendar' );
		return $out;
	}

	/**
	 * Existing location whose name starts the entered text (case-insensitive), else 0.
	 *
	 * @param string $text Location as typed.
	 * @return int
	 */
	private static function match_location( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return 0;
		}
		$first = trim( (string) strtok( $text, ',' ) );
		foreach ( ThreeCal_Location::get_all() as $loc ) {
			if ( 0 === strcasecmp( (string) $loc->name, $first ) ) {
				return (int) $loc->id;
			}
		}
		return 0;
	}

	/**
	 * Tell the site admin about a new submission.
	 *
	 * @param ThreeCal_Event $event    Draft event.
	 * @param string         $location Location as typed.
	 * @param string         $email    Submitter's e-mail, may be empty.
	 */
	private static function notify( $event, $location, $email ) {
		/* translators: %s: event title */
		$subject = sprintf( __( 'New event submitted: %s', '3task-calendar' ), $event->title );
		$lines   = array(
			__( 'A visitor submitted an event. It is saved as a draft and not visible until you publish it.', '3task-calendar' ),
			'',
			__( 'Title:', '3task-calendar' ) . ' ' . $event->title,
			__( 'Date:', '3task-calendar' ) . ' ' . $event->start_date . ( $event->end_date ? ' - ' . $event->end_date : '' ),
			__( 'Location:', '3task-calendar' ) . ' ' . ( '' !== $location ? $location : '-' ),
			__( 'E-mail of the submitter:', '3task-calendar' ) . ' ' . ( '' !== $email ? $email : '-' ),
			'',
			__( 'Check and publish:', '3task-calendar' ) . ' ' . admin_url( 'admin.php?page=3task-calendar&tab=events&action=edit&event=' . (int) $event->id ),
		);
		wp_mail( get_option( 'admin_email' ), $subject, implode( "\n", $lines ) );
	}

	/**
	 * Number of submitted events that are still drafts.
	 *
	 * @return int
	 */
	public static function pending_count() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- small admin count on a custom table.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}threecal_events WHERE status = 'draft' AND settings LIKE %s", '%"' . self::KEY . '":%' ) );
	}

	/**
	 * Is this event a visitor submission?
	 *
	 * @param ThreeCal_Event $event Event.
	 * @return array|null Submission data or null.
	 */
	public static function submission( $event ) {
		$data = $event ? $event->get_setting( self::KEY ) : null;
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Shortcode [threecal_submit].
	 *
	 * @param array $atts Attributes: title (heading), success (own thank-you text).
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'title' => '', 'success' => '' ), $atts, 'threecal_submit' );
		wp_enqueue_style( 'threecal-public' );
		$result = self::$result;
		$errors = $result ? $result['errors'] : array();
		$old    = function ( $key ) use ( $result ) {
			if ( ! $result || $result['ok'] || ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- only re-filling the form after a failed check.
				return '';
			}
			return sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		};
		$feld = function ( $key, $label, $type = 'text', $required = false, $extra = '' ) use ( $errors, $old ) {
			$id   = 'tc-' . str_replace( '_', '-', $key );
			$html = '<p class="threecal-submit-field' . ( isset( $errors[ $key ] ) ? ' has-error' : '' ) . '">';
			$html .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ( $required ? ' <span class="required">*</span>' : '' ) . '</label>';
			if ( 'textarea' === $type ) {
				$html .= '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" rows="5" maxlength="3000">' . esc_textarea( $old( $key ) ) . '</textarea>';
			} else {
				$html .= '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $old( $key ) ) . '"' . ( $required ? ' required' : '' ) . ( $extra ? ' ' . $extra : '' ) . '>';
			}
			if ( isset( $errors[ $key ] ) ) {
				$html .= '<span class="threecal-submit-error">' . esc_html( $errors[ $key ] ) . '</span>';
			}
			return $html . '</p>';
		};

		ob_start();
		?>
		<div class="threecal-submit">
			<?php if ( '' !== $atts['title'] ) : ?><h3><?php echo esc_html( $atts['title'] ); ?></h3><?php endif; ?>
			<?php if ( isset( $_GET['tc_submitted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>
				<div class="threecal-submit-notice is-success"><?php echo esc_html( '' !== $atts['success'] ? $atts['success'] : __( 'Thank you! We have received your event and will check it before it appears in the calendar.', '3task-calendar' ) ); ?></div>
			<?php else : ?>
				<?php if ( $result && ! $result['ok'] ) : ?>
					<div class="threecal-submit-notice is-error"><?php echo esc_html( $result['message'] ); ?></div>
				<?php endif; ?>
				<form method="post" class="threecal-submit-form" novalidate>
					<?php wp_nonce_field( 'threecal_submit', 'threecal_submit_nonce' ); ?>
					<input type="hidden" name="tc_t" value="<?php echo esc_attr( self::time_token() ); ?>">
					<p class="threecal-submit-hp" aria-hidden="true"><label for="tc-website"><?php esc_html_e( 'Leave this field empty', '3task-calendar' ); ?></label><input type="text" id="tc-website" name="tc_website" value="" tabindex="-1" autocomplete="off"></p>
					<?php echo $feld( 'tc_title', __( 'Title of the event', '3task-calendar' ), 'text', true, 'maxlength="255"' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $feld. ?>
					<div class="threecal-submit-row">
						<?php echo $feld( 'tc_start_date', __( 'Start date', '3task-calendar' ), 'date', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $feld( 'tc_start_time', __( 'Start time', '3task-calendar' ), 'time' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<div class="threecal-submit-row">
						<?php echo $feld( 'tc_end_date', __( 'End date', '3task-calendar' ), 'date' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo $feld( 'tc_end_time', __( 'End time', '3task-calendar' ), 'time' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<p class="threecal-submit-field"><label><input type="checkbox" name="tc_all_day" value="1" <?php checked( '' !== $old( 'tc_all_day' ) ); ?>> <?php esc_html_e( 'All day', '3task-calendar' ); ?></label></p>
					<?php echo $feld( 'tc_location', __( 'Location (name, street, town)', '3task-calendar' ), 'text', false, 'maxlength="300"' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $feld( 'tc_url', __( 'Link to more information', '3task-calendar' ), 'url', false, 'placeholder="https://"' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $feld( 'tc_description', __( 'Description', '3task-calendar' ), 'textarea' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $feld( 'tc_organizer', __( 'Organizer', '3task-calendar' ), 'text', false, 'maxlength="200"' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo $feld( 'tc_email', __( 'Your e-mail address (optional, for questions)', '3task-calendar' ), 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<p class="threecal-submit-note"><?php esc_html_e( 'We check every event before it appears. Your e-mail address is used only for questions about this event.', '3task-calendar' ); ?></p>
					<p class="threecal-submit-actions"><button type="submit" class="threecal-button"><?php esc_html_e( 'Submit event', '3task-calendar' ); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
