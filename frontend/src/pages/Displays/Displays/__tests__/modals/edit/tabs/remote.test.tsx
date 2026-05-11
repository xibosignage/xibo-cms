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

import { buildDisplay } from '../../../fixtures/display';
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

describe('Display - edit form: Remote tab', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // TeamViewer Serial
  // ---------------------------------------------------------------------------

  test('TeamViewer serial is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ teamViewerSerial: 'TV-123456' }) });
    await user.click(screen.getByRole('tab', { name: 'Remote' }));

    expect(screen.getByRole('textbox', { name: /teamviewer serial/i })).toHaveValue('TV-123456');
  });

  test('TeamViewer serial is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Remote' }));

    const input = screen.getByRole('textbox', { name: /teamviewer serial/i });
    await user.clear(input);
    await user.type(input, 'TV-NEW-789');

    expect(input).toHaveValue('TV-NEW-789');
  });

  // ---------------------------------------------------------------------------
  // Webkey Serial
  // ---------------------------------------------------------------------------

  test('Webkey serial is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ webkeySerial: 'WK-654321' }) });
    await user.click(screen.getByRole('tab', { name: 'Remote' }));

    expect(screen.getByRole('textbox', { name: /webkey serial/i })).toHaveValue('WK-654321');
  });

  test('Webkey serial is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Remote' }));

    const input = screen.getByRole('textbox', { name: /webkey serial/i });
    await user.clear(input);
    await user.type(input, 'WK-NEW-987');

    expect(input).toHaveValue('WK-NEW-987');
  });
});
