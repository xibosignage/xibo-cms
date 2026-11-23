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

import { QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi, beforeEach, describe, test, expect } from 'vitest';

import ManageDisplayModal from '../../../components/ManageDisplayModal';
import { buildDisplay, mockDisplay, mockUser } from '../../fixtures/display';
import {
  EMPTY_BANDWIDTH,
  MANAGE_DEFAULTS,
  buildBandwidth,
  buildDependency,
  buildFault,
  buildManageData,
  buildManageLayout,
  buildManageMedia,
  buildManageWidget,
  buildWidgetData,
} from '../../fixtures/displayManage';

import { UserProvider } from '@/context/UserContext';
import {
  fetchBandwidthData,
  fetchDisplayManageData,
  fetchPlayerFaults,
} from '@/services/displaysApi';
import { testQueryClient } from '@/setupTests';
import { knownFailure } from '@/testUtils/knownFailure';
import { buildCurrentUser, PERSONAS } from '@/testUtils/personas';
import type { DisplayManageData } from '@/types/displayManage';
import type { User } from '@/types/user';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/components/ui/modals/Modal');
vi.mock('@/services/displaysApi', () => ({
  fetchDisplayManageData: vi.fn(),
  fetchPlayerFaults: vi.fn(),
  fetchBandwidthData: vi.fn(),
}));

