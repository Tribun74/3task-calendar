<?php
/**
 * Locations View
 *
 * @package ThreeCal
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template file included in admin context.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View file, edit param is for loading data only.
$edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
$editing = null;

if ( $edit_id > 0 ) {
    $editing = ThreeCal_Location::get( $edit_id );
}

$locations = ThreeCal_Location::get_all();
$countries = ThreeCal_Location::get_countries();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View file, message param is for display only.
$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
$settings = get_option('threecal_settings', array());
$has_maps_key = !empty($settings['google_maps_api_key']);
?>

<div class="wrap threecal-admin threecal-locations-page">
    <h1><?php esc_html_e('Locations', '3task-calendar'); ?></h1>

    <?php if ($message === 'saved') : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e('Location saved successfully.', '3task-calendar'); ?></p>
    </div>
    <?php elseif ($message === 'deleted') : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e('Location deleted successfully.', '3task-calendar'); ?></p>
    </div>
    <?php endif; ?>

    <div class="threecal-two-columns">
        <!-- Form Column -->
        <div class="threecal-column threecal-form-column">
            <div class="threecal-card">
                <h2><?php echo $editing ? esc_html__('Edit Location', '3task-calendar') : esc_html__('Add New Location', '3task-calendar'); ?></h2>

                <form method="post">
                    <?php wp_nonce_field('threecal_save_location'); ?>
                    <input type="hidden" name="location_id" value="<?php echo esc_attr($edit_id); ?>">

                    <div class="threecal-field">
                        <label for="location_name"><?php esc_html_e('Name', '3task-calendar'); ?> <span class="required">*</span></label>
                        <input type="text" id="location_name" name="location_name" required
                               value="<?php echo esc_attr($editing ? $editing->name : ''); ?>">
                    </div>

                    <div class="threecal-field">
                        <label for="location_address"><?php esc_html_e('Address', '3task-calendar'); ?></label>
                        <input type="text" id="location_address" name="location_address"
                               value="<?php echo esc_attr($editing ? $editing->address : ''); ?>">
                    </div>

                    <div class="threecal-field-row">
                        <div class="threecal-field threecal-field-third">
                            <label for="location_postal_code"><?php esc_html_e('Postal Code', '3task-calendar'); ?></label>
                            <input type="text" id="location_postal_code" name="location_postal_code"
                                   value="<?php echo esc_attr($editing ? $editing->postal_code : ''); ?>">
                        </div>
                        <div class="threecal-field threecal-field-two-thirds">
                            <label for="location_city"><?php esc_html_e('City', '3task-calendar'); ?></label>
                            <input type="text" id="location_city" name="location_city"
                                   value="<?php echo esc_attr($editing ? $editing->city : ''); ?>">
                        </div>
                    </div>

                    <div class="threecal-field">
                        <label for="location_country"><?php esc_html_e('Country', '3task-calendar'); ?></label>
                        <select id="location_country" name="location_country">
                            <?php foreach ($countries as $code => $name) : ?>
                            <option value="<?php echo esc_attr($code); ?>"
                                    <?php selected($editing ? $editing->country : 'DE', $code); ?>>
                                <?php echo esc_html($name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="threecal-field-row">
                        <div class="threecal-field threecal-field-half">
                            <label for="location_latitude"><?php esc_html_e('Latitude', '3task-calendar'); ?></label>
                            <input type="text" id="location_latitude" name="location_latitude"
                                   value="<?php echo esc_attr($editing && $editing->latitude ? $editing->latitude : ''); ?>">
                        </div>
                        <div class="threecal-field threecal-field-half">
                            <label for="location_longitude"><?php esc_html_e('Longitude', '3task-calendar'); ?></label>
                            <input type="text" id="location_longitude" name="location_longitude"
                                   value="<?php echo esc_attr($editing && $editing->longitude ? $editing->longitude : ''); ?>">
                        </div>
                    </div>

                    <?php if ($has_maps_key) : ?>
                    <div class="threecal-field">
                        <label>
                            <input type="checkbox" name="location_geocode" value="1">
                            <?php esc_html_e('Auto-detect coordinates from address', '3task-calendar'); ?>
                        </label>
                    </div>
                    <?php endif; ?>

                    <div class="threecal-field-row">
                        <div class="threecal-field threecal-field-half">
                            <label for="location_phone"><?php esc_html_e('Phone', '3task-calendar'); ?></label>
                            <input type="tel" id="location_phone" name="location_phone"
                                   value="<?php echo esc_attr($editing ? $editing->phone : ''); ?>">
                        </div>
                        <div class="threecal-field threecal-field-half">
                            <label for="location_email"><?php esc_html_e('Email', '3task-calendar'); ?></label>
                            <input type="email" id="location_email" name="location_email"
                                   value="<?php echo esc_attr($editing ? $editing->email : ''); ?>">
                        </div>
                    </div>

                    <div class="threecal-field">
                        <label for="location_website"><?php esc_html_e('Website', '3task-calendar'); ?></label>
                        <input type="url" id="location_website" name="location_website"
                               value="<?php echo esc_url($editing ? $editing->website : ''); ?>">
                    </div>

                    <div class="threecal-field">
                        <label for="location_description"><?php esc_html_e('Description', '3task-calendar'); ?></label>
                        <textarea id="location_description" name="location_description" rows="3"><?php echo esc_textarea($editing ? $editing->description : ''); ?></textarea>
                    </div>

                    <!-- Featured Image -->
                    <div class="threecal-field">
                        <label><?php esc_html_e('Image', '3task-calendar'); ?></label>
                        <div class="threecal-image-upload">
                            <input type="hidden" id="location_featured_image" name="location_featured_image"
                                   value="<?php echo esc_attr($editing ? $editing->featured_image : 0); ?>">
                            <div class="threecal-image-preview">
                                <?php if ($editing && $editing->featured_image) : ?>
                                    <?php echo wp_get_attachment_image($editing->featured_image, 'thumbnail'); ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button threecal-upload-image">
                                <?php esc_html_e('Select Image', '3task-calendar'); ?>
                            </button>
                            <button type="button" class="button threecal-remove-image" style="<?php echo ($editing && $editing->featured_image) ? '' : 'display:none;'; ?>">
                                <?php esc_html_e('Remove', '3task-calendar'); ?>
                            </button>
                        </div>
                    </div>

                    <div class="threecal-submit-row">
                        <button type="submit" name="threecal_save_location" class="button button-primary">
                            <?php echo $editing ? esc_html__('Update Location', '3task-calendar') : esc_html__('Add Location', '3task-calendar'); ?>
                        </button>

                        <?php if ($editing) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=3task-calendar&tab=locations')); ?>" class="button">
                            <?php esc_html_e('Cancel', '3task-calendar'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- List Column -->
        <div class="threecal-column threecal-list-column">
            <div class="threecal-card">
                <h2><?php esc_html_e('All Locations', '3task-calendar'); ?></h2>

                <?php if (empty($locations)) : ?>
                <p class="threecal-no-items"><?php esc_html_e('No locations yet.', '3task-calendar'); ?></p>
                <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', '3task-calendar'); ?></th>
                            <th><?php esc_html_e('City', '3task-calendar'); ?></th>
                            <th><?php esc_html_e('Events', '3task-calendar'); ?></th>
                            <th class="threecal-actions-col"><?php esc_html_e('Actions', '3task-calendar'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locations as $loc) :
                            $event_count = count($loc->get_events(array('status' => 'published')));
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($loc->name); ?></strong>
                                <?php if ($loc->address) : ?>
                                <br><small><?php echo esc_html($loc->address); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($loc->city ?: '—'); ?></td>
                            <td><?php echo esc_html($event_count); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=3task-calendar&tab=locations&edit=' . $loc->id)); ?>"
                                   class="button button-small">
                                    <?php esc_html_e('Edit', '3task-calendar'); ?>
                                </a>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=3task-calendar&tab=locations&action=delete_location&id=' . $loc->id), 'threecal_delete_location_' . $loc->id)); ?>"
                                   class="button button-small threecal-delete"
                                   onclick="return confirm('<?php esc_attr_e('Are you sure?', '3task-calendar'); ?>');">
                                    <?php esc_html_e('Delete', '3task-calendar'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
