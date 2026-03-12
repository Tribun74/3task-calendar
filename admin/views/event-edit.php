<?php
/**
 * Event Edit View
 *
 * @package ThreeCal
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template file included in admin context.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Support both 'id' and 'event' parameters for backwards compatibility.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View file, nonce verified in parent admin class.
$event_id = isset( $_GET['event'] ) ? absint( $_GET['event'] ) : ( isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0 );
$event = null;

if ( $event_id > 0 ) {
    $event = ThreeCal_Event::get( $event_id );
    if ( ! $event ) {
        wp_die( esc_html__( 'Event not found.', '3task-calendar' ) );
    }
}

$is_new = empty($event);
$page_title = $is_new ? __('Add New Event', '3task-calendar') : __('Edit Event', '3task-calendar');

// Get data for dropdowns
$locations = ThreeCal_Location::get_all();
$categories = ThreeCal_Category::get_hierarchical();
$event_categories = $event ? ThreeCal_Event::get_categories($event_id) : array();
$event_category_ids = array_map(function($c) { return $c->id; }, $event_categories);

// Default values.
$defaults = array(
    'title'       => '',
    'description' => '',
    'start_date'  => gmdate( 'Y-m-d\TH:i' ),
    'end_date' => '',
    'all_day' => false,
    'location_id' => 0,
    'url' => '',
    'featured_image' => 0,
    'color' => '#3788d8',
    'status' => 'draft'
);

// Messages.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View file, message param is for display only.
$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
?>

<div class="wrap threecal-admin">
    <h1><?php echo esc_html($page_title); ?></h1>

    <?php if ($message === 'saved') : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e('Event saved successfully.', '3task-calendar'); ?></p>
    </div>
    <?php endif; ?>

    <form method="post" id="threecal-event-form">
        <?php wp_nonce_field('threecal_save_event'); ?>
        <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">

        <div class="threecal-edit-container">
            <div class="threecal-edit-main">
                <!-- Title -->
                <div class="threecal-field">
                    <label for="event_title"><?php esc_html_e('Event Title', '3task-calendar'); ?> <span class="required">*</span></label>
                    <input type="text" id="event_title" name="event_title" class="large-text"
                           value="<?php echo esc_attr($event ? $event->title : $defaults['title']); ?>" required>
                </div>

                <!-- Description -->
                <div class="threecal-field">
                    <label for="event_description"><?php esc_html_e('Description', '3task-calendar'); ?></label>
                    <?php
                    wp_editor(
                        $event ? $event->description : $defaults['description'],
                        'event_description',
                        array(
                            'textarea_name' => 'event_description',
                            'textarea_rows' => 10,
                            'media_buttons' => true
                        )
                    );
                    ?>
                </div>

                <!-- Date & Time -->
                <div class="threecal-field-row">
                    <div class="threecal-field threecal-field-half">
                        <label for="event_start_date"><?php esc_html_e( 'Start Date & Time', '3task-calendar' ); ?> <span class="required">*</span></label>
                        <input type="datetime-local" id="event_start_date" name="event_start_date"
                               value="<?php echo esc_attr( $event ? gmdate( 'Y-m-d\TH:i', strtotime( $event->start_date ) ) : $defaults['start_date'] ); ?>" required>
                    </div>

                    <div class="threecal-field threecal-field-half">
                        <label for="event_end_date"><?php esc_html_e( 'End Date & Time', '3task-calendar' ); ?></label>
                        <input type="datetime-local" id="event_end_date" name="event_end_date"
                               value="<?php echo esc_attr( $event && $event->end_date ? gmdate( 'Y-m-d\TH:i', strtotime( $event->end_date ) ) : '' ); ?>">
                    </div>
                </div>

                <div class="threecal-field">
                    <label>
                        <input type="checkbox" name="event_all_day" id="event_all_day" value="1"
                               <?php checked($event ? $event->all_day : $defaults['all_day']); ?>>
                        <?php esc_html_e('All-day event', '3task-calendar'); ?>
                    </label>
                </div>

                <!-- Location -->
                <div class="threecal-field">
                    <label for="event_location"><?php esc_html_e('Location', '3task-calendar'); ?></label>
                    <select id="event_location" name="event_location">
                        <option value="0"><?php esc_html_e('— No Location —', '3task-calendar'); ?></option>
                        <?php foreach ($locations as $loc) : ?>
                        <option value="<?php echo esc_attr($loc->id); ?>"
                                <?php selected($event ? $event->location_id : $defaults['location_id'], $loc->id); ?>>
                            <?php echo esc_html($loc->name); ?>
                            <?php if ($loc->city) : ?>
                                (<?php echo esc_html($loc->city); ?>)
                            <?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=3task-calendar&tab=locations')); ?>" class="threecal-add-new">
                        + <?php esc_html_e('Add Location', '3task-calendar'); ?>
                    </a>
                </div>

                <!-- URL -->
                <div class="threecal-field">
                    <label for="event_url"><?php esc_html_e('Event URL', '3task-calendar'); ?></label>
                    <input type="url" id="event_url" name="event_url" class="large-text"
                           value="<?php echo esc_url($event ? $event->url : $defaults['url']); ?>"
                           placeholder="https://">
                    <p class="description"><?php esc_html_e('Link to more information about this event.', '3task-calendar'); ?></p>
                </div>
            </div>

            <div class="threecal-edit-sidebar">
                <!-- Publish Box -->
                <div class="threecal-meta-box">
                    <h3><?php esc_html_e('Publish', '3task-calendar'); ?></h3>
                    <div class="threecal-meta-box-content">
                        <div class="threecal-field">
                            <label for="event_status"><?php esc_html_e('Status', '3task-calendar'); ?></label>
                            <select id="event_status" name="event_status">
                                <option value="draft" <?php selected($event ? $event->status : $defaults['status'], 'draft'); ?>>
                                    <?php esc_html_e('Draft', '3task-calendar'); ?>
                                </option>
                                <option value="published" <?php selected($event ? $event->status : $defaults['status'], 'published'); ?>>
                                    <?php esc_html_e('Published', '3task-calendar'); ?>
                                </option>
                                <option value="cancelled" <?php selected($event ? $event->status : $defaults['status'], 'cancelled'); ?>>
                                    <?php esc_html_e('Cancelled', '3task-calendar'); ?>
                                </option>
                            </select>
                        </div>

                        <div class="threecal-submit-actions">
                            <button type="submit" name="threecal_save_event" class="button button-primary button-large">
                                <?php echo $is_new ? esc_html__('Create Event', '3task-calendar') : esc_html__('Update Event', '3task-calendar'); ?>
                            </button>

                            <?php if (!$is_new) : ?>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=3task-calendar&action=delete&id=' . $event_id), 'threecal_delete_' . $event_id)); ?>"
                               class="threecal-delete-link"
                               onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this event?', '3task-calendar'); ?>');">
                                <?php esc_html_e('Delete', '3task-calendar'); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Categories -->
                <div class="threecal-meta-box">
                    <h3><?php esc_html_e('Categories', '3task-calendar'); ?></h3>
                    <div class="threecal-meta-box-content">
                        <?php if (!empty($categories)) : ?>
                        <div class="threecal-categories-list">
                            <?php foreach ($categories as $cat) : ?>
                            <label style="padding-left: <?php echo esc_attr($cat->level * 20); ?>px;">
                                <input type="checkbox" name="event_categories[]" value="<?php echo esc_attr($cat->id); ?>"
                                       <?php checked(in_array($cat->id, $event_category_ids)); ?>>
                                <span class="threecal-cat-color" style="background-color: <?php echo esc_attr($cat->color); ?>;"></span>
                                <?php echo esc_html($cat->name); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php else : ?>
                        <p class="description"><?php esc_html_e('No categories yet.', '3task-calendar'); ?></p>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=3task-calendar&tab=categories')); ?>" class="threecal-add-new">
                            + <?php esc_html_e('Add Category', '3task-calendar'); ?>
                        </a>
                    </div>
                </div>

                <!-- Color -->
                <div class="threecal-meta-box">
                    <h3><?php esc_html_e('Event Color', '3task-calendar'); ?></h3>
                    <div class="threecal-meta-box-content">
                        <input type="text" id="event_color" name="event_color" class="threecal-color-picker"
                               value="<?php echo esc_attr($event ? $event->color : $defaults['color']); ?>">
                    </div>
                </div>

                <!-- Featured Image -->
                <div class="threecal-meta-box">
                    <h3><?php esc_html_e('Featured Image', '3task-calendar'); ?></h3>
                    <div class="threecal-meta-box-content">
                        <div class="threecal-image-upload">
                            <input type="hidden" id="event_featured_image" name="event_featured_image"
                                   value="<?php echo esc_attr($event ? $event->featured_image : $defaults['featured_image']); ?>">
                            <div class="threecal-image-preview">
                                <?php if ($event && $event->featured_image) : ?>
                                    <?php echo wp_get_attachment_image($event->featured_image, 'medium'); ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button threecal-upload-image">
                                <?php esc_html_e('Select Image', '3task-calendar'); ?>
                            </button>
                            <button type="button" class="button threecal-remove-image" style="<?php echo ($event && $event->featured_image) ? '' : 'display:none;'; ?>">
                                <?php esc_html_e('Remove', '3task-calendar'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
