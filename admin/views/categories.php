<?php
/**
 * Categories View
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
    $editing = ThreeCal_Category::get( $edit_id );
}

$categories = ThreeCal_Category::get_hierarchical();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View file, message param is for display only.
$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
?>

<div class="wrap threecal-admin threecal-categories-page">
    <h1><?php esc_html_e('Categories', '3task-calendar'); ?></h1>

    <?php if ($message === 'saved') : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e('Category saved successfully.', '3task-calendar'); ?></p>
    </div>
    <?php elseif ($message === 'deleted') : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e('Category deleted successfully.', '3task-calendar'); ?></p>
    </div>
    <?php endif; ?>

    <div class="threecal-two-columns">
        <!-- Form Column -->
        <div class="threecal-column threecal-form-column">
            <div class="threecal-card">
                <h2><?php echo $editing ? esc_html__('Edit Category', '3task-calendar') : esc_html__('Add New Category', '3task-calendar'); ?></h2>

                <form method="post">
                    <?php wp_nonce_field('threecal_save_category'); ?>
                    <input type="hidden" name="category_id" value="<?php echo esc_attr($edit_id); ?>">

                    <div class="threecal-field">
                        <label for="category_name"><?php esc_html_e('Name', '3task-calendar'); ?> <span class="required">*</span></label>
                        <input type="text" id="category_name" name="category_name" required
                               value="<?php echo esc_attr($editing ? $editing->name : ''); ?>">
                    </div>

                    <div class="threecal-field">
                        <label for="category_slug"><?php esc_html_e('Slug', '3task-calendar'); ?></label>
                        <input type="text" id="category_slug" name="category_slug"
                               value="<?php echo esc_attr($editing ? $editing->slug : ''); ?>">
                        <p class="description"><?php esc_html_e('Leave empty to auto-generate from name.', '3task-calendar'); ?></p>
                    </div>

                    <div class="threecal-field">
                        <label for="category_parent"><?php esc_html_e('Parent Category', '3task-calendar'); ?></label>
                        <select id="category_parent" name="category_parent">
                            <option value="0"><?php esc_html_e('— None —', '3task-calendar'); ?></option>
                            <?php foreach ($categories as $cat) :
                                if ($editing && $cat->id === $editing->id) continue;
                            ?>
                            <option value="<?php echo esc_attr($cat->id); ?>"
                                    <?php selected($editing ? $editing->parent_id : 0, $cat->id); ?>>
                                <?php echo esc_html(str_repeat('— ', $cat->level) . $cat->name); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="threecal-field">
                        <label for="category_color"><?php esc_html_e('Color', '3task-calendar'); ?></label>
                        <input type="text" id="category_color" name="category_color" class="threecal-color-picker"
                               value="<?php echo esc_attr($editing ? $editing->color : '#3788d8'); ?>">
                    </div>

                    <div class="threecal-field">
                        <label for="category_sort_order"><?php esc_html_e('Sort Order', '3task-calendar'); ?></label>
                        <input type="number" id="category_sort_order" name="category_sort_order" min="0"
                               value="<?php echo esc_attr($editing ? $editing->sort_order : 0); ?>">
                    </div>

                    <div class="threecal-field">
                        <label for="category_description"><?php esc_html_e('Description', '3task-calendar'); ?></label>
                        <textarea id="category_description" name="category_description" rows="3"><?php echo esc_textarea($editing ? $editing->description : ''); ?></textarea>
                    </div>

                    <div class="threecal-submit-row">
                        <button type="submit" name="threecal_save_category" class="button button-primary">
                            <?php echo $editing ? esc_html__('Update Category', '3task-calendar') : esc_html__('Add Category', '3task-calendar'); ?>
                        </button>

                        <?php if ($editing) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=3task-calendar&tab=categories')); ?>" class="button">
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
                <h2><?php esc_html_e('All Categories', '3task-calendar'); ?></h2>

                <?php if (empty($categories)) : ?>
                <p class="threecal-no-items"><?php esc_html_e('No categories yet.', '3task-calendar'); ?></p>
                <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', '3task-calendar'); ?></th>
                            <th><?php esc_html_e('Slug', '3task-calendar'); ?></th>
                            <th><?php esc_html_e('Events', '3task-calendar'); ?></th>
                            <th class="threecal-actions-col"><?php esc_html_e('Actions', '3task-calendar'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat) : ?>
                        <tr>
                            <td>
                                <span class="threecal-cat-color" style="background-color: <?php echo esc_attr($cat->color); ?>;"></span>
                                <strong style="padding-left: <?php echo esc_attr($cat->level * 15); ?>px;">
                                    <?php echo esc_html($cat->name); ?>
                                </strong>
                            </td>
                            <td><code><?php echo esc_html($cat->slug); ?></code></td>
                            <td><?php echo esc_html($cat->get_event_count()); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=3task-calendar&tab=categories&edit=' . $cat->id)); ?>"
                                   class="button button-small">
                                    <?php esc_html_e('Edit', '3task-calendar'); ?>
                                </a>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=3task-calendar&tab=categories&action=delete_category&id=' . $cat->id), 'threecal_delete_category_' . $cat->id)); ?>"
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
