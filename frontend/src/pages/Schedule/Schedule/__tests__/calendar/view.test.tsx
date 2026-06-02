/* Copyright (C) 2026 Xibo Signage Ltd — AGPL-3.0 */

import { QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type React from 'react';
import { MemoryRouter } from 'react-router-dom';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import Events from '../../Events';
import { useEventData } from '../../hooks/useEventData';

import { UserProvider } from '@/context/UserContext';
import { testQueryClient } from '@/setupTests';
import type { User } from '@/types/user';

// ─── Mocks ────────────────────────────────────────────────────────────────────

const t = (key: string) => key;
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t, i18n: { changeLanguage: vi.fn() } }),
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/services/userApi', () => ({
  fetchUserPreference: vi.fn().mockResolvedValue(null),
  saveUserPreference: vi.fn().mockResolvedValue(undefined),
}));

vi.mock('@/services/campaignApi', () => ({
  fetchCampaigns: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));

vi.mock('../../hooks/useEventData', () => ({
  useEventData: vi.fn(),
}));

vi.mock('@/hooks/useDebounce');

vi.mock('@/components/ui/modals/Modal');

vi.mock('../../components/DisplayGroupMultiSelect', () => ({
  DisplayGroupMultiSelect: () => null,
}));

vi.mock('../../components/EventModals', () => ({
  EventModals: () => null,
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const mockUser: User = {
  userId: 1,
  userName: 'TestUser',
  userTypeId: 1,
  groupId: 1,
  features: {},
  settings: {
    defaultTimezone: 'UTC',
    defaultLanguage: 'en',
    DATE_FORMAT_JS: 'DD/MM/YYYY',
    TIME_FORMAT_JS: 'HH:mm',
  },
};

const EMPTY_EVENT_TABLE = {
  data: { rows: [], totalCount: 0 },
  isFetching: false,
  isError: false,
  error: null,
};

// ─── Render helper ────────────────────────────────────────────────────────────

const renderPage = () => {
  testQueryClient.setQueryData(['userPref', 'event_page'], null);
  testQueryClient.setQueryData(['userPref', 'event_page_date_range'], null);
  return render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={mockUser}>
        <MemoryRouter>
          <Events />
        </MemoryRouter>
      </UserProvider>
    </QueryClientProvider>,
  );
};

/** Wait until the preference-loading skeleton is gone (isHydrated = true). */
async function waitForHydration() {
  await waitFor(() => {
    expect(screen.queryByText('Loading your events preferences...')).not.toBeInTheDocument();
  });
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('Events page – calendar view toggle', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(useEventData).mockReturnValue(
      EMPTY_EVENT_TABLE as unknown as ReturnType<typeof useEventData>,
    );
  });

  test('initial render shows table view, not calendar heading', async () => {
    renderPage();
    await waitForHydration();

    // DataCalendar's "Calendar View" heading is absent in table mode
    expect(screen.queryByText('Calendar View')).not.toBeInTheDocument();
  });

  test('clicking Calendar View toggle switches to calendar view', async () => {
    const user = userEvent.setup();
    renderPage();
    await waitForHydration();

    await user.click(screen.getByTitle('Calendar View'));

    await waitFor(() => {
      // DataCalendar renders a span with this text when active
      expect(screen.getByText('Calendar View')).toBeInTheDocument();
    });
  });

  test('clicking Table View toggle switches back from calendar view', async () => {
    const user = userEvent.setup();
    renderPage();
    await waitForHydration();

    await user.click(screen.getByTitle('Calendar View'));
    await waitFor(() => expect(screen.getByText('Calendar View')).toBeInTheDocument());

    await user.click(screen.getByTitle('Table View'));

    await waitFor(() => {
      expect(screen.queryByText('Calendar View')).not.toBeInTheDocument();
    });
  });

  test('loading overlay appears while calendar data is fetching', async () => {
    const user = userEvent.setup();
    vi.mocked(useEventData).mockReturnValue({
      ...EMPTY_EVENT_TABLE,
      isFetching: true,
    } as unknown as ReturnType<typeof useEventData>);

    renderPage();
    await waitForHydration();

    await user.click(screen.getByTitle('Calendar View'));

    await waitFor(() => {
      expect(screen.getByRole('status')).toBeInTheDocument();
    });
  });

  test('date range view-mode selector is hidden in calendar mode', async () => {
    const user = userEvent.setup();
    renderPage();
    await waitForHydration();

    // In table mode, DateRangeController shows a dropdown button with the active view name
    expect(screen.getByRole('button', { name: 'Day' })).toBeInTheDocument();

    await user.click(screen.getByTitle('Calendar View'));

    // lockedViewMode='month' hides the dropdown — no "Day"/"Week"/etc. button
    await waitFor(() => {
      expect(screen.queryByRole('button', { name: 'Day' })).not.toBeInTheDocument();
    });
  });

  test('date range view-mode selector reappears after switching back to table', async () => {
    const user = userEvent.setup();
    renderPage();
    await waitForHydration();

    await user.click(screen.getByTitle('Calendar View'));
    await waitFor(() => {
      expect(screen.queryByRole('button', { name: 'Day' })).not.toBeInTheDocument();
    });

    await user.click(screen.getByTitle('Table View'));

    await waitFor(() => {
      // lockedViewMode is cleared — dropdown reappears; DateRangeController stays in
      // the last active viewMode ('Month' after the calendar round-trip)
      expect(
        screen.getByRole('button', { name: /^(Day|Week|Month|Year|Always|Custom)$/ }),
      ).toBeInTheDocument();
    });
  });
});
