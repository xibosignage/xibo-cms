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

// notifyMock and mocks/userApi must be imported before anything that imports
// Notification or userApi.
import '@/testUtils/notifyMock';
import './mocks/userApi';

import { screen, waitFor, within } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { AxiosError, type AxiosResponse } from 'axios';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { mockFont, mockFont2, SINGLE_FONT, TWO_FONTS } from './fixtures/font';
import { renderFontsPage } from './helpers/renderFontsPage';
import { mockDeleteFont, mockFetchFonts } from './mocks/fontApi';

import { deleteFont, fetchFonts } from '@/services/fontApi';
import { testQueryClient } from '@/setupTests';
import { mockNotifySuccess } from '@/testUtils/notifyMock';
import { waitForDialogToClose } from '@/testUtils/rtl';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/fontApi');

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Fonts', path: '/administration/fonts' }]),
}));

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Helpers
// =============================================================================

// Builds a real AxiosError so isAxiosError() and isAlreadyDeletedError() match.
const axiosError = (status: number, message?: string) =>
  new AxiosError('Request failed', 'ERR_BAD_RESPONSE', undefined, undefined, {
    status,
    data: message ? { message } : {},
  } as unknown as AxiosResponse);

const openRowDeleteModal = async (user: UserEvent) => {
  await screen.findByText(mockFont.fileName);
  await user.click(screen.getByRole('button', { name: /more actions/i }));
  await user.click(await screen.findByRole('button', { name: /^delete$/i }));
  return screen.findByRole('dialog');
};

const openBulkDeleteModal = async (user: UserEvent) => {
  await screen.findByText(mockFont2.fileName);
  await user.click(screen.getByRole('checkbox', { name: /select all rows/i }));
  await user.click(await screen.findByRole('button', { name: /delete selected/i }));
  return screen.findByRole('dialog');
};

// =============================================================================
// Tests — single delete
// =============================================================================

describe('Fonts page - single delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchFonts(SINGLE_FONT);
  });

  test('Delete row action opens the confirmation modal showing the font name', async () => {
    const user = userEvent.setup();
    renderFontsPage();

    const dialog = await openRowDeleteModal(user);

    expect(within(dialog).getByRole('heading', { name: /delete font\?/i })).toBeInTheDocument();
    expect(within(dialog).getByText(mockFont.name, { selector: 'strong' })).toBeInTheDocument();
  });

  test(
    'confirming deletes the font, shows a success toast, closes and refreshes',
    { tags: ['smoke'] },
    async () => {
      const user = userEvent.setup();
      mockDeleteFont();
      renderFontsPage();
      await openRowDeleteModal(user);
      const callsBeforeDelete = vi.mocked(fetchFonts).mock.calls.length;

      await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

      await waitForDialogToClose(/delete font\?/i);
      expect(deleteFont).toHaveBeenCalledTimes(1);
      expect(deleteFont).toHaveBeenCalledWith(mockFont.id);
      expect(mockNotifySuccess).toHaveBeenCalledWith('1 font(s) deleted successfully.');
      await waitFor(() => {
        expect(vi.mocked(fetchFonts).mock.calls.length).toBeGreaterThan(callsBeforeDelete);
      });
    },
  );

  test('Cancel closes the modal without calling deleteFont', async () => {
    const user = userEvent.setup();
    renderFontsPage();
    await openRowDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    await waitForDialogToClose(/delete font\?/i);
    expect(deleteFont).not.toHaveBeenCalled();
  });

  test('Yes, Delete shows a disabled loading label while deletion is in progress', async () => {
    const user = userEvent.setup();
    let resolveDelete!: () => void;
    const controlledPromise = new Promise<void>((resolve) => {
      resolveDelete = resolve;
    });
    vi.mocked(deleteFont).mockReturnValue(controlledPromise);
    renderFontsPage();
    await openRowDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Deleting…' })).toBeDisabled();
    });

    resolveDelete();
    await waitForDialogToClose(/delete font\?/i);
  });

  test('a server error keeps the modal open and shows the server message', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteFont).mockRejectedValue(axiosError(409, 'Font is in use'));
    renderFontsPage();
    const dialog = await openRowDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await within(dialog).findByText('Font is in use');
    expect(deleteFont).toHaveBeenCalledWith(mockFont.id);
    expect(screen.getByRole('heading', { name: /delete font\?/i })).toBeInTheDocument();
    expect(mockNotifySuccess).not.toHaveBeenCalled();
  });

  // ---------------------------------------------------------------------------
  // isAlreadyDeletedError: a 404 means someone else already removed the font.
  // ---------------------------------------------------------------------------
  test('a 404 (already deleted) is treated as a successful delete', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteFont).mockRejectedValue(axiosError(404));
    renderFontsPage();
    await openRowDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitForDialogToClose(/delete font\?/i);
    expect(deleteFont).toHaveBeenCalledWith(mockFont.id);
    expect(mockNotifySuccess).toHaveBeenCalledWith('1 font(s) deleted successfully.');
  });
});

// =============================================================================
// Tests — bulk delete
// =============================================================================

describe('Fonts page - bulk delete', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockFetchFonts(TWO_FONTS);
  });

  test('Delete Selected opens the plural confirmation with the count', async () => {
    const user = userEvent.setup();
    renderFontsPage();

    const dialog = await openBulkDeleteModal(user);

    expect(within(dialog).getByRole('heading', { name: /delete fonts\?/i })).toBeInTheDocument();
    expect(within(dialog).getByText('2', { selector: 'strong' })).toBeInTheDocument();
  });

  test('confirming deletes every selected font', async () => {
    const user = userEvent.setup();
    mockDeleteFont();
    renderFontsPage();
    await openBulkDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitForDialogToClose(/delete fonts\?/i);
    expect(deleteFont).toHaveBeenCalledTimes(2);
    expect(deleteFont).toHaveBeenCalledWith(mockFont.id);
    expect(deleteFont).toHaveBeenCalledWith(mockFont2.id);
    expect(mockNotifySuccess).toHaveBeenCalledWith('2 font(s) deleted successfully.');
  });

  // ---------------------------------------------------------------------------
  // Promise.allSettled: one failure does not stop the other delete. The modal
  // stays open with "<success part> <first server message>".
  // ---------------------------------------------------------------------------
  test('partial failure keeps the modal open with a combined message and refreshes', async () => {
    const user = userEvent.setup();
    vi.mocked(deleteFont).mockImplementation(async (id: number) => {
      if (id === mockFont2.id) throw axiosError(409, 'Font is in use');
    });
    renderFontsPage();
    const dialog = await openBulkDeleteModal(user);
    const callsBeforeDelete = vi.mocked(fetchFonts).mock.calls.length;

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await within(dialog).findByText('1 font(s) deleted successfully. Font is in use');
    expect(deleteFont).toHaveBeenCalledTimes(2);
    expect(mockNotifySuccess).not.toHaveBeenCalled();
    await waitFor(() => {
      expect(vi.mocked(fetchFonts).mock.calls.length).toBeGreaterThan(callsBeforeDelete);
    });
  });

  test('row selection is cleared after a successful delete', async () => {
    const user = userEvent.setup();
    mockDeleteFont();
    renderFontsPage();
    await openBulkDeleteModal(user);

    await user.click(screen.getByRole('button', { name: 'Yes, Delete' }));

    await waitForDialogToClose(/delete fonts\?/i);
    await waitFor(() => {
      expect(screen.getByRole('checkbox', { name: /select all rows/i })).not.toBeChecked();
    });
  });
});
