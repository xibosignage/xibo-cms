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

import { DateTime } from 'luxon';

import type { Event } from '@/types/event';

export function expandRecurringEvents(
  events: Event[],
  viewStart: DateTime,
  viewEnd: DateTime,
  timezone: string,
): Event[] {
  const allOccurrences: Event[] = [];

  const generateRecurrences = (sourceEv: Event): Event[] => {
    if (
      !sourceEv.recurrenceType ||
      sourceEv.recurrenceType === 'None' ||
      !sourceEv.recurrenceDetail
    ) {
      return [];
    }

    const interval = sourceEv.recurrenceDetail;
    if (interval <= 0) {
      return [];
    }

    const generated: Event[] = [];
    const originalStart = DateTime.fromSeconds(Number(sourceEv.fromDt), { zone: timezone });
    const durationSecs = Number(sourceEv.toDt) - Number(sourceEv.fromDt);

    // 0 or null means no end date
    const rangeEnd = sourceEv.recurrenceRange
      ? DateTime.fromSeconds(Number(sourceEv.recurrenceRange), { zone: timezone })
      : DateTime.fromISO('9999-12-31T00:00:00.000Z', { zone: timezone });

    const isHighFrequency =
      (sourceEv.recurrenceType === 'Minute' && interval < 1440) ||
      (sourceEv.recurrenceType === 'Hour' && interval < 24);

    if (isHighFrequency) {
      // For Minute/Hour repeats: generate one representative occurrence per day
      let currentDay = originalStart.startOf('day');
      if (currentDay < viewStart.startOf('day')) {
        currentDay = viewStart.startOf('day');
      }

      while (currentDay < viewEnd && currentDay < rangeEnd) {
        let eventMoment = currentDay.set({
          hour: originalStart.hour,
          minute: originalStart.minute,
          second: originalStart.second,
        });

        if (eventMoment < originalStart) {
          eventMoment = originalStart;
        }

        if (eventMoment.hasSame(currentDay, 'day') && eventMoment < rangeEnd) {
          const startSec = eventMoment.toUnixInteger();
          const endSec = startSec + durationSecs;

          if (startSec < viewEnd.toUnixInteger() && endSec > viewStart.toUnixInteger()) {
            generated.push({ ...sourceEv, fromDt: startSec, toDt: endSec });
          }
        }

        currentDay = currentDay.plus({ days: 1 });
      }

      return generated;
    }

    let currentMoment = originalStart;

    while (currentMoment < viewEnd && currentMoment < rangeEnd) {
      const isWeekly = sourceEv.recurrenceType === 'Week' && !!sourceEv.recurrenceRepeatsOn;

      if (isWeekly) {
        const days = sourceEv.recurrenceRepeatsOn!.split(',').map(Number);
        // Luxon startOf('week') = Monday, weekday 1–7 (Mon=1, Sun=7) — matches ISO weekday
        const weekStart = currentMoment.startOf('week');

        for (let i = 0; i < 7; i++) {
          const dayInWeek = weekStart.plus({ days: i });
          if (!days.includes(dayInWeek.weekday)) {
            continue;
          }

          const occStart = dayInWeek.set({
            hour: originalStart.hour,
            minute: originalStart.minute,
            second: originalStart.second,
          });

          if (occStart > originalStart && occStart < rangeEnd) {
            generated.push({
              ...sourceEv,
              fromDt: occStart.toUnixInteger(),
              toDt: occStart.plus({ seconds: durationSecs }).toUnixInteger(),
            });
          }
        }

        currentMoment = currentMoment.plus({ weeks: interval });
        continue;
      }

      let nextMoment: DateTime;
      let eventMoment: DateTime;
      let isValidOccurrence = true;

      if (sourceEv.recurrenceType === 'Month') {
        const nextMonthBase = currentMoment.plus({ months: interval });

        if (sourceEv.recurrenceMonthlyRepeatsOn === 1) {
          // Repeat on the same nth weekday of the month
          const startOfOriginalMonth = originalStart.startOf('month');
          const weekNumber =
            Math.floor(originalStart.diff(startOfOriginalMonth, 'weeks').weeks) + 1;
          const originalWeekday = originalStart.weekday;

          const firstDayOfNextMonth = nextMonthBase.startOf('month');
          const offset = (originalWeekday - firstDayOfNextMonth.weekday + 7) % 7;

          eventMoment = firstDayOfNextMonth.plus({ days: offset + (weekNumber - 1) * 7 }).set({
            hour: originalStart.hour,
            minute: originalStart.minute,
            second: originalStart.second,
          });

          if (eventMoment.month !== nextMonthBase.month) {
            // nth weekday spilled into the next month — skip this occurrence
            isValidOccurrence = false;
            const maxDay = nextMonthBase.daysInMonth!;
            nextMoment = nextMonthBase.set({ day: Math.min(originalStart.day, maxDay) });
          } else {
            nextMoment = eventMoment;
          }
        } else {
          // Repeat on the same day of the month, clamped to the month's length
          const maxDay = nextMonthBase.daysInMonth!;
          nextMoment = nextMonthBase.set({ day: Math.min(originalStart.day, maxDay) });
          eventMoment = nextMoment;
        }
      } else {
        const advanceMap: Record<string, object> = {
          Minute: { minutes: interval },
          Hour: { hours: interval },
          Day: { days: interval },
          Week: { weeks: interval },
          Year: { years: interval },
        };
        nextMoment = currentMoment.plus(advanceMap[sourceEv.recurrenceType] ?? {});
        if (nextMoment <= currentMoment) {
          break;
        }
        eventMoment = nextMoment;
      }

      currentMoment = nextMoment;

      if (eventMoment < rangeEnd && isValidOccurrence) {
        generated.push({
          ...sourceEv,
          fromDt: eventMoment.toUnixInteger(),
          toDt: eventMoment.plus({ seconds: durationSecs }).toUnixInteger(),
        });
      }
    }

    return generated;
  };

  events.forEach((sourceEv) => {
    const exclusions = sourceEv.scheduleExclusions ?? [];

    const isExcluded = (fromDt: number, toDt: number) =>
      exclusions.some((ex) => Number(ex.fromDt) === fromDt && Number(ex.toDt) === toDt);

    if (!isExcluded(Number(sourceEv.fromDt), Number(sourceEv.toDt))) {
      allOccurrences.push(sourceEv);
    }

    if (sourceEv.recurringEvent) {
      const recurrences = generateRecurrences(sourceEv);
      allOccurrences.push(...recurrences.filter((ev) => !isExcluded(ev.fromDt, ev.toDt)));
    }
  });

  // Filter to view window (overlap: event starts before viewEnd AND ends after viewStart)
  const viewStartSec = viewStart.toUnixInteger();
  const viewEndSec = viewEnd.toUnixInteger();

  const filtered = allOccurrences.filter((ev) => {
    if (ev.isAlways === 1) {
      return true;
    }
    return Number(ev.fromDt) < viewEndSec && Number(ev.toDt) > viewStartSec;
  });

  // Deduplicate: keep only the first occurrence of each event per calendar day
  const grouped = new Map<string, Event>();
  filtered.forEach((ev) => {
    const dayKey = DateTime.fromSeconds(ev.isAlways === 1 ? 0 : Number(ev.fromDt), {
      zone: timezone,
    }).toISODate();
    const groupKey = `${ev.eventId}-${dayKey}`;
    if (!grouped.has(groupKey)) {
      grouped.set(groupKey, ev);
    }
  });

  return Array.from(grouped.values());
}
