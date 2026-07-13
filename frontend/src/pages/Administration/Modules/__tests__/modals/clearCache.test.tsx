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
