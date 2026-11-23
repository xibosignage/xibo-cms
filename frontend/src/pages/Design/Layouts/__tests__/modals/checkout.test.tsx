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
import type { ComponentProps } from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import CheckoutLayoutModal from '../../components/CheckoutLayoutModal';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/components/ui/modals/Modal');

// ─── Helpers ──────────────────────────────────────────────────────────────────

const renderModal = (props: Partial<ComponentProps<typeof CheckoutLayoutModal>> = {}) => {
  const onClose = vi.fn();
  const onConfirm = vi.fn();
  render(
    <CheckoutLayoutModal
      onClose={onClose}
      onConfirm={onConfirm}
      isActionPending={false}
      actionError={null}
      {...props}
    />,
  );
  return { onClose, onConfirm };
};

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('CheckoutLayoutModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('clicking Checkout asks the page to check the layout out, and does not close it', async () => {
    const user = userEvent.setup();
    const { onClose, onConfirm } = renderModal();

    await user.click(screen.getByRole('button', { name: 'Checkout' }));

    expect(onConfirm).toHaveBeenCalledOnce();
    expect(onClose).not.toHaveBeenCalled();
  });

  test('clicking Cancel closes the modal without checking the layout out', async () => {
    const user = userEvent.setup();
    const { onClose, onConfirm } = renderModal();

    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(onClose).toHaveBeenCalledOnce();
    expect(onConfirm).not.toHaveBeenCalled();
  });

  // A second click while the first checkout is still running would send a second
  // checkout request, so the button must be locked and say what is happening.
  test('while the checkout is running the button reads "Checking out…" and cannot be clicked again', async () => {
    const user = userEvent.setup();
    const { onConfirm } = renderModal({ isActionPending: true });

    const button = screen.getByRole('button', { name: 'Checking out…' });
    expect(button).toBeDisabled();

    await user.click(button);
    expect(onConfirm).not.toHaveBeenCalled();
    expect(screen.queryByRole('button', { name: 'Checkout' })).not.toBeInTheDocument();
  });

  test('a failed checkout shows the server message inside the modal', () => {
    renderModal({ actionError: 'Layout is locked by another user' });

    expect(screen.getByRole('alert')).toHaveTextContent('Layout is locked by another user');
  });

  test('no error is shown before anything has gone wrong', () => {
    renderModal();

    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });
});
