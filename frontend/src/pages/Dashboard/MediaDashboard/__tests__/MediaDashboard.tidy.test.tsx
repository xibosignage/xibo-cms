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

import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type * as ReactI18nextModule from 'react-i18next';
import type * as RechartsModule from 'recharts';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import {
  buildDashboardMediaRows,
  mockMediaDashboardData,
  tidyUser,
} from './fixtures/mediaDashboard';
import {
  getTidyConfirm,
  openTidyDialog,
  renderMediaDashboardPage,
} from './helpers/renderMediaDashboardPage';
import {
  mockMediaDashboard,
  mockTidy,
  mockTidyError,
  mockTidyPending,
  mockUnusedMedia,
} from './mocks/mediaDashboardApi';

import { notify } from '@/components/ui/Notification';
import { fetchMediaDashboard, fetchUnusedMedia } from '@/services/dashboardApi';
import { tidyLibrary } from '@/services/mediaApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { PERSONAS } from '@/testUtils/personas';

// =============================================================================
// Module mocks
// =============================================================================

vi.mock('@/services/dashboardApi', () => ({
  fetchMediaDashboard: vi.fn(),
  fetchUnusedMedia: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
  fetchUnreleasedMedia: vi.fn().mockResolvedValue({ rows: [], totalCount: 0 }),
}));
vi.mock('@/services/mediaApi', () => ({
  tidyLibrary: vi.fn().mockResolvedValue({ countDeleted: 0 }),
}));
vi.mock('@/components/ui/Notification', () => ({
  notify: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));
vi.mock('@/components/ui/modals/Modal');
vi.mock('recharts', async (importOriginal) => {
  const actual = await importOriginal<typeof RechartsModule>();
  const { cloneElement } = await import('react');
  return {
    ...actual,
    ResponsiveContainer: ({ children }: { children: React.ReactElement }) =>
      cloneElement(children, { width: 200, height: 200 } as Record<string, unknown>),
  };
});
// The success message carries the deleted count via {{count}}; without this the toast text
// would be the raw key, which can't be asserted (§8).
vi.mock('react-i18next', async (importOriginal) => {
  const actual = await importOriginal<typeof ReactI18nextModule>();
  const t = (key: string, opts?: Record<string, unknown>) =>
    key.replace(/{{(\w+)}}/g, (_, name: string) => String(opts?.[name] ?? ''));
  return { ...actual, useTranslation: () => ({ t, i18n: { language: 'en' } }) };
});

// =============================================================================
// Tests
// =============================================================================

// Content Manager is the default user: it may modify the library but is not a Super Admin,
// so the permission check really runs (Super Admin passes every feature check).
describe('Media Manager Dashboard - who sees Tidy Library', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockMediaDashboard(mockMediaDashboardData);
  });

  test('shown when tidying is switched on and the user may modify the library', async () => {
    renderMediaDashboardPage(tidyUser());

    await screen.findByRole('button', { name: /tidy library/i });
  });

  test('hidden when tidying is switched off in Settings', async () => {
    renderMediaDashboardPage(tidyUser(PERSONAS.contentManager, '0'));
    await screen.findByText('Library Count');

    expect(screen.queryByRole('button', { name: /tidy library/i })).not.toBeInTheDocument();
  });

  test('hidden when the tidy setting is missing', async () => {
    renderMediaDashboardPage(tidyUser(PERSONAS.contentManager, null));
    await screen.findByText('Library Count');

    expect(screen.queryByRole('button', { name: /tidy library/i })).not.toBeInTheDocument();
  });

  // Display Manager lacks library.modify (rule 1.11: test as the user who is not allowed).
  test('hidden for a user who may not modify the library, even with tidying on', async () => {
    renderMediaDashboardPage(tidyUser(PERSONAS.displayManager, '1'));
    await screen.findByText('Library Count');

    expect(screen.queryByRole('button', { name: /tidy library/i })).not.toBeInTheDocument();
  });
});

