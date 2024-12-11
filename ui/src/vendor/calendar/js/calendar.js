/*
 * Copyright (C) 2023 Xibo Signage Ltd
 *
 * Xibo - Digital Signage - https://xibosignage.com
 *
 * This file is part of Xibo.
 *
 * Xibo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * Xibo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Xibo.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Bootstrap based calendar full view.
 *
 * https://github.com/Serhioromano/bootstrap-calendar
 *
 * User: Sergey Romanov <serg4172@mail.ru>
 */
"use strict";

Date.prototype.getWeek = function(iso8601) {
	if (iso8601) {
		var target = new Date(this.valueOf());
		var dayNr  = (this.getDay() + 6) % 7;
		target.setDate(target.getDate() - dayNr + 3);
		var firstThursday = target.valueOf();
		target.setMonth(0, 1);
		if (target.getDay() != 4) {
			target.setMonth(0, 1 + ((4 - target.getDay()) + 7) % 7);
		}
		return 1 + Math.ceil((firstThursday - target) / 604800000); // 604800000 = 7 * 24 * 3600 * 1000
	} else {
		var onejan = new Date(this.getFullYear(), 0, 1);
		return Math.ceil((((this.getTime() - onejan.getTime()) / 86400000) + onejan.getDay() + 1) / 7);
	}
};
Date.prototype.getMonthFormatted = function() {
	var month = this.getMonth() + 1;
	return month < 10 ? '0' + month : month;
};
Date.prototype.getDateFormatted = function() {
	var date = this.getDate();
	return date < 10 ? '0' + date : date;
};

if(!String.prototype.format) {
	String.prototype.format = function() {
		var args = arguments;
		return this.replace(/{(\d+)}/g, function(match, number) {
			return typeof args[number] != 'undefined' ? args[number] : match;
		});
	};
}
if(!String.prototype.formatNum) {
	String.prototype.formatNum = function(decimal) {
		var r = "" + this;
		while(r.length < decimal)
			r = "0" + r;
		return r;
	};
}

