/**
 * ThreeCal Admin JavaScript v1.2
 *
 * @package ThreeCal
 * @since 1.0.0
 */

( function( $ ) {
    'use strict';

    var ThreeCalAdmin = {

        /**
         * Initialize admin functionality.
         */
        init: function() {
            this.initColorPicker();
            this.initMediaUploader();
            this.initAllDayToggle();
            this.initDeleteConfirm();
            this.initSlugGeneration();
            this.initHeartModal();
        },

        /**
         * Initialize heart support modal.
         */
        initHeartModal: function() {
            var $modal = $( '#threecal-heart-modal' );
            var $heartBtn = $( '#threecal-heart-support' );
            var $closeBtn = $modal.find( '.threecal-modal-close' );

            // Open modal on heart button click
            $heartBtn.on( 'click', function( e ) {
                e.preventDefault();
                $modal.fadeIn( 200 );
            } );

            // Close modal
            $closeBtn.on( 'click', function() {
                $modal.fadeOut( 200 );
            } );

            // Close on outside click
            $modal.on( 'click', function( e ) {
                if ( e.target === this ) {
                    $modal.fadeOut( 200 );
                }
            } );

            // Close on ESC key
            $( document ).on( 'keydown', function( e ) {
                if ( e.key === 'Escape' && $modal.is( ':visible' ) ) {
                    $modal.fadeOut( 200 );
                }
            } );

            // Copy code button
            $( '.copy-code-btn' ).on( 'click', function() {
                var $btn = $( this );
                var textToCopy = $btn.data( 'copy' );

                if ( navigator.clipboard && navigator.clipboard.writeText ) {
                    navigator.clipboard.writeText( textToCopy ).then( function() {
                        var originalText = $btn.text();
                        $btn.text( 'Copied!' );
                        setTimeout( function() {
                            $btn.text( originalText );
                        }, 2000 );
                    } );
                }
            } );
        },

        /**
         * Initialize color picker
         */
        initColorPicker: function() {
            if ($.fn.wpColorPicker) {
                $('.threecal-color-picker').wpColorPicker();
            }
        },

        /**
         * Initialize media uploader
         */
        initMediaUploader: function() {
            var frame;

            $(document).on('click', '.threecal-upload-image', function(e) {
                e.preventDefault();

                // Check if wp.media is available
                if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                    console.error('ThreeCal: wp.media is not available');
                    return;
                }

                var $button = $(this);
                var $container = $button.closest('.threecal-image-upload');
                var $input = $container.find('input[type="hidden"]');
                var $preview = $container.find('.threecal-image-preview');
                var $removeBtn = $container.find('.threecal-remove-image');

                // Get localized strings with fallbacks
                var selectTitle = (typeof threecal_admin !== 'undefined' && threecal_admin.strings)
                    ? threecal_admin.strings.select_image
                    : 'Select Image';
                var useText = (typeof threecal_admin !== 'undefined' && threecal_admin.strings)
                    ? threecal_admin.strings.use_image
                    : 'Use Image';

                // Create media frame
                if (!frame) {
                    frame = wp.media({
                        title: selectTitle,
                        button: {
                            text: useText
                        },
                        multiple: false
                    });

                    // Handle selection - bind only once when frame is created
                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();

                        $input.val(attachment.id);

                        var imgUrl = attachment.sizes && attachment.sizes.thumbnail
                            ? attachment.sizes.thumbnail.url
                            : attachment.url;

                        $preview.html('<img src="' + imgUrl + '" alt="">');
                        $removeBtn.show();
                    });
                }

                frame.open();
            });

            // Remove image
            $(document).on('click', '.threecal-remove-image', function(e) {
                e.preventDefault();

                var $button = $(this);
                var $container = $button.closest('.threecal-image-upload');
                var $input = $container.find('input[type="hidden"]');
                var $preview = $container.find('.threecal-image-preview');

                $input.val('');
                $preview.empty();
                $button.hide();
            });
        },

        /**
         * Toggle time inputs for all-day events
         */
        initAllDayToggle: function() {
            var $checkbox = $('#event_all_day');
            var $startInput = $('#event_start_date');
            var $endInput = $('#event_end_date');

            function toggleTimeInputs() {
                if ($checkbox.is(':checked')) {
                    // Switch to date only
                    $startInput.attr('type', 'date');
                    $endInput.attr('type', 'date');

                    // Convert datetime-local to date if needed
                    if ($startInput.val().includes('T')) {
                        $startInput.val($startInput.val().split('T')[0]);
                    }
                    if ($endInput.val().includes('T')) {
                        $endInput.val($endInput.val().split('T')[0]);
                    }
                } else {
                    // Switch to datetime-local
                    $startInput.attr('type', 'datetime-local');
                    $endInput.attr('type', 'datetime-local');

                    // Add default time if date only
                    if ($startInput.val() && !$startInput.val().includes('T')) {
                        $startInput.val($startInput.val() + 'T09:00');
                    }
                    if ($endInput.val() && !$endInput.val().includes('T')) {
                        $endInput.val($endInput.val() + 'T17:00');
                    }
                }
            }

            $checkbox.on('change', toggleTimeInputs);

            // Initial state
            if ($checkbox.length) {
                toggleTimeInputs();
            }
        },

        /**
         * Delete confirmation
         */
        initDeleteConfirm: function() {
            $(document).on('click', '.threecal-delete', function(e) {
                if (!confirm(threecal_admin.strings.confirm_delete)) {
                    e.preventDefault();
                    return false;
                }
            });
        },

        /**
         * Auto-generate slug from name
         */
        initSlugGeneration: function() {
            var $nameInput = $('#category_name');
            var $slugInput = $('#category_slug');
            var slugManuallyEdited = false;

            // Check if slug was manually edited
            $slugInput.on('input', function() {
                slugManuallyEdited = true;
            });

            // Generate slug from name
            $nameInput.on('input', function() {
                if (!slugManuallyEdited && !$slugInput.val()) {
                    var slug = $(this).val()
                        .toLowerCase()
                        .replace(/[äöü]/g, function(match) {
                            return {ä: 'ae', ö: 'oe', ü: 'ue'}[match];
                        })
                        .replace(/ß/g, 'ss')
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/(^-|-$)/g, '');

                    $slugInput.val(slug);
                }
            });
        }
    };

    // Expose globally
    window.ThreeCalAdmin = ThreeCalAdmin;

    // Initialize on document ready
    $(document).ready(function() {
        ThreeCalAdmin.init();
    });

})(jQuery);