describe('Media Manager Dashboard - tidying the library', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockMediaDashboard(mockMediaDashboardData);
  });

  test('clicking Tidy Library opens the confirmation with generic files unticked', async () => {
    const user = userEvent.setup();
    renderMediaDashboardPage(tidyUser());

    const dialog = await openTidyDialog(user);

    expect(within(dialog).getByRole('heading', { name: 'Tidy Library' })).toBeInTheDocument();
    expect(within(dialog).getByRole('checkbox', { name: 'Tidy Generic Files' })).not.toBeChecked();
  });

  // Payload (rule 1.1). Both choices come from the same checkbox state, so both are tested.
  test('confirming without generic files asks the server to keep them, once', async () => {
    const user = userEvent.setup();
    mockTidy(0);
    renderMediaDashboardPage(tidyUser());

    const dialog = await openTidyDialog(user);
    await user.click(getTidyConfirm(dialog));

    await waitFor(() => expect(tidyLibrary).toHaveBeenCalledTimes(1));
    expect(tidyLibrary).toHaveBeenCalledWith(false);
  });

  test('ticking Tidy Generic Files asks the server to remove them too', async () => {
    const user = userEvent.setup();
    mockTidy(0);
    renderMediaDashboardPage(tidyUser());

    const dialog = await openTidyDialog(user);
    await user.click(within(dialog).getByRole('checkbox', { name: 'Tidy Generic Files' }));
    await user.click(getTidyConfirm(dialog));

    await waitFor(() => expect(tidyLibrary).toHaveBeenCalledTimes(1));
    expect(tidyLibrary).toHaveBeenCalledWith(true);
  });

  test('a successful tidy reports the count, closes the window and reloads the totals', async () => {
    const user = userEvent.setup();
    mockTidy(3);
    renderMediaDashboardPage(tidyUser());
    await waitFor(() => expect(fetchMediaDashboard).toHaveBeenCalled());
    // React Query may fetch more than once on its own, so compare with the count before.
    const loadsBefore = vi.mocked(fetchMediaDashboard).mock.calls.length;

    const dialog = await openTidyDialog(user);
    await user.click(getTidyConfirm(dialog));

    await waitFor(() =>
      expect(notify.success).toHaveBeenCalledWith('Library tidy complete. 3 item(s) removed.'),
    );
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    await waitFor(() =>
      expect(vi.mocked(fetchMediaDashboard).mock.calls.length).toBeGreaterThan(loadsBefore),
    );
  });

  test('a failed tidy keeps the window open with an error and no success message', async () => {
    const user = userEvent.setup();
    mockTidyError();
    renderMediaDashboardPage(tidyUser());

    const dialog = await openTidyDialog(user);
    await user.click(getTidyConfirm(dialog));

    await within(dialog).findByText('Failed to tidy the library.');
    expect(tidyLibrary).toHaveBeenCalledTimes(1);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(notify.success).not.toHaveBeenCalled();
  });

  test('trying again after a failure clears the old error while the new attempt runs', async () => {
    const user = userEvent.setup();
    mockTidyError();
    renderMediaDashboardPage(tidyUser());

    const dialog = await openTidyDialog(user);
    await user.click(getTidyConfirm(dialog));
    await within(dialog).findByText('Failed to tidy the library.');

    mockTidyPending();
    await user.click(getTidyConfirm(dialog));

    await waitFor(() =>
      expect(within(dialog).queryByText('Failed to tidy the library.')).not.toBeInTheDocument(),
    );
  });

  // Pending state (§4.4). The early-return guard inside handleConfirmTidy can't be reached
  // through the UI while the button is disabled, so only the disabled button is tested.
  test('while tidying, the button reads "Tidying…", is disabled, and a second click sends nothing', async () => {
    const user = userEvent.setup();
    const resolve = mockTidyPending();
    renderMediaDashboardPage(tidyUser());

    const dialog = await openTidyDialog(user);
    await user.click(getTidyConfirm(dialog));

    const busy = await within(dialog).findByRole('button', { name: 'Tidying…' });
    expect(busy).toBeDisabled();
    await user.click(busy);
    expect(tidyLibrary).toHaveBeenCalledTimes(1);

    resolve({ countDeleted: 0 });
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });

  test('Cancel closes the window without tidying', async () => {
    const user = userEvent.setup();
    renderMediaDashboardPage(tidyUser());

    const dialog = await openTidyDialog(user);
    await user.click(within(dialog).getByRole('button', { name: 'Cancel' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    expect(tidyLibrary).not.toHaveBeenCalled();
  });

  // xibosignage/xibo#ISSUE_TIDY_RELOAD: a tidy deletes exactly the media the Unused Media table
  // lists, but only the totals are reloaded, so the deleted rows stay on screen.
  knownFailure(
    'after a tidy, the Unused Media list is reloaded',
    async () => {
      const user = userEvent.setup();
      mockUnusedMedia({ rows: buildDashboardMediaRows(2), totalCount: 2 });
      mockTidy(2);
      renderMediaDashboardPage(tidyUser());
      await waitFor(() => expect(fetchUnusedMedia).toHaveBeenCalled());
      const loadsBefore = vi.mocked(fetchUnusedMedia).mock.calls.length;

      const dialog = await openTidyDialog(user);
      await user.click(getTidyConfirm(dialog));
      // Control: the tidy itself succeeded.
      await waitFor(() => expect(notify.success).toHaveBeenCalled());
      return loadsBefore;
    },
    (loadsBefore) =>
      waitFor(() =>
        expect(vi.mocked(fetchUnusedMedia).mock.calls.length).toBeGreaterThan(loadsBefore),
      ),
  );
});
