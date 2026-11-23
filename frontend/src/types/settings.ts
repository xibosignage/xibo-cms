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

export interface SettingMeta {
  value: string;
  userSee: number;
  userChange: number;
}

export interface SettingsOptionItem {
  id: string;
  value: string;
}

export interface SettingsData {
  settings: Record<string, SettingMeta>;
  options: {
    languages: SettingsOptionItem[];
    timeZones: SettingsOptionItem[];
  };
  relatedEntities: {
    defaultLayout: { layoutId: number; layout: string } | null;
    systemUser: { userId: number; userName: string } | null;
    defaultUserGroup: { groupId: number; group: string } | null;
    defaultTransitionIn: {
      transitionId: number;
      transition: string;
      code: string;
    } | null;
    defaultTransitionOut: {
      transitionId: number;
      transition: string;
      code: string;
    } | null;
  };
  elevateLogUntil: string | null;
  phoneticKey: string;
  // Whether library files are kept on the CMS, which is the only case the CDN address is used.
  hasLocalLibrary: boolean;
}

export type SettingsFormValues = Record<string, string>;
