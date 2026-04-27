/*
 * Copyright (C) 2026 Xibo Signage Ltd
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

import {
  FloatingPortal,
  autoUpdate,
  flip,
  offset,
  shift,
  useDismiss,
  useFloating,
  useInteractions,
} from '@floating-ui/react';
import { ChevronDown, ChevronUp, MonitorPlay, Trash2, X } from 'lucide-react';
import { DateTime } from 'luxon';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { EVENT_LEGEND_BADGES, getEventBadge } from '../EventsConfig';
import { expandRecurringEvents } from '../utils/expandRecurringEvents';

import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import { useUserContext } from '@/context/UserContext';
import type { Event } from '@/types/event';

const DAYS_OF_WEEK = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

function buildMonthWeeks(date: Date, timezone: string): DateTime[][] {
  const month = DateTime.fromJSDate(date, { zone: timezone }).startOf('month');
  const start = month.startOf('week');
  const end = month.endOf('month').endOf('week');

  const weeks: DateTime[][] = [];
  let current = start;

  while (current <= end) {
    const week: DateTime[] = [];
    for (let i = 0; i < 7; i++) {
      week.push(current);
      current = current.plus({ days: 1 });
    }
    weeks.push(week);
  }

  return weeks;
}

function buildEventsByDay(
  events: Event[],
  timezone: string,
): {
  map: Map<string, Event[]>;
  alwaysEvents: Event[];
} {
  const map = new Map<string, Event[]>();
  const alwaysEvents: Event[] = [];

  events.forEach((event) => {
    if (event.isAlways === 1) {
      alwaysEvents.push(event);
      return;
    }
    if (!event.fromDt || !event.toDt) {
      return;
    }
    let day = DateTime.fromSeconds(Number(event.fromDt), { zone: timezone }).startOf('day');
    const end = DateTime.fromSeconds(Number(event.toDt), { zone: timezone }).startOf('day');
    while (day <= end) {
      const key = day.toISODate()!;
      const bucket = map.get(key);
      if (bucket) {
        bucket.push(event);
      } else {
        map.set(key, [event]);
      }
      day = day.plus({ days: 1 });
    }
  });

  return { map, alwaysEvents };
}

function getTimeLabel(event: Event, day: DateTime, timezone: string): string | null {
  if (!event.fromDt || !event.toDt) {
    return null;
  }
  const from = DateTime.fromSeconds(Number(event.fromDt), { zone: timezone });
  const to = DateTime.fromSeconds(Number(event.toDt), { zone: timezone });
  const startHere = from.hasSame(day, 'day');
  const endHere = to.hasSame(day, 'day');
  if (startHere && endHere) {
    return `${from.toFormat('h:mma').toLowerCase()} – ${to.toFormat('h:mma').toLowerCase()}`;
  }
  if (startHere) {
    return `${from.toFormat('h:mma').toLowerCase()} –`;
  }
  if (endHere) {
    return `– ${to.toFormat('h:mma').toLowerCase()}`;
  }
  return null;
}

function getEventsForDay(
  index: { map: Map<string, Event[]>; alwaysEvents: Event[] },
  day: DateTime,
): Event[] {
  const key = day.startOf('day').toISODate()!;
  const dayEvents = index.map.get(key) ?? [];
  return index.alwaysEvents.length > 0 ? [...index.alwaysEvents, ...dayEvents] : dayEvents;
}

function CalendarEventBadge({
  scheduleEvent,
  onEdit,
  onContextMenu,
}: {
  scheduleEvent: Event;
  onEdit?: (scheduleEvent: Event) => void;
  onContextMenu?: (scheduleEvent: Event, x: number, y: number) => void;
}) {
  const badge = getEventBadge(scheduleEvent);
  const Icon = badge.icon;
  const title = scheduleEvent.name ?? scheduleEvent.campaign ?? scheduleEvent.command ?? '';
  return (
    <span
      className="relative inline-flex shrink-0"
      title={title}
      onClick={(e) => {
        e.stopPropagation();
        onEdit?.(scheduleEvent);
      }}
      onContextMenu={(e) => {
        e.preventDefault();
        e.stopPropagation();
        onContextMenu?.(scheduleEvent, e.clientX, e.clientY);
      }}
    >
      <span
        className={`inline-flex items-center justify-center w-5.5 h-5.5 rounded-full ${badge.bg} ${badge.text}`}
      >
        <Icon className="w-3 h-3" />
      </span>
      {scheduleEvent.isPriority > 0 && (
        <span className="absolute -top-1 -right-1 flex items-center justify-center w-3 h-3 text-[10px] rounded-full bg-white border-red-400 border text-red-500 font-bold">
          {scheduleEvent.isPriority}
        </span>
      )}
    </span>
  );
}

interface DayDetailPanelProps {
  day: DateTime;
  events: Event[];
  timezone: string;
  floatingRef: (node: HTMLElement | null) => void;
  floatingStyles: React.CSSProperties;
  getFloatingProps: (props?: React.HTMLProps<HTMLElement>) => Record<string, unknown>;
  onClose: () => void;
  onEditEvent?: (scheduleEvent: Event) => void;
  onContextMenu?: (scheduleEvent: Event, x: number, y: number) => void;
}

function DayDetailPanel({
  day,
  events,
  timezone,
  floatingRef,
  floatingStyles,
  getFloatingProps,
  onClose,
  onEditEvent,
  onContextMenu,
}: DayDetailPanelProps) {
  const { t } = useTranslation();
  const dateLabel = day.toFormat('cccc, d LLL yyyy');

  return (
    <div
      ref={floatingRef}
      style={floatingStyles}
      {...getFloatingProps()}
      className="z-50 max-w-150 max-h-100 flex flex-col rounded-xl border border-gray-200 bg-white shadow-lg overflow-hidden"
    >
      <div className="flex items-start justify-between px-3 py-2">
        <div>
          <p className="text-sm font-semibold text-gray-800">{dateLabel}</p>
        </div>
        <button
          type="button"
          onClick={onClose}
          className="text-gray-400 hover:text-gray-600 transition-colors shrink-0 mt-0.5"
        >
          <X className="w-4 h-4" />
        </button>
      </div>
      <div className="p-8 pt-3 flex flex-col gap-3 flex-1 min-h-0 overflow-hidden">
        <div className="flex justify-between items-center flex-none">
          <p className="text-xs font-semibold text-gray-500">
            {events.length === 1 ? t('1 Event') : t('{{count}} Events', { count: events.length })}
          </p>
          <Button variant="tertiary">{t('Agenda')}</Button>
        </div>
        <ul className="flex flex-col gap-3 overflow-y-auto overflow-x-hidden flex-1 min-h-0">
          {events.map((event) => {
            const badge = getEventBadge(event);
            const Icon = badge.icon;
            const eventName = event.name ?? null;
            const contentName = event.campaign ?? event.command ?? null;
            const displays = event.displayGroups.map((dg) => dg.displayGroup);

            const timeLabel = getTimeLabel(event, day, timezone);

            return (
              <li
                key={`${event.eventId}-${event.fromDt}`}
                onClick={() => {
                  onEditEvent?.(event);
                  onClose();
                }}
                onContextMenu={(e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  onContextMenu?.(event, e.clientX, e.clientY);
                  onClose();
                }}
                className={`flex items-center gap-3 min-w-0 rounded px-1 -mx-1${onEditEvent ? ' cursor-pointer hover:bg-gray-50' : ''}`}
              >
                <span
                  className={`inline-flex items-center justify-center w-6.5 h-6.5 rounded-full shrink-0 mt-0.5 ${badge.bg} ${badge.text}`}
                  title={t(badge.label)}
                >
                  <Icon className="w-4 h-4" />
                </span>
                <div className="flex items-center gap-1 text-xs min-w-0 overflow-hidden">
                  {timeLabel && <span className="shrink-0 font-semibold">{timeLabel}</span>}
                  <div className="flex items-center gap-1 min-w-0 overflow-hidden flex-1">
                    {eventName && (
                      <span className="font-semibold truncate min-w-0 max-w-1/2" title={eventName}>
                        {eventName}
                      </span>
                    )}
                    {eventName && contentName && <span className="shrink-0 text-gray-400">·</span>}
                    {contentName && (
                      <span className="font-normal truncate min-w-0 flex-1" title={contentName}>
                        {contentName}
                      </span>
                    )}
                  </div>
                  {displays[0] && (
                    <Badge variation="outline" className="flex gap-px h-4.25 shrink-0 ml-1">
                      <MonitorPlay size={10} className="shrink-0" />
                      <p className="truncate text-[10px] font-medium max-w-24 p-[2.5px]">
                        {displays[0]}
                      </p>
                    </Badge>
                  )}
                  {displays.length > 1 && (
                    <span title={displays.slice(1).join(', ')} className="shrink-0">
                      <Badge
                        variation="outline"
                        className="flex gap-px h-4.25 text-[10px] font-semibold"
                      >
                        +{displays.length - 1}
                      </Badge>
                    </span>
                  )}
                </div>
              </li>
            );
          })}
        </ul>
      </div>
    </div>
  );
}

interface EventContextMenuProps {
  scheduleEvent: Event;
  timezone: string;
  floatingRef: (node: HTMLElement | null) => void;
  floatingStyles: React.CSSProperties;
  getFloatingProps: (props?: React.HTMLProps<HTMLElement>) => Record<string, unknown>;
  onClose: () => void;
  onDelete?: (scheduleEvent: Event) => void;
}

function EventContextMenu({
  scheduleEvent,
  timezone,
  floatingRef,
  floatingStyles,
  getFloatingProps,
  onClose,
  onDelete,
}: EventContextMenuProps) {
  const { t } = useTranslation();
  const badge = getEventBadge(scheduleEvent);
  const Icon = badge.icon;
  const eventName = scheduleEvent.name ?? null;
  const contentName = scheduleEvent.campaign ?? scheduleEvent.command ?? null;
  const displays = scheduleEvent.displayGroups.map((dg) => dg.displayGroup);

  let timeLabel: string | null = null;
  if (scheduleEvent.fromDt && scheduleEvent.toDt) {
    const from = DateTime.fromSeconds(Number(scheduleEvent.fromDt), { zone: timezone });
    const to = DateTime.fromSeconds(Number(scheduleEvent.toDt), { zone: timezone });
    timeLabel = `${from.toFormat('h:mma').toLowerCase()} – ${to.toFormat('h:mma').toLowerCase()}`;
  }

  return (
    <div
      ref={floatingRef}
      style={floatingStyles}
      {...getFloatingProps()}
      className="z-50 min-w-80 max-w-110 flex flex-col rounded-xl border border-gray-200 bg-white shadow-lg overflow-hidden"
    >
      <div className="flex items-center gap-3 px-3 py-3">
        <span
          className={`inline-flex items-center justify-center w-6.5 h-6.5 rounded-full shrink-0 ${badge.bg} ${badge.text}`}
          title={t(badge.label)}
        >
          <Icon className="w-4 h-4" />
        </span>
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-1 text-sm text-gray-700 min-w-0 overflow-hidden">
            {timeLabel && <span className="shrink-0 font-semibold">{timeLabel}</span>}
            <div className="flex items-center gap-1 min-w-0 overflow-hidden flex-1">
              {eventName && (
                <span className="font-semibold truncate min-w-0 max-w-1/2" title={eventName}>
                  {eventName}
                </span>
              )}
              {eventName && contentName && <span className="shrink-0 text-gray-400">·</span>}
              {contentName && (
                <span className="font-normal truncate min-w-0 flex-1" title={contentName}>
                  {contentName}
                </span>
              )}
            </div>
          </div>
          {displays[0] && (
            <div className="flex items-center gap-1 mt-1 flex-wrap">
              <Badge variation="outline" className="flex gap-px h-4.25 shrink-0">
                <MonitorPlay size={10} className="shrink-0" />
                <p className="truncate text-[10px] font-medium max-w-24 p-[2.5px]">{displays[0]}</p>
              </Badge>
              {displays.length > 1 && (
                <span title={displays.slice(1).join(', ')}>
                  <Badge
                    variation="outline"
                    className="flex gap-px h-4.25 text-[10px] font-semibold"
                  >
                    +{displays.length - 1}
                  </Badge>
                </span>
              )}
            </div>
          )}
        </div>
        {onDelete && (
          <Button
            variant="tertiary"
            leftIcon={Trash2}
            className="text-red-500 hover:text-red-800 hover:bg-white shrink-0"
            onClick={() => {
              onDelete(scheduleEvent);
              onClose();
            }}
          >
            {t('Delete')}
          </Button>
        )}
      </div>
    </div>
  );
}

interface EventCalendarProps {
  date?: Date;
  events?: Event[];
  isLoading?: boolean;
  calendarClassName?: string;
  onEditEvent?: (event: Event) => void;
  onDeleteEvent?: (event: Event) => void;
}

export function EventCalendar({
  date,
  events = [],
  isLoading,
  calendarClassName,
  onEditEvent,
  onDeleteEvent,
}: EventCalendarProps) {
  const { t } = useTranslation();
  const { user } = useUserContext();
  const timezone = user?.settings?.defaultTimezone ?? 'UTC';

  const currentDate = date ?? new Date();
  const currentMonth = DateTime.fromJSDate(currentDate, { zone: timezone });
  const weeks = buildMonthWeeks(currentDate, timezone);

  const viewStart = currentMonth.startOf('month').startOf('week');
  const viewEnd = currentMonth.endOf('month').endOf('week');
  const expandedEvents = expandRecurringEvents(events, viewStart, viewEnd, timezone);
  const eventsIndex = buildEventsByDay(expandedEvents, timezone);

  const [selectedCell, setSelectedCell] = useState<{ day: DateTime; el: HTMLElement } | null>(null);
  const [contextMenuEvent, setContextMenuEvent] = useState<{
    event: Event;
    x: number;
    y: number;
  } | null>(null);

  const selectedDayEvents =
    selectedCell !== null ? getEventsForDay(eventsIndex, selectedCell.day) : [];

  const { refs, floatingStyles, context } = useFloating({
    open: selectedCell !== null,
    onOpenChange: (open) => {
      if (!open) {
        setSelectedCell(null);
      }
    },
    middleware: [offset(6), flip({ padding: 8 }), shift({ padding: 8 })],
    placement: 'bottom-start',
    whileElementsMounted: autoUpdate,
  });

  useEffect(() => {
    refs.setReference(selectedCell?.el ?? null);
  }, [selectedCell, refs]);

  const dismiss = useDismiss(context);
  const { getFloatingProps } = useInteractions([dismiss]);

  const {
    refs: ctxRefs,
    floatingStyles: ctxStyles,
    context: ctxContext,
  } = useFloating({
    open: contextMenuEvent !== null,
    onOpenChange: (open) => {
      if (!open) {
        setContextMenuEvent(null);
      }
    },
    middleware: [offset(6), flip({ padding: 8 }), shift({ padding: 8 })],
    placement: 'bottom-start',
    whileElementsMounted: autoUpdate,
  });

  useEffect(() => {
    if (contextMenuEvent === null) {
      ctxRefs.setReference(null);
      return;
    }
    const { x, y } = contextMenuEvent;
    ctxRefs.setReference({
      getBoundingClientRect() {
        return { width: 0, height: 0, x, y, top: y, left: x, right: x, bottom: y };
      },
    });
  }, [contextMenuEvent, ctxRefs]);

  const ctxDismiss = useDismiss(ctxContext);
  const { getFloatingProps: getCtxFloatingProps } = useInteractions([ctxDismiss]);

  const handleDayClick = (e: React.MouseEvent<HTMLElement>, day: DateTime, dayEvents: Event[]) => {
    if (dayEvents.length === 0) {
      return;
    }
    e.stopPropagation();
    if (selectedCell?.day.hasSame(day, 'day') && selectedCell.el === e.currentTarget) {
      setSelectedCell(null);
    } else {
      setSelectedCell({ day, el: e.currentTarget });
    }
  };

  return (
    <div className={`relative flex flex-row h-full ${calendarClassName ?? ''}`}>
      {isLoading && (
        <div className="absolute inset-0 z-10 bg-white/60 flex items-center justify-center">
          <span className="text-sm text-gray-400 font-medium animate-pulse">{t('Loading...')}</span>
        </div>
      )}
      <div className="flex flex-col bg-white w-40 h-full border border-r-0 border-gray-200 shrink-0 self-start">
        <div className="text-sm font-semibold text-gray-500 bg-gray-50 px-3 py-2 uppercase tracking-tight border-b border-gray-200">
          {t('Legend')}
        </div>
        <div className="px-3 py-5 flex flex-col gap-5 overflow-y-auto">
          {EVENT_LEGEND_BADGES.map((group, gi) => (
            <div key={gi} className="flex flex-col gap-3">
              {group.map(({ icon: Icon, label, bg, text }) => (
                <div key={label} className="flex items-center gap-1">
                  <span
                    className={`inline-flex items-center justify-center w-6.5 h-6.5 rounded-full shrink-0 ${bg} ${text}`}
                  >
                    <Icon className="w-4 h-4" />
                  </span>
                  <span className="text-xs font-semibold text-gray-500">{label}</span>
                </div>
              ))}
            </div>
          ))}
        </div>
      </div>

      <div className="flex-1 min-h-0 h-full border border-gray-200 flex flex-col">
        <div className="grid grid-cols-7 flex-none">
          {DAYS_OF_WEEK.map((day, i) => (
            <div
              key={day}
              className={`text-sm font-semibold text-gray-500 bg-gray-50 text-left uppercase tracking-tight px-3 py-2 border-b border-gray-200${i > 0 ? ' border-l border-gray-200' : ''}`}
            >
              {t(day)}
            </div>
          ))}
        </div>

        <div
          className="flex-1 grid min-h-0 overflow-hidden"
          style={{ gridTemplateRows: `repeat(${weeks.length}, 1fr)` }}
        >
          {weeks.map((week, wi) => (
            <div
              key={wi}
              className={`grid grid-cols-7 overflow-hidden${wi < weeks.length - 1 ? ' border-b border-gray-200' : ''}`}
            >
              {week.map((day, di) => {
                const isCurrentMonth = day.month === currentMonth.month;
                const isToday = day.hasSame(DateTime.now().setZone(timezone), 'day');
                const dayEvents = getEventsForDay(eventsIndex, day);
                const isSelected = selectedCell?.day.hasSame(day, 'day') ?? false;
                const viewLabel = isSelected ? t('View less') : t('View more');
                const ViewIcon = isSelected ? ChevronUp : ChevronDown;
                return (
                  <div
                    key={di}
                    onClick={(e) => handleDayClick(e, day, dayEvents)}
                    className={`relative group flex ${dayEvents.length > 0 ? 'cursor-pointer' : ''} flex-col overflow-hidden${di > 0 ? ' border-l border-gray-200' : ''} ${isSelected ? 'bg-blue-50' : isToday ? 'bg-slate-50' : 'bg-white'}`}
                  >
                    {isToday && (
                      <div className="absolute top-0 left-0 bottom-0 w-0.5 bg-xibo-blue-500 z-1"></div>
                    )}
                    <div
                      className={`p-2 text-right text-sm flex-none ${isCurrentMonth ? 'text-gray-700' : 'text-gray-300'}`}
                    >
                      <span
                        className={
                          isToday
                            ? 'inline-flex items-center justify-center w-6 h-6 rounded-full bg-xibo-blue-600 text-white font-semibold'
                            : ''
                        }
                      >
                        {day.day}
                      </span>
                    </div>
                    {dayEvents.length > 0 && (
                      <>
                        <div className="flex-1 flex flex-wrap gap-1 px-2 pb-2 mt-5 content-start">
                          {dayEvents.map((event) => (
                            <CalendarEventBadge
                              key={`${event.eventId}-${event.fromDt}`}
                              scheduleEvent={event}
                              onEdit={onEditEvent}
                              onContextMenu={(ev, x, y) => setContextMenuEvent({ event: ev, x, y })}
                            />
                          ))}
                        </div>
                        <div className="absolute h-6.25 bottom-0 left-0 right-0 flex items-center justify-center gap-0.5 py-1 opacity-0 group-hover:opacity-100 transition-opacity bg-white text-xs text-xibo-blue-600 font-medium cursor-pointer pointer-events-none group-hover:pointer-events-auto">
                          <span>{viewLabel}</span>
                          <ViewIcon className="w-3 h-3" />
                        </div>
                      </>
                    )}
                  </div>
                );
              })}
            </div>
          ))}
        </div>
      </div>

      {selectedCell !== null && (
        <FloatingPortal>
          <DayDetailPanel
            day={selectedCell.day}
            events={selectedDayEvents}
            timezone={timezone}
            floatingRef={refs.setFloating}
            floatingStyles={floatingStyles}
            getFloatingProps={getFloatingProps}
            onClose={() => setSelectedCell(null)}
            onEditEvent={onEditEvent}
            onContextMenu={(ev, x, y) => setContextMenuEvent({ event: ev, x, y })}
          />
        </FloatingPortal>
      )}

      {contextMenuEvent !== null && (
        <FloatingPortal>
          <EventContextMenu
            scheduleEvent={contextMenuEvent.event}
            timezone={timezone}
            floatingRef={ctxRefs.setFloating}
            floatingStyles={ctxStyles}
            getFloatingProps={getCtxFloatingProps}
            onClose={() => setContextMenuEvent(null)}
            onDelete={onDeleteEvent}
          />
        </FloatingPortal>
      )}
    </div>
  );
}
