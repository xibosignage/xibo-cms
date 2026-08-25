import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';

import type { LogEntry } from '@/types/log';

// --- Module Mocks ---

vi.mock('@/services/logApi', () => ({
  fetchLogs: vi.fn(),
  truncateLogs: vi.fn(),
}));

vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => [{ name: 'Log', path: '/advanced/log' }]),
}));

// Mock ResizeObserver used by DataTable column resizing. Must be a real class (not an
// arrow-function-backed vi.fn()) so `new ResizeObserver(...)` works when Preline's
// floating-ui autoUpdate (used by the DateFilter tooltip) constructs one.
global.ResizeObserver = class {
  observe = vi.fn();
  unobserve = vi.fn();
  disconnect = vi.fn();
};

// --- Mock Data ---

export const createMockLogEntry = (overrides?: Partial<LogEntry>): LogEntry => ({
  logId: 1,
  runNo: 'run-001',
  logDate: '2024-01-01 10:00:00',
  channel: 'WEB',
  page: '/layout',
  function: 'GET',
  message: 'Test log message',
  displayId: 0,
  type: 'INFO',
  display: '',
  sessionHistoryId: 1,
  userId: 1,
  ...overrides,
});

export const mockLogsList: LogEntry[] = [
  createMockLogEntry({ logId: 1, type: 'INFO', message: 'Info log message', channel: 'WEB' }),
  createMockLogEntry({ logId: 2, type: 'ERROR', message: 'Error log message', function: 'POST' }),
  createMockLogEntry({
    logId: 3,
    type: 'DEBUG',
    message: 'Debug log message',
    display: 'Screen 1',
  }),
];

export interface MockLogsPage {
  rows: LogEntry[];
  totalCount: number;
}

// useLogsData is an infinite query, so its result carries `data.pages`, not a flat row list.
// Every test that mocks the hook builds its return value here, so a future change to the hook's
// shape is a one-line fix rather than a hunt through each spec.
export const createMockLogsQuery = (
  pages: MockLogsPage[] = [{ rows: mockLogsList, totalCount: mockLogsList.length }],
  overrides: Record<string, unknown> = {},
) => ({
  data: { pages, pageParams: pages.map((_, index) => index) },
  isFetching: false,
  isFetchingNextPage: false,
  hasNextPage: false,
  fetchNextPage: vi.fn(),
  isError: false,
  error: null,
  ...overrides,
});

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
