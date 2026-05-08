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

import type { FetchDisplaysResponse } from '@/services/displaysApi';
import type { Display } from '@/types/display';
import type { User } from '@/types/user';

// -----------------------------------------------------------------------------
// Factory that produces a Display with safe minimal defaults.
// Only fields used in assertions carry meaningful values — everything else
// is set to the zero value for its type so the component renders without errors.
//
// displayId: 1     — asserted in delete tests
// display: 'Test Display' — asserted in render and modal tests
// displayGroupId: 1 — required by action hooks
// folderId / permissionsFolderId: 1 — required by SelectFolder stub
// -----------------------------------------------------------------------------
export const buildDisplay = (overrides: Partial<Display> = {}): Display => ({
  displayId: 1,
  display: 'Test Display',
  description: null,
  displayTypeId: null,
  displayType: null,
  venueId: null,
  address: null,
  isMobile: 0,
  languages: null,
  screenSize: null,
  isOutdoor: 0,
  customId: null,
  costPerPlay: null,
  impressionsPerPlay: null,
  ref1: null,
  ref2: null,
  ref3: null,
  ref4: null,
  ref5: null,
  auditingUntil: null,
  defaultLayoutId: 1,
  license: '',
  licensed: 1,
  loggedIn: 0,
  lastAccessed: null,
  incSchedule: 0,
  emailAlert: 0,
  alertTimeout: 0,
  clientAddress: null,
  mediaInventoryStatus: 0,
  macAddress: null,
  lastChanged: null,
  numberOfMacAddressChanges: 0,
  lastWakeOnLanCommandSent: null,
  wakeOnLanEnabled: 0,
  wakeOnLanTime: null,
  broadCastAddress: null,
  secureOn: null,
  cidr: null,
  latitude: null,
  longitude: null,
  clientType: null,
  clientVersion: null,
  clientCode: null,
  displayProfileId: null,
  displayProfile: null,
  currentLayoutId: null,
  screenShotRequested: 0,
  storageAvailableSpace: null,
  storageTotalSpace: null,
  displayGroupId: 1,
  currentLayout: null,
  defaultLayout: null,
  displayGroups: [],
  xmrChannel: null,
  xmrPubKey: null,
  lastCommandSuccess: 0,
  deviceName: null,
  timeZone: null,
  tags: [],
  overrideConfig: [],
  bandwidthLimit: null,
  newCmsAddress: null,
  newCmsKey: null,
  orientation: null,
  resolution: null,
  commercialLicence: 0,
  teamViewerSerial: null,
  webkeySerial: null,
  groupsWithPermissions: null,
  isPlayerSupported: null,
  createdDt: null,
  modifiedDt: null,
  folderId: 1,
  permissionsFolderId: 1,
  countFaults: 0,
  lanIpAddress: null,
  syncGroupId: null,
  osVersion: null,
  osSdk: null,
  manufacturer: null,
  brand: null,
  model: null,
  ...overrides,
});

export const mockDisplay = buildDisplay();

export const SINGLE_DISPLAY: FetchDisplaysResponse = {
  rows: [mockDisplay],
  totalCount: 1,
};

export const EMPTY_DISPLAY_TABLE: FetchDisplaysResponse = {
  rows: [],
  totalCount: 0,
};

// The default logged-in user for display tests.
export const mockUser: User = {
  userId: 1,
  userName: 'TestUser',
  userTypeId: 1,
  groupId: 1,
  features: { 'folder.view': true },
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

// Query keys that mirror what useTableState builds internally.
export const queryKeys = {
  displaysPage: ['userPref', 'displays_page'] as const,
};
