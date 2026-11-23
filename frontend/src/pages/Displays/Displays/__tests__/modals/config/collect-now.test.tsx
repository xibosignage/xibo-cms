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
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import CollectNowModal from '../../../components/CollectNowModal';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Tests
// =============================================================================

describe('CollectNowModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // Cancel must call onClose so the modal can be dismissed.
  // ---------------------------------------------------------------------------
  test('Cancel button calls onClose', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();

    render(
      <CollectNowModal
        onClose={onClose}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError={null}
      />,
    );

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(onClose).toHaveBeenCalledOnce();
  });

  // ---------------------------------------------------------------------------
  // The confirm button must call onConfirm so the action is dispatched.
  // ---------------------------------------------------------------------------
  test('Collect Now button calls onConfirm', async () => {
    const onConfirm = vi.fn();
    const user = userEvent.setup();

    render(
      <CollectNowModal
        onClose={vi.fn()}
        onConfirm={onConfirm}
        isActionPending={false}
        actionError={null}
      />,
    );

    await user.click(screen.getByRole('button', { name: /collect now/i }));

    expect(onConfirm).toHaveBeenCalledOnce();
  });

  // ---------------------------------------------------------------------------
  // While the action is in-flight the button must be disabled so users cannot
  // submit twice, and the label switches to "Collecting…" as feedback.
  // ---------------------------------------------------------------------------
  test('confirm button is disabled and shows pending label when isActionPending is true', () => {
    render(
      <CollectNowModal
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={true}
        actionError={null}
      />,
    );

    expect(screen.getByRole('button', { name: /collecting/i })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // API errors must surface to the user via the modal's error slot.
  // ---------------------------------------------------------------------------
  test('shows error message when actionError is provided', () => {
    render(
      <CollectNowModal
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError="Collection failed"
      />,
    );

    expect(screen.getByRole('alert')).toHaveTextContent('Collection failed');
  });
});
