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

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import { renderIconDashboard } from './helpers/renderIconDashboard';

import { testQueryClient } from '@/setupTests';

// =============================================================================
// Module mocks
// =============================================================================

// This file tests how the dashboard opens and closes About, not the About content, so the
// window is a stub that calls onClose from its Close button and on Escape (mirroring the
// shared Modal mock).
vi.mock('@/components/layout/UserMenu/AboutModal', async () => {
  const { useEffect } = await import('react');
  return {
    default: function AboutModalStub({ onClose }: { onClose: () => void }) {
      useEffect(() => {
        const handler = (e: KeyboardEvent) => {
          if (e.key === 'Escape') onClose();
        };
        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
      }, [onClose]);
      return (
        <div role="dialog" aria-label="About">
          <button onClick={onClose}>Close</button>
        </div>
      );
    },
  };
});

// =============================================================================
// Tests
// =============================================================================

describe('Icon Dashboard - About', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
  });

  test('the About window is closed when the dashboard first appears', () => {
    renderIconDashboard();

    expect(screen.queryByRole('dialog', { name: 'About' })).not.toBeInTheDocument();
  });

  test('clicking About opens the About window', async () => {
    const user = userEvent.setup();
    renderIconDashboard();

    await user.click(screen.getByRole('button', { name: 'About' }));

    await screen.findByRole('dialog', { name: 'About' });
  });

  test('Close shuts the About window', async () => {
    const user = userEvent.setup();
    renderIconDashboard();
    await user.click(screen.getByRole('button', { name: 'About' }));
    await screen.findByRole('dialog', { name: 'About' });

    await user.click(screen.getByRole('button', { name: 'Close' }));

    await waitFor(() =>
      expect(screen.queryByRole('dialog', { name: 'About' })).not.toBeInTheDocument(),
    );
  });

  test('pressing Escape shuts the About window', async () => {
    const user = userEvent.setup();
    renderIconDashboard();
    await user.click(screen.getByRole('button', { name: 'About' }));
    await screen.findByRole('dialog', { name: 'About' });

    await user.keyboard('{Escape}');

    await waitFor(() =>
      expect(screen.queryByRole('dialog', { name: 'About' })).not.toBeInTheDocument(),
    );
  });

  test('the About window can be opened again after closing it', async () => {
    const user = userEvent.setup();
    renderIconDashboard();
    await user.click(screen.getByRole('button', { name: 'About' }));
    await user.click(await screen.findByRole('button', { name: 'Close' }));
    await waitFor(() =>
      expect(screen.queryByRole('dialog', { name: 'About' })).not.toBeInTheDocument(),
    );

    await user.click(screen.getByRole('button', { name: 'About' }));

    await screen.findByRole('dialog', { name: 'About' });
  });
});
