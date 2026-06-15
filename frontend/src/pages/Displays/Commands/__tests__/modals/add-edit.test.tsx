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
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildCommand } from '../fixtures/command';

import { renderAddEditCommandModal } from './helpers/renderAddEditCommandModal';

import { createCommand, updateCommand } from '@/services/commandApi';
import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('react-i18next');

vi.mock('@/components/ui/modals/Modal');

vi.mock('@/services/commandApi', () => ({
  createCommand: vi.fn(),
  updateCommand: vi.fn(),
}));

vi.mock('../../components/CommandBuilder/CommandBuilder', () => ({
  default: ({ value, onChange }: { value: string; onChange: (v: string) => void }) => (
    <input
      data-testid="mock-command-builder"
      value={value}
      onChange={(e) => onChange(e.target.value)}
    />
  ),
}));

vi.mock('@/components/ui/forms/MultiSelectDropdown', () => ({
  default: ({ label, value }: { label?: string; value?: string[] }) => (
    <div data-testid="available-on" aria-label={label} data-selected={(value ?? []).join(',')} />
  ),
}));

vi.mock('@/components/ui/forms/SelectDropdown', () => ({
  default: ({
    label,
    value,
    options,
    onSelect,
  }: {
    label?: string;
    value?: string;
    options?: Array<{ value: string; label: string }>;
    onSelect?: (value: string) => void;
  }) => (
    <select aria-label={label} value={value ?? ''} onChange={(e) => onSelect?.(e.target.value)}>
      {options?.map((o) => (
        <option key={o.value} value={o.value}>
          {o.label}
        </option>
      ))}
    </select>
  ),
}));

// =============================================================================
// Tests — AddEditCommandModal
// =============================================================================

