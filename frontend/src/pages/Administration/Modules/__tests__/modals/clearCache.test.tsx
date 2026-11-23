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

import { renderClearCacheModal } from './helpers/renderModuleModal';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/components/ui/modals/Modal');

// =============================================================================
// Tests — ClearCacheModuleModal
// =============================================================================

describe('ClearCacheModuleModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('the "Clear Cache?" heading is shown', () => {
    renderClearCacheModal();

    expect(screen.getByText('Clear Cache?')).toBeInTheDocument();
  });

  // Note: i18n is stubbed in tests (no interpolation), so we assert on the
  // confirmation sentence, not the interpolated module name.
  test('the confirmation sentence is shown', () => {
    renderClearCacheModal();

    expect(screen.getByText(/clear the cache for/i)).toBeInTheDocument();
  });

  test('clicking Cancel calls onClose', async () => {
    const user = userEvent.setup();
    const { onClose } = renderClearCacheModal();

    await user.click(screen.getByRole('button', { name: /^cancel$/i }));

    expect(onClose).toHaveBeenCalled();
  });

  test('clicking Clear Cache calls onConfirm', async () => {
    const user = userEvent.setup();
    const { onConfirm } = renderClearCacheModal();

    await user.click(screen.getByRole('button', { name: /^clear cache$/i }));

    expect(onConfirm).toHaveBeenCalled();
  });

  test('the confirm button shows "Clearing…" and is disabled while loading', () => {
    renderClearCacheModal({ isLoading: true });

    expect(screen.getByRole('button', { name: /clearing/i })).toBeDisabled();
  });

  test('an API error is displayed in the modal', () => {
    renderClearCacheModal({ error: 'Cache could not be cleared.' });

    expect(screen.getByText('Cache could not be cleared.')).toBeInTheDocument();
  });
});
