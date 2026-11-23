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
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps } from 'react';
import { vi, beforeEach, beforeAll, afterAll, describe, test, expect } from 'vitest';

import DisplayInfoPanel from '../../components/DisplayInfoPanel';
import { buildDisplay, mockUser } from '../fixtures/display';

import { UserProvider } from '@/context/UserContext';
import { fetchDisplayStatusWindow } from '@/services/displaysApi';
import { testQueryClient } from '@/setupTests';
import { setTimezone } from '@/testUtils/timezone';
import type { User } from '@/types/user';

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/services/displaysApi', () => ({
  fetchDisplayStatusWindow: vi.fn(),
}));

// ─── Timezones ────────────────────────────────────────────────────────────────

const BROWSER_TZ = 'Pacific/Auckland'; // UTC+13 in March
const CMS_TZ = 'America/New_York'; // UTC-4 in March

const originalTz = process.env.TZ;
beforeAll(() => {
  process.env.TZ = BROWSER_TZ;
});
afterAll(() => {
  setTimezone(originalTz);
});

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const cmsUser: User = {
  ...mockUser,
  settings: { ...mockUser.settings, defaultTimezone: CMS_TZ, DATE_FORMAT_JS: 'YYYY-MM-DD HH:mm' },
};

// 02:00 UTC on 10 March 2026 = 22:00 on 9 March in New York = 15:00 on 10 March in Auckland.
const LAST_ACCESSED = Date.UTC(2026, 2, 10, 2, 0, 0) / 1000;

