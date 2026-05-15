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

import { vi } from 'vitest';

import { createEvent, updateEvent, fetchEventById, fetchEvent } from '@/services/eventApi';
import type { Event } from '@/types/event';

const EMPTY_EVENT = {} as unknown as Event;

export const setupEventApiMocks = (): void => {
  vi.mocked(createEvent).mockResolvedValue(EMPTY_EVENT);
  vi.mocked(updateEvent).mockResolvedValue(EMPTY_EVENT);
  vi.mocked(fetchEventById).mockResolvedValue(EMPTY_EVENT);
  vi.mocked(fetchEvent).mockResolvedValue({ rows: [], totalCount: 0 });
};

export const mockFetchEventById = (event: Event): void => {
  vi.mocked(fetchEventById).mockResolvedValue(event);
};
