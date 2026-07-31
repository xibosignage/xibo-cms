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
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildSettings, mockSettingsWithEditableField } from './fixtures/settings';
import { renderSettingsPage } from './helpers/renderSettingsPage';
import { mockFetchSettings, mockUpdateSettings } from './mocks/settingsApi';

import { notify } from '@/components/ui/Notification';
import { reloadAppLanguage } from '@/lib/i18n';
import { updateSettings } from '@/services/settingsApi';
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
// Helpers
// =============================================================================

// Navigate to the General tab (which renders the HELP_BASE field from mockSettingsWithEditableField)
const goToGeneralTab = async (user: UserEvent) => {
  await user.click(screen.getByRole('button', { name: 'General' }));
  await screen.findByRole('heading', { name: 'General' });
};

// =============================================================================
// Tests
// =============================================================================

describe('Settings page - save', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchSettings(mockSettingsWithEditableField);
  });

  test('Save is disabled until a field is changed', async () => {
    const user = userEvent.setup();
    renderSettingsPage();
    await screen.findByRole('heading', { name: 'Configuration' });

    expect(screen.getByRole('button', { name: /^save$/i })).toBeDisabled();

    await goToGeneralTab(user);
    const helpInput = screen.getByRole('textbox', { name: /location of the manual/i });
    await user.type(helpInput, '!');

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /^save$/i })).toBeEnabled();
    });
  });

  test('Save calls updateSettings with formValues', async () => {
    const user = userEvent.setup();
    mockUpdateSettings();
    renderSettingsPage();
    await screen.findByRole('heading', { name: 'Configuration' });

    await goToGeneralTab(user);
    const helpInput = screen.getByRole('textbox', { name: /location of the manual/i });
    await user.clear(helpInput);
    await user.type(helpInput, 'https://new-help.example.com');

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => {
      expect(updateSettings).toHaveBeenCalledWith(
        expect.objectContaining({ HELP_BASE: 'https://new-help.example.com' }),
      );
    });
  });

  test('shows a success notification after a successful save', async () => {
    const user = userEvent.setup();
    mockUpdateSettings();
    renderSettingsPage();
    await screen.findByRole('heading', { name: 'Configuration' });

    await goToGeneralTab(user);
    await user.type(screen.getByRole('textbox', { name: /location of the manual/i }), '!');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => {
      expect(vi.mocked(notify.success)).toHaveBeenCalledWith('Settings Updated');
    });
  });

  test('calls reloadAppLanguage after a successful save', async () => {
    const user = userEvent.setup();
    mockUpdateSettings();
    renderSettingsPage();
    await screen.findByRole('heading', { name: 'Configuration' });

    await goToGeneralTab(user);
    await user.type(screen.getByRole('textbox', { name: /location of the manual/i }), '!');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => {
      expect(reloadAppLanguage).toHaveBeenCalledOnce();
    });
  });

  test('shows "Saving..." while the request is in progress', async () => {
    const user = userEvent.setup();
    let resolveUpdate!: () => void;
    vi.mocked(updateSettings).mockReturnValueOnce(
      new Promise<void>((r) => {
        resolveUpdate = r;
      }),
    );
    renderSettingsPage();
    await screen.findByRole('heading', { name: 'Configuration' });

    await goToGeneralTab(user);
    await user.type(screen.getByRole('textbox', { name: /location of the manual/i }), '!');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /saving/i })).toBeDisabled();
    });

    resolveUpdate();
  });

  test('shows a save error alert when updateSettings throws', async () => {
    const user = userEvent.setup();
    vi.mocked(updateSettings).mockRejectedValue(new Error('Network Error'));
    renderSettingsPage();
    await screen.findByRole('heading', { name: 'Configuration' });

    await goToGeneralTab(user);
    await user.type(screen.getByRole('textbox', { name: /location of the manual/i }), '!');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Network Error');
  });

  test('Cancel reverts the form and disables the Save button', async () => {
    const user = userEvent.setup();
    renderSettingsPage();
    await screen.findByRole('heading', { name: 'Configuration' });

    await goToGeneralTab(user);
    const helpInput = screen.getByRole('textbox', {
      name: /location of the manual/i,
    }) as HTMLInputElement;
    const originalValue = helpInput.value;
    await user.clear(helpInput);
    await user.type(helpInput, 'https://changed.example.com');

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /^save$/i })).toBeEnabled();
    });

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    await waitFor(() => {
      expect(screen.getByRole('textbox', { name: /location of the manual/i })).toHaveValue(
        originalValue,
      );
      expect(screen.getByRole('button', { name: /^save$/i })).toBeDisabled();
    });
  });

  test('a field with userChange: 0 renders as a disabled input', async () => {
    const user = userEvent.setup();
    mockFetchSettings(
      buildSettings({
        settings: {
          HELP_BASE: { value: 'https://example.com/help', userSee: 1, userChange: 0 },
        },
      }),
    );

    renderSettingsPage();
    await screen.findByRole('heading', { name: 'Configuration' });
    await goToGeneralTab(user);

    const input = screen.getByRole('textbox', { name: /location of the manual/i });
    expect(input).toBeDisabled();
  });

  test('save error shows the API error message', async () => {
    const user = userEvent.setup();
    const axiosError = Object.assign(new Error('Request failed with status code 400'), {
      isAxiosError: true,
      response: { data: { message: 'Invalid value for HELP_BASE' } },
    });
    vi.mocked(updateSettings).mockRejectedValueOnce(axiosError);

    renderSettingsPage();
    await screen.findByRole('heading', { name: 'Configuration' });
    await goToGeneralTab(user);
    await user.type(screen.getByRole('textbox', { name: /location of the manual/i }), '!');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    const alert = await screen.findByRole('alert');
    expect(alert).toHaveTextContent('Invalid value for HELP_BASE');
  });
});
