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

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import AddAndEditDisplayGroupModal from '../components/AddAndEditDisplayGroupModal';

import { mockDisplayGroup } from './fixtures/displayGroup';

import { UserProvider } from '@/context/UserContext';
import { updateDisplayGroup } from '@/services/displayGroupApi';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

const mockUser: User = {
  userId: 1,
  userName: 'TestUser',
  userTypeId: 1,
  groupId: 1,
  features: {},
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/services/displayGroupApi', () => ({
  updateDisplayGroup: vi.fn(),
  createDisplayGroup: vi.fn(),
  fetchDisplayGroups: vi.fn(),
}));
vi.mock('@/services/displaysApi', () => ({
  fetchDisplays: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/services/folderApi', () => ({
  fetchFolderById: vi.fn().mockResolvedValue({ id: 1, text: 'Root' }),
  fetchFolderTree: vi.fn().mockResolvedValue([]),
  searchFolders: vi.fn().mockResolvedValue([]),
  fetchContextButtons: vi.fn().mockResolvedValue({ create: true }),
  selectFolder: vi.fn().mockResolvedValue({ success: true }),
}));
vi.mock('@/hooks/useDebounce', () => ({
  useDebounce: (value: unknown) => value,
}));
vi.mock('@/components/ui/modals/Modal');
// Replace the real folder picker with a plain placeholder so it does not
// fire any background requests during these tests. We are testing form
// fields here, not folder selection.
vi.mock('@/components/ui/forms/SelectFolder', () => ({
  default: ({ selectedId }: { selectedId?: number | null }) => (
    <div data-testid="mock-select-folder" data-folder-id={selectedId ?? ''} />
  ),
}));

// =============================================================================
// Helpers
// =============================================================================

const renderEditModal = async (
  overrides: Partial<React.ComponentProps<typeof AddAndEditDisplayGroupModal>> = {},
) => {
  const defaults = {
    type: 'edit' as const,
    isOpen: true,
    data: mockDisplayGroup,
    onClose: vi.fn(),
    onSave: vi.fn(),
  };
  const utils = render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter>
          <AddAndEditDisplayGroupModal {...defaults} {...overrides} />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
  // Wait for the modal to appear on screen before the test does anything.
  // This also lets any background work (like loading folder names) finish first.
  await screen.findByRole('dialog');
  return utils;
};

// =============================================================================
// Tests
// =============================================================================

