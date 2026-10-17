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
            this.initPrecisionToggle();
            this.initDeleteConfirm();
            this.initSlugGeneration();
            this.initHeartModal();
            this.initPrefill();
        },

        /**
         * "Fill in from this website": read the event's page and fill empty fields (since 1.7.0).
         */
        initPrefill: function() {
            var $btn = $( '#threecal-prefill-btn' );
            if ( ! $btn.length ) {
                return;
            }
            var $box = $( '#threecal-prefill-result' );
            var strings = ( typeof threecal_admin !== 'undefined' && threecal_admin.strings ) ? threecal_admin.strings : {};
            var show = function( text, kind ) {
                $box.attr( 'hidden', false ).removeClass( 'is-error is-ok' ).addClass( kind ).text( text );
            };
            var fillIfEmpty = function( selector, value ) {
                var $el = $( selector );
                if ( $el.length && value && ! $.trim( $el.val() ) ) {
                    $el.val( value ).trigger( 'change' );
                    return true;
                }
                return false;
            };
            $btn.on( 'click', function() {
                var url = $.trim( $( '#event_url' ).val() );
                if ( ! url ) {
                    show( strings.prefill_no_url || 'Please enter the address first.', 'is-error' );
                    $( '#event_url' ).trigger( 'focus' );
                    return;
                }
                $btn.prop( 'disabled', true );
                show( strings.prefill_wait || 'Reading the website …', '' );
                $.post( threecal_admin.ajax_url, {
                    action: 'threecal_prefill_from_url',
                    nonce: threecal_admin.nonce,
                    url: url
                } ).done( function( response ) {
                    if ( ! response || ! response.success ) {
                        show( ( response && response.data && response.data.message ) ? response.data.message : ( strings.prefill_error || 'The website could not be read.' ), 'is-error' );
                        return;
                    }
                    var d = response.data;
                    var taken = [];
                    var hints = [];
                    if ( fillIfEmpty( '#event_title', d.title ) ) { taken.push( d.labels.title ); }
                    if ( d.start && ! $( '#event_start_date' ).val() ) {
                        $( 'input[name="event_date_precision"][value="day"]' ).prop( 'checked', true ).trigger( 'change' );
                        $( '#event_start_date' ).val( d.start.replace( ' ', 'T' ) ).trigger( 'change' );
                        taken.push( d.labels.start );
                        if ( d.end && ! $( '#event_end_date' ).val() ) {
                            $( '#event_end_date' ).val( d.end.replace( ' ', 'T' ) ).trigger( 'change' );
                            taken.push( d.labels.end );
                        }
                        if ( d.all_day && ! $( '#event_all_day' ).is( ':checked' ) ) {
                            $( '#event_all_day' ).prop( 'checked', true ).trigger( 'change' );
                        }
                    }
                    if ( d.description ) {
                        var editor = ( window.tinyMCE && tinyMCE.get( 'event_description' ) ) ? tinyMCE.get( 'event_description' ) : null;
                        var $ta = $( '#event_description' );
                        if ( editor && ! editor.isHidden() ) {
                            if ( ! $.trim( editor.getContent( { format: 'text' } ) ) ) {
                                editor.setContent( '<p>' + $( '<div>' ).text( d.description ).html() + '</p>' );
                                taken.push( d.labels.description );
                            }
                        } else if ( fillIfEmpty( $ta, d.description ) ) {
                            taken.push( d.labels.description );
                        }
                    }
                    if ( d.organizer && ( d.organizer.name || d.organizer.url ) ) {
                        var o1 = fillIfEmpty( '#event_organizer_name', d.organizer.name );
                        var o2 = fillIfEmpty( '#event_organizer_url', d.organizer.url );
                        if ( o1 || o2 ) { taken.push( d.labels.organizer ); }
                    }
                    if ( d.location_id && $( '#event_location' ).val() === '0' ) {
                        $( '#event_location' ).val( String( d.location_id ) ).trigger( 'change' );
                        taken.push( d.labels.location );
                    } else if ( d.location && d.location.name && $( '#event_location' ).val() === '0' ) {
                        var where = [ d.location.name, d.location.address, $.trim( ( d.location.postal_code || '' ) + ' ' + ( d.location.city || '' ) ) ].filter( Boolean ).join( ', ' );
                        hints.push( ( strings.prefill_location || 'Location on the website: %s. Add it under Locations and choose it here.' ).replace( '%s', where ) );
                    }
                    if ( d.image ) {
                        hints.push( ( strings.prefill_image || 'Image on the website: %s' ).replace( '%s', d.image ) );
                    }
                    var text = taken.length
                        ? ( strings.prefill_done || 'Taken from the website: %s.' ).replace( '%s', taken.join( ', ' ) )
                        : ( strings.prefill_nothing_new || 'All fields were already filled in, nothing changed.' );
                    if ( hints.length ) {
                        text += ' ' + hints.join( ' ' );
                    }
                    show( text, 'is-ok' );
                } ).fail( function() {
                    show( strings.prefill_error || 'The website could not be read.', 'is-error' );
                } ).always( function() {
                    $btn.prop( 'disabled', false );
                } );
            } );
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
         * Switch between "the date is known" and "only the month is known".
         * A month-only event has no day, no time and no repeat pattern.
         */
        initPrecisionToggle: function() {
            var $radios = $('input[name="event_date_precision"]');
            if (!$radios.length) {
                return;
            }
            var $monthField = $('.threecal-month-field');
            var $monthInput = $('#event_start_month');
            var $dayFields = $('.threecal-day-fields');
            var $startInput = $('#event_start_date');
            var $repeat = $('.threecal-recurrence-fields');

            function apply() {
                var monthOnly = $radios.filter(':checked').val() === 'month';
                $monthField.prop('hidden', !monthOnly);
                $monthInput.prop('required', monthOnly);
                $dayFields.prop('hidden', monthOnly);
                $startInput.prop('required', !monthOnly);
                $repeat.prop('hidden', monthOnly);
                if (monthOnly) {
                    $('#event_recurrence').val('');
                    // Take the month of the date that is already entered.
                    if (!$monthInput.val() && $startInput.val()) {
                        $monthInput.val($startInput.val().substr(0, 7));
                    }
                }
            }

            $radios.on('change', apply);
            apply();
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
