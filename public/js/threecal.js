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
                year: parseInt($calendar.find('.threecal-calendar').data('year')),
                // Search and day filter (since 1.8.0)
                search: '',
                day: null,
                events: [],
                loaded: false,
                stale: false
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

            // Navigation events (a running search ends when the visitor moves on)
            $calendar.find('.threecal-prev').on('click', function() {
                self.clearSearch($calendar, true);
                self.navigateMonth($calendar, -1);
            });

            $calendar.find('.threecal-next').on('click', function() {
                self.clearSearch($calendar, true);
                self.navigateMonth($calendar, 1);
            });

            $calendar.find('.threecal-today-btn').on('click', function() {
                self.clearSearch($calendar, true);
                self.goToToday($calendar);
            });

            // Search (since 1.8.0): Enter or the button searches, Escape clears
            $calendar.find('.threecal-search').on('submit', function(e) {
                e.preventDefault();
                self.runSearch($calendar);
            });

            $calendar.find('.threecal-search-input').on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    self.runSearch($calendar);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    $(this).val('');
                    self.clearSearch($calendar, false);
                }
            }).on('input search', function() {
                // The native clear button of the field (or deleting the text) ends the search
                if ($(this).val() === '' && $calendar.data('threecal').search !== '') {
                    self.clearSearch($calendar, false);
                }
            });

            $calendar.find('.threecal-results-reset').on('click', function() {
                if ($calendar.data('threecal').search !== '') {
                    $calendar.find('.threecal-search-input').val('');
                    self.clearSearch($calendar, false);
                    $calendar.find('.threecal-search-input').trigger('focus');
                } else {
                    self.closeResults($calendar);
                }
            });

            // Day filter (since 1.8.0): a day number or an empty part of a day cell
            $calendar.on('click', '.threecal-day-number', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.showDay($calendar, $(this).data('date'));
            });

            $calendar.on('click', 'td.threecal-day:not(.threecal-day-empty)', function(e) {
                if ($(e.target).closest('a, button').length) {
                    return;
                }
                self.showDay($calendar, $(this).data('date'));
            });

            // Print the month that is on screen (since 1.7.0)
            $calendar.find('.threecal-print-btn').on('click', function() {
                $('.threecal-wrapper').removeClass('threecal-printing');
                $calendar.addClass('threecal-printing');
                window.print();
            });

            // Category filter: combines with a running search
            $calendar.find('.threecal-category-filter').on('change', function() {
                var data = $calendar.data('threecal');
                data.category = parseInt($(this).val()) || 0;
                $calendar.data('threecal', data);
                if (data.search !== '') {
                    data.stale = true;
                    self.runSearch($calendar);
                } else {
                    self.loadMonth($calendar);
                }
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
                self.clearSearch($calendar, true);
                self.loadMonth($calendar);
            });

            // Event click handlers
            $calendar.on('click', '.threecal-event-dot, .threecal-tba-event', function(e) {
                e.preventDefault();
                var eventId = $(this).data('event-id');
                self.showEventModal($calendar, eventId);
            });

            // More events click: the list of that day under the grid (since 1.8.0)
            $calendar.on('click', '.threecal-more-events', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.showDay($calendar, $(this).data('date'));
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

            // Add loading state, close the day list of the month that goes away
            self.closeResults($calendar);
            data.stale = false;
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
                        data.events = response.data || [];
                        data.loaded = true;

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

            // Group events by every day they cover (multi-day events).
            // Events of which only the month is known go to a bar above the grid.
            var eventsByDay = {};
            var monthOnly = [];
            events.forEach(function(event) {
                if (event.month_only) {
                    monthOnly.push(event);
                    return;
                }
                var days = (event.days && event.days.length) ? event.days : [event.day];
                days.forEach(function(day) {
                    if (!eventsByDay[day]) {
                        eventsByDay[day] = [];
                    }
                    eventsByDay[day].push(event);
                });
            });

            // Build HTML
            var html = self.monthBar(monthOnly);
            html += '<table class="threecal-month-grid"><thead><tr>';

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
                        html += '<button type="button" class="threecal-day-number" data-date="' + dateStr + '"' + (isToday ? ' aria-current="date"' : '') + ' aria-label="' + self.escapeHtml(self.fill(threecal_data.i18n.events_on || 'Events on {date}', { date: self.formatDateYear(dateStr) })) + '">' + currentDay + '</button>';
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
                                html += '<a href="#" class="threecal-more-events" data-date="' + dateStr + '">+' + (dayEvents.length - 3) + ' ' + self.escapeHtml(threecal_data.i18n.more) + '</a>';
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
         * Bar above the month grid with the events of which only the month is known
         */
        monthBar: function(events) {
            var self = this;
            if (!events || !events.length) {
                return '';
            }
            var html = '<div class="threecal-month-tba">';
            html += '<span class="threecal-month-tba-label">' + self.icon('calendar-question') + self.escapeHtml(threecal_data.i18n.month_tba || '') + '</span>';
            html += '<span class="threecal-month-tba-events">';
            events.forEach(function(event) {
                html += '<a href="#" role="button" aria-haspopup="dialog" class="threecal-tba-event' + (event.cancelled ? ' threecal-is-cancelled' : '') + '" data-event-id="' + parseInt(event.id, 10) + '" style="' + self.evStyle(event.color) + '" title="' + self.escapeHtml((event.cancelled ? self.cancelledText() + ': ' : '') + event.title) + '">';
                html += self.cancelledBadge(event) + '<span class="threecal-event-title">' + self.escapeHtml(event.title) + '</span>';
                html += '</a>';
            });
            html += '</span></div>';
            return html;
        },

        /**
         * One event row of the list view
         */
        listEvent: function(event) {
            var self = this;
            var highlight = event.highlight && event.highlight.name ? event.highlight : null;
            var html = '<div class="threecal-list-event' + (event.cancelled ? ' threecal-is-cancelled' : '') + (highlight ? ' threecal-is-highlight' : '') + '" data-event-id="' + parseInt(event.id, 10) + '" role="button" tabindex="0" style="' + self.evStyle(event.color) + '">';
            html += '<div class="threecal-list-event-color"></div>';
            html += '<div class="threecal-list-event-content">';
            if (highlight) {
                // Badge of a highlighted category (since 1.7.0), colors like the PHP badge.
                html += '<span class="threecal-highlight-badge" style="' + self.evStyle(highlight.color) + '">' + self.icon('star') + self.escapeHtml(highlight.name) + '</span>';
            }
            html += '<div class="threecal-list-event-title">' + self.cancelledBadge(event) + self.escapeHtml(event.title) + '</div>';
            var metaParts = [];
            if (event.month_only && event.tba) {
                metaParts.push(self.escapeHtml(event.tba));
            } else if (event.time) {
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
            return html;
        },

        /**
         * Render list view
         */
        renderList: function($calendar, events) {
            var self = this;
            var data = $calendar.data('threecal');
            var $calendarGrid = $calendar.find('.threecal-calendar');

            $calendar.find('.threecal-load-error').remove();

            // Events of which only the month is known come first, as a group of their own
            var monthOnly = events.filter(function(event) {
                return event.month_only;
            });
            var dayEvents = events.filter(function(event) {
                return !event.month_only;
            });

            // Sort events by date
            dayEvents.sort(function(a, b) {
                return a.day - b.day;
            });

            // Build HTML
            var html = '<div class="threecal-list-view">';

            if (events.length === 0) {
                html += '<div class="threecal-no-events">' + threecal_data.i18n.no_events + '</div>';
            } else {
                var currentDay = null;

                if (monthOnly.length) {
                    html += '<div class="threecal-list-day threecal-list-tba">';
                    html += '<div class="threecal-list-day-header">';
                    html += '<span class="threecal-list-day-name">' + self.escapeHtml(threecal_data.i18n.months[data.month - 1] + ' ' + parseInt(data.year, 10)) + '</span>';
                    html += '<span class="threecal-list-day-date">' + self.escapeHtml(threecal_data.i18n.month_tba || '') + '</span>';
                    html += '</div>';
                    monthOnly.forEach(function(event) {
                        html += self.listEvent(event);
                    });
                    html += '</div>';
                }

                dayEvents.forEach(function(event) {
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
                    html += self.listEvent(event);
                });

                if (currentDay !== null) {
                    html += '</div>'; // Close last day group
                }
            }

            html += '</div>';

            // Update DOM
            $calendarGrid.data('month', data.month).data('year', data.year);
            $calendarGrid.html(html);
            self.bindListEvents($calendar, $calendarGrid);
        },

        /**
         * Click and keyboard handler for the rows of a list
         */
        bindListEvents: function($calendar, $container) {
            var self = this;
            $container.find('.threecal-list-event').on('click keydown', function(e) {
                if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
                    return;
                }
                e.preventDefault();
                var eventId = $(this).data('event-id');
                self.showEventModal($calendar, eventId);
            });
        },

        /**
         * Events grouped by day, in the markup of the list view. Used by the day filter
         * under the month grid and by the search results (since 1.8.0).
         */
        renderDayGroups: function(events, emptyMessage, forceDate) {
            var self = this;
            var html = '<div class="threecal-list-view">';
            if (!events || !events.length) {
                html += '<div class="threecal-no-events">' + self.escapeHtml(emptyMessage || threecal_data.i18n.no_events) + '</div></div>';
                return html;
            }
            var currentKey = null;
            events.forEach(function(event) {
                var dateStr = String(event.date || event.start || '').substr(0, 10);
                if (forceDate && !event.month_only && dateStr !== forceDate) {
                    // Day filter: a multi-day event is listed under the chosen day, without its start time
                    event = $.extend({}, event, { time: '' });
                    dateStr = forceDate;
                }
                var key = event.month_only ? 'm' + dateStr.substr(0, 7) : 'd' + dateStr;
                if (key !== currentKey) {
                    if (currentKey !== null) {
                        html += '</div>';
                    }
                    currentKey = key;
                    html += '<div class="threecal-list-day' + (event.month_only ? ' threecal-list-tba' : '') + '" data-date="' + self.escapeHtml(dateStr) + '">';
                    html += '<div class="threecal-list-day-header">';
                    if (event.month_only) {
                        html += '<span class="threecal-list-day-name">' + self.escapeHtml(self.formatMonthYear(dateStr)) + '</span>';
                        html += '<span class="threecal-list-day-date">' + self.escapeHtml(threecal_data.i18n.month_tba || '') + '</span>';
                    } else {
                        html += '<span class="threecal-list-day-name">' + self.escapeHtml(self.weekdayName(dateStr)) + '</span>';
                        html += '<span class="threecal-list-day-date">' + self.escapeHtml(self.formatDateYear(dateStr)) + '</span>';
                    }
                    html += '</div>';
                }
                html += self.listEvent(event);
            });
            html += '</div></div>';
            return html;
        },

        /**
         * Search: ask the server for matching running and upcoming events of every month
         */
        runSearch: function($calendar) {
            var self = this;
            var data = $calendar.data('threecal');
            var $input = $calendar.find('.threecal-search-input');
            var term = $.trim(String($input.val() || '')).substr(0, 80);

            if (term === '') {
                self.clearSearch($calendar, false);
                return;
            }

            var $panel = $calendar.find('.threecal-results');
            $panel.attr('aria-busy', 'true');

            $.ajax({
                url: threecal_data.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'threecal_get_events',
                    search: term,
                    category: data.category,
                    location: data.location
                },
                success: function(response) {
                    if (response && response.success) {
                        var events = response.data || [];
                        data.search = term;
                        data.day = null;
                        $calendar.find('.threecal-is-selected').removeClass('threecal-is-selected');
                        $calendar.addClass('threecal-searching');
                        var title;
                        if (!events.length) {
                            title = self.fill(threecal_data.i18n.no_events_for || 'No events for "{term}"', { term: term });
                        } else if (events.length === 1) {
                            title = self.fill(threecal_data.i18n.one_event_for || 'One event for "{term}"', { term: term });
                        } else {
                            title = self.fill(threecal_data.i18n.events_for || '{count} events for "{term}"', { count: events.length, term: term });
                        }
                        self.showResults($calendar, title, events.length ? events : null, threecal_data.i18n.reset_search, '', false);
                        self.announce($calendar, title);
                    } else {
                        self.showLoadError($calendar);
                    }
                },
                error: function() {
                    self.showLoadError($calendar);
                },
                complete: function() {
                    $panel.removeAttr('aria-busy');
                }
            });
        },

        /**
         * End the search and show the month again (reloaded when the category changed meanwhile)
         */
        clearSearch: function($calendar, silent) {
            var data = $calendar.data('threecal');
            var wasSearching = data.search !== '';
            data.search = '';
            $calendar.removeClass('threecal-searching');
            if (!wasSearching) {
                return;
            }
            this.closeResults($calendar);
            if (data.stale) {
                this.loadMonth($calendar);
            }
            if (!silent) {
                this.announce($calendar, threecal_data.i18n.all_events || '');
            }
        },

        /**
         * Day filter: the events of one day of the loaded month, listed under the grid
         */
        showDay: function($calendar, dateStr) {
            var self = this;
            var data = $calendar.data('threecal');
            dateStr = String(dateStr || '');
            if (!/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
                return;
            }
            // The first month comes from the server without event data: fetch it once
            if (!data.loaded) {
                self.fetchMonthData($calendar, function() {
                    self.showDay($calendar, dateStr);
                });
                return;
            }
            var dayNumber = parseInt(dateStr.substr(8, 2), 10);
            var events = (data.events || []).filter(function(event) {
                if (event.month_only) {
                    return false;
                }
                var days = (event.days && event.days.length) ? event.days : [event.day];
                return days.indexOf(dayNumber) !== -1;
            });

            data.day = dateStr;
            $calendar.find('.threecal-is-selected').removeClass('threecal-is-selected');
            $calendar.find('td.threecal-day[data-date="' + dateStr + '"]').addClass('threecal-is-selected');

            var dateText = self.formatDateYear(dateStr);
            var title = self.fill(threecal_data.i18n.events_on || 'Events on {date}', { date: dateText });
            var empty = self.fill(threecal_data.i18n.no_events_on || 'No events on {date}', { date: dateText });
            self.showResults($calendar, title, events, threecal_data.i18n.all_events, empty, true, dateStr);
            self.announce($calendar, events.length ? title : empty);
        },

        /**
         * Event data of the month on screen, without touching the grid (for the day filter
         * on the month the server rendered)
         */
        fetchMonthData: function($calendar, callback) {
            var self = this;
            var data = $calendar.data('threecal');
            $.ajax({
                url: threecal_data.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'threecal_get_events',
                    month: data.month,
                    year: data.year,
                    category: data.category,
                    location: data.location
                },
                success: function(response) {
                    if (response && response.success) {
                        data.events = response.data || [];
                        data.loaded = true;
                        callback();
                    } else {
                        self.showLoadError($calendar);
                    }
                },
                error: function() {
                    self.showLoadError($calendar);
                }
            });
        },

        /**
         * Fill the results panel under the grid and move the focus to its heading
         */
        showResults: function($calendar, title, events, resetLabel, emptyMessage, focusTitle, forceDate) {
            var $panel = $calendar.find('.threecal-results');
            var $body = $panel.find('.threecal-results-body');
            $calendar.find('.threecal-load-error').remove();
            $panel.find('.threecal-results-title').text(title);
            $panel.find('.threecal-results-reset span').text(resetLabel || '');
            $body.html(events === null ? '' : this.renderDayGroups(events, emptyMessage, forceDate || null));
            this.bindListEvents($calendar, $body);
            $panel.removeAttr('hidden');
            if (focusTitle) {
                $panel.find('.threecal-results-title').trigger('focus');
            }
        },

        /**
         * Hide the results panel and the day marker
         */
        closeResults: function($calendar) {
            var data = $calendar.data('threecal');
            data.day = null;
            $calendar.find('.threecal-is-selected').removeClass('threecal-is-selected');
            $calendar.find('.threecal-results').attr('hidden', 'hidden').find('.threecal-results-body').empty();
        },

        /**
         * Text for screen readers (role=status). Cleared first so the same text is read again.
         */
        announce: function($calendar, text) {
            var $live = $calendar.find('.threecal-announce');
            $live.text('');
            window.setTimeout(function() {
                $live.text(text || '');
            }, 60);
        },

        /**
         * Replace {name} placeholders of a translated pattern
         */
        fill: function(pattern, values) {
            return String(pattern || '').replace(/\{(\w+)\}/g, function(match, key) {
                return Object.prototype.hasOwnProperty.call(values, key) ? String(values[key]) : match;
            });
        },

        /**
         * Parts of a Y-m-d string as numbers
         */
        dateParts: function(dateStr) {
            var parts = String(dateStr || '').split('-');
            return { year: parseInt(parts[0], 10), month: parseInt(parts[1], 10), day: parseInt(parts[2], 10) };
        },

        /**
         * "October 24, 2026" in the pattern of the site language
         */
        formatDateYear: function(dateStr) {
            var p = this.dateParts(dateStr);
            if (!p.year || !p.month || !p.day) {
                return String(dateStr || '');
            }
            return this.fill(threecal_data.i18n.list_date_year || '{month} {day}, {year}', { day: p.day, month: threecal_data.i18n.months[p.month - 1] || '', year: p.year });
        },

        /**
         * "October 2026"
         */
        formatMonthYear: function(dateStr) {
            var p = this.dateParts(dateStr);
            return (threecal_data.i18n.months[p.month - 1] || '') + ' ' + (p.year || '');
        },

        /**
         * Full weekday name of a Y-m-d string
         */
        weekdayName: function(dateStr) {
            var p = this.dateParts(dateStr);
            var d = new Date(p.year, p.month - 1, p.day);
            var names = threecal_data.i18n.weekdays_full || threecal_data.i18n.weekdays;
            return names[d.getDay()] || '';
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
                html += ' ' + this.escapeHtml(threecal_data.i18n.range_to) + ' ' + this.escapeHtml(event.end_date);
            }
            if (event.month_only && event.tba) {
                html += ' <span class="threecal-tba">' + this.escapeHtml(event.tba) + '</span>';
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
                    html += ' ' + this.escapeHtml(threecal_data.i18n.range_to) + ' ' + this.escapeHtml(event.end_time);
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

            // URL (events of which only the month is known have no calendar file)
            var icsUrl = this.safeUrl(event.ics_url);
            var url = this.safeUrl(event.url);
            if (icsUrl || url) {
                html += '<div class="threecal-modal-actions">';
            }
            if (icsUrl) {
                html += '<a href="' + this.escapeHtml(icsUrl) + '" class="threecal-button threecal-modal-event-ics" download>';
                html += this.icon('calendar-plus') + ' ' + this.escapeHtml(threecal_data.i18n.add_to_calendar);
                html += '</a>';
            }

            if (url) {
                var rel = (typeof event.url_rel === 'string' && /^[a-z ]+$/.test(event.url_rel)) ? event.url_rel : 'noopener';
                html += '<a href="' + this.escapeHtml(url) + '" class="threecal-button threecal-button-ghost threecal-modal-event-url" target="_blank" rel="' + rel + '">';
                html += this.icon('external-link') + ' ' + this.escapeHtml(threecal_data.i18n.more_info);
                html += '</a>';
            }
            if (icsUrl || url) {
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
