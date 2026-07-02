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

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockSettings } from './fixtures/settings';
import { renderSettingsPage } from './helpers/renderSettingsPage';
import { mockFetchSettings } from './mocks/settingsApi';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/settingsApi');

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Settings', path: '/administration/settings' }]),
}));

vi.mock('@/lib/i18n', () => ({
  reloadAppLanguage: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn() },
}));

vi.mock('@/services/transitionApi', () => ({
  fetchTransitions: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));

vi.mock('@/services/layoutsApi', () => ({
  fetchLayouts: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));

vi.mock('@/services/userApi', () => ({
  fetchUsers: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/services/userGroupApi', () => ({
  fetchUserGroups: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));

vi.mock('@/components/ui/forms/SelectFolder', () => ({
  default: () => null,
}));

vi.mock('@/components/ui/forms/DatePickerInput', () => ({
  default: ({ label }: { label: string }) => <div>{label}</div>,
}));

// =============================================================================
// Tests
// =============================================================================

describe('Settings page - tabs', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchSettings(mockSettings);
  });

  const TABS = [
    'Configuration',
    'Defaults',
    'Displays',
    'General',
    'Maintenance',
    'Network',
    'Regional',
    'Sharing',
    'Troubleshooting',
    'Users',
  ] as const;

  test('Configuration tab is active by default', async () => {
    renderSettingsPage();
    await screen.findByRole('heading', { name: 'Configuration' });

    expect(screen.getByRole('button', { name: 'Configuration' })).toHaveAttribute(
      'aria-current',
      'page',
    );
    for (const tab of TABS.filter((t) => t !== 'Configuration')) {
      expect(screen.getByRole('button', { name: tab })).not.toHaveAttribute('aria-current', 'page');
    }
  });

  test.each(TABS.slice(1))('clicking the %s tab shows its heading', async (tabName) => {
    const user = userEvent.setup();
    renderSettingsPage();
    await screen.findByRole('heading', { name: 'Configuration' });

    await user.click(screen.getByRole('button', { name: tabName }));

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: tabName })).toBeInTheDocument();
    });
  });

  test.each(TABS.slice(1))(
    'clicking %s sets aria-current="page" on that tab button',
    async (tabName) => {
      const user = userEvent.setup();
      renderSettingsPage();
      await screen.findByRole('heading', { name: 'Configuration' });

      await user.click(screen.getByRole('button', { name: tabName }));

      await waitFor(() => {
        expect(screen.getByRole('button', { name: tabName })).toHaveAttribute(
          'aria-current',
          'page',
        );
        expect(screen.getByRole('button', { name: 'Configuration' })).not.toHaveAttribute(
          'aria-current',
          'page',
        );
      });
    },
  );
});
