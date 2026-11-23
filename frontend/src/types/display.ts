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

import type { DisplayGroup } from '@/types/displayGroup';
import type { Tag } from '@/types/tag';

export type { DisplayGroup };

export interface DisplayCommandTarget {
  displayGroupId: number;
  display: string;
  clientType?: string | null;
}

export interface DisplayPermissions {
  view?: number;
  edit?: number;
  delete?: number;
  modifyPermissions?: number;
}

export interface Display {
  displayId: number;
  display: string;
  description: string | null;
  displayTypeId: number | null;
  displayType: string | null;
  venueId: number | null;
  address: string | null;
  isMobile: number;
  languages: string | null;
  screenSize: number | null;
  isOutdoor: number;
  customId: string | null;
  costPerPlay: number | null;
  impressionsPerPlay: number | null;
  ref1: string | null;
  ref2: string | null;
  ref3: string | null;
  ref4: string | null;
  ref5: string | null;
  auditingUntil: number | null;
  defaultLayoutId: number;
  license: string;
  licensed: number;
  loggedIn: number;
  lastAccessed: number | null;
  incSchedule: number;
  emailAlert: number;
  alertTimeout: number;
  clientAddress: string | null;
  mediaInventoryStatus: number;
  macAddress: string | null;
  lastChanged: number | null;
  numberOfMacAddressChanges: number;
  lastWakeOnLanCommandSent: number | null;
  wakeOnLanEnabled: number;
  wakeOnLanTime: string | null;
  broadCastAddress: string | null;
  secureOn: string | null;
  cidr: string | null;
  latitude: number | null;
  longitude: number | null;
  clientType: string | null;
  displayProfileType?: string | null;
  clientVersion: string | null;
  clientCode: number | null;
  displayProfileId: number | null;
  displayProfile: string | null;
  currentLayoutId: number | null;
  screenShotRequested: number;
  thumbnail: string;
  storageAvailableSpace: number | null;
  storageTotalSpace: number | null;
  displayGroupId: number;
  currentLayout: string | null;
  defaultLayout: string | null;
  displayGroups: DisplayGroup[];
  xmrChannel: string | null;
  xmrPubKey: string | null;
  lastCommandSuccess: number;
  deviceName: string | null;
  timeZone: string | null;
  tags: Tag[];
  overrideConfig: Record<string, unknown>[];
  bandwidthLimit: number | null;
  newCmsAddress: string | null;
  newCmsKey: string | null;
  orientation: string | null;
  resolution: string | null;
  commercialLicence: number;
  /** Pro: why the licensing server last refused the display, when it has not reported a valid licence. */
  licenceRefusal?: string;
  /** Pro: licenceRefusal for the user. */
  licenceRefusalDescription?: string;
  teamViewerSerial: string | null;
  webkeySerial: string | null;
  groupsWithPermissions: string | null;
  groupsWithPermissionsList?: string[] | null;
  isPlayerSupported: number | null;
  createdDt: string | null;
  modifiedDt: string | null;
  folderId: number | null;
  permissionsFolderId: number | null;
  countFaults: number;
  lanIpAddress: string | null;
  syncGroupId: number | null;
  osVersion: string | null;
  osSdk: string | null;
  manufacturer: string | null;
  brand: string | null;
  model: string | null;
  userPermissions?: DisplayPermissions;
}
