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

import { cleanup, render, screen, waitFor } from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import type { ComponentProps } from 'react';
import { vi, beforeEach, afterEach, describe, test, expect } from 'vitest';

import CommandDropdown from '../CommandDropdown';

import { installIntersectionObserverStub } from '@/pages/Dashboard/PlaylistDashboard/__tests__/helpers/intersectionObserverStub';
import { fetchCommands } from '@/services/commandApi';
import type { FetchCommandsResponse } from '@/services/commandApi';
import { deferred } from '@/testUtils/deferred';
import { knownFailure } from '@/testUtils/knownFailure';
import type { Command } from '@/types/command';

// The command picker used by Displays → Send Command. SelectDropdown is REAL here,
// including its scroll-to-load-more, because a paginated dropdown stubbed as a native
// <select> is how four pagination faults went unnoticed elsewhere.

// ─── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('@/services/commandApi', () => ({
  fetchCommands: vi.fn(),
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────

const command = (commandId: number, name = `Command ${commandId}`) =>
  ({ commandId, command: name }) as unknown as Command;

// 25 commands on the server, served in pages of ten.
const page = (start: number, total = 25): FetchCommandsResponse =>
  ({
    rows: Array.from({ length: Math.min(10, total - start) }, (_, i) => command(start + i + 1)),
    totalCount: total,
  }) as unknown as FetchCommandsResponse;

// ─── Helpers ──────────────────────────────────────────────────────────────────

const renderDropdown = (props: Partial<ComponentProps<typeof CommandDropdown>> = {}) => {
  const onSelect = vi.fn();
  render(<CommandDropdown value={null} onSelect={onSelect} {...props} />);
  return { onSelect };
};

const openDropdown = async (user: UserEvent) => {
  await waitFor(() => expect(fetchCommands).toHaveBeenCalled());
  await user.click(screen.getByRole('combobox', { name: 'Command' }));
  return screen.findByRole('listbox');
};

const optionNames = () => screen.queryAllByRole('option').map((o) => o.textContent);

