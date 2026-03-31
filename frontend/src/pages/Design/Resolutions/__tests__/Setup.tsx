import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';

// Mock the API calls
vi.mock('@/services/resolutionApi', () => ({
  fetchResolution: vi.fn(),
  createResolution: vi.fn(),
  updateResolution: vi.fn(),
  deleteResolution: vi.fn(),
}));

// Mock translations to just return the key
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key: string) => key }),
}));

// Mock the tabs hook to bypass the missing UserProvider error
vi.mock('@/hooks/useFilteredTabs', () => ({
  useFilteredTabs: vi.fn(() => []),
}));

// FIX: Mock the table state so the component bypasses the loading skeleton
vi.mock('@/hooks/useTableState', () => {
  const React = require('react');
  return {
    useTableState: (_key: string, initial: any) => {
      // Use real state so interactions (like typing in search) actually update the UI
      const [pagination, setPagination] = React.useState(initial.pagination);
      const [sorting, setSorting] = React.useState(initial.sorting);
      const [columnVisibility, setColumnVisibility] = React.useState(initial.columnVisibility);
      const [globalFilter, setGlobalFilter] = React.useState(initial.globalFilter);
      const [filterInputs, setFilterInputs] = React.useState(initial.filterInputs);

      return {
        pagination, setPagination,
        sorting, setSorting,
        columnVisibility, setColumnVisibility,
        globalFilter, setGlobalFilter,
        debouncedFilter: globalFilter, // Pass-through instantly for tests
        filterInputs, setFilterInputs,
        isHydrated: true, // <-- CRITICAL: Forces the DataTable to render instantly
      };
    },
  };
});

// Provide a fresh QueryClient and Router for each test
export const renderWithClient = (ui: React.ReactElement) => {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        {ui}
      </MemoryRouter>
    </QueryClientProvider>
  );
};

export const mockResolutions = [
  { resolutionId: 1, resolution: '1080p', width: 1920, height: 1080, enabled: 1 },
  { resolutionId: 2, resolution: '720p', width: 1280, height: 720, enabled: 1 },
];