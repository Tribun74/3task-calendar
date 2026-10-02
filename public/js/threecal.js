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

            // Small screens: start with the list, the month grid only fits dots there
            if (String($calendar.data('mobile-list')) === '1' && window.matchMedia && window.matchMedia('(max-width: 600px)').matches) {
                var initial = $calendar.data('threecal');
                if (initial.view === 'month') {
                    initial.view = 'list';
                    $calendar.data('threecal', initial);
                    $calendar.find('.threecal-view-btn').removeClass('active').attr('aria-pressed', 'false').filter('[data-view="list"]').addClass('active').attr('aria-pressed', 'true');
                }
            }

            // The server always renders the month grid, so the list has to be loaded here
            if ($calendar.data('threecal').view === 'list') {
                self.loadMonth($calendar);
            }

            // Navigation events
            $calendar.find('.threecal-prev').on('click', function() {
                self.navigateMonth($calendar, -1);
            });

            $calendar.find('.threecal-next').on('click', function() {
                self.navigateMonth($calendar, 1);
            });

            $calendar.find('.threecal-today-btn').on('click', function() {
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

                $calendar.find('.threecal-view-btn').removeClass('active').attr('aria-pressed', 'false');
                $btn.addClass('active').attr('aria-pressed', 'true');

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

            // More events click: expand the hidden events of that day
            $calendar.on('click', '.threecal-more-events', function(e) {
                e.preventDefault();
                self.showDayEvents($(this));
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
            $calendarGrid.addClass('threecal-loading').attr('aria-busy', 'true');

            $.ajax({
                url: threecal_data.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'threecal_get_events',
                    month: data.month,
                    year: data.year,
                    category: data.category,
                    location: data.location,
                    view: data.view
                },
                success: function(response) {
                    if (response && response.success) {
                        // Update the title only after the new month has arrived
                        var monthName = threecal_data.i18n.months[data.month - 1];
                        $title.html('<span class="threecal-title-month">' + self.escapeHtml(monthName) + '</span> <span class="threecal-title-year">' + parseInt(data.year, 10) + '</span>');

                        if (data.view === 'list') {
                            self.renderList($calendar, response.data);
                        } else {
                            self.renderMonth($calendar, response.data);
                        }
                    } else {
                        self.showLoadError($calendar);
                    }
                },
                error: function() {
                    self.showLoadError($calendar);
                },
                complete: function() {
                    $calendarGrid.removeClass('threecal-loading').removeAttr('aria-busy');
                }
            });
        },

        /**
         * Show an error and restore the month that is still on screen
         */
        showLoadError: function($calendar) {
            var data = $calendar.data('threecal');
            var $grid = $calendar.find('.threecal-calendar');
            data.month = parseInt($grid.data('month'), 10) || data.month;
            data.year = parseInt($grid.data('year'), 10) || data.year;
            $calendar.data('threecal', data);

            $calendar.find('.threecal-load-error').remove();
            $('<div class="threecal-load-error" role="alert"></div>')
                .text(threecal_data.i18n.error)
                .insertBefore($grid);
        },

        /**
         * Render month grid
         */
        renderMonth: function($calendar, events) {
            var self = this;
            var data = $calendar.data('threecal');
            var $calendarGrid = $calendar.find('.threecal-calendar');

            // Calculate calendar structure
            var firstDay = new Date(data.year, data.month - 1, 1);
            var lastDay = new Date(data.year, data.month, 0);
            var daysInMonth = lastDay.getDate();
            var firstWeekday = firstDay.getDay();

            // Adjust for week start
            firstWeekday = (firstWeekday - data.weekStarts + 7) % 7;

            $calendar.find('.threecal-load-error').remove();

            // Group events by every day they cover (multi-day events)
            var eventsByDay = {};
            events.forEach(function(event) {
                var days = (event.days && event.days.length) ? event.days : [event.day];
                days.forEach(function(day) {
                    if (!eventsByDay[day]) {
                        eventsByDay[day] = [];
                    }
                    eventsByDay[day].push(event);
                });
            });

            // Build HTML
            var html = '<table class="threecal-month-grid"><thead><tr>';

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

                    var realWeekday = (weekday + data.weekStarts) % 7;
                    var weekendClass = (realWeekday === 0 || realWeekday === 6) ? ' threecal-weekend' : '';

                    if (cellIndex < firstWeekday || currentDay > daysInMonth) {
                        html += '<td class="threecal-day threecal-day-empty' + weekendClass + '"></td>';
                    } else {
                        var dateStr = data.year + '-' + String(data.month).padStart(2, '0') + '-' + String(currentDay).padStart(2, '0');
                        var isToday = dateStr === todayStr;
                        var dayEvents = eventsByDay[currentDay] || [];
                        var hasEvents = dayEvents.length > 0;

                        var classes = 'threecal-day' + weekendClass;
                        if (isToday) classes += ' threecal-is-today';
                        if (hasEvents) classes += ' threecal-has-events';

                        html += '<td class="' + classes + '" data-date="' + dateStr + '">';
                        html += '<div class="threecal-day-header">';
                        html += '<span class="threecal-day-number"' + (isToday ? ' aria-current="date"' : '') + '>' + currentDay + '</span>';
                        html += '</div>';

                        if (hasEvents) {
                            html += '<div class="threecal-day-events">';

                            dayEvents.forEach(function(event, index) {
                                var extra = index >= 3;
                                var startsHere = String(event.start || '').substr(0, 10) === dateStr;
                                html += '<a href="#" role="button" aria-haspopup="dialog" class="threecal-event-dot' + (extra ? ' threecal-event-extra' : '') + (startsHere ? '' : ' threecal-event-continues') + (event.cancelled ? ' threecal-is-cancelled' : '') + '" data-event-id="' + parseInt(event.id, 10) + '" style="' + self.evStyle(event.color) + (extra ? 'display:none;' : '') + '" title="' + self.escapeHtml((event.cancelled ? self.cancelledText() + ': ' : '') + event.title) + '">';
                                if (startsHere && !event.allDay && event.time) {
                                    html += '<span class="threecal-event-time">' + self.escapeHtml(event.time) + '</span>';
                                }
                                html += self.cancelledBadge(event) + '<span class="threecal-event-title">' + self.escapeHtml(event.title) + '</span>';
                                html += '</a>';
                            });

                            if (dayEvents.length > 3) {
                                html += '<a href="#" class="threecal-more-events" data-date="' + dateStr + '" aria-expanded="false">+' + (dayEvents.length - 3) + ' ' + self.escapeHtml(threecal_data.i18n.more) + '</a>';
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

            $calendar.find('.threecal-load-error').remove();

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
                        var formattedDate = self.escapeHtml((threecal_data.i18n.list_date || '{day}. {month}').replace('{day}', event.day).replace('{month}', threecal_data.i18n.months[data.month - 1]));

                        html += '<div class="threecal-list-day" data-date="' + dateStr + '">';
                        html += '<div class="threecal-list-day-header">';
                        html += '<span class="threecal-list-day-name">' + dayName + '</span>';
                        html += '<span class="threecal-list-day-date">' + formattedDate + '</span>';
                        html += '</div>';
                    }

                    // Event item
                    html += '<div class="threecal-list-event' + (event.cancelled ? ' threecal-is-cancelled' : '') + '" data-event-id="' + parseInt(event.id, 10) + '" role="button" tabindex="0" style="' + self.evStyle(event.color) + '">';
                    html += '<div class="threecal-list-event-color"></div>';
                    html += '<div class="threecal-list-event-content">';
                    html += '<div class="threecal-list-event-title">' + self.cancelledBadge(event) + self.escapeHtml(event.title) + '</div>';
                    var metaParts = [];
                    if (event.time) {
                        metaParts.push(self.escapeHtml(event.time));
                    }
                    if (event.location && event.location.name) {
                        metaParts.push(self.escapeHtml(event.location.name));
                    }
                    if (metaParts.length) {
                        html += '<div class="threecal-list-event-time">' + metaParts.join(' · ') + '</div>';
                    }
                    if (event.postponed) {
                        html += '<div class="threecal-postponed">' + self.escapeHtml(event.postponed) + '</div>';
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

            // Click and keyboard handler for list events
            $calendarGrid.find('.threecal-list-event').on('click keydown', function(e) {
                if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
                    return;
                }
                e.preventDefault();
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

            // Show loading, remember the trigger to return focus on close
            $modal.data('trigger', document.activeElement);
            $body.html('<div class="threecal-loading" style="min-height: 200px;"></div>');
            $modal.addClass('active').show();
            $modal.find('.threecal-modal-close').trigger('focus');

            // Fetch event details
            $.ajax({
                url: threecal_data.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'threecal_get_event_details',
                    event_id: eventId
                },
                success: function(response) {
                    if (response && response.success) {
                        self.renderEventModal($body, response.data);
                    } else {
                        var message = (response && response.data && response.data.message) ? response.data.message : threecal_data.i18n.error;
                        $body.empty().append($('<p></p>').text(message));
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
                html += '<img src="' + this.escapeHtml(this.safeUrl(event.featured_image)) + '" alt="' + this.escapeHtml(event.title) + '">';
                html += '</div>';
            }

            // Title (labels the dialog)
            var titleId = $body.closest('.threecal-wrapper').attr('id') + '-dialog-title';
            html += '<h3 class="threecal-modal-event-title' + (event.cancelled ? ' threecal-is-cancelled' : '') + '" id="' + this.escapeHtml(titleId) + '">' + this.cancelledBadge(event) + this.escapeHtml(event.title) + '</h3>';

            // Categories
            if (event.categories && event.categories.length > 0) {
                html += '<div class="threecal-modal-event-categories">';
                var self = this;
                event.categories.forEach(function(cat) {
                    html += '<span class="threecal-modal-category-tag" style="' + self.evStyle(cat.color) + '">' + self.escapeHtml(cat.name) + '</span>';
                });
                html += '</div>';
            }

            // Meta
            html += '<div class="threecal-modal-event-meta">';

            // Date
            html += '<div class="threecal-modal-meta-item">';
            html += this.icon('calendar-event');
            html += '<div>' + this.escapeHtml(event.start_date);
            if (event.end_date && event.end_date !== event.start_date) {
                html += ' – ' + this.escapeHtml(event.end_date);
            }
            if (event.postponed) {
                html += '<br><span class="threecal-postponed">' + this.escapeHtml(event.postponed) + '</span>';
            }
            html += '</div></div>';

            // Time
            if (!event.all_day) {
                html += '<div class="threecal-modal-meta-item">';
                html += this.icon('clock');
                html += '<div>' + this.escapeHtml(event.start_time);
                if (event.end_time) {
                    html += ' – ' + this.escapeHtml(event.end_time);
                }
                html += '</div></div>';
            }

            // Location
            if (event.location) {
                html += '<div class="threecal-modal-meta-item">';
                html += this.icon('map-pin');
                html += '<div><strong>' + this.escapeHtml(event.location.name) + '</strong>';
                if (event.location.address) {
                    html += '<br>' + this.escapeHtml(event.location.address);
                }
                var routeUrl = this.safeUrl(event.location.route_url);
                if (routeUrl) {
                    html += '<br><a href="' + this.escapeHtml(routeUrl) + '" target="_blank" rel="noopener noreferrer">' + this.escapeHtml(threecal_data.i18n.plan_route) + '</a>';
                }
                html += '</div></div>';
            }

            html += '</div>';

            // Description
            if (event.description) {
                html += '<div class="threecal-modal-event-description">' + event.description + '</div>';
            }

            // URL
            var icsUrl = this.safeUrl(event.ics_url);
            if (icsUrl) {
                html += '<div class="threecal-modal-actions">';
                html += '<a href="' + this.escapeHtml(icsUrl) + '" class="threecal-button threecal-modal-event-ics" download>';
                html += this.icon('calendar-plus') + ' ' + this.escapeHtml(threecal_data.i18n.add_to_calendar);
                html += '</a>';
            }

            var url = this.safeUrl(event.url);
            if (url) {
                html += '<a href="' + this.escapeHtml(url) + '" class="threecal-button threecal-button-ghost threecal-modal-event-url" target="_blank" rel="noopener">';
                html += this.icon('external-link') + ' ' + this.escapeHtml(threecal_data.i18n.more_info);
                html += '</a>';
            }
            if (icsUrl) {
                html += '</div>';
            }

            $body.html(html);
        },

        /**
         * Close modal on backdrop click or close button
         */
        initModalClose: function() {
            var close = function($modal) {
                var trigger = $modal.data('trigger');
                $modal.removeClass('active').hide();
                if (trigger && trigger.focus) {
                    trigger.focus();
                }
            };

            $(document).on('click', '.threecal-modal', function(e) {
                if (e.target === this) {
                    close($(this));
                }
            });

            $(document).on('click', '.threecal-modal-close', function() {
                close($(this).closest('.threecal-modal'));
            });

            // ESC closes the dialog, Tab stays inside it
            $(document).on('keydown', function(e) {
                var $open = $('.threecal-modal.active');
                if (!$open.length) {
                    return;
                }
                if (e.key === 'Escape') {
                    close($open);
                    return;
                }
                if (e.key === 'Tab') {
                    var $focusable = $open.find('a[href], button, [tabindex="0"]').filter(':visible');
                    if (!$focusable.length) {
                        return;
                    }
                    var first = $focusable.get(0);
                    var last = $focusable.get($focusable.length - 1);
                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            });
        },

        /**
         * Show all events of a day by expanding the hidden ones
         */
        showDayEvents: function($link) {
            var $events = $link.closest('.threecal-day-events');
            $events.find('.threecal-event-extra').show();
            $link.attr('aria-expanded', 'true').hide();
            $events.find('.threecal-event-extra').first().trigger('focus');
        },

        /**
         * Allow only hex colors in inline styles
         */
        safeColor: function(color) {
            return /^#[0-9a-fA-F]{3,8}$/.test(color || '') ? color : '';
        },

        /**
         * Text for cancelled events
         */
        cancelledText: function() {
            return (threecal_data.i18n && threecal_data.i18n.cancelled) ? threecal_data.i18n.cancelled : 'Cancelled';
        },

        /**
         * Badge in front of the title of a cancelled event
         */
        cancelledBadge: function(event) {
            return event && event.cancelled ? '<span class="threecal-cancelled-badge">' + this.escapeHtml(this.cancelledText()) + '</span> ' : '';
        },

        /**
         * Inline style that sets the event color variable (empty = design accent)
         */
        evStyle: function(color) {
            var safe = this.safeColor(color);
            return safe ? '--tc-ev:' + safe + ';--tc-ev-ink:' + this.ink(safe) + ';' : '';
        },

        /**
         * Readable text color on a colored background, same rule as ThreeCal_Themes::ink()
         */
        ink: function(color) {
            var hex = String(color).replace('#', '');
            if (hex.length === 3) {
                hex = hex.charAt(0) + hex.charAt(0) + hex.charAt(1) + hex.charAt(1) + hex.charAt(2) + hex.charAt(2);
            }
            if (hex.length !== 6) {
                return '#ffffff';
            }
            var weights = [0.2126, 0.7152, 0.0722];
            var luminance = 0;
            for (var i = 0; i < 3; i++) {
                var c = parseInt(hex.substr(i * 2, 2), 16) / 255;
                c = c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
                luminance += weights[i] * c;
            }
            return (1.05 / (luminance + 0.05)) >= 3 ? '#ffffff' : '#0f172a';
        },

        /**
         * Inline SVG icon from the server (fixed markup, not user data)
         */
        icon: function(name) {
            return (threecal_data.icons && threecal_data.icons[name]) ? threecal_data.icons[name] : '';
        },

        /**
         * Allow only http(s) URLs
         */
        safeUrl: function(url) {
            return /^https?:\/\//i.test(url || '') ? url : '';
        },

        /**
         * Escape HTML entities (also quotes, safe for attributes)
         */
        escapeHtml: function(text) {
            if (!text) return '';
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    };

    // Expose globally
    window.ThreeCal = ThreeCal;

    // Auto-init on document ready
    $(document).ready(function() {
        ThreeCal.init();
    });

})(jQuery);