const onlineDisplay = buildDisplay({
  displayId: 42,
  display: 'Reception',
  loggedIn: 1,
  licensed: 1,
  clientType: 'android',
  clientVersion: 'R406',
  clientAddress: '10.0.0.15',
  currentLayout: 'Lobby Layout',
  resolution: '1920x1080',
  orientation: 'landscape',
  lastAccessed: LAST_ACCESSED,
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

const renderPanel = (props: Partial<ComponentProps<typeof DisplayInfoPanel>> = {}) => {
  const onClose = vi.fn();
  const utils = render(
    <QueryClientProvider client={testQueryClient}>
      <UserProvider initialUser={cmsUser}>
        <DisplayInfoPanel display={onlineDisplay} onClose={onClose} {...props} />
      </UserProvider>
    </QueryClientProvider>,
  );
  return { onClose, ...utils };
};

const statusSignal = () => vi.mocked(fetchDisplayStatusWindow).mock.calls[0]![1] as AbortSignal;

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('DisplayInfoPanel', () => {
  beforeEach(() => {
    testQueryClient.clear();
    vi.clearAllMocks();
    vi.mocked(fetchDisplayStatusWindow).mockResolvedValue({});
  });

  test('the test really runs with the browser and the CMS in different timezones', () => {
    expect(new Date('2026-03-10T00:00:00Z').getTimezoneOffset()).toBe(-13 * 60);
  });

  describe('status', () => {
    test('an online, authorised display says so', async () => {
      renderPanel();

      expect(await screen.findByText('Online')).toBeInTheDocument();
      expect(screen.getByText('Authorised')).toBeInTheDocument();
    });

    test('an offline, unauthorised display says so', async () => {
      renderPanel({ display: buildDisplay({ loggedIn: 0, licensed: 0 }) });

      expect(await screen.findByText('Offline')).toBeInTheDocument();
      expect(screen.getByText('Unauthorised')).toBeInTheDocument();
      expect(screen.queryByText('Online')).not.toBeInTheDocument();
      expect(screen.queryByText('Authorised')).not.toBeInTheDocument();
    });
  });

  describe('details', () => {
    test('shows the player type, version, address, current layout, resolution and orientation', async () => {
      renderPanel();

      await screen.findByText('Online');
      for (const value of [
        'android',
        'R406',
        '10.0.0.15',
        'Lobby Layout',
        '1920x1080',
        'landscape',
      ]) {
        expect(screen.getByText(value)).toBeInTheDocument();
      }
    });

    // A display that has never connected has none of these, and must not show "null".
    test('a display that has never connected shows a dash for each missing detail', async () => {
      renderPanel({ display: buildDisplay() });

      await screen.findByText('Offline');
      // player type, version, IP, current layout, resolution, orientation, last accessed
      expect(screen.getAllByText('-')).toHaveLength(7);
      expect(screen.queryByText('null')).not.toBeInTheDocument();
    });

    test('the last-accessed time is shown in the CMS timezone, not the browser timezone', async () => {
      renderPanel();

      expect(await screen.findByText('2026-03-09 22:00')).toBeInTheDocument();
      expect(screen.queryByText('2026-03-10 15:00')).not.toBeInTheDocument();
    });

    test('lists the display tags, and leaves the Tags heading out when there are none', async () => {
      const { unmount } = renderPanel({
        display: buildDisplay({
          tags: [
            { tag: 'lobby', tagId: 1, value: '' },
            { tag: 'floor-2', tagId: 2, value: '' },
          ],
        }),
      });
      expect(await screen.findByText('lobby')).toBeInTheDocument();
      expect(screen.getByText('floor-2')).toBeInTheDocument();
      unmount();

      renderPanel({ display: buildDisplay({ tags: [] }) });
      await screen.findByText('Offline');
      expect(screen.queryByText('TAGS')).not.toBeInTheDocument();
    });

    test('with no display there are no details, only the panel header', async () => {
      renderPanel({ display: null });

      expect(screen.getByText('Display Details')).toBeInTheDocument();
      expect(screen.queryByText('STATUS')).not.toBeInTheDocument();
      expect(fetchDisplayStatusWindow).not.toHaveBeenCalled();
    });
  });

  // The Player Status block is whatever the player last reported. The API returns a
  // key/value object, a plain string with <br> line breaks, or an empty array.
  describe('player status', () => {
    test('asks for the player status of this display', async () => {
      renderPanel();

      await waitFor(() => expect(fetchDisplayStatusWindow).toHaveBeenCalledOnce());
      expect(vi.mocked(fetchDisplayStatusWindow).mock.calls[0]![0]).toBe(42);
    });

    test('a key/value status is listed as labelled values', async () => {
      vi.mocked(fetchDisplayStatusWindow).mockResolvedValue({
        currentLayoutId: 12,
        storage: '4.2 GB free',
      });
      renderPanel();

      expect(await screen.findByText('PLAYER STATUS')).toBeInTheDocument();
      expect(screen.getByText('currentLayoutId')).toBeInTheDocument();
      expect(screen.getByText('12')).toBeInTheDocument();
      expect(screen.getByText('4.2 GB free')).toBeInTheDocument();
    });

    test('a text status keeps its line breaks', async () => {
      vi.mocked(fetchDisplayStatusWindow).mockResolvedValue('Line one<br>Line two<br />Line three');
      renderPanel();

      await screen.findByText('PLAYER STATUS');
      // The block is one <pre>; its text has real newlines where the <br>s were.
      const block = screen.getByText(/Line one/);
      expect(block.textContent).toBe('Line one\nLine two\nLine three');
    });

    test.each([
      ['an empty object', {}],
      ['an empty array', []],
      ['a blank string', '   '],
    ])('%s means there is no status to show, so the block is left out', async (_label, value) => {
      vi.mocked(fetchDisplayStatusWindow).mockResolvedValue(value);
      renderPanel();

      await waitFor(() => expect(fetchDisplayStatusWindow).toHaveBeenCalled());
      await screen.findByText('Online');
      expect(screen.queryByText('PLAYER STATUS')).not.toBeInTheDocument();
    });

    test('a failed status request leaves the block out and the rest of the panel intact', async () => {
      vi.mocked(fetchDisplayStatusWindow).mockRejectedValue(new Error('timeout'));
      renderPanel();

      await waitFor(() => expect(fetchDisplayStatusWindow).toHaveBeenCalled());
      expect(await screen.findByText('Online')).toBeInTheDocument();
      expect(screen.queryByText('PLAYER STATUS')).not.toBeInTheDocument();
    });

    test('a closed panel does not ask for the player status', async () => {
      renderPanel({ isOpen: false });

      await screen.findByText('Online');
      expect(fetchDisplayStatusWindow).not.toHaveBeenCalled();
    });

    test('closing the panel cancels a status request still in flight', async () => {
      let respond!: (value: Record<string, string>) => void;
      vi.mocked(fetchDisplayStatusWindow).mockReturnValue(new Promise((res) => (respond = res)));
      const { unmount } = renderPanel();

      await waitFor(() => expect(fetchDisplayStatusWindow).toHaveBeenCalled());
      expect(statusSignal().aborted).toBe(false);
      unmount();

      expect(statusSignal().aborted).toBe(true);
      respond({}); // settle it so nothing is left pending after the test
    });
  });

  test('the Close button closes the panel', async () => {
    const user = userEvent.setup();
    const { onClose } = renderPanel();

    await user.click(screen.getByRole('button', { name: 'Close' }));

    expect(onClose).toHaveBeenCalledOnce();
  });
});
