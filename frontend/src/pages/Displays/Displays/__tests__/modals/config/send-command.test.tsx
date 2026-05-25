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

import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import SendCommandModal from '../../../components/SendCommandModal';

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

// CommandDropdown fetches commands from the API and renders a custom combobox.
// Replace with a labelled native <select> so tests can drive selection via
// role queries without API calls or custom combobox events.
vi.mock('@/components/ui/forms/CommandDropdown', () => ({
  default: ({
    value,
    onSelect,
  }: {
    value: number | null;
    onSelect: (id: number | null) => void;
    type?: string;
    helpText?: string;
  }) => (
    <label>
      Command
      <select
        value={value ?? ''}
        onChange={(e) => onSelect(e.target.value ? Number(e.target.value) : null)}
      >
        <option value="">-- pick a command --</option>
        <option value="5">Reboot</option>
        <option value="7">Clear Cache</option>
      </select>
    </label>
  ),
}));

// =============================================================================
// Shared fixtures
// =============================================================================

const singleItem = [{ displayGroupId: 1, display: 'Test Display', clientType: 'android' }];

// =============================================================================
// Tests
// =============================================================================

describe('SendCommandModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // Send must be disabled before a command is selected — there is nothing to
  // send without knowing which command.
  // ---------------------------------------------------------------------------
  test('Send button is disabled before a command is selected', () => {
    render(
      <SendCommandModal
        items={singleItem}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError={null}
      />,
    );

    expect(screen.getByRole('button', { name: /^send$/i })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Selecting a command must enable the Send button.
  // ---------------------------------------------------------------------------
  test('Send button is enabled after selecting a command', async () => {
    const user = userEvent.setup();

    render(
      <SendCommandModal
        items={singleItem}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError={null}
      />,
    );

    await user.selectOptions(screen.getByRole('combobox', { name: /command/i }), '5');

    expect(screen.getByRole('button', { name: /^send$/i })).not.toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Clicking Send must call onConfirm with the items array and selected command id.
  // ---------------------------------------------------------------------------
  test('Send button calls onConfirm with items and commandId', async () => {
    const onConfirm = vi.fn();
    const user = userEvent.setup();

    render(
      <SendCommandModal
        items={singleItem}
        onClose={vi.fn()}
        onConfirm={onConfirm}
        isActionPending={false}
        actionError={null}
      />,
    );

    await user.selectOptions(screen.getByRole('combobox', { name: /command/i }), '7');
    await user.click(screen.getByRole('button', { name: /^send$/i }));

    expect(onConfirm).toHaveBeenCalledWith(singleItem, 7);
  });

  // ---------------------------------------------------------------------------
  // Cancel calls onClose.
  // ---------------------------------------------------------------------------
  test('Cancel button calls onClose', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();

    render(
      <SendCommandModal
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
  // While the action is in-flight the Send button must be disabled and show
  // "Sending…" so the user knows a request is in progress.
  // ---------------------------------------------------------------------------
  test('Send button is disabled and shows pending label when isActionPending is true', () => {
    render(
      <SendCommandModal
        items={singleItem}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={true}
        actionError={null}
      />,
    );

    expect(screen.getByRole('button', { name: /sending/i })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // API errors must surface via the modal's error slot.
  // ---------------------------------------------------------------------------
  test('shows error message when actionError is provided', () => {
    render(
      <SendCommandModal
        items={singleItem}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError="Command failed"
      />,
    );

    expect(screen.getByRole('alert')).toHaveTextContent('Command failed');
  });
});
