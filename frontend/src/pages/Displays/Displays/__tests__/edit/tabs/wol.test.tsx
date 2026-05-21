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

describe('Display - edit form: Wake on LAN tab', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // Enable Wake on LAN
  // ---------------------------------------------------------------------------

  test('enable wake on LAN checkbox reflects the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ wakeOnLanEnabled: 1 }) });
    await user.click(screen.getByRole('tab', { name: 'Wake on LAN' }));

    expect(screen.getByRole('checkbox', { name: /enable wake on lan/i })).toBeChecked();
  });

  test('enable wake on LAN checkbox can be toggled', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ wakeOnLanEnabled: 0 }) });
    await user.click(screen.getByRole('tab', { name: 'Wake on LAN' }));

    const checkbox = screen.getByRole('checkbox', { name: /enable wake on lan/i });
    expect(checkbox).not.toBeChecked();
    await user.click(checkbox);
    expect(checkbox).toBeChecked();
  });

  // ---------------------------------------------------------------------------
  // Broadcast Address
  // ---------------------------------------------------------------------------

  test('broadcast address is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ broadCastAddress: '192.168.1.255' }) });
    await user.click(screen.getByRole('tab', { name: 'Wake on LAN' }));

    expect(screen.getByRole('textbox', { name: /broadcast address/i })).toHaveValue(
      '192.168.1.255',
    );
  });

  test('broadcast address is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Wake on LAN' }));

    const input = screen.getByRole('textbox', { name: /broadcast address/i });
    await user.clear(input);
    await user.type(input, '10.0.0.255');

    expect(input).toHaveValue('10.0.0.255');
  });

  // ---------------------------------------------------------------------------
  // Wake on LAN SecureOn
  // ---------------------------------------------------------------------------

  test('wake on LAN SecureOn is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ secureOn: 'AA-BB-CC-DD-EE-FF' }) });
    await user.click(screen.getByRole('tab', { name: 'Wake on LAN' }));

    expect(screen.getByRole('textbox', { name: /secureon/i })).toHaveValue('AA-BB-CC-DD-EE-FF');
  });

  test('wake on LAN SecureOn is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Wake on LAN' }));

    const input = screen.getByRole('textbox', { name: /secureon/i });
    await user.clear(input);
    await user.type(input, '11-22-33-44-55-66');

    expect(input).toHaveValue('11-22-33-44-55-66');
  });

  // ---------------------------------------------------------------------------
  // Wake on LAN Time
  // ---------------------------------------------------------------------------

  test('wake on LAN time is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ wakeOnLanTime: '07:00' }) });
    await user.click(screen.getByRole('tab', { name: 'Wake on LAN' }));

    expect(screen.getByRole('textbox', { name: /wake on lan time/i })).toHaveValue('07:00');
  });

  test('wake on LAN time is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Wake on LAN' }));

    const input = screen.getByRole('textbox', { name: /wake on lan time/i });
    await user.clear(input);
    await user.type(input, '08:30');

    expect(input).toHaveValue('08:30');
  });

  // ---------------------------------------------------------------------------
  // Wake on LAN CIDR
  // ---------------------------------------------------------------------------

  test('wake on LAN CIDR is pre-populated with the existing value', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ cidr: '24' }) });
    await user.click(screen.getByRole('tab', { name: 'Wake on LAN' }));

    expect(screen.getByRole('textbox', { name: /wake on lan cidr/i })).toHaveValue('24');
  });

  test('wake on LAN CIDR is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();
    await user.click(screen.getByRole('tab', { name: 'Wake on LAN' }));

    const input = screen.getByRole('textbox', { name: /wake on lan cidr/i });
    await user.clear(input);
    await user.type(input, '16');

    expect(input).toHaveValue('16');
  });
});
