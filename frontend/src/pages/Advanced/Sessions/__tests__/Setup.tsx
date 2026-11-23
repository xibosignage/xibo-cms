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

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';

// --- Mocks ---

vi.mock('@/services/sessionApi', () => ({
  fetchSession: vi.fn(),
  logoutSession: vi.fn(),
}));

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [
    { name: 'Sessions', path: '/advanced/sessions' }, // Providing a dummy tab array
  ]),
}));

// Mock ResizeObserver for DataTable
global.ResizeObserver = vi.fn().mockImplementation(() => ({
  observe: vi.fn(),
  unobserve: vi.fn(),
  disconnect: vi.fn(),
}));

// --- Mock Data Generators ---
// Re-exported so the existing specs keep importing them from here.
export { createMockSession, mockSessionsList } from './fixtures/session';

// --- Render Wrapper ---
export const createTestQueryClient = (): QueryClient => {
  return new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });
};

export function renderWithProviders(ui: ReactElement) {
  const queryClient = createTestQueryClient();
  const user = userEvent.setup();

  const Wrapper = ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      {/* Add MemoryRouter here to satisfy TabNav's useNavigate hook */}
      <MemoryRouter>{children}</MemoryRouter>
    </QueryClientProvider>
  );

  return {
    user,
    queryClient,
    ...render(ui, { wrapper: Wrapper }),
  };
}
