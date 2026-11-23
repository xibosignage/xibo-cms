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

import TriggerWebhookModal from '../../../components/TriggerWebhookModal';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Shared fixture
// =============================================================================

const singleItem = [{ displayGroupId: 1, display: 'Test Display', clientType: null }];

// =============================================================================
// Tests
// =============================================================================

describe('TriggerWebhookModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // The Trigger button must be disabled before the user enters a code — there
  // is nothing to trigger without one.
  // ---------------------------------------------------------------------------
  test('Trigger button is disabled when the code field is empty', () => {
    render(
      <TriggerWebhookModal
        items={singleItem}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError={null}
      />,
    );

    expect(screen.getByRole('button', { name: /^trigger$/i })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Typing a code must enable the Trigger button.
  // ---------------------------------------------------------------------------
  test('Trigger button is enabled after typing a trigger code', async () => {
    const user = userEvent.setup();

    render(
      <TriggerWebhookModal
        items={singleItem}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError={null}
      />,
    );

    await user.type(screen.getByLabelText(/trigger code/i), 'my-hook');

    expect(screen.getByRole('button', { name: /^trigger$/i })).not.toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Whitespace-only input is not a valid code — the button must stay disabled.
  // ---------------------------------------------------------------------------
  test('Trigger button stays disabled when code is whitespace only', async () => {
    const user = userEvent.setup();

    render(
      <TriggerWebhookModal
        items={singleItem}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError={null}
      />,
    );

    await user.type(screen.getByLabelText(/trigger code/i), '   ');

    expect(screen.getByRole('button', { name: /^trigger$/i })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Clicking Trigger must call onConfirm with the items array and the code.
  // ---------------------------------------------------------------------------
  test('Trigger button calls onConfirm with items and code', async () => {
    const onConfirm = vi.fn();
    const user = userEvent.setup();

    render(
      <TriggerWebhookModal
        items={singleItem}
        onClose={vi.fn()}
        onConfirm={onConfirm}
        isActionPending={false}
        actionError={null}
      />,
    );

    await user.type(screen.getByLabelText(/trigger code/i), 'webhook-abc');
    await user.click(screen.getByRole('button', { name: /^trigger$/i }));

    expect(onConfirm).toHaveBeenCalledWith(singleItem, 'webhook-abc');
  });

  // ---------------------------------------------------------------------------
  // Clicking Cancel must clear the code input and call onClose. The cleared
  // state is an internal detail; what we can verify is that onClose was called.
  // ---------------------------------------------------------------------------
  test('Cancel button calls onClose', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();

    render(
      <TriggerWebhookModal
        items={singleItem}
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
  // While the action is in-flight the Trigger button must be disabled and show
  // "Triggering…" so the user cannot submit twice.
  // ---------------------------------------------------------------------------
  test('Trigger button is disabled and shows pending label when isActionPending is true', () => {
    render(
      <TriggerWebhookModal
        items={singleItem}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={true}
        actionError={null}
      />,
    );

    expect(screen.getByRole('button', { name: /triggering/i })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // API errors must surface via the modal's error slot.
  // ---------------------------------------------------------------------------
  test('shows error message when actionError is provided', () => {
    render(
      <TriggerWebhookModal
        items={singleItem}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError="Webhook trigger failed"
      />,
    );

    expect(screen.getByRole('alert')).toHaveTextContent('Webhook trigger failed');
  });
});