const callArgs = (n: number) => vi.mocked(fetchCommands).mock.calls[n]![0]!;

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('CommandDropdown', () => {
  let observer: ReturnType<typeof installIntersectionObserverStub>;

  beforeEach(() => {
    vi.clearAllMocks();
    observer = installIntersectionObserverStub();
    vi.mocked(fetchCommands).mockImplementation(async (opts) => page(opts?.start ?? 0));
  });

  afterEach(() => {
    // Unmount BEFORE removing the IntersectionObserver stub. Vitest runs afterEach hooks
    // in reverse order, so Testing Library's own cleanup would otherwise unmount an open,
    // paginated dropdown after the stub is gone, and its effect throws
    // "IntersectionObserver is not defined" into whichever test runs next.
    cleanup();
    vi.unstubAllGlobals();
  });

  describe('first page', () => {
    test('loads the first ten commands and lists them', async () => {
      const user = userEvent.setup();
      renderDropdown();

      await openDropdown(user);

      expect(callArgs(0)).toEqual(
        expect.objectContaining({ start: 0, length: 10, command: undefined }),
      );
      expect(await screen.findByRole('option', { name: 'Command 1' })).toBeInTheDocument();
      expect(screen.getAllByRole('option')).toHaveLength(10);
    });

    test('a command type given by the page narrows the list to that type', async () => {
      renderDropdown({ type: 'xmr' });

      await waitFor(() => expect(fetchCommands).toHaveBeenCalled());
      expect(callArgs(0).type).toBe('xmr');
    });

    test('picking a command hands its id to the page as a number', async () => {
      const user = userEvent.setup();
      const { onSelect } = renderDropdown();

      await openDropdown(user);
      await user.click(await screen.findByRole('option', { name: 'Command 3' }));

      expect(onSelect).toHaveBeenCalledWith(3);
    });

    test('the chosen command is shown in the closed dropdown', async () => {
      renderDropdown({ value: 4 });

      expect(await screen.findByRole('combobox', { name: 'Command' })).toHaveTextContent(
        'Command 4',
      );
    });

    test('a failed request leaves an empty list rather than an error page', async () => {
      const user = userEvent.setup();
      vi.mocked(fetchCommands).mockRejectedValue(new Error('500'));
      renderDropdown();

      await openDropdown(user);

      expect(await screen.findByText('No results')).toBeInTheDocument();
      expect(optionNames()).toEqual([]);
    });
  });

  describe('more pages', () => {
    test('scrolling to the end loads the next ten commands after the first ten', async () => {
      const user = userEvent.setup();
      renderDropdown();
      await openDropdown(user);
      await screen.findByRole('option', { name: 'Command 10' });

      await observer.scrollToEnd();

      await screen.findByRole('option', { name: 'Command 20' });
      expect(callArgs(1)).toEqual(expect.objectContaining({ start: 10, length: 10 }));
      expect(screen.getAllByRole('option')).toHaveLength(20);
    });

    test('scrolling to the end of the last page asks for nothing more', async () => {
      const user = userEvent.setup();
      renderDropdown();
      await openDropdown(user);

      await observer.scrollToEnd();
      await screen.findByRole('option', { name: 'Command 20' });
      await observer.scrollToEnd();
      await screen.findByRole('option', { name: 'Command 25' });
      const callsAtEnd = vi.mocked(fetchCommands).mock.calls.length;

      await observer.scrollToEnd();

      expect(vi.mocked(fetchCommands).mock.calls.length).toBe(callsAtEnd);
      expect(screen.getAllByRole('option')).toHaveLength(25);
    });
  });

  describe('search', () => {
    test('typing a search asks the server for matching commands, from the first page', async () => {
      const user = userEvent.setup();
      vi.mocked(fetchCommands).mockImplementation(async (opts) =>
        opts?.command
          ? ({
              rows: [command(7, 'Reboot player')],
              totalCount: 1,
            } as unknown as FetchCommandsResponse)
          : page(opts?.start ?? 0),
      );
      renderDropdown();
      await openDropdown(user);

      await user.type(screen.getByPlaceholderText('Search commands...'), 'reboot');

      expect(await screen.findByRole('option', { name: 'Reboot player' })).toBeInTheDocument();
      const calls = vi.mocked(fetchCommands).mock.calls;
      expect(calls[calls.length - 1]![0]).toEqual(
        expect.objectContaining({ start: 0, command: 'reboot' }),
      );
      expect(optionNames()).toEqual(['Reboot player']);
    });

    // The unfiltered list as the server holds it: 25 commands, with "Reboot player" as
    // number 15, so it sits on page 2 of the unfiltered list as well as matching a search.
    const ALL = Array.from({ length: 25 }, (_, i) =>
      command(i + 1, i + 1 === 15 ? 'Reboot player' : `Command ${i + 1}`),
    );
    // Answers like GET /command: filter by name, then page.
    const serve = (opts?: { start?: number; length?: number; command?: string }) => {
      const term = (opts?.command ?? '').toLowerCase();
      const rows = ALL.filter((c) => c.command.toLowerCase().includes(term));
      const start = opts?.start ?? 0;
      return {
        rows: rows.slice(start, start + (opts?.length ?? 10)),
        totalCount: rows.length,
      } as unknown as FetchCommandsResponse;
    };

    // Scroll so page 2 of the unfiltered list starts loading, search for `term` while it
    // is still in flight, then let the stale page arrive.
    const searchWhileSecondPageLoads = async (term: string, firstResult: string) => {
      const user = userEvent.setup();
      const secondPage = deferred<FetchCommandsResponse>();
      vi.mocked(fetchCommands).mockImplementation(async (opts) =>
        !opts?.command && (opts?.start ?? 0) === 10 ? secondPage.promise : serve(opts),
      );
      renderDropdown();
      await openDropdown(user);
      await screen.findByRole('option', { name: 'Command 10' });

      await observer.scrollToEnd(); // page 2 requested, still loading
      // Paste the term in one go: typing it key by key can, under load, space the keys
      // further apart than the 300 ms debounce and send extra searches ("r", "re", ...)
      // that race with the stale page and make the outcome depend on timing.
      await user.click(screen.getByPlaceholderText('Search commands...'));
      await user.paste(term);
      // The list is emptied when the search is sent, so the first option to appear after
      // that request comes from the search, not the old list.
      await waitFor(() =>
        expect(vi.mocked(fetchCommands).mock.calls.some((c) => c[0]?.command === term)).toBe(true),
      );
      await screen.findByRole('option', { name: firstResult }, { timeout: 3000 });

      secondPage.resolve(serve({ start: 10 })); // the stale page 2 arrives now
      await waitFor(() => expect(screen.queryByText('Loading…')).not.toBeInTheDocument());
      return user;
    };

    // Open — xibosignage/xibo#4062.
    knownFailure(
      'a page that was loading when the user started a search is not mixed into the search results',
      () => searchWhileSecondPageLoads('reboot', 'Reboot player'),
      () => expect(optionNames()).toEqual(['Reboot player']),
    );

    // Open — xibosignage/xibo#4062.
    knownFailure(
      'a search never lists the same command twice',
      () => searchWhileSecondPageLoads('reboot', 'Reboot player'),
      () => {
        const names = optionNames();
        expect(names.length).toBe(new Set(names).size);
      },
    );

    // Open — xibosignage/xibo#4062.
    knownFailure(
      'scrolling on after a search asks for the second page of that search, not the third',
      async () => {
        await searchWhileSecondPageLoads('command', 'Command 1');
        const before = vi.mocked(fetchCommands).mock.calls.length;
        await observer.scrollToEnd();
        await waitFor(() =>
          expect(vi.mocked(fetchCommands).mock.calls.length).toBeGreaterThan(before),
        );
        return callArgs(vi.mocked(fetchCommands).mock.calls.length - 1);
      },
      (next) => expect(next).toEqual(expect.objectContaining({ command: 'command', start: 10 })),
    );
  });
});
