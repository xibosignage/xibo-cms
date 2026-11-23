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

// =============================================================================
// Tests — settings that only have a default (xibosignage/xibo#3924)
// =============================================================================

// The server sends `value: null` for a setting nobody has changed, alongside
// its `default`. Before #3924 the form ignored the default, so those fields
// opened empty, and saving the form wrote the empty value over the default.
// MODULE_WITH_SETTINGS gives every setting a value, so it can't show this.
describe('ConfigureModuleModal - settings left at their default', () => {
  const MODULE_WITH_DEFAULTS = buildModule({
    moduleId: 'core-weather',
    name: 'Weather',
    settings: [
      { id: 'apiKey', type: 'text', title: 'API Key', default: 'demo-key' },
      { id: 'cacheMinutes', type: 'number', title: 'Cache Minutes', value: null, default: 60 },
      { id: 'units', type: 'text', title: 'Units', value: 'imperial', default: 'metric' },
    ] as unknown as NonNullable<Parameters<typeof buildModule>[0]>['settings'],
  });

  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('a setting with no value shows its default', () => {
    renderConfigureModal({ module: MODULE_WITH_DEFAULTS });

    expect(screen.getByLabelText(/api key/i)).toHaveValue('demo-key');
    expect(screen.getByLabelText(/cache minutes/i)).toHaveValue(60);
  });

  test('saving without changes keeps each default', async () => {
    const user = userEvent.setup();
    const { onSave } = renderConfigureModal({ module: MODULE_WITH_DEFAULTS });

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    expect(onSave).toHaveBeenCalledWith(
      'core-weather',
      expect.objectContaining({ apiKey: 'demo-key', cacheMinutes: 60 }),
    );
  });

  // A value the admin has set wins over the default.
  test('a setting with a value shows the value, not the default', () => {
    renderConfigureModal({ module: MODULE_WITH_DEFAULTS });

    expect(screen.getByLabelText(/units/i)).toHaveValue('imperial');
  });
});
