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

import type { Display } from '@/types/display';

export interface ManageDependency {
  path: string;
  fileType: string;
  bytesRequested: number;
  complete: number;
}

export interface ManageLayout {
  rfId: number;
  displayId: number;
  type: string;
  itemId: number;
  size: number;
  path: string | null;
  bytesRequested: number;
  complete: number;
  released: number;
  fileType: string | null;
  layout: string;
}

export interface ManageMedia {
  rfId: number;
  displayId: number;
  type: string;
  itemId: number;
  size: number;
  path: string | null;
  bytesRequested: number;
  complete: number;
  released: number;
  fileType: string | null;
  name: string;
  storedAs: string;
}

export interface ManageWidget {
  rfId: number;
  displayId: number;
  type: string;
  itemId: number;
  size: number;
  bytesRequested: number;
  complete: number;
  widgetName: string | null;
  widgetType: string;
}

export interface ManageWidgetData {
  widgetId: number;
  widgetName: string | null;
  widgetType: string;
  bytesRequested: number;
}

export interface ManageInventory {
  dependencies: ManageDependency[];
  layouts: ManageLayout[];
  media: ManageMedia[];
  widgets: ManageWidget[];
  widgetData: ManageWidgetData[];
}

export interface ManageStatus {
  units: string;
  countComplete: number;
  countRemaining: number;
  sizeComplete: number;
  sizeRemaining: number;
}

export interface ManageDefaults {
  fromDate: string;
  fromDateOneDay: string;
  toDate: string;
}

export interface DisplayManageData {
  display: Display;
  timeAgo: string;
  errorSearch: string;
  inventory: ManageInventory;
  status: ManageStatus;
  defaults: ManageDefaults;
}

export interface PlayerFault {
  playerFaultId: number;
  displayId: number;
  incidentDt: string;
  expires: string;
  code: number;
  reason: string;
  layoutId: number;
  regionId: number;
  scheduleId: number;
  widgetId: number;
  mediaId: number;
}

export interface BandwidthResponse {
  labels: string[];
  data: number[];
  backgroundColor: string[];
  postUnits: string;
}

/**
 * Display event types relevant to connectivity, as mapped by
 * DisplayEvent::getEventIdFromString.
 *
 * DisplayUpDown rows are raised by the CMS itself when a display's lastAccessed goes stale.
 */
export const DISPLAY_EVENT_TYPE = {
  displayUpDown: 1,
} as const;

/** One screenshot in a display's history, newest first from the API. */
export interface DisplayScreenshot {
  displayScreenshotId: number;
  displayId: number;
  /** Unix seconds. */
  createdDt: number;
  storedAs: string;
  /** The player's status window as it stood when the screenshot arrived, if it had reported one. */
  status: Record<string, unknown> | null;
  /** Built by the API, so the client does not need to know the route. */
  url: string;
}

/** One row from /stats/timeDisconnected. Dates are 'YYYY-MM-DD HH:mm:ss' in CMS time. */
export interface DisconnectionEvent {
  displayId: number;
  display: string;
  isFinished: boolean;
  start: string;
  end: string | null;
  duration: number;
}
