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

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';

import type { AuditLog } from '@/types/auditTrail';

// --- Module Mocks ---

vi.mock('@/services/auditTrailApi', () => ({
  fetchAuditTrail: vi.fn(),
  exportAuditTrail: vi.fn(),
}));

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Audit Trail', path: '/advanced/audit-trail' }]),
}));

// DateFilter renders DatePicker which imports react-day-picker (heavy) and uses
// useUserContext. In unit tests we only care that filter labels are present; the
// calendar interaction is covered by the export modal tests which mock DateFilter
// independently. A lightweight shim here prevents userEvent from blocking on
// react-day-picker's async mount effects.
vi.mock('@/components/ui/DateFilter', () => ({
  default: ({
    label,
    name,
  }: {
    label: string;
    name: string;
    value: string;
    onChange: (name: string, val: string | null) => void;
  }) => (
    <div>
      <label htmlFor={name}>{label}</label>
    </div>
  ),
}));

// Mock ResizeObserver used by DataTable column resizing
global.ResizeObserver = vi.fn().mockImplementation(() => ({
  observe: vi.fn(),
  unobserve: vi.fn(),
  disconnect: vi.fn(),
}));

// --- Mock Data ---

export const createMockAuditLog = (overrides?: Partial<AuditLog>): AuditLog => ({
  logId: 1,
  logDate: 1704067200,
  userName: 'admin',
  entity: 'Layout',
  entityId: 42,
  ipAddress: '127.0.0.1',
  message: 'Layout saved',
  objectAfter: null,
  ...overrides,
});

export const mockAuditLogList: AuditLog[] = [
  createMockAuditLog({ logId: 1, entity: 'Layout', message: 'Layout saved' }),
  createMockAuditLog({
    logId: 2,
    entity: 'User',
    message: 'User updated',
    userName: 'editor',
  }),
  createMockAuditLog({
    logId: 3,
    entity: 'Campaign',
    message: 'Campaign deleted',
    objectAfter: { name: 'Test Campaign', status: 'active' },
  }),
];

// --- Render Helpers ---

export const createTestQueryClient = (): QueryClient =>
  new QueryClient({ defaultOptions: { queries: { retry: false } } });

export function renderWithProviders(ui: ReactElement) {
  const queryClient = createTestQueryClient();
  const user = userEvent.setup();

  const Wrapper = ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>{children}</MemoryRouter>
    </QueryClientProvider>
  );

  return {
    user,
    queryClient,
    ...render(ui, { wrapper: Wrapper }),
  };
}