describe('AddEditCommandModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ===========================================================================
  // ADD MODE
  // ===========================================================================
  describe('Add mode', () => {
    test('modal opens with the title "Add Command"', () => {
      renderAddEditCommandModal({ mode: 'add' });

      expect(screen.getByRole('dialog', { name: /add command/i })).toBeInTheDocument();
    });

    test('all expected fields are present', () => {
      renderAddEditCommandModal({ mode: 'add' });

      expect(screen.getByRole('textbox', { name: /^name$/i })).toBeInTheDocument();
      expect(screen.getByRole('textbox', { name: /^code$/i })).toBeInTheDocument();
      expect(screen.getByRole('textbox', { name: /^description/i })).toBeInTheDocument();
      expect(screen.getByRole('textbox', { name: /^validation$/i })).toBeInTheDocument();
      expect(screen.getByTestId('mock-command-builder')).toBeInTheDocument();
      expect(screen.getByTestId('available-on')).toBeInTheDocument();
      expect(screen.getByRole('combobox', { name: /create alert on/i })).toBeInTheDocument();
    });

    test('default values are applied when the modal opens', () => {
      renderAddEditCommandModal({ mode: 'add' });

      expect(screen.getByRole('combobox', { name: /create alert on/i })).toHaveValue('never');
      expect(screen.getByTestId('available-on')).toHaveAttribute('data-selected', '');
    });

    test('the Code field is editable in add mode', () => {
      renderAddEditCommandModal({ mode: 'add' });

      expect(screen.getByRole('textbox', { name: /^code$/i })).toBeEnabled();
    });

    test('saving with an empty Name shows a validation error', async () => {
      const user = userEvent.setup();
      renderAddEditCommandModal({ mode: 'add' });

      await user.type(screen.getByRole('textbox', { name: /^code$/i }), 'VALID_CODE');
      await user.click(screen.getByRole('button', { name: /^save$/i }));

      expect(await screen.findByText('Name is required')).toBeInTheDocument();
      expect(createCommand).not.toHaveBeenCalled();
    });

    test('saving with an empty Code shows a validation error', async () => {
      const user = userEvent.setup();
      renderAddEditCommandModal({ mode: 'add' });

      await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'My Command');
      await user.click(screen.getByRole('button', { name: /^save$/i }));

      expect(await screen.findByText('Code is required')).toBeInTheDocument();
      expect(createCommand).not.toHaveBeenCalled();
    });

    test('saving with an invalid Code shows the format error', async () => {
      const user = userEvent.setup();
      renderAddEditCommandModal({ mode: 'add' });

      await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'My Command');
      await user.type(screen.getByRole('textbox', { name: /^code$/i }), 'bad code!');
      await user.click(screen.getByRole('button', { name: /^save$/i }));

      expect(
        await screen.findByText('Code must contain only letters, numbers, and underscores'),
      ).toBeInTheDocument();
      expect(createCommand).not.toHaveBeenCalled();
    });

    test('a successful save sends the correct data to the API', async () => {
      const user = userEvent.setup();
      vi.mocked(createCommand).mockResolvedValue(buildCommand());
      renderAddEditCommandModal({ mode: 'add' });

      await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'My Command');
      await user.type(screen.getByRole('textbox', { name: /^code$/i }), 'MY_CODE');
      await user.click(screen.getByRole('button', { name: /^save$/i }));

      await waitFor(() => {
        expect(createCommand).toHaveBeenCalledWith(
          expect.objectContaining({
            command: 'My Command',
            code: 'MY_CODE',
            createAlertOn: 'never',
          }),
        );
      });
    });

    test('clicking Cancel closes the modal without saving', async () => {
      const user = userEvent.setup();
      const onClose = vi.fn();
      renderAddEditCommandModal({ mode: 'add', onClose });

      await user.click(screen.getByRole('button', { name: /^cancel$/i }));

      expect(onClose).toHaveBeenCalled();
      expect(createCommand).not.toHaveBeenCalled();
    });

    test('Save button shows "Saving…" while the request is in progress', async () => {
      const user = userEvent.setup();
      let resolveCreate: (value: ReturnType<typeof buildCommand>) => void = () => {};
      vi.mocked(createCommand).mockReturnValueOnce(
        new Promise((resolve) => {
          resolveCreate = resolve;
        }),
      );
      renderAddEditCommandModal({ mode: 'add' });

      await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'My Command');
      await user.type(screen.getByRole('textbox', { name: /^code$/i }), 'MY_CODE');
      await user.click(screen.getByRole('button', { name: /^save$/i }));

      expect(await screen.findByRole('button', { name: /saving/i })).toBeDisabled();

      resolveCreate(buildCommand());
    });

    test('an API error is displayed in the modal', async () => {
      const user = userEvent.setup();
      vi.mocked(createCommand).mockRejectedValueOnce(new Error('Code already in use.'));
      renderAddEditCommandModal({ mode: 'add' });

      await user.type(screen.getByRole('textbox', { name: /^name$/i }), 'My Command');
      await user.type(screen.getByRole('textbox', { name: /^code$/i }), 'MY_CODE');
      await user.click(screen.getByRole('button', { name: /^save$/i }));

      expect(await screen.findByRole('alert')).toHaveTextContent('Code already in use.');
    });
  });

  // ===========================================================================
  // EDIT MODE
  // ===========================================================================
  describe('Edit mode', () => {
    const existingCommand = buildCommand({
      commandId: 7,
      command: 'Existing Command',
      code: 'EXISTING',
      description: 'An existing description',
      validationString: 'val-string',
      availableOn: 'android,windows',
      createAlertOn: 'success',
      commandString: 'tpv_led|red',
    });

    test('modal opens with the title "Edit Command"', () => {
      renderAddEditCommandModal({ mode: 'edit', command: existingCommand });

      expect(screen.getByRole('dialog', { name: /edit command/i })).toBeInTheDocument();
    });

    test("fields are pre-filled with the command's existing values", () => {
      renderAddEditCommandModal({ mode: 'edit', command: existingCommand });

      expect(screen.getByRole('textbox', { name: /^name$/i })).toHaveValue('Existing Command');
      expect(screen.getByRole('textbox', { name: /^code$/i })).toHaveValue('EXISTING');
      expect(screen.getByRole('textbox', { name: /^description/i })).toHaveValue(
        'An existing description',
      );
      expect(screen.getByRole('textbox', { name: /^validation$/i })).toHaveValue('val-string');
      expect(screen.getByRole('combobox', { name: /create alert on/i })).toHaveValue('success');
    });

    test('the Code field is disabled in edit mode', () => {
      renderAddEditCommandModal({ mode: 'edit', command: existingCommand });

      expect(screen.getByRole('textbox', { name: /^code$/i })).toBeDisabled();
    });

    test('Available On is populated from the comma-separated value', () => {
      renderAddEditCommandModal({ mode: 'edit', command: existingCommand });

      expect(screen.getByTestId('available-on')).toHaveAttribute(
        'data-selected',
        'android,windows',
      );
    });

    test('the Command Builder is pre-populated from the stored command string', () => {
      renderAddEditCommandModal({ mode: 'edit', command: existingCommand });

      expect(screen.getByTestId('mock-command-builder')).toHaveValue('tpv_led|red');
    });

    test('a successful save sends the updated values to the API without the code', async () => {
      const user = userEvent.setup();
      vi.mocked(updateCommand).mockResolvedValue(existingCommand);
      renderAddEditCommandModal({ mode: 'edit', command: existingCommand });

      const nameInput = screen.getByRole('textbox', { name: /^name$/i });
      await user.clear(nameInput);
      await user.type(nameInput, 'Renamed Command');
      await user.click(screen.getByRole('button', { name: /^save$/i }));

      await waitFor(() => {
        expect(updateCommand).toHaveBeenCalledWith(
          existingCommand.commandId,
          expect.objectContaining({ command: 'Renamed Command', createAlertOn: 'success' }),
        );
      });
      const payload = vi.mocked(updateCommand).mock.calls[0]![1];
      expect(payload).not.toHaveProperty('code');
    });

    test('clicking Cancel closes the modal without saving', async () => {
      const user = userEvent.setup();
      const onClose = vi.fn();
      renderAddEditCommandModal({ mode: 'edit', command: existingCommand, onClose });

      await user.click(screen.getByRole('button', { name: /^cancel$/i }));

      expect(onClose).toHaveBeenCalled();
      expect(updateCommand).not.toHaveBeenCalled();
    });

    test('an API error is displayed in the modal', async () => {
      const user = userEvent.setup();
      vi.mocked(updateCommand).mockRejectedValueOnce(new Error('Update failed.'));
      renderAddEditCommandModal({ mode: 'edit', command: existingCommand });

      await user.click(screen.getByRole('button', { name: /^save$/i }));

      expect(await screen.findByRole('alert')).toHaveTextContent('Update failed.');
    });
  });
});
