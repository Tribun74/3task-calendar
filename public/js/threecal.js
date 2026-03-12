/**
 * ThreeCal - Frontend JavaScript
 * Calendar Interactions
 */

(function($) {
    'use strict';

    // ThreeCal Main Object
    var ThreeCal = {

        /**
         * Initialize all calendars on page
         */
        init: function() {
            var self = this;

            // Initialize each calendar instance
            $('.threecal-wrapper').each(function() {
                self.initCalendar($(this));
            });

            // Global event handlers
            this.initModalClose();
        },

        /**
         * Initialize a single calendar instance
         */
        initCalendar: function($calendar) {
            var self = this;
            var calendarId = $calendar.attr('id');

            // Store calendar data
            $calendar.data('threecal', {
                view: $calendar.data('view') || 'month',
                category: $calendar.data('category') || 0,
                location: $calendar.data('location') || 0,
                weekStarts: $calendar.data('week-starts') || 1,
                month: parseInt($calendar.find('.threecal-calendar').data('month')),
                year: parseInt($calendar.find('.threecal-calendar').data('year'))
            });

            // Navigation events
            $calendar.find('.threecal-prev').on('click', function() {
                self.navigateMonth($calendar, -1);
            });

            $calendar.find('.threecal-next').on('click', function() {
                self.navigateMonth($calendar, 1);
            });

            $calendar.find('.threecal-today').on('click', function() {
                self.goToToday($calendar);
            });

            // Category filter
            $calendar.find('.threecal-category-filter').on('change', function() {
                var data = $calendar.data('threecal');
                data.category = parseInt($(this).val()) || 0;
                $calendar.data('threecal', data);
                self.loadMonth($calendar);
            });

            // View switcher
            $calendar.find('.threecal-view-btn').on('click', function() {
                var $btn = $(this);
                var view = $btn.data('view');

                $calendar.find('.threecal-view-btn').removeClass('active');
                $btn.addClass('active');

                var data = $calendar.data('threecal');
                data.view = view;
                $calendar.data('threecal', data);

                // Load appropriate view
                self.loadMonth($calendar);
            });

            // Event click handlers
            $calendar.on('click', '.threecal-event-dot', function(e) {
                e.preventDefault();
                var eventId = $(this).data('event-id');
                self.showEventModal($calendar, eventId);
            });

            // More events click
            $calendar.on('click', '.threecal-more-events', function(e) {
                e.preventDefault();
                var date = $(this).data('date');
                self.showDayEvents($calendar, date);
            });
        },

        /**
         * Navigate to previous/next month
         */
        navigateMonth: function($calendar, direction) {
            var data = $calendar.data('threecal');

            data.month += direction;

            if (data.month > 12) {
                data.month = 1;
                data.year++;
            } else if (data.month < 1) {
                data.month = 12;
                data.year--;
            }

            $calendar.data('threecal', data);
            this.loadMonth($calendar);
        },

        /**
         * Go to today
         */
        goToToday: function($calendar) {
            var now = new Date();
            var data = $calendar.data('threecal');

            data.month = now.getMonth() + 1;
            data.year = now.getFullYear();

            $calendar.data('threecal', data);
            this.loadMonth($calendar);
        },

        /**
         * Load month via AJAX
         */
        loadMonth: function($calendar) {
            var self = this;
            var data = $calendar.data('threecal');
            var $calendarGrid = $calendar.find('.threecal-calendar');
            var $title = $calendar.find('.threecal-title');

            // Add loading state
            $calendarGrid.addClass('threecal-loading');

            // Update title
            var monthName = threecal_data.i18n.months[data.month - 1];
            $title.text(monthName + ' ' + data.year);

            $.ajax({
                url: threecal_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'threecal_get_events',
                    nonce: threecal_data.nonce,
                    month: data.month,
                    year: data.year,
                    category: data.category,
                    location: data.location,
                    view: data.view
                },
                success: function(response) {
                    if (response.success) {
                        if (data.view === 'list') {
                            self.renderList($calendar, response.data);
                        } else {
                            self.renderMonth($calendar, response.data);
                        }
                    }
                },
                complete: function() {
                    $calendarGrid.removeClass('threecal-loading');
                }
            });
        },

        /**
         * Render month grid
         */
        renderMonth: function($calendar, events) {
            var data = $calendar.data('threecal');
            var $calendarGrid = $calendar.find('.threecal-calendar');

            // Calculate calendar structure
            var firstDay = new Date(data.year, data.month - 1, 1);
            var lastDay = new Date(data.year, data.month, 0);
            var daysInMonth = lastDay.getDate();
            var firstWeekday = firstDay.getDay();

            // Adjust for week start
            firstWeekday = (firstWeekday - data.weekStarts + 7) % 7;

            // Group events by day
            var eventsByDay = {};
            events.forEach(function(event) {
                var day = event.day;
                if (!eventsByDay[day]) {
                    eventsByDay[day] = [];
                }
                eventsByDay[day].push(event);
            });

            // Build HTML
            var html = '<table class="threecal-month-grid" role="grid"><thead><tr>';

            // Weekday headers
            for (var i = 0; i < 7; i++) {
                var dayIndex = (i + data.weekStarts) % 7;
                html += '<th scope="col">' + threecal_data.i18n.weekdays[dayIndex] + '</th>';
            }
            html += '</tr></thead><tbody>';

            // Calculate weeks
            var totalCells = firstWeekday + daysInMonth;
            var weeks = Math.ceil(totalCells / 7);
            var currentDay = 1;
            var today = new Date();
            var todayStr = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');

            for (var week = 0; week < weeks; week++) {
                html += '<tr>';

                for (var weekday = 0; weekday < 7; weekday++) {
                    var cellIndex = week * 7 + weekday;

                    if (cellIndex < firstWeekday || currentDay > daysInMonth) {
                        html += '<td class="threecal-day threecal-day-empty"></td>';
                    } else {
                        var dateStr = data.year + '-' + String(data.month).padStart(2, '0') + '-' + String(currentDay).padStart(2, '0');
                        var isToday = dateStr === todayStr;
                        var dayEvents = eventsByDay[currentDay] || [];
                        var hasEvents = dayEvents.length > 0;

                        var classes = 'threecal-day';
                        if (isToday) classes += ' threecal-today';
                        if (hasEvents) classes += ' threecal-has-events';

                        html += '<td class="' + classes + '" data-date="' + dateStr + '">';
                        html += '<div class="threecal-day-header">';
                        html += '<span class="threecal-day-number">' + currentDay + '</span>';
                        html += '</div>';

                        if (hasEvents) {
                            html += '<div class="threecal-day-events">';

                            var displayEvents = dayEvents.slice(0, 3);
                            displayEvents.forEach(function(event) {
                                html += '<a href="#" class="threecal-event-dot" data-event-id="' + event.id + '" style="background-color: ' + event.color + ';" title="' + self.escapeHtml(event.title) + '">';
                                html += '<span class="threecal-event-title">' + self.escapeHtml(event.title) + '</span>';
                                html += '</a>';
                            });

                            if (dayEvents.length > 3) {
                                html += '<a href="#" class="threecal-more-events" data-date="' + dateStr + '">+' + (dayEvents.length - 3) + ' ' + threecal_data.i18n.more + '</a>';
                            }

                            html += '</div>';
                        }

                        html += '</td>';
                        currentDay++;
                    }
                }

                html += '</tr>';
            }

            html += '</tbody></table>';

            // Update DOM with animation
            $calendarGrid.data('month', data.month).data('year', data.year);
            $calendarGrid.html(html);
        },

        /**
         * Render list view
         */
        renderList: function($calendar, events) {
            var self = this;
            var data = $calendar.data('threecal');
            var $calendarGrid = $calendar.find('.threecal-calendar');

            // Sort events by date
            events.sort(function(a, b) {
                return a.day - b.day;
            });

            // Build HTML
            var html = '<div class="threecal-list-view">';

            if (events.length === 0) {
                html += '<div class="threecal-no-events">' + threecal_data.i18n.no_events + '</div>';
            } else {
                var currentDay = null;

                events.forEach(function(event) {
                    // Group header for each day
                    if (event.day !== currentDay) {
                        if (currentDay !== null) {
                            html += '</div>'; // Close previous day group
                        }
                        currentDay = event.day;

                        var dateStr = data.year + '-' + String(data.month).padStart(2, '0') + '-' + String(event.day).padStart(2, '0');
                        var dateObj = new Date(data.year, data.month - 1, event.day);
                        var dayName = threecal_data.i18n.weekdays_full ? threecal_data.i18n.weekdays_full[dateObj.getDay()] : threecal_data.i18n.weekdays[dateObj.getDay()];
                        var formattedDate = event.day + '. ' + threecal_data.i18n.months[data.month - 1];

                        html += '<div class="threecal-list-day" data-date="' + dateStr + '">';
                        html += '<div class="threecal-list-day-header">';
                        html += '<span class="threecal-list-day-name">' + dayName + '</span>';
                        html += '<span class="threecal-list-day-date">' + formattedDate + '</span>';
                        html += '</div>';
                    }

                    // Event item
                    html += '<div class="threecal-list-event" data-event-id="' + event.id + '">';
                    html += '<div class="threecal-list-event-color" style="background-color: ' + event.color + ';"></div>';
                    html += '<div class="threecal-list-event-content">';
                    html += '<div class="threecal-list-event-title">' + self.escapeHtml(event.title) + '</div>';
                    if (event.time) {
                        html += '<div class="threecal-list-event-time">' + event.time + '</div>';
                    }
                    html += '</div>';
                    html += '</div>';
                });

                if (currentDay !== null) {
                    html += '</div>'; // Close last day group
                }
            }

            html += '</div>';

            // Update DOM
            $calendarGrid.data('month', data.month).data('year', data.year);
            $calendarGrid.html(html);

            // Add click handler for list events
            $calendarGrid.find('.threecal-list-event').on('click', function() {
                var eventId = $(this).data('event-id');
                self.showEventModal($calendar, eventId);
            });
        },

        /**
         * Show event modal
         */
        showEventModal: function($calendar, eventId) {
            var self = this;
            var $modal = $calendar.find('.threecal-modal');
            var $body = $modal.find('.threecal-modal-body');

            // Show loading
            $body.html('<div class="threecal-loading" style="min-height: 200px;"></div>');
            $modal.addClass('active').show();

            // Fetch event details
            $.ajax({
                url: threecal_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'threecal_get_event_details',
                    nonce: threecal_data.nonce,
                    event_id: eventId
                },
                success: function(response) {
                    if (response.success) {
                        self.renderEventModal($body, response.data);
                    } else {
                        $body.html('<p>' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    $body.html('<p>' + threecal_data.i18n.error + '</p>');
                }
            });
        },

        /**
         * Render event modal content
         */
        renderEventModal: function($body, event) {
            var html = '';

            // Featured image
            if (event.featured_image) {
                html += '<div class="threecal-modal-event-image">';
                html += '<img src="' + event.featured_image + '" alt="' + this.escapeHtml(event.title) + '">';
                html += '</div>';
            }

            // Title
            html += '<h3 class="threecal-modal-event-title">' + this.escapeHtml(event.title) + '</h3>';

            // Categories
            if (event.categories && event.categories.length > 0) {
                html += '<div class="threecal-modal-event-categories">';
                event.categories.forEach(function(cat) {
                    html += '<span class="threecal-modal-category-tag" style="background-color: ' + cat.color + ';">' + cat.name + '</span>';
                });
                html += '</div>';
            }

            // Meta
            html += '<div class="threecal-modal-event-meta">';

            // Date
            html += '<div class="threecal-modal-meta-item">';
            html += '<span class="dashicons dashicons-calendar-alt"></span>';
            html += '<div>' + event.start_date;
            if (event.end_date && event.end_date !== event.start_date) {
                html += ' - ' + event.end_date;
            }
            html += '</div></div>';

            // Time
            if (!event.all_day) {
                html += '<div class="threecal-modal-meta-item">';
                html += '<span class="dashicons dashicons-clock"></span>';
                html += '<div>' + event.start_time;
                if (event.end_time) {
                    html += ' - ' + event.end_time;
                }
                html += '</div></div>';
            }

            // Location
            if (event.location) {
                html += '<div class="threecal-modal-meta-item">';
                html += '<span class="dashicons dashicons-location"></span>';
                html += '<div><strong>' + this.escapeHtml(event.location.name) + '</strong>';
                if (event.location.address) {
                    html += '<br>' + this.escapeHtml(event.location.address);
                }
                html += '</div></div>';
            }

            html += '</div>';

            // Description
            if (event.description) {
                html += '<div class="threecal-modal-event-description">' + event.description + '</div>';
            }

            // URL
            if (event.url) {
                html += '<a href="' + event.url + '" class="threecal-modal-event-url" target="_blank" rel="noopener">';
                html += '<span class="dashicons dashicons-external"></span> ' + threecal_data.i18n.more_info;
                html += '</a>';
            }

            $body.html(html);
        },

        /**
         * Close modal on backdrop click or close button
         */
        initModalClose: function() {
            $(document).on('click', '.threecal-modal', function(e) {
                if (e.target === this) {
                    $(this).removeClass('active').hide();
                }
            });

            $(document).on('click', '.threecal-modal-close', function() {
                $(this).closest('.threecal-modal').removeClass('active').hide();
            });

            // ESC key closes modal
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.threecal-modal.active').removeClass('active').hide();
                }
            });
        },

        /**
         * Show all events for a specific day
         */
        showDayEvents: function($calendar, date) {
            // This could open a modal or expand the day view
            console.log('Show all events for:', date);
        },

        /**
         * Escape HTML entities
         */
        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Expose globally
    window.ThreeCal = ThreeCal;

    // Auto-init on document ready
    $(document).ready(function() {
        ThreeCal.init();
    });

})(jQuery);
