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
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockSettings } from './fixtures/settings';
import { renderSettingsPage } from './helpers/renderSettingsPage';
import { mockFetchSettings } from './mocks/settingsApi';

import { fetchSettings } from '@/services/settingsApi';
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

describe('Settings page - render', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchSettings(mockSettings);
  });

  test('renders all 10 tab buttons', async () => {
    renderSettingsPage();

    const tabLabels = [
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
    ];

    for (const label of tabLabels) {
      await screen.findByRole('button', { name: label });
    }
  });

  test('shows "Loading..." while settings are loading', async () => {
    let resolve!: (v: typeof mockSettings) => void;
    vi.mocked(fetchSettings).mockReturnValueOnce(
      new Promise<typeof mockSettings>((r) => {
        resolve = r;
      }),
    );

    renderSettingsPage();

    await screen.findByText('Loading...');

    resolve(mockSettings);
    await waitFor(() => {
      expect(screen.queryByText('Loading...')).not.toBeInTheDocument();
    });
  });

  test('shows the "Configuration" heading by default', async () => {
    renderSettingsPage();

    await screen.findByRole('heading', { name: 'Configuration' });
  });

  test('"Configuration" tab button has aria-current="page" by default', async () => {
    renderSettingsPage();
    await screen.findByRole('heading', { name: 'Configuration' });

    expect(screen.getByRole('button', { name: 'Configuration' })).toHaveAttribute(
      'aria-current',
      'page',
    );
  });

  test('Save and Cancel buttons are present', async () => {
    renderSettingsPage();
    await screen.findByRole('heading', { name: 'Configuration' });

    expect(screen.getByRole('button', { name: /^save$/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^cancel$/i })).toBeInTheDocument();
  });

  test('Save button is disabled when the form is clean', async () => {
    renderSettingsPage();
    await screen.findByRole('heading', { name: 'Configuration' });

    expect(screen.getByRole('button', { name: /^save$/i })).toBeDisabled();
  });

  test('shows an error alert when fetchSettings fails', async () => {
    vi.mocked(fetchSettings).mockRejectedValue(new Error('Server Error'));
    renderSettingsPage();

    await screen.findByRole('alert');
  });
});
