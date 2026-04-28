import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';

import type { Session } from '@/types/session';

// --- Mocks ---
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}));

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
export const createMockSession = (overrides?: Partial<Session>): Session => ({
  userId: Math.floor(Math.random() * 1000),
  userName: 'john_doe',
  remoteAddress: '192.168.1.1',
  userAgent: 'Chrome 120',
  isExpired: false,
  lastAccessed: '2024-01-01T10:00:00Z',
  expiresAt: '2024-01-02T10:00:00Z',
  ...overrides,
});

export const mockSessionsList: Session[] = [
  createMockSession({ userId: 1, userName: 'alice_admin', expiresAt: '2024-01-02T10:00:01Z' }),
  createMockSession({
    userId: 2,
    userName: 'bob_user',
    isExpired: true,
    expiresAt: '2024-01-02T10:00:02Z',
  }),
  createMockSession({ userId: 3, userName: 'charlie_guest', expiresAt: '2024-01-02T10:00:03Z' }),
];

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
