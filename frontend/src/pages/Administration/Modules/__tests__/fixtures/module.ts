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

import type { FetchModulesResponse } from '@/services/moduleApi';
import type { Module } from '@/types/module';
import type { User } from '@/types/user';

export const buildModule = (overrides: Partial<Module> = {}): Module => ({
  moduleId: 'core-image',
  name: 'Image',
  description: 'Upload Image files to assign to Layouts',
  type: 'image',
  regionSpecific: 0,
  defaultDuration: 10,
  previewEnabled: 1,
  assignable: 1,
  enabled: 1,
  isError: false,
  errors: [],
  settings: [],
  allowPreview: 1,
  ...overrides,
});

export const mockModule = buildModule();

export const SINGLE_MODULE: FetchModulesResponse = {
  rows: [mockModule],
  totalCount: 1,
};

export const EMPTY_MODULE_TABLE: FetchModulesResponse = {
  rows: [],
  totalCount: 0,
};

// Two-row fixture. The second row is region-specific so it also offers Clear Cache.
export const MULTIPLE_MODULES: FetchModulesResponse = {
  rows: [
    buildModule({ moduleId: 'core-image', name: 'Image' }),
    buildModule({ moduleId: 'core-video', name: 'Video', regionSpecific: 1 }),
  ],
  totalCount: 2,
};

// A region-specific module — the only kind that offers "Clear Cache".
export const REGION_SPECIFIC_MODULE = buildModule({
  moduleId: 'core-clock',
  name: 'Clock',
  regionSpecific: 1,
});

// A module whose settings schema exercises every DynamicSettingField branch
// (checkbox / dropdown / number / text).
export const MODULE_WITH_SETTINGS = buildModule({
  moduleId: 'core-currencies',
  name: 'Currencies',
  settings: [
    { id: 'autoRefresh', type: 'checkbox', title: 'Auto Refresh', value: 1 },
    {
      id: 'provider',
      type: 'dropdown',
      title: 'Data Provider',
      value: 'yahoo',
      options: [
        { name: 'yahoo', title: 'Yahoo' },
        { name: 'google', title: 'Google' },
      ],
    },
    { id: 'maxItems', type: 'number', title: 'Max Items', value: 5 },
    { id: 'apiKey', type: 'text', title: 'API Key', value: 'abc123' },
  ],
});

// allowPreview === 0 disables the "Preview Enabled?" checkbox in the configure modal.
export const PREVIEW_DISABLED_MODULE = buildModule({
  moduleId: 'core-text',
  name: 'Text',
  allowPreview: 0,
  previewEnabled: 0,
});

export const mockUser: User = {
  userId: 1,
  userName: 'TestUser',
  userTypeId: 1,
  groupId: 1,
  // 'module.view' is required by useFilteredTabs('administration') to render the Modules tab.
  features: { 'module.view': true },
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

export const queryKeys = {
  // Mirrors the preference key useTableState builds internally for 'module_page'.
  modulePage: ['userPref', 'module_page'] as const,
};
