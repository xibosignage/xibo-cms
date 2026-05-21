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

import { buildDisplay, mockDisplay } from '../../../fixtures/display';
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

describe('Display - edit form: General tab', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // Display name
  // ---------------------------------------------------------------------------

  test('display name is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'General' }));

    expect(screen.getByRole('textbox', { name: /^Display$/i })).toHaveValue(mockDisplay.display);
  });

  test('display name is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'General' }));

    const input = screen.getByRole('textbox', { name: /^Display$/i });
    await user.clear(input);
    await user.type(input, 'Updated Display');

    expect(input).toHaveValue('Updated Display');
  });

  // ---------------------------------------------------------------------------
  // Hardware Key
  // ---------------------------------------------------------------------------

  test('hardware key is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ license: 'HW-KEY-001' }) });
    await user.click(screen.getByRole('tab', { name: 'General' }));

    expect(screen.getByRole('textbox', { name: /hardware key/i })).toHaveValue('HW-KEY-001');
  });

  test('hardware key is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'General' }));

    const input = screen.getByRole('textbox', { name: /hardware key/i });
    await user.clear(input);
    await user.type(input, 'NEW-HW-KEY');

    expect(input).toHaveValue('NEW-HW-KEY');
  });

  // ---------------------------------------------------------------------------
  // Description
  // ---------------------------------------------------------------------------

  test('description is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ description: 'A test description' }) });
    await user.click(screen.getByRole('tab', { name: 'General' }));

    expect(screen.getByRole('textbox', { name: /description/i })).toHaveValue('A test description');
  });

  test('description is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'General' }));

    const input = screen.getByRole('textbox', { name: /description/i });
    await user.clear(input);
    await user.type(input, 'Updated description');

    expect(input).toHaveValue('Updated description');
  });

  // ---------------------------------------------------------------------------
  // Tags
  // ---------------------------------------------------------------------------

  test('tags input is present and accepts input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'General' }));

    const tagsInput = screen.getByRole('textbox', { name: /^Tags$/i });
    await user.type(tagsInput, 'my-tag');
    expect(tagsInput).toHaveValue('my-tag');
  });

  // ---------------------------------------------------------------------------
  // Authorise display (Switch — role="switch")
  // ---------------------------------------------------------------------------

  test('authorise display switch is present', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'General' }));

    expect(screen.getByRole('switch', { name: /authorise/i })).not.toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Default Layout (SelectDropdown)
  // ---------------------------------------------------------------------------

  test('default layout combobox is present', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'General' }));

    expect(screen.getByRole('combobox', { name: /default layout/i })).not.toBeDisabled();
  });
});
