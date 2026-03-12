<?php
/**
 * Events List View
 *
 * @package ThreeCal
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template file included in admin context.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Process bulk actions
$list_table = new ThreeCal_Events_List();
$list_table->process_bulk_action();
$list_table->prepare_items();

// Messages.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View file, message param is for display only.
$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
?>

<div class="wrap threecal-admin">
    <h1 class="wp-heading-inline"><?php esc_html_e('Events', '3task-calendar'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=3task-calendar-new')); ?>" class="page-title-action">
        <?php esc_html_e('Add New', '3task-calendar'); ?>
    </a>

    <?php if ($message === 'saved') : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e('Event saved successfully.', '3task-calendar'); ?></p>
    </div>
    <?php elseif ($message === 'deleted') : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e('Event deleted successfully.', '3task-calendar'); ?></p>
    </div>
    <?php endif; ?>

    <hr class="wp-header-end">

    <form method="get">
        <input type="hidden" name="page" value="3task-calendar">
        <?php
        $list_table->search_box(__('Search Events', '3task-calendar'), 'threecal-search');
        $list_table->display();
        ?>
    </form>

    <div class="threecal-stats">
        <h3><?php esc_html_e('Quick Stats', '3task-calendar'); ?></h3>
        <ul>
            <li>
                <strong><?php echo esc_html(ThreeCal_Event::count()); ?></strong>
                <?php esc_html_e('Total Events', '3task-calendar'); ?>
            </li>
            <li>
                <strong><?php echo esc_html(ThreeCal_Event::count(array('status' => 'published'))); ?></strong>
                <?php esc_html_e('Published', '3task-calendar'); ?>
            </li>
            <li>
                <strong><?php echo esc_html(count(ThreeCal_Event::get_upcoming(100))); ?></strong>
                <?php esc_html_e('Upcoming', '3task-calendar'); ?>
            </li>
        </ul>
    </div>
</div>
