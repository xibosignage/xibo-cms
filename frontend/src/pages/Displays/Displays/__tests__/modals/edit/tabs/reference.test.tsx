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

// Enhanced t: supports {{key}} interpolation so ref field labels resolve to
// "Reference 1", "Reference 2", etc. rather than the raw key "Reference {{n}}".
vi.mock('react-i18next', () => {
  const t = (key: string, options?: Record<string, unknown>) => {
    if (!options) return key;
    return key.replace(/\{\{(\w+)\}\}/g, (_, k) => String(options[k] ?? `{{${k}}}`));
  };
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

describe('Display - edit form: Reference tab', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // Reference 1
  // ---------------------------------------------------------------------------

  test('reference 1 is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ ref1: 'REF-001' }) });
    await user.click(screen.getByRole('tab', { name: 'Reference' }));

    expect(screen.getByRole('textbox', { name: /^Reference 1$/i })).toHaveValue('REF-001');
  });

  test('reference 1 is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Reference' }));

    const input = screen.getByRole('textbox', { name: /^Reference 1$/i });
    await user.clear(input);
    await user.type(input, 'new-ref-1');

    expect(input).toHaveValue('new-ref-1');
  });

  // ---------------------------------------------------------------------------
  // Reference 2
  // ---------------------------------------------------------------------------

  test('reference 2 is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ ref2: 'REF-002' }) });
    await user.click(screen.getByRole('tab', { name: 'Reference' }));

    expect(screen.getByRole('textbox', { name: /^Reference 2$/i })).toHaveValue('REF-002');
  });

  test('reference 2 is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Reference' }));

    const input = screen.getByRole('textbox', { name: /^Reference 2$/i });
    await user.clear(input);
    await user.type(input, 'new-ref-2');

    expect(input).toHaveValue('new-ref-2');
  });

  // ---------------------------------------------------------------------------
  // Reference 3
  // ---------------------------------------------------------------------------

  test('reference 3 is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ ref3: 'REF-003' }) });
    await user.click(screen.getByRole('tab', { name: 'Reference' }));

    expect(screen.getByRole('textbox', { name: /^Reference 3$/i })).toHaveValue('REF-003');
  });

  test('reference 3 is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Reference' }));

    const input = screen.getByRole('textbox', { name: /^Reference 3$/i });
    await user.clear(input);
    await user.type(input, 'new-ref-3');

    expect(input).toHaveValue('new-ref-3');
  });

  // ---------------------------------------------------------------------------
  // Reference 4
  // ---------------------------------------------------------------------------

  test('reference 4 is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ ref4: 'REF-004' }) });
    await user.click(screen.getByRole('tab', { name: 'Reference' }));

    expect(screen.getByRole('textbox', { name: /^Reference 4$/i })).toHaveValue('REF-004');
  });

  test('reference 4 is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Reference' }));

    const input = screen.getByRole('textbox', { name: /^Reference 4$/i });
    await user.clear(input);
    await user.type(input, 'new-ref-4');

    expect(input).toHaveValue('new-ref-4');
  });

  // ---------------------------------------------------------------------------
  // Reference 5
  // ---------------------------------------------------------------------------

  test('reference 5 is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ ref5: 'REF-005' }) });
    await user.click(screen.getByRole('tab', { name: 'Reference' }));

    expect(screen.getByRole('textbox', { name: /^Reference 5$/i })).toHaveValue('REF-005');
  });

  test('reference 5 is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Reference' }));

    const input = screen.getByRole('textbox', { name: /^Reference 5$/i });
    await user.clear(input);
    await user.type(input, 'new-ref-5');

    expect(input).toHaveValue('new-ref-5');
  });

  // ---------------------------------------------------------------------------
  // Custom ID
  // ---------------------------------------------------------------------------

  test('custom ID is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ customId: 'CUSTOM-001' }) });
    await user.click(screen.getByRole('tab', { name: 'Reference' }));

    expect(screen.getByRole('textbox', { name: /custom id/i })).toHaveValue('CUSTOM-001');
  });

  test('custom ID is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Reference' }));

    const input = screen.getByRole('textbox', { name: /custom id/i });
    await user.clear(input);
    await user.type(input, 'NEW-CUSTOM');

    expect(input).toHaveValue('NEW-CUSTOM');
  });
});
