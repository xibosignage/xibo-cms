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

import { render } from '@testing-library/react';

import { EventCalendar } from '../../../components/EventCalendar';

import { CALENDAR_DATE } from './buildCalendarEvents';

import type { Event } from '@/types/event';

interface RenderCalendarOptions {
  date?: Date;
  events?: Event[];
  isLoading?: boolean;
  onEditEvent?: (event: Event) => void;
  onDeleteEvent?: (event: Event) => void;
}

export function renderCalendar(options: RenderCalendarOptions = {}) {
  const { date = CALENDAR_DATE, events = [], isLoading, onEditEvent, onDeleteEvent } = options;

  return render(
    <EventCalendar
      date={date}
      events={events}
      isLoading={isLoading}
      onEditEvent={onEditEvent}
      onDeleteEvent={onDeleteEvent}
    />,
  );
}