(function($) {

	var defaults = {
        // Container to append the tooltip
        tooltip_container : 'body',
		// Width of the calendar
		width: '100%',
		// Initial view (can be 'month', 'week', 'day')
		view: 'month',
		// Initial date. No matter month, week or day this will be a starting point. Can be 'now' or a date in format 'yyyy-mm-dd'
		day: 'now',
		// Day Start time and end time with time intervals. Time split 10, 15 or 30.
		time_start: '06:00',
		time_end: '22:00',
		time_split: '30',
		// Source of events data. It can be one of the following:
		// - URL to return JSON list of events in special format.
		//   {success:1, result: [....]} or for error {success:0, error:'Something terrible happened'}
		//   events: [...] as described in events property description
		//   The start and end variables will be sent to this url
		// - A function that received the start and end date, and that
		//   returns an array of events (as described in events property description)
		// - An array containing the events
		events_source: '',
		// Static cache of events. If set to true, events will only be loaded once.
		// Useful if response is not constrained by date.
		events_cache: false,
		// Set format12 to true if you want to use 12 Hour format instead of 24 Hour
		format12: false,
		am_suffix: "AM",
		pm_suffix: "PM",
		// Path to templates should end with slash /. It can be as relative
		// /component/bootstrap-calendar/tmpls/
		// or absolute
		// http://localhost/component/bootstrap-calendar/tmpls/
		tmpl_path: 'tmpls/',
		tmpl_cache: true,
		classes: {
			months: {
				inmonth: 'cal-day-inmonth',
				outmonth: 'cal-day-outmonth',
				saturday: 'cal-day-weekend',
				sunday: 'cal-day-weekend',
				holidays: 'cal-day-holiday',
				today: 'cal-day-today'
			},
			week: {
				workday: 'cal-day-workday',
				saturday: 'cal-day-weekend',
				sunday: 'cal-day-weekend',
				holidays: 'cal-day-holiday',
				today: 'cal-day-today'
			}
		},
		// ID of the element of modal window. If set, events URLs will be opened in modal windows.
		modal: null,
		//	modal handling setting, one of "iframe", "ajax" or "template"
		modal_type: "iframe",
		//	function to set modal title, will be passed the event as a parameter
		modal_title: null,
		views: {
			year: {
				slide_events: 1,
				enable: 1
			},
			month: {
				slide_events: 1,
				enable: 1
			},
			week: {
				enable: 1
			},
			day: {
				enable: 1
			},
			agenda: {
				enable: 1
			}
		},
		merge_holidays: false,
		display_week_numbers: true,
		weekbox: true,
		// ------------------------------------------------------------
		// CALLBACKS. Events triggered by calendar class. You can use
		// those to affect you UI
		// ------------------------------------------------------------
		onAfterEventsLoad: function(events) {
			// Inside this function 'this' is the calendar instance
		},
		onBeforeEventsLoad: function(next) {
			// Inside this function 'this' is the calendar instance
			next();
		},
		onAfterViewLoad: function(view) {
			// Inside this function 'this' is the calendar instance
		},
		onAfterModalShown: function(events) {
			// Inside this function 'this' is the calendar instance
		},
		onAfterModalHidden: function(events) {
			// Inside this function 'this' is the calendar instance
		},
		// -------------------------------------------------------------
		// INTERNAL USE ONLY. DO NOT ASSIGN IT WILL BE OVERRIDDEN ANYWAY
		// -------------------------------------------------------------
		events: [],
		templates: {
			year: '',
			month: '',
			week: '',
			day: '',
			agenda: ''
		},
		stop_cycling: false
	};

	var defaults_extended = {
		first_day: 2,
		week_numbers_iso_8601: false,
		holidays: {
			// January 1
			'01-01': "New Year's Day",
			// Third (+3*) Monday (1) in January (01)
			'01+3*1': "Birthday of Dr. Martin Luther King, Jr.",
			// Third (+3*) Monday (1) in February (02)
			'02+3*1': "Washington's Birthday",
			// Last (-1*) Monday (1) in May (05)
			'05-1*1': "Memorial Day",
			// July 4
			'04-07': "Independence Day",
			// First (+1*) Monday (1) in September (09)
			'09+1*1': "Labor Day",
			// Second (+2*) Monday (1) in October (10)
			'10+2*1': "Columbus Day",
			// November 11
			'11-11': "Veterans Day",
			// Fourth (+4*) Thursday (4) in November (11)
			'11+4*4': "Thanksgiving Day",
			// December 25
			'25-12': "Christmas"
		}
	};

	var strings = {
		error_noview: 'Calendar: View {0} not found',
		error_dateformat: 'Calendar: Wrong date format {0}. Should be either "now" or "yyyy-mm-dd"',
		error_loadurl: 'Calendar: Event URL is not set',
		error_where: 'Calendar: Wrong navigation direction {0}. Can be only "next" or "prev" or "today"',
		error_timedevide: 'Calendar: Time split parameter should divide 60 without decimals. Something like 10, 15, 30',

		no_events_in_day: 'No events in this day.',

		title_year: '{0}',
		title_month: '{0} {1}',
		title_week: 'week {0} of {1}',
		title_day: '{0} {1} {2}, {3}',

		week: 'Week {0}',
		all_day: 'All day',
		time: 'Time',
		events: 'Events',
		before_time: 'Ends before timeline',
		after_time: 'Starts after timeline',

		m0: 'January',
		m1: 'February',
		m2: 'March',
		m3: 'April',
		m4: 'May',
		m5: 'June',
		m6: 'July',
		m7: 'August',
		m8: 'September',
		m9: 'October',
		m10: 'November',
		m11: 'December',

		ms0: 'Jan',
		ms1: 'Feb',
		ms2: 'Mar',
		ms3: 'Apr',
		ms4: 'May',
		ms5: 'Jun',
		ms6: 'Jul',
		ms7: 'Aug',
		ms8: 'Sep',
		ms9: 'Oct',
		ms10: 'Nov',
		ms11: 'Dec',

		d0: 'Sunday',
		d1: 'Monday',
		d2: 'Tuesday',
		d3: 'Wednesday',
		d4: 'Thursday',
		d5: 'Friday',
		d6: 'Saturday'
	};

	var browser_timezone = '';
	try {
		if($.type(window.jstz) == 'object' && $.type(jstz.determine) == 'function') {
			browser_timezone = jstz.determine().name();
			if($.type(browser_timezone) !== 'string') {
				browser_timezone = '';
			}
		}
	}
	catch(e) {
	}

	function buildEventsUrl(events_url, data) {
		var separator, key, url;
		url = events_url;
		separator = (events_url.indexOf('?') < 0) ? '?' : '&';
		for(key in data) {
			url += separator + key + '=' + encodeURIComponent(data[key]);
			separator = '&';
		}
		return url;
	}

	function getExtentedOption(cal, option_name) {
		var fromOptions = (cal.options[option_name] != null) ? cal.options[option_name] : null;
		var fromLanguage = (cal.locale[option_name] != null) ? cal.locale[option_name] : null;
		if((option_name == 'holidays') && cal.options.merge_holidays) {
			var holidays = {};
			$.extend(true, holidays, fromLanguage ? fromLanguage : defaults_extended.holidays);
			if(fromOptions) {
				$.extend(true, holidays, fromOptions);
			}
			return holidays;
		}
		else {
			if(fromOptions != null) {
				return fromOptions;
			}
			if(fromLanguage != null) {
				return fromLanguage;
			}
			return defaults_extended[option_name];
		}
	}

	function getHolidays(cal, year) {
		var hash = [];
		var holidays_def = getExtentedOption(cal, 'holidays');
		for(var k in holidays_def) {
			hash.push(k + ':' + holidays_def[k]);
		}
		hash.push(year);
		hash = hash.join('|');
		if(hash in getHolidays.cache) {
			return getHolidays.cache[hash];
		}
		var holidays = [];
		$.each(holidays_def, function(key, name) {
			var firstDay = null, lastDay = null, failed = false;
			$.each(key.split('>'), function(i, chunk) {
				var m, date = null;
				if(m = /^(\d\d)-(\d\d)$/.exec(chunk)) {
					date = new Date(year, parseInt(m[2], 10) - 1, parseInt(m[1], 10));
				}
				else if(m = /^(\d\d)-(\d\d)-(\d\d\d\d)$/.exec(chunk)) {
					if(parseInt(m[3], 10) == year) {
						date = new Date(year, parseInt(m[2], 10) - 1, parseInt(m[1], 10));
					}
				}
				else if(m = /^easter(([+\-])(\d+))?$/.exec(chunk)) {
					date = getEasterDate(year, m[1] ? parseInt(m[1], 10) : 0);
				}
				else if(m = /^(\d\d)([+\-])([1-5])\*([0-6])$/.exec(chunk)) {
					var month = parseInt(m[1], 10) - 1;
					var direction = m[2];
					var offset = parseInt(m[3]);
					var weekday = parseInt(m[4]);
					switch(direction) {
						case '+':
							var d = new Date(year, month, 1 - 7);
							while(d.getDay() != weekday) {
								d = new Date(d.getFullYear(), d.getMonth(), d.getDate() + 1);
							}
							date = new Date(d.getFullYear(), d.getMonth(), d.getDate() + 7 * offset);
							break;
						case '-':
							var d = new Date(year, month + 1, 0 + 7);
							while(d.getDay() != weekday) {
								d = new Date(d.getFullYear(), d.getMonth(), d.getDate() - 1);
							}
							date = new Date(d.getFullYear(), d.getMonth(), d.getDate() - 7 * offset);
							break;
					}
				}
				if(!date) {
					warn('Unknown holiday: ' + key);
					failed = true;
					return false;
				}
				switch(i) {
					case 0:
						firstDay = date;
						break;
					case 1:
						if(date.getTime() <= firstDay.getTime()) {
							warn('Unknown holiday: ' + key);
							failed = true;
							return false;
						}
						lastDay = date;
						break;
					default:
						warn('Unknown holiday: ' + key);
						failed = true;
						return false;
				}
			});
			if(!failed) {
				var days = [];
				if(lastDay) {
					for(var date = new Date(firstDay.getTime()); date.getTime() <= lastDay.getTime(); date.setDate(date.getDate() + 1)) {
						days.push(new Date(date.getTime()));
					}
				}
				else {
					days.push(firstDay);
				}
				holidays.push({name: name, days: days});
			}
		});
		getHolidays.cache[hash] = holidays;
		return getHolidays.cache[hash];
	}

	getHolidays.cache = {};

	function warn(message) {
		if($.type(window.console) == 'object' && $.type(window.console.warn) == 'function') {
			window.console.warn('[Bootstrap-Calendar] ' + message);
		}
	}

	function Calendar(params, context) {
		this.options = $.extend(true, {position: {start: new Date(), end: new Date()}}, defaults, params);
		this.setLanguage(this.options.language);
		this.context = context;

		context.css('width', this.options.width).addClass('cal-context');

		this.view();
		return this;
	}

	Calendar.prototype.setOptions = function(object) {
		$.extend(this.options, object);
		if('language' in object) {
			this.setLanguage(object.language);
		}
		if('modal' in object) {
			this._update_modal();
		}
	}

	Calendar.prototype.setLanguage = function(lang) {
		if(window.calendar_languages && (lang in window.calendar_languages)) {
			this.locale = $.extend(true, {}, strings, calendar_languages[lang]);
			this.options.language = lang;
		} else {
			this.locale = strings;
			delete this.options.language;
		}
	}

	Calendar.prototype._render = function() {
		
		this.context.html('');
		this._loadTemplate(this.options.view);
		this.stop_cycling = false;
		
		var data = {};

		// Render the non agenda views ( Day, Week, Month,Year)
		if (this.options.view != 'agenda') {
			
			data.cal = this;
			data.day = 1;

			// Getting list of days in a week in correct order. Works for month and week views
			if(getExtentedOption(this, 'first_day') == 1) {
				data.days_name = [this.locale.d1, this.locale.d2, this.locale.d3, this.locale.d4, this.locale.d5, this.locale.d6, this.locale.d0]
			} else {
				data.days_name = [this.locale.d0, this.locale.d1, this.locale.d2, this.locale.d3, this.locale.d4, this.locale.d5, this.locale.d6]
			}

			// Get all events between start and end
			var start = parseInt(this.options.position.start.getTime());
			var end = parseInt(this.options.position.end.getTime());

			data.events = this.getEventsBetween(start, end);

			switch(this.options.view) {
				case 'month':
					break;
				case 'week':
					this._calculate_hour_minutes(data);
					break;
				case 'day':
					this._calculate_hour_minutes(data);
					break;
			}

			data.start = new Date(this.options.position.start.getTime());
			data.lang = this.locale;
		} else { // Render the event view 
			data.cal = this;
			data.agenda = this.options.events;
			data.lang = this.locale;
		}
	
		this.context.append(this.options.templates[this.options.view](data));
		
		this._update();
	};

	Calendar.prototype._format_hour = function(str_hour) {
		var hour_split = str_hour.split(":");
		var hour = parseInt(hour_split[0]);
		var minutes = parseInt(hour_split[1]);

		var suffix = '';

		if(this.options.format12) {
			if(hour < 12) {
				suffix = this.options.am_suffix;
			}
			else {
				suffix = this.options.pm_suffix;
			}

			hour = hour % 12;
			if(hour == 0) {
				hour = 12;
			}
		}

		return hour.toString().formatNum(2) + ':' + minutes.toString().formatNum(2) + suffix;
	};

	Calendar.prototype._format_time = function(datetime) {
		return this._format_hour(datetime.getHours() + ':' + datetime.getMinutes());
	};

	Calendar.prototype._calculate_hour_minutes = function(data) {
		var $self = this;
		var time_split = parseInt(this.options.time_split);
		var time_split_count = 60 / time_split;
		var time_split_hour = Math.min(time_split_count, 1);

		if(((time_split_count >= 1) && (time_split_count % 1 != 0)) || ((time_split_count < 1) && (1440 / time_split % 1 != 0))) {
			$.error(this.locale.error_timedevide);
		}

		var time_start = this.options.time_start.split(":");
		var time_end = this.options.time_end.split(":");

        if (time_end[0] === "00" && time_end[1] === "00") {
            data.hours = 24 * time_split_hour;
        } else {
            data.hours = (parseInt(time_end[0]) - parseInt(time_start[0])) * time_split_hour;
        }
		var lines = data.hours * time_split_count - parseInt(time_start[1]) / time_split;
		var ms_per_line = (60000 * time_split);

		var start = new Date(this.options.position.start.getTime());
		start.setHours(time_start[0]);
		start.setMinutes(time_start[1]);

		var end = new Date(this.options.position.end.getTime()-(86400000));
        if (time_end[0] === "00" && time_end[1] === "00") {
            end.setHours(time_end[0]);
            end.setMinutes(time_end[1]);
            end.setTime(end.getTime()+86400000);
        }
		else {
            end.setHours(time_end[0]);
            end.setMinutes(time_end[1]);
        }

		data.all_day = [];
		data.by_hour = [];
		data.after_time = [];
		data.before_time = [];
		$.each(data.events, function(k, e) {
			var s = new Date(parseInt(e.start));
			var f = new Date(parseInt(e.end));

			e.start_hour = moment(s).format(jsTimeFormat);
			e.end_hour = moment(f).format(jsTimeFormat);

			if(e.start < start.getTime()) {
				warn(1);
				e.start_hour = s.getDate() + ' ' + $self.locale['ms' + s.getMonth()] + ' ' + e.start_hour;
			}

			if(e.end > end.getTime()) {
				warn(1);
				e.end_hour = f.getDate() + ' ' + $self.locale['ms' + f.getMonth()] + ' ' + e.end_hour;
			}

			if(e.start < start.getTime() && e.end > end.getTime()) {
				data.all_day.push(e);
				return;
			}

			if(e.end < start.getTime()) {
				data.before_time.push(e);
				return;
			}

			if(e.start > end.getTime()) {
				data.after_time.push(e);
				return;
			}

			var event_start = start.getTime() - e.start;

			if(event_start >= 0) {
				e.top = 0;
			} else {
				e.top = Math.abs(event_start) / ms_per_line;
			}

			var lines_left = Math.abs(lines - e.top);
			var lines_in_event = (e.end - e.start) / ms_per_line;
			if(event_start >= 0) {
				lines_in_event = (e.end - start.getTime()) / ms_per_line;
			}

			e.lines = lines_in_event;
			if(lines_in_event > lines_left) {
				e.lines = lines_left;
			}

			data.by_hour.push(e);
		});

		//var d = new Date('2013-03-14 13:20:00');
		//warn(d.getTime());
	};

	Calendar.prototype._hour_min = function(hour) {
		var time_start = this.options.time_start.split(":");
		var time_split = parseInt(this.options.time_split);
		var in_hour = 60 / time_split;
		return (hour == 0) ? (in_hour - (parseInt(time_start[1]) / time_split)) : in_hour;
	};

	Calendar.prototype._hour = function(hour, part) {
		var time_start = this.options.time_start.split(":");
		var time_split = parseInt(this.options.time_split);
		var h = "" + (parseInt(time_start[0]) + hour * Math.max(time_split / 60, 1));
		var m = "" + time_split * part;
		
		var time = this._format_hour(h.formatNum(2) + ":" + m.formatNum(2));

		// Return formatted time
		return moment(time, "HH:mm").format(jsTimeFormat);
	};

	Calendar.prototype._week = function(event) {
		this._loadTemplate('week-days');

		var t = {};
		var start = parseInt(this.options.position.start.getTime());
		var end = parseInt(this.options.position.end.getTime());
		var events = [];
		var self = this;
		var first_day = getExtentedOption(this, 'first_day');

		$.each(this.getEventsBetween(start, end), function(k, event) {
			var eventStart  = new Date(parseInt(event.start));
			eventStart.setHours(0,0,0,0);
			var eventEnd    = new Date(parseInt(event.end));
			eventEnd.setHours(23,59,59,999);

			event.start_day = new Date(parseInt(eventStart.getTime())).getDay();
			if(first_day == 1) {
				event.start_day = (event.start_day + 6) % 7;
			}
			if((eventEnd.getTime() - eventStart.getTime()) <= 86400000) {
				event.days = 1;
			} else {
				event.days = ((eventEnd.getTime() - eventStart.getTime()) / 86400000);
			}

			if(eventStart.getTime() < start) {

				event.days = event.days - ((start - eventStart.getTime()) / 86400000);
				event.start_day = 0;
			}

			event.days = Math.ceil(event.days);

			if(event.start_day + event.days > 7) {
				event.days = 7 - (event.start_day);
			}

			events.push(event);
		});
		t.events = events;
		t.cal = this;
		return self.options.templates['week-days'](t);
	}

	Calendar.prototype._month = function(month) {
		this._loadTemplate('year-month');

		var t = {cal: this};
		var newmonth = month + 1;
		t.data_day = this.options.position.start.getFullYear() + '-' + (newmonth < 10 ? '0' + newmonth : newmonth) + '-' + '01';
		t.month_name = this.locale['m' + month];

		var curdate = new Date(this.options.position.start.getFullYear(), month, 1, 0, 0, 0);
		t.start = parseInt(curdate.getTime());
		t.end = parseInt(new Date(this.options.position.start.getFullYear(), month + 1, 1, 0, 0, 0).getTime());
		t.events = this.getEventsBetween(t.start, t.end);
		return this.options.templates['year-month'](t);
	}

	Calendar.prototype._day = function(week, day) {
		this._loadTemplate('month-day');

		var t = {tooltip: '', cal: this};
		var cls = this.options.classes.months.outmonth;

		var firstday = this.options.position.start.getDay();
		if(getExtentedOption(this, 'first_day') == 2) {
			firstday++;
		} else {
			firstday = (firstday == 0 ? 7 : firstday);
		}

		day = (day - firstday) + 1;
		var curdate = new Date(this.options.position.start.getFullYear(), this.options.position.start.getMonth(), day, 0, 0, 0);

		// if day of the current month
		var incEvents = false;
		if(day > 0) {
			incEvents = true;
			cls = this.options.classes.months.inmonth;
		}
		// stop cycling table rows;
		var daysinmonth = (new Date(this.options.position.end.getTime() - 1)).getDate();
		if((day + 1) > daysinmonth) {
			this.stop_cycling = true;
		}
		// if day of the next month
		if(day > daysinmonth) {
			incEvents = false;
			day = day - daysinmonth;
			cls = this.options.classes.months.outmonth;
		}

		cls = $.trim(cls + " " + this._getDayClass("months", curdate));

		if(day <= 0) {
			var daysinprevmonth = (new Date(this.options.position.start.getFullYear(), this.options.position.start.getMonth(), 0)).getDate();
			day = daysinprevmonth - Math.abs(day);
			cls += ' cal-month-first-row';
		}

		var holiday = this._getHoliday(curdate);
		if(holiday !== false) {
			t.tooltip = holiday;
		}

		t.data_day = curdate.getFullYear() + '-' + curdate.getMonthFormatted() + '-' + (day < 10 ? '0' + day : day);
		t.cls = cls;
		t.day = day;

		t.start = parseInt(curdate.getTime());
		t.end = parseInt(t.start + 86400000);
        t.events = (incEvents) ? this.getEventsBetween(t.start, t.end) : [];

		return this.options.templates['month-day'](t);
	}
	
	Calendar.prototype._layouts = function(ev, la, type) {
			
		this._loadTemplate('agenda-layouts');
		
		var t = {tooltip: '', cal: this};
		var layouts = [];
		var maxPriority = 0;
		
		for (var i = 0; i < ev.length; i++) {
			if (ev[i].isPriority > maxPriority && ev[i].eventTypeId == type) {
				maxPriority = ev[i].isPriority;
			}
		}
		
		for (var i = 0; i < ev.length; i++) {

			// Add if it's a normal layout (1) or an overlay (3)
			if(ev[i].eventTypeId == type && ev[i].layoutId != 0) {
				var layout = la[ev[i].layoutId];
				var event = ev[i];
				var elementPriority = 0;
				var elementPriorityIcon = '';
				var elementPriorityClass = '';
				
				if(event.isPriority == maxPriority && maxPriority != 0) {
					elementPriority = 1;
					elementPriorityIcon = 'fa-bullseye event-important';
					elementPriorityClass = 'high-priority';
				} else 	if(event.isPriority < maxPriority) {
					elementPriority = -1;
					elementPriorityClass = 'low-priority';
				}
				
				layouts.push({
					eventPriorityFlag: elementPriority,
					eventId: event.eventId,
					layoutId: event.layoutId,
					layoutName: layout.layout,
					layoutStatus: layout.status,
					eventFromDt: moment(event.fromDt, "X").tz ? moment(event.fromDt, "X").tz(timezone).format(jsDateFormat) : moment(event.fromDt, "X").format(jsDateFormat),
					eventToDt: moment(event.toDt, "X").tz ? moment(event.toDt, "X").tz(timezone).format(jsDateFormat) : moment(event.toDt, "X").format(jsDateFormat),
					eventDayPartId: event.dayPartId,
					isAlways: event.isAlways,
					isCustom: event.isCustom,
					layoutDuration: layout.duration,
					layoutDisplayOrder: event.displayOrder,
					eventPriority: event.isPriority,
					itemClass: elementPriorityClass,
					itemIcon: elementPriorityIcon,
					shareOfVoice: event.shareOfVoice
				});
			}
		}
		
		// Render only if there is at least one layout
		if (layouts.length > 0) {
			t.layouts = layouts;
			t.layouts['type'] = type;
			return this.options.templates['agenda-layouts'](t);
		} else {
			return '';
		}	
	}
	
	Calendar.prototype._displaygroups = function(ev, dg) {
		this._loadTemplate('agenda-displaygroups');
		
		var t = {tooltip: '', cal: this};
		var displaygroups = {};
		var atLeastOneDisplayGroup = 0;
		
		for (var i = 0; i < ev.length; i++) {
			displaygroups[ev[i].displayGroupId] = dg[ev[i].displayGroupId];
			atLeastOneDisplayGroup++;
		}
		
		// Render only if there is at least one display group
		if (atLeastOneDisplayGroup > 0) {
			t.displaygroups = displaygroups;
			return this.options.templates['agenda-displaygroups'](t);
		} else {
			return '';
		}
	}

	Calendar.prototype._campaigns = function(ev, ca) {
		this._loadTemplate('agenda-campaigns');
		
		var t = {tooltip: '', cal: this};
		var campaigns = {};
		var atLeastOneCampaign = 0;
		
		for (var i = 0; i < ev.length; i++) {
			if(typeof ca[ev[i].campaignId] != 'undefined'){
				campaigns[ev[i].campaignId] = ca[ev[i].campaignId];
				atLeastOneCampaign++;
			}
		}
		
		// Render only if there is at least one campaign
		if (atLeastOneCampaign > 0) {
			t.campaigns = campaigns;
			return this.options.templates['agenda-campaigns'](t);
		} else {
			return '';
		}
	}
	
	Calendar.prototype._breadcrumbTrail = function(layoutId, data, eventId) {
		this._loadTemplate('breadcrumb-trail');
		
		var t = {};
		
		var targetEvent = {};
	    var displayGroupLink = '';
			var campaignLink = '';

	    var results = data.results[data.selectedDisplayGroup];
	    
	    var allEvents = results.events;
		
	    // Get the correspondent event
	    for (var i = 0; i < allEvents.length; i++) {
	        if (allEvents[i].layoutId == layoutId && allEvents[i].eventId == eventId) {
	            targetEvent = allEvents[i];
	        }
	    }
	    
	    // Layout
	    var layoutData = results.layouts[layoutId];
	    t.layout = {link: layoutData.link, name: layoutData.layout};

	    // Campaign
		if (typeof results.campaigns[targetEvent.campaignId] != 'undefined'){
			t.campaign = {link:campaignLink, name: results.campaigns[targetEvent.campaignId].campaign};
	    }
	    
	    // Schedule
		t.schedule = {link: targetEvent.link, fromDt: targetEvent.fromDt * 1000, toDt: targetEvent.toDt * 1000};

	    // Display groups
		t.displayGroups = [];

        // Assigned display group
        var assignedDisplayGroup = targetEvent.displayGroupId;

        // Add the final display group ( if it's not the directly assigned one)
        if (data.selectedDisplayGroup != assignedDisplayGroup) {
            if (typeof results.displayGroups[data.selectedDisplayGroup] != 'undefined'){
                t.displayGroups.push( { link: displayGroupLink, name: results.displayGroups[data.selectedDisplayGroup].displayGroup } );
            }
        }
	    
	    // Add intermediate display groups
	    for (var i = targetEvent.intermediateDisplayGroupIds.length; i >= 0; i--) {
	        var displayGroupId = targetEvent.intermediateDisplayGroupIds[i];
	        if (typeof results.displayGroups[displayGroupId] != 'undefined'){
				t.displayGroups.push( { link: displayGroupLink, name: results.displayGroups[displayGroupId].displayGroup } );
	        }
	    }

        // Assigned display Group
        if (typeof results.displayGroups[assignedDisplayGroup] != 'undefined'){
            t.displayGroups.push( { link: displayGroupLink, name: results.displayGroups[assignedDisplayGroup].displayGroup } );
        }
		
		return this.options.templates['breadcrumb-trail'](t);
	};
	
	Calendar.prototype._getHoliday = function(date) {
		var result = false;
		$.each(getHolidays(this, date.getFullYear()), function() {
			var found = false;
			$.each(this.days, function() {
				if(this.toDateString() == date.toDateString()) {
					found = true;
					return false;
				}
			});
			if(found) {
				result = this.name;
				return false;
			}
		});
		return result;
	};

	Calendar.prototype._getHolidayName = function(date) {
		var holiday = this._getHoliday(date);
		return (holiday === false) ? "" : holiday;
	};

	Calendar.prototype._getDayClass = function(class_group, date) {
		var self = this;
		var addClass = function(which, to) {
			var cls;
			cls = (self.options.classes && (class_group in self.options.classes) && (which in self.options.classes[class_group])) ? self.options.classes[class_group][which] : "";
			if((typeof(cls) == "string") && cls.length) {
				to.push(cls);
			}
		};
		var classes = [];
		if(date.toDateString() == (new Date()).toDateString()) {
			addClass("today", classes);
		}
		var holiday = this._getHoliday(date);
		if(holiday !== false) {
			addClass("holidays", classes);
		}
		switch(date.getDay()) {
			case 0:
				addClass("sunday", classes);
				break;
			case 6:
				addClass("saturday", classes);
				break;
		}

		addClass(date.toDateString(), classes);

		return classes.join(" ");
	};

	Calendar.prototype.view = function(view) {
		if(view) {
			if(!this.options.views[view].enable) {
				return;
			}
			this.options.view = view;
		}

		this._init_position();
		this._loadEvents();
		this._render();

		this.options.onAfterViewLoad.call(this, this.options.view);
	};

	Calendar.prototype.navigate = function(where, next) {
		var to = $.extend({}, this.options.position);
		if(where == 'next') {
			switch(this.options.view) {
				case 'year':
					to.start.setFullYear(this.options.position.start.getFullYear() + 1);
					break;
				case 'month':
					to.start.setMonth(this.options.position.start.getMonth() + 1);
					break;
				case 'week':
					to.start.setDate(this.options.position.start.getDate() + 7);
					break;
				case 'day':
					to.start.setDate(this.options.position.start.getDate() + 1);
					break;
				case 'agenda':
					to.start.setDate(this.options.position.start.getDate() + 1);
					break;
			}
		} else if(where == 'prev') {
			switch(this.options.view) {
				case 'year':
					to.start.setFullYear(this.options.position.start.getFullYear() - 1);
					break;
				case 'month':
					to.start.setMonth(this.options.position.start.getMonth() - 1);
					break;
				case 'week':
					to.start.setDate(this.options.position.start.getDate() - 7);
					break;
				case 'day':
					to.start.setDate(this.options.position.start.getDate() - 1);
					break;
				case 'agenda':
					to.start.setDate(this.options.position.start.getDate() - 1);
					break;
			}
		} else if(where == 'today') {
			to.start.setTime(new Date().getTime());
		} else if(where == 'date') {
			to.start.setTime(next.format('x'));
		}
		else {
			$.error(this.locale.error_where.format(where))
		}
		this.options.day = to.start.getFullYear() + '-' + to.start.getMonthFormatted() + '-' + to.start.getDateFormatted();
		this.view();
		if(_.isFunction(next)) {
			next();
		}
	};

	Calendar.prototype._init_position = function() {
		var year, month, day;

		if(this.options.day == 'now') {
			var date = new Date();
			year = date.getFullYear();
			month = date.getMonth();
			day = date.getDate();
		} else if(this.options.day.match(/^\d{4}-\d{2}-\d{2}$/g)) {
			var list = this.options.day.split('-');
			year = parseInt(list[0], 10);
			month = parseInt(list[1], 10) - 1;
			day = parseInt(list[2], 10);
		}
		else {
			$.error(this.locale.error_dateformat.format(this.options.day));
		}

		switch(this.options.view) {
			case 'year':
				this.options.position.start.setTime(new Date(year, 0, 1).getTime());
				this.options.position.end.setTime(new Date(year + 1, 0, 1).getTime());
				break;
			case 'month':
				this.options.position.start.setTime(new Date(year, month, 1).getTime());
				this.options.position.end.setTime(new Date(year, month + 1, 1).getTime());
				break;
			case 'day':
				this.options.position.start.setTime(new Date(year, month, day).getTime());
				this.options.position.end.setTime(new Date(year, month, day + 1).getTime());
				break;
			case 'week':
				var curr = new Date(year, month, day);
				var first;
				if(getExtentedOption(this, 'first_day') == 1) {
					first = curr.getDate() - ((curr.getDay() + 6) % 7);
				}
				else {
					first = curr.getDate() - curr.getDay();
				}
				this.options.position.start.setTime(new Date(year, month, first).getTime());
				this.options.position.end.setTime(new Date(year, month, first + 7).getTime());
				break;
			case 'agenda':
				this.options.position.start.setTime(new Date(year, month, day).getTime());
				this.options.position.end.setTime(new Date(year, month, day).getTime());
				break;
			default:
				$.error(this.locale.error_noview.format(this.options.view))
		}
		return this;
	};

	Calendar.prototype.getTitle = function() {
		var p = this.options.position.start;
		switch(this.options.view) {
			case 'year':
				return this.locale.title_year.format(p.getFullYear());
				break;
			case 'month':
				return this.locale.title_month.format(this.locale['m' + p.getMonth()], p.getFullYear());
				break;
			case 'week':
				return this.locale.title_week.format(p.getWeek(getExtentedOption(this, 'week_numbers_iso_8601')), p.getFullYear());
				break;
			case 'day':
				return this.locale.title_day.format(this.locale['d' + p.getDay()], p.getDate(), this.locale['m' + p.getMonth()], p.getFullYear());
				break;
			case 'agenda':
				return this.locale.title_day.format(this.locale['d' + p.getDay()], p.getDate(), this.locale['m' + p.getMonth()], p.getFullYear());
				break;
		}
		return;
	};
	
	Calendar.prototype.getYear = function() {
		var p = this.options.position.start;
		return p.getFullYear();
	};

	Calendar.prototype.getMonth = function() {
		var p = this.options.position.start;
		return this.locale['m' + p.getMonth()];
	};

	Calendar.prototype.getDay = function() {
		var p = this.options.position.start;
		return this.locale['d' + p.getDay()];
	};

	Calendar.prototype.isToday = function() {
		var now = new Date().getTime();

		return ((now > this.options.position.start) && (now < this.options.position.end));
	}

	Calendar.prototype.getStartDate = function() {
		return this.options.position.start;
	}

	Calendar.prototype.getEndDate = function() {
		return this.options.position.end;
	}

	Calendar.prototype._loadEvents = function() {
		var self = this;
		var source = null;
		if('events_source' in this.options && this.options.events_source !== '') {
			source = this.options.events_source;
		}
		else if('events_url' in this.options) {
			source = this.options.events_url;
			warn('The events_url option is DEPRECATED and it will be REMOVED in near future. Please use events_source instead.');
		}
		var loader;
		switch($.type(source)) {
			case 'function':
				loader = function() {
					return source(self.options.position.start, self.options.position.end, browser_timezone);
				};
				break;
			case 'array':
				loader = function() {
					return [].concat(source);
				};
				break;
		}
		if(!loader) {
			$.error(this.locale.error_loadurl);
		}
		this.options.onBeforeEventsLoad.call(this, function() {
			if (!self.options.events.length || !self.options.events_cache) {
				self.options.events = loader();
				self.options.events.sort(function (a, b) {
					var delta;
					delta = a.start - b.start;
					if (delta == 0) {
						delta = a.end - b.end;
					}
					return delta;
				});
			}
			self.options.onAfterEventsLoad.call(self, self.options.events);
		});
	};

	Calendar.prototype._templatePath = function(name) {
		if(typeof this.options.tmpl_path == 'function') {
			return this.options.tmpl_path(name)
		}
		else {
			return this.options.tmpl_path + name + '.html';
		}
	};

	Calendar.prototype._loadTemplate = function(name) {
		
		if(this.options.templates[name]) {
			return;
		}
		
		this.options.templates[name] = _.template($('#' + this._templatePath(name)).text());
	};

	Calendar.prototype._update = function() {
		var self = this;

		$('*[data-toggle="tooltip"]').tooltip({container: this.options.tooltip_container});

		$('*[data-cal-date]').click(function() {
			var view = $(this).data('cal-view');
			self.options.day = $(this).data('cal-date');
			self.view(view);
			$('#range').val(view)
		});
		$('.cal-cell').dblclick(function() {
			var view = $('[data-cal-date]', this).data('cal-view');
			self.options.day = $('[data-cal-date]', this).data('cal-date');
			// Add event to the picker to update the calendar
			updateDatePicker($('#dateInput'), self.options.day, jsDateOnlyFormat);
			self.view(view)
			$('#range').val(view)
		});

		this['_update_' + this.options.view]();

		this._update_modal();

	};

	Calendar.prototype._update_modal = function() {
		var self = this;

		$('a[data-event-id]', this.context).unbind('click');

		if (!$('a[data-event-id]', this.context).attr("data-event-class") == "XiboFormButton")
			return;

		$('a[data-event-id]', this.context).on('click', function(event) {
			event.preventDefault();
			event.stopPropagation();

            var eventStart = $(this).data("eventStart");
            var eventEnd = $(this).data("eventEnd");
            if (eventStart !== undefined && eventEnd !== undefined ) {
                var data = {
                    eventStart: eventStart,
                    eventEnd: eventEnd,
                };
                XiboFormRender($(this), data);
            } else {
                XiboFormRender($(this));
            }
		});
	};

	Calendar.prototype._update_day = function() {
		$('#cal-day-panel').height($('#cal-day-panel-hour').height());
		
		// Adjust Row time title to the used time format
		// Get an example of time format to get the length of it ( 10pm because it's the bigger size both for 24 an 12 format)
		var exampleTimeLength = this._hour(22, 0).length;
		
		// Calculate adjustment usign 9 pixels per char and a margin of 20 px
		var adjustment = (exampleTimeLength * 9) + 20;
		
		// Apply the new calculated value to the DOM objects
		$('#cal-day-panel').css('padding-left', parseInt(adjustment) + 'px');
		$('#cal-day-panel-hour').css('margin-left', -parseInt(adjustment) + 'px');
	};

	Calendar.prototype._update_week = function() {
	};

	Calendar.prototype._update_year = function() {
		this._update_month_year();
	};
	
	Calendar.prototype._update_agenda = function() {
	};

	Calendar.prototype._update_month = function() {
		this._update_month_year();

		var self = this;

		if(this.options.weekbox == true) {
			var week = $(document.createElement('div')).attr('id', 'cal-week-box');
			var start = this.options.position.start.getFullYear() + '-' + this.options.position.start.getMonthFormatted() + '-';
			self.context.find('.cal-month-box .cal-row-fluid')
				.on('mouseenter', function() {
					var p = new Date(self.options.position.start);
					var child = $('.cal-cell1:first-child .cal-month-day', this);
					var day = (child.hasClass('cal-month-first-row') ? 1 : $('[data-cal-date]', child).text());
					p.setDate(parseInt(day));
					day = (day < 10 ? '0' + day : day);
					week.html(self.locale.week.format(self.options.display_week_numbers == true ? p.getWeek(getExtentedOption(self, 'week_numbers_iso_8601')) : ''));
					week.attr('data-cal-week', start + day).show().appendTo(child);
				})
				.on('mouseleave', function() {
					week.hide();
				});

			week.click(function() {
				self.options.day = $(this).data('cal-week');
				self.view('week');
				$('#range').val('week')
			});
		}


		self.context.find('a.event').mouseenter(function() {
			$('a[data-event-id="' + $(this).data('event-id') + '"]').closest('.cal-cell1').addClass('day-highlight dh-' + $(this).data('event-class'));
		});
		self.context.find('a.event').mouseleave(function() {
			$('div.cal-cell1').removeClass('day-highlight dh-' + $(this).data('event-class'));
		});
	};

	Calendar.prototype._update_month_year = function() {
		if(!this.options.views[this.options.view].slide_events) {
			return;
		}
		var self = this;
		var activecell = 0;
		var downbox = $(document.createElement('div')).attr('id', 'cal-day-tick').html('<i class="icon-chevron-down glyphicon glyphicon-chevron-down"></i>');

		self.context.find('.cal-month-day, .cal-year-box .span3')
			.on('mouseenter', function() {
				if($('.events-list', this).length == 0) {
					return;
				}
				if($(this).children('[data-cal-date]').text() == self.activecell) {
					return;
				}
				downbox.show().appendTo(this);
			})
			.on('mouseleave', function() {
				downbox.hide();
			})
			.on('click', function(event) {
				self.options.day = $('[data-cal-date]', this).data('cal-date');
				updateDatePicker($('#dateInput'), self.options.day, jsDateOnlyFormat);
				if($('.events-list', this).length == 0) {
					return;
				}
				if($(this).children('[data-cal-date]').text() == self.activecell) {
					return;
				}
				showEventsList(event, downbox, slider, self);
			})
		;

		var slider = $(document.createElement('div')).attr('id', 'cal-slide-box');
		slider.hide().click(function(event) {
			event.stopPropagation();
		});

		this._loadTemplate('events-list');

		downbox.click(function(event) {
			showEventsList(event, $(this), slider, self);
		});
	};

	Calendar.prototype.getEventsBetween = function(start, end) {
		var events = [];
		var period_start = moment(start, "x");
		var period_end = moment(end, "x");
		$.each(this.options.events, function() {
			if(this.start == null) {
				return true;
			}
			// Convert to a local date, without the timezone
			// Grab the date from the scheduled event (which has already been adjusted appropriately by the CMS)
			let event_start = moment(this.scheduleEvent.fromDt, "YYYY-MM-DD HH:mm:ss");
			let event_end = (this.end != null) ? moment(this.scheduleEvent.toDt, "YYYY-MM-DD HH:mm:ss") : event_start;

			if (event_start.isBefore(period_end) && event_end.isAfter(period_start)) {
				//console.log("X. ES: " + event_start.format() + "(" + this.scheduleEvent.fromDt + "), EE: " + event_end.format() + " (" + this.scheduleEvent.toDt + ")");
				// Override the dates with our timezone neutral ones.
				this.start = event_start.valueOf();
				this.end = event_end.valueOf();

				events.push(this);
			}
		});
		return events;
	};

	function showEventsList(event, that, slider, self) {

		event.stopPropagation();

		var that = $(that);
		var cell = that.closest('.cal-cell');
		var row = cell.closest('.cal-before-eventlist');
		var tick_position = cell.data('cal-row');

		that.fadeOut('fast');

		slider.slideUp('fast', function() {
			var event_list = $('.events-list', cell);
			slider.html(self.options.templates['events-list']({
				cal: self,
				events: self.getEventsBetween(parseInt(event_list.data('cal-start')), parseInt(event_list.data('cal-end')))
			}));
			row.after(slider);
			self.activecell = $('[data-cal-date]', cell).text();
			$('#cal-slide-tick').addClass('tick' + tick_position).show();
			slider.slideDown('fast', function() {
				$('body').one('click', function() {
					slider.slideUp('fast');
					self.activecell = 0;
				});
			});
		});

		// Wait 400ms before updating the modal & attach the mouseenter&mouseleave(400ms is the time for the slider to fade out and slide up)
		setTimeout(function() {
			$('a.event-item').mouseenter(function() {
				$('a[data-event-id="' + $(this).data('event-id') + '"]').closest('.cal-cell1').addClass('day-highlight dh-' + $(this).data('event-class'));
			});
			$('a.event-item').mouseleave(function() {
				$('div.cal-cell1').removeClass('day-highlight dh-' + $(this).data('event-class'));
			});
			self._update_modal();
		}, 400);
	}

	function getEasterDate(year, offsetDays) {
		var a = year % 19;
		var b = Math.floor(year / 100);
		var c = year % 100;
		var d = Math.floor(b / 4);
		var e = b % 4;
		var f = Math.floor((b + 8) / 25);
		var g = Math.floor((b - f + 1) / 3);
		var h = (19 * a + b - d - g + 15) % 30;
		var i = Math.floor(c / 4);
		var k = c % 4;
		var l = (32 + 2 * e + 2 * i - h - k) % 7;
		var m = Math.floor((a + 11 * h + 22 * l) / 451);
		var n0 = (h + l + 7 * m + 114)
		var n = Math.floor(n0 / 31) - 1;
		var p = n0 % 31 + 1;
		return new Date(year, n, p + (offsetDays ? offsetDays : 0), 0, 0, 0);
	}

	$.fn.calendar = function(params) {
		return new Calendar(params, this);
	}
}(jQuery));
