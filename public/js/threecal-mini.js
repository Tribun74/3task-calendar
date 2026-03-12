/**
 * 3task Calendar - Mini Calendar JavaScript
 *
 * @package 3task-calendar
 * @since 1.2.2
 */

(function() {
    'use strict';

    /**
     * Initialize all mini calendars on the page
     */
    function initMiniCalendars() {
        var calendars = document.querySelectorAll('.threecal-mini-wrapper');

        calendars.forEach(function(calendar) {
            initMiniCalendar(calendar);
        });
    }

    /**
     * Initialize a single mini calendar
     */
    function initMiniCalendar(calendar) {
        // Skip if already initialized
        if (calendar.dataset.initialized === 'true') {
            return;
        }
        calendar.dataset.initialized = 'true';

        var popup = calendar.querySelector('.threecal-mini-popup');
        var popupDate = calendar.querySelector('.threecal-mini-popup-date');
        var popupEvents = calendar.querySelector('.threecal-mini-popup-events');
        var popupClose = calendar.querySelector('.threecal-mini-popup-close');

        if (!popup || !popupClose) {
            return;
        }

        // Close popup function
        function closePopup() {
            popup.style.display = 'none';
        }

        // Close button click
        popupClose.addEventListener('click', closePopup);

        // Click outside to close
        document.addEventListener('click', function(e) {
            if (!calendar.contains(e.target)) {
                closePopup();
            }
        });

        // Day click handler
        calendar.querySelectorAll('.threecal-mini-has-events').forEach(function(day) {
            day.addEventListener('click', function(e) {
                e.stopPropagation();

                var events = JSON.parse(this.getAttribute('data-events') || '[]');
                var dateFormatted = this.getAttribute('data-date-formatted');

                // If only one event with URL, go directly
                if (events.length === 1 && events[0].url) {
                    window.location.href = events[0].url;
                    return;
                }

                // Show popup
                popupDate.textContent = dateFormatted;
                popupEvents.innerHTML = '';

                events.forEach(function(event) {
                    var eventEl;
                    if (event.url) {
                        eventEl = document.createElement('a');
                        eventEl.href = event.url;
                        eventEl.className = 'threecal-mini-popup-event';
                    } else {
                        eventEl = document.createElement('div');
                        eventEl.className = 'threecal-mini-popup-event no-link';
                    }

                    eventEl.innerHTML =
                        '<div class="threecal-mini-popup-event-color" style="background-color: ' + event.color + ';"></div>' +
                        '<div class="threecal-mini-popup-event-info">' +
                            '<div class="threecal-mini-popup-event-title">' + event.title + '</div>' +
                            '<div class="threecal-mini-popup-event-time">' + event.time + '</div>' +
                        '</div>';

                    popupEvents.appendChild(eventEl);
                });

                popup.style.display = 'block';
            });
        });
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMiniCalendars);
    } else {
        initMiniCalendars();
    }

    // Re-initialize for dynamically added calendars (e.g., AJAX)
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    initMiniCalendars();
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
})();