describe('DisplayGroup - edit form fields', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(updateDisplayGroup).mockResolvedValue(mockDisplayGroup);
  });

  // ---------------------------------------------------------------------------
  // When opening the edit form for an existing group, the Name field should
  // already show that group's current name so the user does not have to retype it.
  // ---------------------------------------------------------------------------
  test('Name field is pre-populated with the existing display group name', async () => {
    await renderEditModal();

    expect(screen.getByRole('textbox', { name: /name/i })).toHaveValue(
      mockDisplayGroup.displayGroup,
    );
  });

  // ---------------------------------------------------------------------------
  // The user should be able to clear the Name field and type a new value,
  // and the field should show exactly what they typed.
  // ---------------------------------------------------------------------------
  test('Name field is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();

    const nameInput = screen.getByRole('textbox', { name: /name/i });
    await user.clear(nameInput);
    await user.type(nameInput, 'Updated Group');

    expect(nameInput).toHaveValue('Updated Group');
  });

  // ---------------------------------------------------------------------------
  // Same as the Name field — the Description should be filled in with the
  // group's current description when the edit form opens.
  // ---------------------------------------------------------------------------
  test('Description field is pre-populated with the existing description', async () => {
    await renderEditModal();

    expect(screen.getByRole('textbox', { name: /description/i })).toHaveValue(
      mockDisplayGroup.description,
    );
  });

  // ---------------------------------------------------------------------------
  // A regular (non-dynamic) group should open with the "Dynamic Group"
  // checkbox unticked. Turning it on lets the group auto-assign displays
  // by criteria, so it must start off by default for a static group.
  // ---------------------------------------------------------------------------
  test('Dynamic Group checkbox starts unchecked for a static display group', async () => {
    await renderEditModal();

    expect(screen.getByRole('checkbox', { name: 'Dynamic Group' })).not.toBeChecked();
  });

  // ---------------------------------------------------------------------------
  // The user should be able to tick and untick the "Dynamic Group" checkbox
  // freely. Each click should flip the state.
  // ---------------------------------------------------------------------------
  test('Dynamic Group checkbox toggles on and off', async () => {
    const user = userEvent.setup();
    await renderEditModal();

    const checkbox = screen.getByRole('checkbox', { name: 'Dynamic Group' });
    await user.click(checkbox);
    expect(checkbox).toBeChecked();

    await user.click(checkbox);
    expect(checkbox).not.toBeChecked();
  });

  // ---------------------------------------------------------------------------
  // If the user clears the Name field and tries to save, they should see a
  // "Name is required" error message and nothing should be sent to the server.
  // ---------------------------------------------------------------------------
  test('Save with empty name shows validation error and does not call updateDisplayGroup', async () => {
    const user = userEvent.setup();
    await renderEditModal();

    await user.clear(screen.getByRole('textbox', { name: /name/i }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByText('Name is required')).toBeInTheDocument();
    expect(updateDisplayGroup).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // When the user clicks Save with valid data, the group should be updated
  // on the server with the correct values, and the modal should close afterwards.
  // ---------------------------------------------------------------------------
  test('Successful save calls updateDisplayGroup with correct payload and calls onClose', async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();
    await renderEditModal({ onClose });

    await user.click(screen.getByRole('button', { name: 'Save' }));

    // Check the API call and the modal closing together so we know the full
    // save flow finished before we draw any conclusions.
    await waitFor(() => {
      expect(updateDisplayGroup).toHaveBeenCalledWith(
        mockDisplayGroup.displayGroupId,
        expect.objectContaining({
          displayGroup: mockDisplayGroup.displayGroup,
          description: mockDisplayGroup.description,
          isDynamic: 0,
        }),
      );
      expect(onClose).toHaveBeenCalledTimes(1);
    });
  });

  // ---------------------------------------------------------------------------
  // Clicking Cancel should close the modal without touching the server.
  // ---------------------------------------------------------------------------
  test('Clicking cancel closes the modal without saving', async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();
    await renderEditModal({ onClose });

    await user.click(screen.getByRole('button', { name: /cancel/i }));

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(updateDisplayGroup).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // If the server rejects the save (e.g. the name is already taken), the
  // modal should stay open and display the error so the user can fix it and try again.
  // ---------------------------------------------------------------------------
  test('Failed save keeps the modal open and shows the error', async () => {
    const user = userEvent.setup();
    vi.mocked(updateDisplayGroup).mockRejectedValueOnce({
      message: 'Request failed with status code 422',
      response: { data: { message: 'Display group name already exists' } },
    });

    await renderEditModal();

    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(updateDisplayGroup).toHaveBeenCalledTimes(1);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(await screen.findByText('Display group name already exists')).toBeInTheDocument();
  });

  // ---------------------------------------------------------------------------
  // While a save is in progress, the Save button should show "Saving…" and
  // be disabled so the user cannot accidentally submit the form a second time.
  // ---------------------------------------------------------------------------
  test('Save button is disabled with loading label while save is in progress', async () => {
    const user = userEvent.setup();
    // Controlled promise — gives us a handle to resolve it at the end of the
    // test so the component can finish cleanly before unmounting.
    let resolvePromise!: () => void;
    const controlledPromise = new Promise<typeof mockDisplayGroup>((resolve) => {
      resolvePromise = () => resolve(mockDisplayGroup);
    });
    vi.mocked(updateDisplayGroup).mockReturnValue(controlledPromise);

    await renderEditModal();

    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Saving…' })).toBeDisabled();
    });
    expect(updateDisplayGroup).toHaveBeenCalledTimes(1);

    // Resolve and wait for the component to settle before the test tears down.
    resolvePromise();
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled();
    });
  });

  // ---------------------------------------------------------------------------
  // The form has a "Reference" tab. Clicking it should reveal input fields
  // where the user can store custom reference values for the group.
  // ---------------------------------------------------------------------------
  test('Reference tab renders reference input fields', async () => {
    const user = userEvent.setup();
    await renderEditModal();

    await user.click(screen.getByRole('button', { name: 'Reference' }));

    // The i18n mock returns the key literally, so all 5 labels are "Reference {{n}}".
    // Querying by role + label and asserting the exact count pins the full set.
    const refInputs = screen.getAllByRole('textbox', { name: /reference/i });
    expect(refInputs).toHaveLength(5);
  });
});
