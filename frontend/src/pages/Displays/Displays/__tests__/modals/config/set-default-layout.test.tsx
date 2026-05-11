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
import { vi, describe, test, expect, beforeEach } from 'vitest';

import { buildDisplay } from '../../fixtures/display';
import SetDefaultLayoutModal from '../../../components/SetDefaultLayoutModal';

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

// fetchLayouts is called inside a useEffect on mount — return a minimal list so
// the component can populate the dropdown options.
vi.mock('@/services/layoutsApi', () => ({
  fetchLayouts: vi.fn().mockResolvedValue({
    rows: [{ layoutId: 10, layout: 'Lobby Layout' }],
    totalCount: 1,
  }),
}));

// SelectDropdown is a custom combobox with IntersectionObserver-based lazy loading.
// Replace with a native <select> so tests can drive selection without relying on
// pointer events or scroll observers.
vi.mock('@/components/ui/forms/SelectDropdown', () => ({
  default: ({
    value,
    options,
    onSelect,
    label,
  }: {
    value: string;
    options: { value: string; label: string }[];
    onSelect: (v: string) => void;
    label?: string;
  }) => (
    <select
      aria-label={label ?? 'select'}
      value={value}
      onChange={(e) => onSelect(e.target.value)}
    >
      <option value="">-- select --</option>
      {options.map((o) => (
        <option key={o.value} value={o.value}>
          {o.label}
        </option>
      ))}
    </select>
  ),
}));

vi.mock('@/hooks/useDebounce', () => ({
  useDebounce: (v: unknown) => v,
}));

// =============================================================================
// Tests
// =============================================================================

describe('SetDefaultLayoutModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  // ---------------------------------------------------------------------------
  // Without a display prop there is no pre-selected layout — Save must be
  // disabled until the user picks one.
  // ---------------------------------------------------------------------------
  test('Save button is disabled when no layout is selected', async () => {
    render(
      <SetDefaultLayoutModal
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError={null}
      />,
    );

    // findByRole drains the fetchLayouts microtask before asserting, avoiding
    // "not wrapped in act()" warnings from the useEffect state update.
    expect(await screen.findByRole('button', { name: /^save$/i })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // When the display has a defaultLayoutId, that layout is pre-selected and
  // Save should be enabled immediately.
  // ---------------------------------------------------------------------------
  test('Save button is enabled when display has a defaultLayoutId', async () => {
    const display = buildDisplay({ defaultLayoutId: 1, defaultLayout: 'Default Layout' });

    render(
      <SetDefaultLayoutModal
        display={display}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError={null}
      />,
    );

    expect(await screen.findByRole('button', { name: /^save$/i })).not.toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // Selecting a layout from the dropdown must enable the Save button and call
  // onConfirm with the chosen layout id.
  // ---------------------------------------------------------------------------
  test('selecting a layout enables Save and calls onConfirm', async () => {
    const onConfirm = vi.fn();
    const user = userEvent.setup();

    render(
      <SetDefaultLayoutModal
        onClose={vi.fn()}
        onConfirm={onConfirm}
        isActionPending={false}
        actionError={null}
      />,
    );

    // Wait for fetchLayouts mock to populate the dropdown.
    const select = await screen.findByRole('combobox', { name: /default layout/i });
    await user.selectOptions(select, '10');

    expect(screen.getByRole('button', { name: /^save$/i })).not.toBeDisabled();

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(onConfirm).toHaveBeenCalledWith(10);
  });

  // ---------------------------------------------------------------------------
  // Cancel must call onClose without triggering onConfirm.
  // ---------------------------------------------------------------------------
  test('Cancel button calls onClose', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();

    render(
      <SetDefaultLayoutModal
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
  // While the action is in-flight the Save button must be disabled and show
  // "Saving…" to prevent double-submission. Pass a display so selectedLayoutId
  // is pre-populated — otherwise Save is already disabled for a different reason.
  // ---------------------------------------------------------------------------
  test('Save button is disabled and shows pending label when isActionPending is true', async () => {
    const display = buildDisplay({ defaultLayoutId: 1, defaultLayout: 'Default Layout' });

    render(
      <SetDefaultLayoutModal
        display={display}
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={true}
        actionError={null}
      />,
    );

    expect(await screen.findByRole('button', { name: /saving/i })).toBeDisabled();
  });

  // ---------------------------------------------------------------------------
  // API errors must surface via the modal's error slot.
  // ---------------------------------------------------------------------------
  test('shows error message when actionError is provided', async () => {
    render(
      <SetDefaultLayoutModal
        onClose={vi.fn()}
        onConfirm={vi.fn()}
        isActionPending={false}
        actionError="Layout update failed"
      />,
    );

    // findByRole drains the fetchLayouts microtask before asserting.
    expect(await screen.findByRole('alert')).toHaveTextContent('Layout update failed');
  });
});
