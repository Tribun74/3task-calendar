<?php
/**
 * Settings View
 *
 * @package ThreeCal
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template file included in admin context.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = get_option( 'threecal_settings', array() );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View file, tab param is for navigation only.
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';

$tabs = array(
    'general' => __('General', '3task-calendar'),
    'display' => __('Display', '3task-calendar'),
    'maps' => __('Google Maps', '3task-calendar'),
    'email' => __('Email', '3task-calendar'),
    'advanced' => __('Advanced', '3task-calendar')
);

$views = array(
    'month' => __('Month', '3task-calendar'),
    'list' => __('List', '3task-calendar')
);

$themes = array(
    'default' => __('Default', '3task-calendar'),
    'minimal' => __('Minimal', '3task-calendar'),
    'boxed' => __('Boxed', '3task-calendar'),
    'gradient' => __('Gradient', '3task-calendar'),
    'glassmorphism' => __('Glassmorphism', '3task-calendar')
);

$weekdays = array(
    0 => __('Sunday', '3task-calendar'),
    1 => __('Monday', '3task-calendar'),
    2 => __('Tuesday', '3task-calendar'),
    3 => __('Wednesday', '3task-calendar'),
    4 => __('Thursday', '3task-calendar'),
    5 => __('Friday', '3task-calendar'),
    6 => __('Saturday', '3task-calendar')
);
?>

<div class="wrap threecal-admin threecal-settings-page">
    <h1><?php esc_html_e('ThreeCal Settings', '3task-calendar'); ?></h1>

    <nav class="nav-tab-wrapper">
        <?php foreach ($tabs as $tab_id => $tab_label) : ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=3task-calendar-settings&tab=' . $tab_id)); ?>"
           class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html($tab_label); ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <form method="post" action="options.php">
        <?php settings_fields('threecal_settings'); ?>

        <div class="threecal-settings-content">
            <?php if ($active_tab === 'general') : ?>
            <!-- General Settings -->
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="date_format"><?php esc_html_e('Date Format', '3task-calendar'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="date_format" name="threecal_settings[date_format]"
                               value="<?php echo esc_attr($settings['date_format'] ?? get_option('date_format')); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Uses WordPress date format codes.', '3task-calendar'); ?>
                            <a href="https://wordpress.org/documentation/article/customize-date-and-time-format/" target="_blank">
                                <?php esc_html_e('Learn more', '3task-calendar'); ?>
                            </a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="time_format"><?php esc_html_e('Time Format', '3task-calendar'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="time_format" name="threecal_settings[time_format]"
                               value="<?php echo esc_attr($settings['time_format'] ?? get_option('time_format')); ?>"
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="week_starts_on"><?php esc_html_e('Week Starts On', '3task-calendar'); ?></label>
                    </th>
                    <td>
                        <select id="week_starts_on" name="threecal_settings[week_starts_on]">
                            <?php foreach ($weekdays as $day_num => $day_name) : ?>
                            <option value="<?php echo esc_attr($day_num); ?>"
                                    <?php selected($settings['week_starts_on'] ?? 1, $day_num); ?>>
                                <?php echo esc_html($day_name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="default_view"><?php esc_html_e('Default View', '3task-calendar'); ?></label>
                    </th>
                    <td>
                        <select id="default_view" name="threecal_settings[default_view]">
                            <?php foreach ($views as $view_id => $view_name) : ?>
                            <option value="<?php echo esc_attr($view_id); ?>"
                                    <?php selected($settings['default_view'] ?? 'month', $view_id); ?>>
                                <?php echo esc_html($view_name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="default_theme"><?php esc_html_e('Default Theme', '3task-calendar'); ?></label>
                    </th>
                    <td>
                        <select id="default_theme" name="threecal_settings[default_theme]">
                            <?php foreach ($themes as $theme_id => $theme_name) : ?>
                            <option value="<?php echo esc_attr($theme_id); ?>"
                                    <?php selected($settings['default_theme'] ?? 'default', $theme_id); ?>>
                                <?php echo esc_html($theme_name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <?php elseif ($active_tab === 'display') : ?>
            <!-- Display Settings -->
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Show Event Details', '3task-calendar'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="threecal_settings[show_event_time]" value="1"
                                       <?php checked($settings['show_event_time'] ?? true); ?>>
                                <?php esc_html_e('Show event time', '3task-calendar'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="threecal_settings[show_event_location]" value="1"
                                       <?php checked($settings['show_event_location'] ?? true); ?>>
                                <?php esc_html_e('Show event location', '3task-calendar'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="threecal_settings[show_event_description]" value="1"
                                       <?php checked($settings['show_event_description'] ?? true); ?>>
                                <?php esc_html_e('Show event description', '3task-calendar'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="events_per_page"><?php esc_html_e('Events Per Page', '3task-calendar'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="events_per_page" name="threecal_settings[events_per_page]"
                               value="<?php echo esc_attr($settings['events_per_page'] ?? 10); ?>"
                               min="1" max="100" class="small-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Event Popup', '3task-calendar'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="threecal_settings[enable_event_popup]" value="1"
                                   <?php checked($settings['enable_event_popup'] ?? true); ?>>
                            <?php esc_html_e('Show event details in popup when clicked', '3task-calendar'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Schema.org SEO', '3task-calendar'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="threecal_settings[enable_schema]" value="1"
                                   <?php checked($settings['enable_schema'] ?? true); ?>>
                            <?php esc_html_e('Add Schema.org Event markup for SEO', '3task-calendar'); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <?php elseif ($active_tab === 'maps') : ?>
            <!-- Google Maps Settings -->
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="google_maps_api_key"><?php esc_html_e('Google Maps API Key', '3task-calendar'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="google_maps_api_key" name="threecal_settings[google_maps_api_key]"
                               value="<?php echo esc_attr($settings['google_maps_api_key'] ?? ''); ?>"
                               class="large-text">
                        <p class="description">
                            <?php esc_html_e('Required for displaying maps and geocoding addresses.', '3task-calendar'); ?>
                            <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank">
                                <?php esc_html_e('Get API Key', '3task-calendar'); ?>
                            </a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="default_map_zoom"><?php esc_html_e('Default Map Zoom', '3task-calendar'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="default_map_zoom" name="threecal_settings[default_map_zoom]"
                               value="<?php echo esc_attr($settings['default_map_zoom'] ?? 14); ?>"
                               min="1" max="20" class="small-text">
                        <p class="description"><?php esc_html_e('1 = World, 20 = Building level', '3task-calendar'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="default_map_type"><?php esc_html_e('Map Type', '3task-calendar'); ?></label>
                    </th>
                    <td>
                        <select id="default_map_type" name="threecal_settings[default_map_type]">
                            <option value="roadmap" <?php selected($settings['default_map_type'] ?? 'roadmap', 'roadmap'); ?>>
                                <?php esc_html_e('Roadmap', '3task-calendar'); ?>
                            </option>
                            <option value="satellite" <?php selected($settings['default_map_type'] ?? 'roadmap', 'satellite'); ?>>
                                <?php esc_html_e('Satellite', '3task-calendar'); ?>
                            </option>
                            <option value="hybrid" <?php selected($settings['default_map_type'] ?? 'roadmap', 'hybrid'); ?>>
                                <?php esc_html_e('Hybrid', '3task-calendar'); ?>
                            </option>
                            <option value="terrain" <?php selected($settings['default_map_type'] ?? 'roadmap', 'terrain'); ?>>
                                <?php esc_html_e('Terrain', '3task-calendar'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
            </table>

            <?php elseif ($active_tab === 'email') : ?>
            <!-- Email Settings -->
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Email Notifications', '3task-calendar'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="threecal_settings[enable_notifications]" value="1"
                                   <?php checked($settings['enable_notifications'] ?? false); ?>>
                            <?php esc_html_e('Enable event reminder emails', '3task-calendar'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="notification_sender_name"><?php esc_html_e('Sender Name', '3task-calendar'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="notification_sender_name" name="threecal_settings[notification_sender_name]"
                               value="<?php echo esc_attr($settings['notification_sender_name'] ?? get_bloginfo('name')); ?>"
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="notification_sender_email"><?php esc_html_e('Sender Email', '3task-calendar'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="notification_sender_email" name="threecal_settings[notification_sender_email]"
                               value="<?php echo esc_attr($settings['notification_sender_email'] ?? get_option('admin_email')); ?>"
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="notification_template"><?php esc_html_e('Email Template', '3task-calendar'); ?></label>
                    </th>
                    <td>
                        <textarea id="notification_template" name="threecal_settings[notification_template]"
                                  rows="10" class="large-text code"><?php
                            echo esc_textarea($settings['notification_template'] ?? '');
                        ?></textarea>
                        <p class="description">
                            <?php esc_html_e('Available placeholders:', '3task-calendar'); ?><br>
                            <code>{subscriber_name}</code>, <code>{event_title}</code>, <code>{event_date}</code>,
                            <code>{event_time}</code>, <code>{event_location}</code>, <code>{event_address}</code>,
                            <code>{site_name}</code>, <code>{unsubscribe_url}</code>
                        </p>
                    </td>
                </tr>
            </table>

            <?php elseif ($active_tab === 'advanced') : ?>
            <!-- Advanced Settings -->
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Uninstall', '3task-calendar'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="threecal_settings[delete_data_on_uninstall]" value="1"
                                   <?php checked($settings['delete_data_on_uninstall'] ?? false); ?>>
                            <?php esc_html_e('Delete all data when plugin is uninstalled', '3task-calendar'); ?>
                        </label>
                        <p class="description threecal-warning">
                            <?php esc_html_e('Warning: This will permanently delete all events, locations, categories, and settings!', '3task-calendar'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <div class="threecal-shortcode-reference">
                <h3><?php esc_html_e('Shortcode Reference', '3task-calendar'); ?></h3>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Shortcode', '3task-calendar'); ?></th>
                            <th><?php esc_html_e('Description', '3task-calendar'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[threecal]</code></td>
                            <td><?php esc_html_e('Display the full calendar', '3task-calendar'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[threecal view="list"]</code></td>
                            <td><?php esc_html_e('Display as list view', '3task-calendar'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[threecal category="1"]</code></td>
                            <td><?php esc_html_e('Filter by category ID', '3task-calendar'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[threecal theme="glassmorphism"]</code></td>
                            <td><?php esc_html_e('Use specific theme', '3task-calendar'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[threecal_events limit="5"]</code></td>
                            <td><?php esc_html_e('Display event list', '3task-calendar'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[threecal_upcoming limit="3"]</code></td>
                            <td><?php esc_html_e('Display upcoming events widget', '3task-calendar'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[threecal_event id="123"]</code></td>
                            <td><?php esc_html_e('Display single event', '3task-calendar'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <?php submit_button(); ?>
    </form>
</div>
