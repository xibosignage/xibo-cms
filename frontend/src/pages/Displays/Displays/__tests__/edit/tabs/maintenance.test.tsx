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

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildDisplay } from '../../fixtures/display';
import { renderEditModal } from '../helpers/renderEditModal';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next', () => {
  const t = (key: string) => key;
  return {
    useTranslation: () => ({ t, i18n: { changeLanguage: vi.fn() } }),
    Trans: ({ children }: { children: React.ReactNode }) => children,
  };
});

vi.mock('@/services/displaysApi', () => ({
  updateDisplay: vi.fn(),
  fetchDisplayVenues: vi.fn().mockResolvedValue([]),
  fetchDisplayLocales: vi.fn().mockResolvedValue([]),
  fetchDisplays: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/services/displayProfileApi', () => ({
  fetchDisplayProfile: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  fetchDisplayProfileById: vi.fn().mockResolvedValue(null),
}));
vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
  fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
  selectFolder: vi.fn().mockResolvedValue({ success: true }),
}));
vi.mock('@/services/layoutsApi', () => ({
  fetchLayouts: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/services/playerSoftwareApi', () => ({
  fetchPlayerSoftware: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/services/daypartApi', () => ({
  fetchDaypart: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('@/components/ui/forms/SelectFolder', () => ({
  default: ({ selectedId }: { selectedId?: number | null }) => (
    <div data-testid="mock-select-folder" data-folder-id={selectedId ?? ''} />
  ),
}));

// =============================================================================
// Tests
// =============================================================================

describe('Display - edit form: Maintenance tab', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // Use the Global Timeout?
  // ---------------------------------------------------------------------------

  test('alert timeout checkbox reflects the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ alertTimeout: 1 }) });
    await user.click(screen.getByRole('tab', { name: 'Maintenance' }));

    expect(screen.getByRole('checkbox', { name: /use the global timeout/i })).toBeChecked();
  });

  test('alert timeout checkbox can be toggled', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ alertTimeout: 0 }) });
    await user.click(screen.getByRole('tab', { name: 'Maintenance' }));

    const checkbox = screen.getByRole('checkbox', { name: /use the global timeout/i });
    expect(checkbox).not.toBeChecked();
    await user.click(checkbox);
    expect(checkbox).toBeChecked();
  });

  // ---------------------------------------------------------------------------
  // Email Alerts (SelectDropdown with role="combobox")
  // ---------------------------------------------------------------------------

  test('email alerts combobox is present', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Maintenance' }));

    expect(screen.getByRole('combobox', { name: /email alerts/i })).toBeInTheDocument();
  });
});
