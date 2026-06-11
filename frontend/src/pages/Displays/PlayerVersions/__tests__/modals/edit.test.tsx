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

import { buildPlayerVersion } from '../fixtures/playerVersion';

import { renderEditPlayerVersionModal } from './helpers/renderEditPlayerVersionModal';

import { updatePlayerVersion } from '@/services/playerVersionApi';

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

vi.mock('@/services/playerVersionApi', () => ({
  updatePlayerVersion: vi.fn(),
}));

// =============================================================================
// Tests — EditPlayerVersionModal (form)
// =============================================================================

describe('EditPlayerVersionModal', () => {
  const existing = buildPlayerVersion({
    versionId: 7,
    playerShowVersion: 'Android v3',
    version: '3.2.1',
    code: 321,
  });

  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('modal opens with the title Edit "<name>"', () => {
    renderEditPlayerVersionModal({ data: existing });

    expect(screen.getByRole('dialog', { name: 'Edit "Android v3"' })).toBeInTheDocument();
  });

  test("fields are pre-filled with the version's existing values", () => {
    renderEditPlayerVersionModal({ data: existing });

    expect(screen.getByRole('textbox', { name: /player version name/i })).toHaveValue('Android v3');
    expect(screen.getByRole('textbox', { name: /^version$/i })).toHaveValue('3.2.1');
    expect(screen.getByRole('spinbutton', { name: /^code$/i })).toHaveValue(321);
  });

  test('no Lead Display or folder selector is shown', () => {
    renderEditPlayerVersionModal({ data: existing });

    expect(screen.queryByText(/lead display/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/folder/i)).not.toBeInTheDocument();
  });

  test('opening the modal does not trigger any API call', () => {
    renderEditPlayerVersionModal({ data: existing });

    expect(updatePlayerVersion).not.toHaveBeenCalled();
  });

  test('saving with an empty Player Version Name shows a validation error', async () => {
    const user = userEvent.setup();
    renderEditPlayerVersionModal({ data: existing });

    await user.clear(screen.getByRole('textbox', { name: /player version name/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByText('Display Name is required')).toBeInTheDocument();
    expect(updatePlayerVersion).not.toHaveBeenCalled();
  });

  test('saving with an empty Version shows a validation error', async () => {
    const user = userEvent.setup();
    renderEditPlayerVersionModal({ data: existing });

    await user.clear(screen.getByRole('textbox', { name: /^version$/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByText('Version is required')).toBeInTheDocument();
    expect(updatePlayerVersion).not.toHaveBeenCalled();
  });

  test('saving with Code less than 1 shows a validation error', async () => {
    const user = userEvent.setup();
    renderEditPlayerVersionModal({ data: existing });

    await user.clear(screen.getByRole('spinbutton', { name: /^code$/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByText('Code must be at least 1')).toBeInTheDocument();
    expect(updatePlayerVersion).not.toHaveBeenCalled();
  });

  test('a summary error is shown above the form when validation fails', async () => {
    const user = userEvent.setup();
    renderEditPlayerVersionModal({ data: existing });

    await user.clear(screen.getByRole('textbox', { name: /player version name/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Please fix the highlighted errors before saving.',
    );
  });

  test('a successful save sends the updated values to the API', async () => {
    const user = userEvent.setup();
    vi.mocked(updatePlayerVersion).mockResolvedValue(existing);
    renderEditPlayerVersionModal({ data: existing });

    const nameInput = screen.getByRole('textbox', { name: /player version name/i });
    await user.clear(nameInput);
    await user.type(nameInput, 'Android v3.1');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => {
      expect(updatePlayerVersion).toHaveBeenCalledWith(
        existing.versionId,
        expect.objectContaining({
          playerShowVersion: 'Android v3.1',
          version: '3.2.1',
          code: 321,
        }),
      );
    });
  });

  test('clicking Cancel closes the modal without saving', async () => {
    const user = userEvent.setup();
    const { onClose } = renderEditPlayerVersionModal({ data: existing });

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(onClose).toHaveBeenCalled();
    expect(updatePlayerVersion).not.toHaveBeenCalled();
  });

  test('Save button shows "Saving…" while the request is in progress', async () => {
    const user = userEvent.setup();
    let resolveUpdate: (value: ReturnType<typeof buildPlayerVersion>) => void = () => {};
    vi.mocked(updatePlayerVersion).mockReturnValueOnce(
      new Promise((resolve) => {
        resolveUpdate = resolve;
      }),
    );
    renderEditPlayerVersionModal({ data: existing });

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByRole('button', { name: /saving/i })).toBeDisabled();

    resolveUpdate(existing);
  });

  test('an API error is displayed in the modal', async () => {
    const user = userEvent.setup();
    vi.mocked(updatePlayerVersion).mockRejectedValueOnce(new Error('Update failed.'));
    renderEditPlayerVersionModal({ data: existing });

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Update failed.');
  });
});
