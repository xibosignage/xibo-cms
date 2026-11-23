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

import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type * as ReactI18nextModule from 'react-i18next';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import DeleteSpotModal from '../../components/DeleteSpotModal';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/components/ui/modals/Modal');
vi.mock('react-i18next', async (importOriginal) => {
  const actual = await importOriginal<typeof ReactI18nextModule>();
  const t = (key: string, opts?: Record<string, unknown>) =>
    key.replace(/{{(\w+)}}/g, (_, name: string) => String(opts?.[name] ?? ''));
  return { ...actual, useTranslation: () => ({ t, i18n: { language: 'en' } }) };
});

// =============================================================================
// Helpers local to this file
// =============================================================================

const onClose = vi.fn();
const onConfirm = vi.fn();

const renderModal = (props: Partial<React.ComponentProps<typeof DeleteSpotModal>> = {}) =>
  render(<DeleteSpotModal onClose={onClose} onConfirm={onConfirm} {...props} />);

const confirmButton = () => screen.getByRole('button', { name: /^(yes, remove|removing\.\.\.)$/i });
const libraryOption = () =>
  screen.queryByRole('checkbox', { name: 'Also delete from the Library?' });

// =============================================================================
// Tests
// =============================================================================

describe('DeleteSpotModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('a single removal is headed "Remove Widget?" and Remove All "Remove All Widgets?"', () => {
    const { unmount } = renderModal({ widgetName: 'Welcome' });
    expect(screen.getByRole('heading', { name: 'Remove Widget?' })).toBeInTheDocument();
    unmount();

    renderModal({ isRemoveAll: true, itemCount: 4 });
    expect(screen.getByRole('heading', { name: 'Remove All Widgets?' })).toBeInTheDocument();
  });

  test('by default, confirming does not delete library media', async () => {
    const user = userEvent.setup();
    renderModal({ hasLibraryMedia: true });

    await user.click(confirmButton());

    expect(onConfirm).toHaveBeenCalledWith(false);
  });

  // Both values come from one checkbox, so both directions are checked.
  test('ticking the library option deletes media; unticking it again does not', async () => {
    const user = userEvent.setup();
    renderModal({ hasLibraryMedia: true });

    await user.click(libraryOption()!);
    await user.click(confirmButton());
    await user.click(libraryOption()!);
    await user.click(confirmButton());

    expect(onConfirm.mock.calls).toEqual([[true], [false]]);
  });

  test('without library media, the library option is not shown', () => {
    renderModal({ hasLibraryMedia: false });

    expect(libraryOption()).not.toBeInTheDocument();
  });

  test('while removing, the confirm button reads "Removing..." and is disabled', () => {
    renderModal({ isLoading: true });

    expect(screen.getByRole('button', { name: 'Removing...' })).toBeDisabled();
  });

  test('for a dynamic playlist, confirm is disabled and the reason is shown', async () => {
    const user = userEvent.setup();
    renderModal({ isDynamic: true });

    expect(confirmButton()).toBeDisabled();
    await user.click(confirmButton());
    expect(onConfirm).not.toHaveBeenCalled();
    expect(screen.getByText(/dynamically managed/)).toBeInTheDocument();
  });

  test('Cancel closes the window without confirming', async () => {
    const user = userEvent.setup();
    renderModal();

    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(onConfirm).not.toHaveBeenCalled();
  });

  // Low priority.
  test('a single removal names the widget, and Remove All says how many', () => {
    const { unmount } = renderModal({ widgetName: 'Welcome' });
    expect(screen.getByText('Welcome')).toBeInTheDocument();
    unmount();

    renderModal({ isRemoveAll: true, itemCount: 4 });
    expect(screen.getByText('4')).toBeInTheDocument();
  });
});
