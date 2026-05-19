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

import { fetchCampaigns } from '@/services/campaignApi';
import { fetchCommands } from '@/services/commandApi';
import { fetchDataset } from '@/services/datasetApi';
import { fetchDaypart } from '@/services/daypartApi';
import { createEvent, fetchEvent, fetchEventById, updateEvent } from '@/services/eventApi';
import { fetchLayoutCodes, fetchLayouts } from '@/services/layoutsApi';
import { fetchMedia } from '@/services/mediaApi';
import { fetchPlaylist } from '@/services/playlistApi';
import { fetchResolution } from '@/services/resolutionApi';
import { fetchSyncGroupDisplays, fetchSyncGroups } from '@/services/syncGroupApi';
import type { Daypart } from '@/types/daypart';
import type { Event } from '@/types/event';

type DaypartRow = Pick<Daypart, 'dayPartId' | 'name' | 'isAlways' | 'isCustom'>;

export const ALWAYS_ONLY: DaypartRow[] = [
  { dayPartId: 1, name: 'Always', isAlways: 1, isCustom: 0 },
];

export const ALWAYS_AND_CUSTOM: DaypartRow[] = [
  { dayPartId: 1, name: 'Always', isAlways: 1, isCustom: 0 },
  { dayPartId: 2, name: 'Custom', isAlways: 0, isCustom: 1 },
  { dayPartId: 3, name: 'Morning Slot', isAlways: 0, isCustom: 0 },
];

const EMPTY_EVENT = {} as unknown as Event;

// Campaign
export const setupCampaignMocks = (): void => {
  vi.mocked(fetchCampaigns).mockResolvedValue({ rows: [], totalCount: 0 });
};

// Command
export const setupCommandMocks = (): void => {
  vi.mocked(fetchCommands).mockResolvedValue({ rows: [], totalCount: 0 });
};

// Dataset
export const setupDatasetMocks = (): void => {
  vi.mocked(fetchDataset).mockResolvedValue({ rows: [], totalCount: 0 });
};

// Daypart
export const setupDaypartMocks = (): void => {
  vi.mocked(fetchDaypart).mockResolvedValue({ rows: [], totalCount: 0 });
};

export const mockDaypartRows = (rows: DaypartRow[]): void => {
  vi.mocked(fetchDaypart).mockResolvedValue({
    rows: rows as unknown as Daypart[],
    totalCount: rows.length,
  });
};

// Event
export const setupEventMocks = (): void => {
  vi.mocked(createEvent).mockResolvedValue(EMPTY_EVENT);
  vi.mocked(updateEvent).mockResolvedValue(EMPTY_EVENT);
  vi.mocked(fetchEventById).mockResolvedValue(EMPTY_EVENT);
  vi.mocked(fetchEvent).mockResolvedValue({ rows: [], totalCount: 0 });
};

export const mockFetchEventById = (event: Event): void => {
  vi.mocked(fetchEventById).mockResolvedValue(event);
};

// Layouts
export const setupLayoutsMocks = (): void => {
  vi.mocked(fetchLayouts).mockResolvedValue({ rows: [], totalCount: 0 });
  vi.mocked(fetchLayoutCodes).mockResolvedValue([]);
};

// Media
export const setupMediaMocks = (): void => {
  vi.mocked(fetchMedia).mockResolvedValue({ rows: [], totalCount: 0 });
};

// Playlist
export const setupPlaylistMocks = (): void => {
  vi.mocked(fetchPlaylist).mockResolvedValue({ rows: [], totalCount: 0 });
};

// Resolution
export const setupResolutionMocks = (): void => {
  vi.mocked(fetchResolution).mockResolvedValue({ rows: [], totalCount: 0 });
};

// SyncGroup
export const setupSyncGroupMocks = (): void => {
  vi.mocked(fetchSyncGroups).mockResolvedValue({ rows: [], totalCount: 0 });
  vi.mocked(fetchSyncGroupDisplays).mockResolvedValue([]);
};
