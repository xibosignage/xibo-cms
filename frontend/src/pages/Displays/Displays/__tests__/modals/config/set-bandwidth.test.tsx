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

import SetBandwidthModal from '../../../components/SetBandwidthModal';

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

// BandwidthInput is a composite widget (NumberInput + unit SelectDropdown). Mock
// it with a labelled number input so tests can drive the value via role queries
// without depending on SelectDropdown internals or the unit conversion logic.
vi.mock('@/components/ui/forms/BandwidthInput', () => ({
  default: ({
    label,
    onChange,
  }: {
    valueKb: number | null;
    onChange: (kb: number | null) => void;
    label?: string;
    helpText?: string;
  }) => (
    <label>
      {label ?? 'Bandwidth limit'}
      <input
        type="number"
        onChange={(e) => {
          const v = Number(e.target.value);
          onChange(v > 0 ? v : null);
        }}
      />
    </label>
  ),
}));

// =============================================================================
// Tests
// =============================================================================

describe('SetBandwidthModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // Save must be disabled on open because no bandwidth value has been entered
  // yet (bandwidthKb starts as null).
  // ---------------------------------------------------------------------------
  test('Save button is disabled before a bandwidth value is entered', () => {
    render(
      <SetBandwidthModal
        displayCount={1}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError={null}
      />,
    );

    expect(screen.getByRole('button', { name: /^save$/i })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Entering a non-zero value must enable the Save button.
  // ---------------------------------------------------------------------------
  test('Save button is enabled after entering a bandwidth value', async () => {
    const user = userEvent.setup();

    render(
      <SetBandwidthModal
        displayCount={1}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError={null}
      />,
    );

    await user.type(screen.getByRole('spinbutton', { name: /bandwidth limit/i }), '1024');

    expect(screen.getByRole('button', { name: /^save$/i })).not.toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Cancel must call onClose to dismiss the modal without persisting changes.
  // ---------------------------------------------------------------------------
  test('Cancel button calls onClose', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();

    render(
      <SetBandwidthModal
        displayCount={1}
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
  // Clicking Save must call onConfirm with the entered kb value.
  // ---------------------------------------------------------------------------
  test('Save button calls onConfirm with the bandwidth value', async () => {
    const onConfirm = vi.fn();
    const user = userEvent.setup();

    render(
      <SetBandwidthModal
        displayCount={1}
        onClose={vi.fn()}
        onConfirm={onConfirm}
        isActionPending={false}
        actionError={null}
      />,
    );

    await user.type(screen.getByRole('spinbutton', { name: /bandwidth limit/i }), '2048');
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(onConfirm).toHaveBeenCalledWith(2048);
  });

  // ---------------------------------------------------------------------------
  // While the action is in-flight the Save button must be disabled and its
  // label must switch to "Saving…" so the user knows a request is pending.
  // ---------------------------------------------------------------------------
  test('Save button is disabled and shows pending label when isActionPending is true', () => {
    render(
      <SetBandwidthModal
        displayCount={1}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={true}
        actionError={null}
      />,
    );

    expect(screen.getByRole('button', { name: /saving/i })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // API errors must surface via the modal's error slot.
  // ---------------------------------------------------------------------------
  test('shows error message when actionError is provided', () => {
    render(
      <SetBandwidthModal
        displayCount={1}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError="Bandwidth update failed"
      />,
    );

    expect(screen.getByRole('alert')).toHaveTextContent('Bandwidth update failed');
  });
});
