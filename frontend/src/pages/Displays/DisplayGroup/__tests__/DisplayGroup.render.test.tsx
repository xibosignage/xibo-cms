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

import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockDisplayGroup, SINGLE_DISPLAY_GROUP } from './fixtures/displayGroup';
import { renderDisplayGroupPage } from './helpers/renderDisplayGroupPage';
import { mockFetchDisplayGroups } from './mocks/displayGroupApi';

import { testQueryClient } from '@/setupTests';
import { findRow } from '@/testUtils/rowActions';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/displayGroupApi');
vi.mock('@/services/displaysApi', () => ({
  fetchDisplays: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  sendCommand: vi.fn(),
  triggerWebhook: vi.fn(),
}));
vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
  fetchUsers: vi.fn().mockResolvedValue([]),
}));
vi.mock('@/services/folderApi');
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/FolderActionModals', () => ({ default: () => null }));

// =============================================================================
// Tests
// =============================================================================

describe('Display Groups page - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchDisplayGroups(SINGLE_DISPLAY_GROUP);
  });

  test(
    'the table shows the display group row once data has loaded',
    { tags: ['smoke'] },
    async () => {
      renderDisplayGroupPage();

      expect(await findRow(mockDisplayGroup.displayGroup)).toHaveTextContent(
        mockDisplayGroup.description,
      );
    },
  );
});
