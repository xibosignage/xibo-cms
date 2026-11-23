/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

import { mockDisplay } from './display';

import type {
  BandwidthResponse,
  DisplayManageData,
  ManageDependency,
  ManageLayout,
  ManageMedia,
  ManageWidget,
  ManageWidgetData,
  PlayerFault,
} from '@/types/displayManage';

// -----------------------------------------------------------------------------
// Data behind the Manage Display modal (GET /display/manage/{id}, /display/faults/{id}
// and /stats/data/bandwidth). Factories default to an EMPTY inventory, so every
// test that wants rows must add them — the empty state is never silently assumed.
// -----------------------------------------------------------------------------

export const buildDependency = (overrides: Partial<ManageDependency> = {}): ManageDependency => ({
  path: 'bundle.min.js',
  fileType: 'bundle',
  bytesRequested: 0,
  complete: 1,
  ...overrides,
});

export const buildManageLayout = (overrides: Partial<ManageLayout> = {}): ManageLayout => ({
  rfId: 0,
  displayId: mockDisplay.displayId,
  type: 'L',
  itemId: 1,
  size: 0,
  path: null,
  bytesRequested: 0,
  complete: 1,
  released: 1,
  fileType: null,
  layout: 'Lobby Layout',
  ...overrides,
});

export const buildManageMedia = (overrides: Partial<ManageMedia> = {}): ManageMedia => ({
  rfId: 0,
  displayId: mockDisplay.displayId,
  type: 'M',
  itemId: 1,
  size: 0,
  path: null,
  bytesRequested: 0,
  complete: 1,
  released: 1,
  fileType: null,
  name: 'welcome.png',
  storedAs: '1.png',
  ...overrides,
});

export const buildManageWidget = (overrides: Partial<ManageWidget> = {}): ManageWidget => ({
  rfId: 0,
  displayId: mockDisplay.displayId,
  type: 'W',
  itemId: 1,
  size: 0,
  bytesRequested: 0,
  complete: 1,
  widgetName: 'Clock',
  widgetType: 'clock',
  ...overrides,
});

export const buildWidgetData = (overrides: Partial<ManageWidgetData> = {}): ManageWidgetData => ({
  widgetId: 1,
  widgetName: 'Weather feed',
  widgetType: 'weather',
  bytesRequested: 0,
  ...overrides,
});

export const buildFault = (overrides: Partial<PlayerFault> = {}): PlayerFault => ({
  playerFaultId: 1,
  displayId: mockDisplay.displayId,
  incidentDt: '2026-03-01 09:00:00',
  expires: '2026-03-02 09:00:00',
  code: 0,
  reason: '',
  layoutId: 0,
  regionId: 0,
  scheduleId: 0,
  widgetId: 0,
  mediaId: 0,
  ...overrides,
});

// Server-formatted defaults, as Display::manage() sends them (system format).
export const MANAGE_DEFAULTS = {
  fromDate: '2026-02-22 00:00:00',
  fromDateOneDay: '2026-02-28 00:00:00',
  toDate: '2026-03-01 00:00:00',
};

export const buildManageData = (overrides: Partial<DisplayManageData> = {}): DisplayManageData => ({
  display: mockDisplay,
  timeAgo: '',
  errorSearch: '',
  inventory: { dependencies: [], layouts: [], media: [], widgets: [], widgetData: [] },
  status: { units: 'MB', countComplete: 0, countRemaining: 0, sizeComplete: 0, sizeRemaining: 0 },
  defaults: MANAGE_DEFAULTS,
  ...overrides,
});

export const buildBandwidth = (
  labels: string[],
  data: number[],
  postUnits = 'MiB',
): BandwidthResponse => ({ labels, data, backgroundColor: [], postUnits });

export const EMPTY_BANDWIDTH = buildBandwidth([], [], '');