/**
 * Global functions for inline onclick handlers
 * Uses relative admin URLs for simplicity
 */

// Helper to get admin URL base
function threecalGetAdminUrl() {
    if (typeof threecal_admin !== 'undefined' && threecal_admin.admin_url) {
        return threecal_admin.admin_url;
    }
    // Fallback: extract from current URL
    var path = window.location.pathname;
    var adminPos = path.indexOf('/wp-admin/');
    if (adminPos !== -1) {
        return window.location.origin + path.substring(0, adminPos) + '/wp-admin/';
    }
    return '/wp-admin/';
}

// Helper to get nonce
function threecalGetNonce() {
    if (typeof threecal_admin !== 'undefined' && threecal_admin.nonce) {
        return threecal_admin.nonce;
    }
    return '';
}

// Event functions
function threecalDeleteEvent(id) {
    var msg = (typeof threecal_admin !== 'undefined' && threecal_admin.strings)
        ? threecal_admin.strings.confirm_delete
        : 'Are you sure you want to delete this event?';
    if (confirm(msg)) {
        window.location.href = threecalGetAdminUrl() + 'admin.php?page=3task-calendar&tab=events&action=delete&id=' + id + '&_wpnonce=' + threecalGetNonce();
    }
}

// Category functions
function threecalAddCategory() {
    window.location.href = threecalGetAdminUrl() + 'admin.php?page=3task-calendar&tab=categories&action=new';
}

function threecalEditCategory(id) {
    window.location.href = threecalGetAdminUrl() + 'admin.php?page=3task-calendar&tab=categories&edit=' + id;
}

function threecalDeleteCategory(id) {
    var msg = (typeof threecal_admin !== 'undefined' && threecal_admin.strings)
        ? threecal_admin.strings.confirm_delete
        : 'Are you sure you want to delete this category?';
    if (confirm(msg)) {
        window.location.href = threecalGetAdminUrl() + 'admin.php?page=3task-calendar&tab=categories&action=delete_category&id=' + id + '&_wpnonce=' + threecalGetNonce();
    }
}

// Location functions
function threecalAddLocation() {
    window.location.href = threecalGetAdminUrl() + 'admin.php?page=3task-calendar&tab=locations&action=new';
}

function threecalEditLocation(id) {
    window.location.href = threecalGetAdminUrl() + 'admin.php?page=3task-calendar&tab=locations&edit=' + id;
}

function threecalDeleteLocation(id) {
    var msg = (typeof threecal_admin !== 'undefined' && threecal_admin.strings)
        ? threecal_admin.strings.confirm_delete
        : 'Are you sure you want to delete this location?';
    if (confirm(msg)) {
        window.location.href = threecalGetAdminUrl() + 'admin.php?page=3task-calendar&tab=locations&action=delete_location&id=' + id + '&_wpnonce=' + threecalGetNonce();
    }
}