// The two doughnut charts and the bandwidth bar chart draw SVG that JSDOM cannot
// lay out. The stubs list exactly the data each chart was handed, so a test can
// check what the user would see plotted.
vi.mock('@/pages/Dashboard/StatusDashboard/components/DisplayChart', () => ({
  default: ({ data, label }: { data: { name: string; value: number }[]; label?: string }) => (
    <ul aria-label={`${label} chart`}>
      {data.map((d) => (
        <li key={d.name}>{`${d.name}: ${d.value}`}</li>
      ))}
    </ul>
  ),
}));
vi.mock('recharts', () => ({
  ResponsiveContainer: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  BarChart: ({ data }: { data: { name: string; value: number }[] }) => (
    <ul aria-label="Bandwidth chart">
      {data.map((d) => (
        <li key={d.name}>{`${d.name}: ${d.value}`}</li>
      ))}
    </ul>
  ),
  Bar: () => null,
  CartesianGrid: () => null,
  Legend: () => null,
  XAxis: () => null,
  YAxis: () => null,
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

// Content Manager: a real non-admin persona with no displays.reporting feature.
const userWithoutReporting: User = buildCurrentUser(PERSONAS.contentManager, {
  settings: mockUser.settings,
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

const renderModal = ({
  user = mockUser,
  display = mockDisplay,
}: { user?: User; display?: typeof mockDisplay } = {}) => {
  const onClose = vi.fn();
  render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={user}>
        <ManageDisplayModal display={display} onClose={onClose} />
      </UserProvider>
    </QueryClientProvider>,
  );
  return { onClose };
};

const mockManage = (data: DisplayManageData) => {
  vi.mocked(fetchDisplayManageData).mockResolvedValue(data);
};

// Each section is a card with an <h3> title; return the card so a test can look inside it.
const section = async (title: string) => {
  const heading = await screen.findByRole('heading', { name: title });
  return heading.parentElement!.parentElement as HTMLElement;
};

const lastBandwidthCall = () => {
  const calls = vi.mocked(fetchBandwidthData).mock.calls;
  return calls[calls.length - 1]![0];
};

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('ManageDisplayModal', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    mockManage(buildManageData());
    vi.mocked(fetchPlayerFaults).mockResolvedValue([]);
    vi.mocked(fetchBandwidthData).mockResolvedValue(EMPTY_BANDWIDTH);
  });

  describe('loading', () => {
    test('asks for the manage data and faults of the display it was opened for', async () => {
      renderModal({ display: buildDisplay({ displayId: 42, display: 'Reception' }) });

      expect(await screen.findByRole('dialog', { name: 'Manage: Reception' })).toBeInTheDocument();
      await waitFor(() => expect(fetchDisplayManageData).toHaveBeenCalled());
      expect(vi.mocked(fetchDisplayManageData).mock.calls[0]![0]).toBe(42);
      expect(vi.mocked(fetchPlayerFaults).mock.calls[0]![0]).toBe(42);
    });

    test('shows nothing but the spinner until the manage data has arrived', async () => {
      let resolve!: (d: DisplayManageData) => void;
      vi.mocked(fetchDisplayManageData).mockReturnValue(new Promise((r) => (resolve = r)));
      renderModal();

      await screen.findByRole('dialog', { name: 'Manage: Test Display' });
      expect(screen.queryByRole('heading', { name: 'File Count' })).not.toBeInTheDocument();

      resolve(buildManageData());
      expect(await screen.findByRole('heading', { name: 'File Count' })).toBeInTheDocument();
    });

    test('if the manage data cannot be loaded, the error is shown and no sections appear', async () => {
      vi.mocked(fetchDisplayManageData).mockRejectedValue(new Error('Display not found'));
      renderModal();

      expect(await screen.findByRole('alert')).toHaveTextContent('Display not found');
      expect(screen.queryByRole('heading', { name: 'File Count' })).not.toBeInTheDocument();
    });

    test('Close closes the modal', async () => {
      const user = userEvent.setup();
      const { onClose } = renderModal();

      await user.click(await screen.findByRole('button', { name: 'Close' }));

      expect(onClose).toHaveBeenCalledOnce();
    });
  });

  describe('file status charts', () => {
    test('the file count chart plots downloaded against pending files', async () => {
      mockManage(
        buildManageData({
          status: {
            units: 'MB',
            countComplete: 12,
            countRemaining: 3,
            sizeComplete: 0,
            sizeRemaining: 0,
          },
        }),
      );
      renderModal();

      const chart = await screen.findByRole('list', { name: 'Files chart' });
      expect(within(chart).getByText('Downloaded: 12')).toBeInTheDocument();
      expect(within(chart).getByText('Pending: 3')).toBeInTheDocument();
    });

    test('the file size chart plots downloaded against pending size, in the units the server sent', async () => {
      mockManage(
        buildManageData({
          status: {
            units: 'GB',
            countComplete: 1,
            countRemaining: 0,
            sizeComplete: 4.5,
            sizeRemaining: 0.5,
          },
        }),
      );
      renderModal();

      const chart = await screen.findByRole('list', { name: 'GB chart' });
      expect(within(chart).getByText('Downloaded (GB): 4.5')).toBeInTheDocument();
      expect(within(chart).getByText('Pending (GB): 0.5')).toBeInTheDocument();
    });

    test('a display with no files yet shows "No file data available" instead of empty charts', async () => {
      renderModal();

      expect(await screen.findAllByText('No file data available')).toHaveLength(2);
      expect(screen.queryByRole('list', { name: 'Files chart' })).not.toBeInTheDocument();
    });
  });

  describe('player faults', () => {
    test('lists each reported fault, with a dash for the ids that are not set', async () => {
      vi.mocked(fetchPlayerFaults).mockResolvedValue([
        buildFault({ playerFaultId: 1, code: 5001, reason: 'Media download failed', layoutId: 8 }),
        buildFault({ playerFaultId: 2, code: 6002, reason: 'Widget render error', mediaId: 15 }),
      ]);
      renderModal();

      const card = await section('Reported Player Faults');
      const faultRow = (await within(card).findByText('Media download failed')).closest('tr')!;
      const cells = within(faultRow).getAllByRole('cell');
      expect(cells.map((c) => c.textContent)).toEqual([
        '5001',
        'Media download failed',
        '2026-03-01 09:00:00',
        '2026-03-02 09:00:00',
        '-',
        '8',
        '-',
      ]);
      expect(within(card).getByText('Widget render error')).toBeInTheDocument();
    });

    test('says so when the player has reported no faults', async () => {
      renderModal();

      const card = await section('Reported Player Faults');
      expect(await within(card).findByText('No reported faults')).toBeInTheDocument();
    });

    test('a failed faults request shows its error but leaves the rest of the modal working', async () => {
      vi.mocked(fetchPlayerFaults).mockRejectedValue(new Error('Faults unavailable'));
      mockManage(
        buildManageData({
          inventory: { ...buildManageData().inventory, layouts: [buildManageLayout()] },
        }),
      );
      renderModal();

      expect(await screen.findByText('Faults unavailable')).toBeInTheDocument();
      expect(screen.getByText('Lobby Layout')).toBeInTheDocument();
    });
  });

  describe('inventory', () => {
    test('lists the dependencies, layouts, media and widgets the player has been sent', async () => {
      mockManage(
        buildManageData({
          inventory: {
            dependencies: [buildDependency({ path: 'fonts.css', fileType: 'font' })],
            layouts: [buildManageLayout({ itemId: 5, layout: 'Lobby Layout' })],
            media: [
              buildManageMedia({ itemId: 6, name: 'welcome.png' }),
              buildManageMedia({ itemId: 7, name: 'menu.mp4' }),
            ],
            widgets: [buildManageWidget({ itemId: 9, widgetName: 'Clock' })],
            widgetData: [],
          },
        }),
      );
      renderModal();

      expect(within(await section('Dependencies')).getByText('fonts.css')).toBeInTheDocument();
      expect(within(await section('Layouts')).getByText('Lobby Layout')).toBeInTheDocument();
      const media = await section('Media');
      expect(within(media).getByText('welcome.png')).toBeInTheDocument();
      expect(within(media).getByText('menu.mp4')).toBeInTheDocument();
      expect(within(await section('Widgets')).getByText('Clock')).toBeInTheDocument();
    });

    // Widgets that were never given a name in the editor have widgetName null.
    test('a widget with no name is listed by its type instead', async () => {
      mockManage(
        buildManageData({
          inventory: {
            ...buildManageData().inventory,
            widgets: [buildManageWidget({ widgetName: null, widgetType: 'rss-ticker' })],
          },
        }),
      );
      renderModal();

      expect(within(await section('Widgets')).getByText('rss-ticker')).toBeInTheDocument();
    });

    test('each empty inventory list says it is empty', async () => {
      renderModal();

      expect(
        within(await section('Dependencies')).getByText('No dependencies'),
      ).toBeInTheDocument();
      expect(within(await section('Layouts')).getByText('No layouts')).toBeInTheDocument();
      expect(within(await section('Media')).getByText('No media')).toBeInTheDocument();
      expect(within(await section('Widgets')).getByText('No widgets')).toBeInTheDocument();
    });

    test('the Widget Data section appears only when the player holds widget data', async () => {
      renderModal();
      await section('Widgets');
      expect(screen.queryByRole('heading', { name: 'Widget Data' })).not.toBeInTheDocument();
    });

    test('widget data is listed by widget name, or by type when unnamed', async () => {
      mockManage(
        buildManageData({
          inventory: {
            ...buildManageData().inventory,
            widgetData: [
              buildWidgetData({ widgetId: 1, widgetName: 'Weather feed' }),
              buildWidgetData({ widgetId: 2, widgetName: null, widgetType: 'dataset' }),
            ],
          },
        }),
      );
      renderModal();

      const card = await section('Widget Data');
      expect(within(card).getByText('Weather feed')).toBeInTheDocument();
      expect(within(card).getByText('dataset')).toBeInTheDocument();
    });

    // Open — xibosignage/xibo#4067.
    knownFailure(
      'a downloaded file and a missing file are told apart without relying on colour or icon shape',
      async () => {
        mockManage(
          buildManageData({
            inventory: {
              ...buildManageData().inventory,
              dependencies: [
                buildDependency({ path: 'done.js', complete: 1 }),
                buildDependency({ path: 'missing.js', complete: 0 }),
              ],
            },
          }),
        );
        renderModal();
        const card = await section('Dependencies');
        const completeCell = (path: string) =>
          within(within(card).getByText(path).closest('tr')!).getAllByRole('cell')[3]!;
        return { done: completeCell('done.js'), missing: completeCell('missing.js') };
      },
      ({ done, missing }) => expect(done.textContent).not.toBe(missing.textContent),
    );
  });

  describe('bandwidth', () => {
    test('a user with display reporting sees bandwidth for the default period the server suggested', async () => {
      vi.mocked(fetchBandwidthData).mockResolvedValue(
        buildBandwidth(['Mon', 'Tue', 'Wed'], [120, 80, 40]),
      );
      renderModal({ display: buildDisplay({ displayId: 42 }) });

      const chart = await screen.findByRole('list', { name: 'Bandwidth chart' });
      expect(
        within(chart)
          .getAllByRole('listitem')
          .map((li) => li.textContent),
      ).toEqual(['Mon: 120', 'Tue: 80', 'Wed: 40']);
      expect(lastBandwidthCall()).toEqual({
        displayId: 42,
        fromDt: MANAGE_DEFAULTS.fromDate,
        toDt: MANAGE_DEFAULTS.toDate,
      });
    });

    // The server can send fewer values than labels; a missing day must plot as 0
    // rather than vanish or break the chart.
    test('a day with no value from the server is plotted as 0', async () => {
      vi.mocked(fetchBandwidthData).mockResolvedValue(buildBandwidth(['Mon', 'Tue'], [120]));
      renderModal();

      const chart = await screen.findByRole('list', { name: 'Bandwidth chart' });
      expect(within(chart).getByText('Tue: 0')).toBeInTheDocument();
    });

    test('says so when there is no bandwidth data for the period', async () => {
      renderModal();

      expect(
        await screen.findByText('No bandwidth data for the selected period'),
      ).toBeInTheDocument();
    });

    test('a user without display reporting gets no bandwidth section and no bandwidth request', async () => {
      renderModal({ user: userWithoutReporting });

      await section('Widgets');
      expect(screen.queryByRole('heading', { name: 'Bandwidth' })).not.toBeInTheDocument();
      expect(fetchBandwidthData).not.toHaveBeenCalled();
    });

    test('changing the From date asks for bandwidth from that date', async () => {
      renderModal();
      const from = await screen.findByLabelText('From');
      await waitFor(() => expect(fetchBandwidthData).toHaveBeenCalledOnce());

      // A date input only takes a complete value, so set it in one go.
      fireEvent.change(from, { target: { value: '2026-01-05' } });

      await waitFor(() => expect(fetchBandwidthData).toHaveBeenCalledTimes(2));
      expect(lastBandwidthCall()).toEqual(
        expect.objectContaining({ fromDt: '2026-01-05', toDt: MANAGE_DEFAULTS.toDate }),
      );
    });

    test('changing the To date asks for bandwidth up to that date', async () => {
      renderModal();
      const to = await screen.findByLabelText('To');
      await waitFor(() => expect(fetchBandwidthData).toHaveBeenCalledOnce());

      fireEvent.change(to, { target: { value: '2026-02-10' } });

      await waitFor(() => expect(fetchBandwidthData).toHaveBeenCalledTimes(2));
      expect(lastBandwidthCall()).toEqual(
        expect.objectContaining({ fromDt: MANAGE_DEFAULTS.fromDate, toDt: '2026-02-10' }),
      );
    });

    test('clearing a date goes back to the default period rather than sending an empty date', async () => {
      renderModal();
      const from = await screen.findByLabelText('From');
      fireEvent.change(from, { target: { value: '2026-01-05' } });
      await waitFor(() => expect(lastBandwidthCall().fromDt).toBe('2026-01-05'));

      fireEvent.change(from, { target: { value: '' } });

      // The default period was already fetched on open and is still cached, so going
      // back to it shows that data again without a new request.
      await waitFor(() => expect(from).toHaveValue('2026-02-22'));
      const sent = vi.mocked(fetchBandwidthData).mock.calls.map((c) => c[0].fromDt);
      expect(sent).toEqual([MANAGE_DEFAULTS.fromDate, '2026-01-05']);
    });
  });
});
