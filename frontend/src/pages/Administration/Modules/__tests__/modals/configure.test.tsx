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

import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { buildModule, MODULE_WITH_SETTINGS, PREVIEW_DISABLED_MODULE } from '../fixtures/module';

import { renderConfigureModal } from './helpers/renderModuleModal';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/components/ui/modals/Modal');
// The dynamic "dropdown" setting uses SelectDropdown — stub it as a native <select>.
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
// Tests — ConfigureModuleModal
// =============================================================================

describe('ConfigureModuleModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('the modal opens with the title "Edit Module"', () => {
    renderConfigureModal();

    expect(screen.getByRole('dialog', { name: /edit module/i })).toBeInTheDocument();
  });

  test('the three fixed fields are present', () => {
    renderConfigureModal();

    expect(screen.getByRole('checkbox', { name: /preview enabled/i })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: /^enabled\?$/i })).toBeInTheDocument();
    expect(screen.getByLabelText(/default duration/i)).toBeInTheDocument();
  });

  test('the Preview Enabled? checkbox is disabled when allowPreview is 0', () => {
    renderConfigureModal({ module: PREVIEW_DISABLED_MODULE });

    expect(screen.getByRole('checkbox', { name: /preview enabled/i })).toBeDisabled();
  });

  test("fixed fields default to the module's current values", () => {
    renderConfigureModal({
      module: buildModule({ previewEnabled: 1, enabled: 0, defaultDuration: 30 }),
    });

    expect(screen.getByRole('checkbox', { name: /preview enabled/i })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: /^enabled\?$/i })).not.toBeChecked();
    expect(screen.getByLabelText(/default duration/i)).toHaveValue(30);
  });

  test('dynamic settings render one field per type', () => {
    renderConfigureModal({ module: MODULE_WITH_SETTINGS });

    expect(screen.getByRole('checkbox', { name: /auto refresh/i })).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: /data provider/i })).toBeInTheDocument();
    expect(screen.getByLabelText(/max items/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/api key/i)).toBeInTheDocument();
  });

  test('a successful save sends the merged fixed + dynamic payload to onSave', async () => {
    const user = userEvent.setup();
    const { onSave } = renderConfigureModal({ module: MODULE_WITH_SETTINGS });

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(onSave).toHaveBeenCalledWith(
      MODULE_WITH_SETTINGS.moduleId,
      expect.objectContaining({
        enabled: 1,
        previewEnabled: 1,
        defaultDuration: 10,
        autoRefresh: 1,
        provider: 'yahoo',
        maxItems: 5,
        apiKey: 'abc123',
      }),
    );
  });

  test('toggling a fixed field is reflected in the saved payload', async () => {
    const user = userEvent.setup();
    const { onSave } = renderConfigureModal({ module: buildModule({ enabled: 1 }) });

    await user.click(screen.getByRole('checkbox', { name: /^enabled\?$/i }));
    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(onSave).toHaveBeenCalledWith(
      expect.any(String),
      expect.objectContaining({ enabled: 0 }),
    );
  });

  test('clicking Cancel calls onClose without saving', async () => {
    const user = userEvent.setup();
    const { onClose, onSave } = renderConfigureModal();

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(onClose).toHaveBeenCalled();
    expect(onSave).not.toHaveBeenCalled();
  });

  test('the Save button shows "Saving…" and is disabled while loading', () => {
    renderConfigureModal({ isLoading: true });

    expect(screen.getByRole('button', { name: /saving/i })).toBeDisabled();
  });

  test('an API error is displayed in the modal', () => {
    renderConfigureModal({ error: 'Settings could not be saved.' });

    expect(screen.getByText('Settings could not be saved.')).toBeInTheDocument();
  });
});
