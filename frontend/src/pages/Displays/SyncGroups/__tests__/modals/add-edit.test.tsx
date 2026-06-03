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

import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildSyncGroupDisplay, mockSyncGroup } from '../fixtures/syncGroup';

import { renderAddEditModal } from './helpers/renderAddEditModal';

import { createSyncGroup, fetchSyncGroupDisplays, updateSyncGroup } from '@/services/syncGroupApi';
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

vi.mock('@/components/ui/modals/Modal');

vi.mock('@/services/syncGroupApi', () => ({
  createSyncGroup: vi.fn(),
  updateSyncGroup: vi.fn(),
  fetchSyncGroupDisplays: vi.fn().mockResolvedValue([]),
}));

vi.mock('@/components/ui/forms/SelectFolder', () => ({
  default: ({ selectedId }: { selectedId?: number | null }) => (
    <div data-testid="mock-select-folder" data-folder-id={selectedId ?? ''} />
  ),
}));

// SelectDropdown is replaced with a native <select> so tests can drive it
// using fireEvent.change. The real component uses a custom dropdown with
// internal search + keyboard logic that's unnecessary here.
vi.mock('@/components/ui/forms/SelectDropdown', () => ({
  default: ({
    label,
    value,
    options,
    onSelect,
    placeholder,
  }: {
    label?: string;
    value?: string;
    options?: Array<{ value: string; label: string }>;
    onSelect?: (value: string) => void;
    placeholder?: string;
  }) => (
    <select aria-label={label} value={value ?? ''} onChange={(e) => onSelect?.(e.target.value)}>
      {placeholder !== undefined && <option value="">{placeholder}</option>}
      {options?.map((o) => (
        <option key={o.value} value={o.value}>
          {o.label}
        </option>
      ))}
    </select>
  ),
}));

// =============================================================================
// Tests — AddAndEditSyncGroupModal
// =============================================================================

describe('AddAndEditSyncGroupModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ===========================================================================
  // ADD MODE
  // ===========================================================================
  describe('Add mode', () => {
    // -------------------------------------------------------------------------
    // The dialog title comes from the mode prop — "Add Sync Group" when adding.
    // -------------------------------------------------------------------------
    test('modal opens with the title "Add Sync Group"', () => {
      renderAddEditModal({ mode: 'add' });

      expect(screen.getByRole('dialog', { name: /add sync group/i })).toBeInTheDocument();
    });

    // -------------------------------------------------------------------------
    // The form must contain the four basic fields. Lead Display is asserted
    // separately because it must NOT be present in add mode.
    // -------------------------------------------------------------------------
    test('all expected fields are present', () => {
      renderAddEditModal({ mode: 'add' });

      expect(screen.getByRole('textbox', { name: /^name$/i })).toBeInTheDocument();
      expect(screen.getByRole('spinbutton', { name: /^publisher port$/i })).toBeInTheDocument();
      expect(screen.getByRole('spinbutton', { name: /^switch delay$/i })).toBeInTheDocument();
      expect(screen.getByRole('spinbutton', { name: /^video pause delay$/i })).toBeInTheDocument();
    });

    // -------------------------------------------------------------------------
    // The Lead Display dropdown is only shown in edit mode (it lets the user
    // pick from the sync group's existing member displays).
    // -------------------------------------------------------------------------
    test('Lead Display is not shown in add mode', () => {
      renderAddEditModal({ mode: 'add' });

      expect(screen.queryByRole('combobox', { name: /lead display/i })).not.toBeInTheDocument();
    });

    // -------------------------------------------------------------------------
    // Default values come from DEFAULT_DRAFT in the modal source — these are
    // the recommended starter values for a new sync group.
    // -------------------------------------------------------------------------
    test('default values are applied when the modal opens', () => {
      renderAddEditModal({ mode: 'add' });

      expect(screen.getByRole('spinbutton', { name: /^publisher port$/i })).toHaveValue(9590);
      expect(screen.getByRole('spinbutton', { name: /^switch delay$/i })).toHaveValue(750);
      expect(screen.getByRole('spinbutton', { name: /^video pause delay$/i })).toHaveValue(100);
    });

    // -------------------------------------------------------------------------
    // The schema requires Name to be non-empty. Trying to save with an empty
    // Name should show the validation error and NOT call createSyncGroup.
    // -------------------------------------------------------------------------
    test('saving with an empty Name shows a validation error', async () => {
      const user = userEvent.setup();
      renderAddEditModal({ mode: 'add' });

      await user.click(screen.getByRole('button', { name: /^save$/i }));

      expect(await screen.findByText('Name is required')).toBeInTheDocument();
      expect(createSyncGroup).not.toHaveBeenCalled();
    });

    // -------------------------------------------------------------------------
    // Publisher Port must be greater than 0. Setting it to 0 should fail
    // validation and not call createSyncGroup.
    // -------------------------------------------------------------------------
    test('saving with Publisher Port set to 0 shows a validation error', async () => {
      const user = userEvent.setup();
      renderAddEditModal({ mode: 'add' });

      // Provide a valid name so port is the only invalid field.
      await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'Test Group');
      const portInput = screen.getByRole('spinbutton', { name: /^publisher port$/i });
      await user.clear(portInput);
      await user.type(portInput, '0');

      await user.click(screen.getByRole('button', { name: /^save$/i }));

      expect(await screen.findByText('Publisher Port must be greater than 0')).toBeInTheDocument();
      expect(createSyncGroup).not.toHaveBeenCalled();
    });

    // -------------------------------------------------------------------------
    // On validation failure the modal also surfaces a summary error message
    // in its `error` slot (which the Modal mock renders as role="alert").
    // -------------------------------------------------------------------------
    test('a summary error message appears above the buttons when validation fails', async () => {
      const user = userEvent.setup();
      renderAddEditModal({ mode: 'add' });

      await user.click(screen.getByRole('button', { name: /^save$/i }));

      expect(await screen.findByRole('alert')).toHaveTextContent(
        'Please fix the highlighted errors before saving.',
      );
      expect(createSyncGroup).not.toHaveBeenCalled();
    });

    // -------------------------------------------------------------------------
    // A valid submit calls createSyncGroup with the entered values and the
    // default port/delay values from DEFAULT_DRAFT.
    // -------------------------------------------------------------------------
    test('a successful save sends the correct data to the API', async () => {
      const user = userEvent.setup();
      vi.mocked(createSyncGroup).mockResolvedValue(mockSyncGroup);
      renderAddEditModal({ mode: 'add' });

      await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'Lobby Sync');
      await user.click(screen.getByRole('button', { name: /^save$/i }));

      await waitFor(() => {
        expect(createSyncGroup).toHaveBeenCalledWith(
          expect.objectContaining({
            name: 'Lobby Sync',
            syncPublisherPort: 9590,
            syncSwitchDelay: 750,
            syncVideoPauseDelay: 100,
          }),
        );
      });
    });

    // -------------------------------------------------------------------------
    // Cancel closes the modal via onClose without triggering any API mutation.
    // -------------------------------------------------------------------------
    test('clicking Cancel closes the modal without saving', async () => {
      const user = userEvent.setup();
      const onClose = vi.fn();
      renderAddEditModal({ mode: 'add', onClose });

      await user.click(screen.getByRole('button', { name: /^cancel$/i }));

      expect(onClose).toHaveBeenCalled();
      expect(createSyncGroup).not.toHaveBeenCalled();
    });

    // -------------------------------------------------------------------------
    // While the create request is in flight the Save button switches to
    // "Saving…" and is disabled to prevent double-submit.
    // -------------------------------------------------------------------------
    test('Save button shows "Saving…" while the request is in progress', async () => {
      const user = userEvent.setup();
      let resolveCreate: (value: typeof mockSyncGroup) => void = () => {};
      vi.mocked(createSyncGroup).mockReturnValueOnce(
        new Promise((resolve) => {
          resolveCreate = resolve;
        }),
      );
      renderAddEditModal({ mode: 'add' });

      await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'Lobby Sync');
      await user.click(screen.getByRole('button', { name: /^save$/i }));

      // While the promise is unresolved the button label flips to "Saving…".
      expect(await screen.findByRole('button', { name: /saving/i })).toBeDisabled();

      // Resolve so the test doesn't leak a pending promise.
      resolveCreate(mockSyncGroup);
    });

    // -------------------------------------------------------------------------
    // When the API rejects with a server-side error message, that message is
    // surfaced in the modal's error slot and the modal stays open.
    // -------------------------------------------------------------------------
    test('an API error is displayed in the modal', async () => {
      const user = userEvent.setup();
      vi.mocked(createSyncGroup).mockRejectedValueOnce(new Error('Name already in use.'));
      renderAddEditModal({ mode: 'add' });

      await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'Existing Group');
      await user.click(screen.getByRole('button', { name: /^save$/i }));

      // Modal's auto-mock surfaces the error via role="alert".
      expect(await screen.findByRole('alert')).toHaveTextContent('Name already in use.');
    });
  });

  // ===========================================================================
  // EDIT MODE
  // ===========================================================================
  describe('Edit mode', () => {
    // -------------------------------------------------------------------------
    // The dialog title flips to "Edit Sync Group" when editing.
    // -------------------------------------------------------------------------
    test('modal opens with the title "Edit Sync Group"', () => {
      renderAddEditModal({ mode: 'edit' });

      expect(screen.getByRole('dialog', { name: /edit sync group/i })).toBeInTheDocument();
    });

    // -------------------------------------------------------------------------
    // The Name, Publisher Port, Switch Delay and Video Pause Delay fields are
    // pre-populated from the syncGroup prop.
    // -------------------------------------------------------------------------
    test("fields are pre-filled with the sync group's existing values", () => {
      const existing = {
        ...mockSyncGroup,
        name: 'Existing Group',
        syncPublisherPort: 8080,
        syncSwitchDelay: 500,
        syncVideoPauseDelay: 200,
      };
      renderAddEditModal({ mode: 'edit', syncGroup: existing });

      expect(screen.getByRole('textbox', { name: /^name$/i })).toHaveValue('Existing Group');
      expect(screen.getByRole('spinbutton', { name: /^publisher port$/i })).toHaveValue(8080);
      expect(screen.getByRole('spinbutton', { name: /^switch delay$/i })).toHaveValue(500);
      expect(screen.getByRole('spinbutton', { name: /^video pause delay$/i })).toHaveValue(200);
    });

    // -------------------------------------------------------------------------
    // In edit mode the modal renders an additional Lead Display dropdown
    // populated from the sync group's member displays.
    // -------------------------------------------------------------------------
    test('Lead Display field is shown in edit mode', () => {
      renderAddEditModal({ mode: 'edit' });

      expect(screen.getByRole('combobox', { name: /lead display/i })).toBeInTheDocument();
    });

    // -------------------------------------------------------------------------
    // On open in edit mode the modal calls fetchSyncGroupDisplays with the
    // sync group's id and uses the returned displays to populate the Lead
    // Display dropdown options.
    // -------------------------------------------------------------------------
    test("the Lead Display dropdown is populated from the sync group's member displays", async () => {
      vi.mocked(fetchSyncGroupDisplays).mockResolvedValueOnce([
        buildSyncGroupDisplay({ displayId: 100, display: 'Lobby Screen 1' }),
        buildSyncGroupDisplay({ displayId: 101, display: 'Lobby Screen 2' }),
      ]);

      renderAddEditModal({ mode: 'edit' });

      await waitFor(() => {
        expect(fetchSyncGroupDisplays).toHaveBeenCalledWith(mockSyncGroup.syncGroupId);
      });

      const leadDisplay = await screen.findByRole('combobox', { name: /lead display/i });
      // Wait for the async state update that pushes options into the dropdown.
      await waitFor(() => {
        expect(within(leadDisplay).getAllByRole('option').length).toBeGreaterThanOrEqual(2);
      });
      expect(
        within(leadDisplay).getByRole('option', { name: 'Lobby Screen 1' }),
      ).toBeInTheDocument();
      expect(
        within(leadDisplay).getByRole('option', { name: 'Lobby Screen 2' }),
      ).toBeInTheDocument();
    });

    // -------------------------------------------------------------------------
    // Validation parity with add mode — the schema is shared, so we sanity-
    // check the two core cases (empty Name, port=0) in edit mode too. If the
    // shared schema ever splits per-mode this will catch a regression.
    // -------------------------------------------------------------------------
    test('saving with an empty Name shows a validation error (edit mode)', async () => {
      const user = userEvent.setup();
      renderAddEditModal({ mode: 'edit', syncGroup: mockSyncGroup });

      const nameInput = screen.getByRole('textbox', { name: /^name$/i });
      await user.clear(nameInput);
      await user.click(screen.getByRole('button', { name: /^save$/i }));

      expect(await screen.findByText('Name is required')).toBeInTheDocument();
      expect(updateSyncGroup).not.toHaveBeenCalled();
    });

    test('saving with Publisher Port set to 0 shows a validation error (edit mode)', async () => {
      const user = userEvent.setup();
      renderAddEditModal({ mode: 'edit', syncGroup: mockSyncGroup });

      const portInput = screen.getByRole('spinbutton', { name: /^publisher port$/i });
      await user.clear(portInput);
      await user.type(portInput, '0');
      await user.click(screen.getByRole('button', { name: /^save$/i }));

      expect(await screen.findByText('Publisher Port must be greater than 0')).toBeInTheDocument();
      expect(updateSyncGroup).not.toHaveBeenCalled();
    });

    // -------------------------------------------------------------------------
    // A valid edit submit calls updateSyncGroup with the sync group's id and
    // the modified values.
    // -------------------------------------------------------------------------
    test('a successful save sends the updated values to the API', async () => {
      const user = userEvent.setup();
      vi.mocked(updateSyncGroup).mockResolvedValue(mockSyncGroup);
      renderAddEditModal({ mode: 'edit', syncGroup: mockSyncGroup });

      const nameInput = screen.getByRole('textbox', { name: /^name$/i });
      await user.clear(nameInput);
      await user.type(nameInput, 'Renamed Group');

      await user.click(screen.getByRole('button', { name: /^save$/i }));

      await waitFor(() => {
        expect(updateSyncGroup).toHaveBeenCalledWith(
          mockSyncGroup.syncGroupId,
          expect.objectContaining({ name: 'Renamed Group' }),
        );
      });
    });

    // -------------------------------------------------------------------------
    // The same API-error path applies to the update endpoint.
    // -------------------------------------------------------------------------
    test('an API error is displayed in the modal', async () => {
      const user = userEvent.setup();
      vi.mocked(updateSyncGroup).mockRejectedValueOnce(new Error('Update failed.'));
      renderAddEditModal({ mode: 'edit', syncGroup: mockSyncGroup });

      await user.click(screen.getByRole('button', { name: /^save$/i }));

      expect(await screen.findByRole('alert')).toHaveTextContent('Update failed.');
    });
  });
});
