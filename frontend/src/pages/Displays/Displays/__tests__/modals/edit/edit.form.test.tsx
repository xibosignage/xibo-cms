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

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildDisplay, mockDisplay } from '../../fixtures/display';

import { renderEditModal } from './helpers/renderEditModal';

import { updateDisplay } from '@/services/displaysApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

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

describe('Display - edit form fields', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(updateDisplay).mockResolvedValue(mockDisplay);
  });

  // ---------------------------------------------------------------------------
  // When opening the edit form the Display name field should already show the
  // current display name so the user does not have to retype it.
  // ---------------------------------------------------------------------------
  test('Display name field is pre-populated with the existing display name', async () => {
    await renderEditModal();

    expect(screen.getByRole('textbox', { name: /^Display$/i })).toHaveValue(mockDisplay.display);
  });

  // ---------------------------------------------------------------------------
  // The user should be able to clear the name and type a new value, and the
  // field should reflect exactly what they typed.
  // ---------------------------------------------------------------------------
  test('Display name field is editable and reflects typed input', async () => {
    const user = userEvent.setup();
    await renderEditModal();

    const nameInput = screen.getByRole('textbox', { name: /^Display$/i });
    await user.clear(nameInput);
    await user.type(nameInput, 'Updated Display');

    expect(nameInput).toHaveValue('Updated Display');
  });

  // ---------------------------------------------------------------------------
  // The Description field should be pre-populated with the display's current
  // description. When description is null the draft initialises to empty string.
  // ---------------------------------------------------------------------------
  test('Description field is pre-populated with the existing description', async () => {
    const description = 'My existing description';
    await renderEditModal({ data: buildDisplay({ description }) });

    expect(screen.getByRole('textbox', { name: /description/i })).toHaveValue(description);
  });

  // ---------------------------------------------------------------------------
  // If the user clears the Display name and tries to save, a "Name is required"
  // validation error must appear and the API must not be called.
  // ---------------------------------------------------------------------------
  test('Save with empty name shows validation error and does not call updateDisplay', async () => {
    const user = userEvent.setup();
    await renderEditModal();

    await user.clear(screen.getByRole('textbox', { name: /^Display$/i }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await screen.findByText('Name is required');
    expect(updateDisplay).not.toHaveBeenCalled();
  });

  // Regression cover for xibosignage/xibo#3873. The shared fixture has a
  // Hardware Key, so this test clears it.
  test('Save with an empty Hardware Key shows a validation error and does not call updateDisplay', async () => {
    const user = userEvent.setup();
    await renderEditModal({ data: buildDisplay({ license: '' }) });

    await user.click(screen.getByRole('button', { name: 'Save' }));

    await screen.findByText('Hardware Key is required');
    expect(updateDisplay).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // When the user saves valid data, updateDisplay should be called with the
  // correct payload and onClose should be called once the save succeeds.
  // ---------------------------------------------------------------------------
  test(
    'Successful save calls updateDisplay with correct payload and calls onClose',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      const onClose = vi.fn();
      await renderEditModal({ onClose });

      await user.click(screen.getByRole('button', { name: 'Save' }));

      await waitFor(() => {
        expect(updateDisplay).toHaveBeenCalledWith(
          mockDisplay.displayId,
          expect.objectContaining({
            display: mockDisplay.display,
            licensed: mockDisplay.licensed,
          }),
        );
        expect(onClose).toHaveBeenCalledTimes(1);
      });
    },
  );

  // ---------------------------------------------------------------------------
  // Clicking Cancel should call onClose immediately without touching the server.
  // ---------------------------------------------------------------------------
  test('Clicking Cancel closes the modal without saving', async () => {
    const user = userEvent.setup();
    const onClose = vi.fn();
    await renderEditModal({ onClose });

    await user.click(screen.getByRole('button', { name: /cancel/i }));

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(updateDisplay).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // If the server rejects the save the modal must stay open and the error
  // message from the API response must be visible.
  // ---------------------------------------------------------------------------
  test('Failed save keeps the modal open and shows the error', async () => {
    const user = userEvent.setup();
    vi.mocked(updateDisplay).mockRejectedValueOnce({
      isAxiosError: true,
      message: 'Request failed with status code 422',
      response: { data: { message: 'Display name already exists' } },
    });

    await renderEditModal();

    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(updateDisplay).toHaveBeenCalledTimes(1);
    screen.getByRole('dialog', { name: /edit/i });
    expect(await screen.findByRole('alert')).toHaveTextContent('Display name already exists');
  });

  // ---------------------------------------------------------------------------
  // While a save is in progress the Save button must switch to "Saving…" and
  // be disabled so the user cannot submit the form a second time.
  // ---------------------------------------------------------------------------
  test('Save button is disabled with loading label while save is in progress', async () => {
    const user = userEvent.setup();
    let resolvePromise!: () => void;
    const controlledPromise = new Promise<typeof mockDisplay>((resolve) => {
      resolvePromise = () => resolve(mockDisplay);
    });
    vi.mocked(updateDisplay).mockReturnValue(controlledPromise);

    await renderEditModal();

    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Saving…' })).toBeDisabled();
    });
    expect(updateDisplay).toHaveBeenCalledTimes(1);

    resolvePromise();
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Save' })).toBeEnabled();
    });
  });

  // ---------------------------------------------------------------------------
  // The Reference tab should render the five reference input fields (ref1–ref5).
  // ---------------------------------------------------------------------------
  test('Reference tab renders reference input fields', async () => {
    const user = userEvent.setup();
    await renderEditModal();

    await user.click(screen.getByRole('tab', { name: 'Reference' }));

    const refInputs = screen.getAllByRole('textbox', { name: /reference/i });
    expect(refInputs).toHaveLength(5);
  });
});
