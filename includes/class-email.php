<?php
/**
 * ThreeCal Email Handler
 *
 * Handles email notifications for events.
 */

if (!defined('ABSPATH')) {
    exit;
}

class ThreeCal_Email {

    /**
     * Settings
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('threecal_settings', array());

        // Schedule daily notification check
        if (!wp_next_scheduled('threecal_daily_notifications')) {
            wp_schedule_event(time(), 'daily', 'threecal_daily_notifications');
        }

        add_action('threecal_daily_notifications', array($this, 'send_reminder_notifications'));
    }

    /**
     * Send event reminder notifications
     */
    public function send_reminder_notifications() {
        if (!$this->is_enabled()) {
            return;
        }

        // Get events happening tomorrow
        $tomorrow = gmdate('Y-m-d', strtotime('+1 day'));
        $events = ThreeCal_Event::get_all(array(
            'status' => 'published',
            'start_after' => $tomorrow . ' 00:00:00',
            'start_before' => $tomorrow . ' 23:59:59'
        ));

        foreach ($events as $event) {
            $this->send_event_reminders($event);
        }
    }

    /**
     * Send reminders for a specific event
     */
    public function send_event_reminders($event) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, caching not needed for email reminders.
        $subscribers = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . $wpdb->prefix . "threecal_subscribers
             WHERE (event_id = %d OR event_id IS NULL)
             AND status = 'active'",
            $event->id
        ) );

        foreach ($subscribers as $subscriber) {
            $this->send_reminder($subscriber, $event);
        }
    }

    /**
     * Send individual reminder
     */
    private function send_reminder($subscriber, $event) {
        $location = null;
        if ($event->location_id) {
            $location = ThreeCal_Location::get($event->location_id);
        }

        $template = isset($this->settings['notification_template'])
            ? $this->settings['notification_template']
            : $this->get_default_template();

        $date_format = isset($this->settings['date_format']) ? $this->settings['date_format'] : get_option('date_format');
        $time_format = isset($this->settings['time_format']) ? $this->settings['time_format'] : get_option('time_format');

        // Replace placeholders
        $replacements = array(
            '{subscriber_name}' => $subscriber->name ?: __('Subscriber', '3task-calendar'),
            '{subscriber_email}' => $subscriber->email,
            '{event_title}' => $event->title,
            '{event_date}' => date_i18n($date_format, strtotime($event->start_date)),
            '{event_time}' => $event->all_day ? __('All day', '3task-calendar') : date_i18n($time_format, strtotime($event->start_date)),
            '{event_location}' => $location ? $location->name : '',
            '{event_address}' => $location ? $location->get_full_address() : '',
            '{event_description}' => wp_strip_all_tags($event->description),
            '{event_url}' => $event->url ?: '',
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => home_url(),
            '{unsubscribe_url}' => $this->get_unsubscribe_url($subscriber)
        );

        $message = str_replace(array_keys($replacements), array_values($replacements), $template);

        $subject = sprintf(
            /* translators: %s: event title */
            __('Reminder: %s', '3task-calendar'),
            $event->title
        );

        $this->send($subscriber->email, $subject, $message);
    }

    /**
     * Send email
     */
    public function send($to, $subject, $message, $headers = array()) {
        $sender_name = isset($this->settings['notification_sender_name'])
            ? $this->settings['notification_sender_name']
            : get_bloginfo('name');

        $sender_email = isset($this->settings['notification_sender_email'])
            ? $this->settings['notification_sender_email']
            : get_option('admin_email');

        $default_headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $sender_name . ' <' . $sender_email . '>'
        );

        $headers = array_merge($default_headers, $headers);

        // Wrap message in HTML template
        $html_message = $this->wrap_html($message, $subject);

        return wp_mail($to, $subject, $html_message, $headers);
    }

    /**
     * Wrap message in HTML template
     */
    private function wrap_html($message, $subject) {
        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($subject); ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: #3788d8; color: #fff; padding: 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .footer { background: #f9f9f9; padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .footer a { color: #3788d8; }
        p { margin: 0 0 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo esc_html(get_bloginfo('name')); ?></h1>
        </div>
        <div class="content">
            <?php echo wp_kses_post( wpautop( esc_html( $message ) ) ); ?>
        </div>
        <div class="footer">
            <p><?php echo esc_html(get_bloginfo('name')); ?> - <a href="<?php echo esc_url(home_url()); ?>"><?php echo esc_url(home_url()); ?></a></p>
        </div>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Subscribe email to event
     */
    public static function subscribe($email, $event_id = null, $name = '') {
        global $wpdb;

        $email = sanitize_email($email);

        if (!is_email($email)) {
            return new WP_Error('invalid_email', __('Invalid email address.', '3task-calendar'));
        }

        // Check if already subscribed.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for subscriptions.
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . $wpdb->prefix . "threecal_subscribers
             WHERE email = %s AND (event_id = %d OR (event_id IS NULL AND %d = 0))",
            $email,
            $event_id ? $event_id : 0,
            $event_id ? $event_id : 0
        ) );

        if ( $existing ) {
            if ( $existing->status === 'active' ) {
                return new WP_Error( 'already_subscribed', __( 'This email is already subscribed.', '3task-calendar' ) );
            } else {
                // Reactivate.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for subscriptions.
                $wpdb->update(
                    $wpdb->prefix . 'threecal_subscribers',
                    array( 'status' => 'active' ),
                    array( 'id' => $existing->id ),
                    array( '%s' ),
                    array( '%d' )
                );
                return true;
            }
        }

        // Create new subscription.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table for subscriptions.
        $result = $wpdb->insert(
            $wpdb->prefix . 'threecal_subscribers',
            array(
                'email'    => $email,
                'name'     => sanitize_text_field( $name ),
                'event_id' => $event_id ? $event_id : null,
                'status'   => 'active',
                'token'    => wp_generate_password( 32, false ),
            ),
            array( '%s', '%s', '%d', '%s', '%s' )
        );

        return $result ? true : new WP_Error('subscribe_failed', __('Subscription failed.', '3task-calendar'));
    }

    /**
     * Unsubscribe by token
     */
    public static function unsubscribe( $token ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table for subscriptions.
        $result = $wpdb->update(
            $wpdb->prefix . 'threecal_subscribers',
            array( 'status' => 'unsubscribed' ),
            array( 'token' => sanitize_text_field( $token ) ),
            array( '%s' ),
            array( '%s' )
        );

        return $result !== false;
    }

    /**
     * Get unsubscribe URL
     */
    private function get_unsubscribe_url($subscriber) {
        return add_query_arg(array(
            'threecal_unsubscribe' => $subscriber->token
        ), home_url());
    }

    /**
     * Check if notifications are enabled
     */
    private function is_enabled() {
        return isset($this->settings['enable_notifications']) && $this->settings['enable_notifications'];
    }

    /**
     * Get default email template
     */
    private function get_default_template() {
        return __("Hello {subscriber_name},

This is a reminder that the following event is happening tomorrow:

{event_title}
Date: {event_date}
Time: {event_time}
Location: {event_location}

We look forward to seeing you!

Best regards,
{site_name}

---
If you no longer wish to receive these notifications, click here: {unsubscribe_url}", '3task-calendar');
    }

    /**
     * Send new event notification to admin
     */
    public static function notify_admin_new_event($event) {
        $settings = get_option('threecal_settings', array());

        if (!isset($settings['notify_admin_new_event']) || !$settings['notify_admin_new_event']) {
            return;
        }

        $admin_email = get_option('admin_email');
        /* translators: %s: event title */
        $subject = sprintf( __( 'New Event Created: %s', '3task-calendar' ), $event->title );

        /* translators: 1: event title, 2: event date, 3: event status, 4: edit URL */
        $message = sprintf(
            __( "A new event has been created:\n\nTitle: %1\$s\nDate: %2\$s\nStatus: %3\$s\n\nEdit: %4\$s", '3task-calendar' ),
            $event->title,
            $event->start_date,
            $event->status,
            admin_url( 'admin.php?page=3task-calendar&tab=events&action=edit&event=' . $event->id )
        );

        $instance = new self();
        $instance->send($admin_email, $subject, $message);
    }
}
