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

import type { Mock } from 'vitest';

export interface SequentialCallTracker {
  /** The highest number of calls that were in flight at the same instant. */
  maxInFlight: number;
  /** The key of each call (by default its `targetId`), in the order the calls started. */
  callOrder: Array<number | string>;
}

export interface SequentialCallOptions {
  /**
   * Which value identifies a call. Defaults to `args[0].targetId`, the shape of `selectFolder`.
   * For `deleteX(id)` pass `(id) => id`.
   */
  key?: (...args: never[]) => number | string;
  /**
   * What each call resolves (or rejects) with. Defaults to the `result` argument. Use it to make
   * one item of a bulk action fail, e.g. `(id) => id === 2 ? Promise.reject(notFound) : ok`.
   */
  respond?: (...args: never[]) => unknown;
}

/**
 * Makes a mocked function take a brief moment before resolving, so if two
 * calls happen at once there's a real window for them to overlap in. While
 * it's "thinking", it counts how many calls are waiting at the same time.
 *
 * Just checking how many times a mock was called isn't enough — that looks
 * the same whether the calls went out one after another or all at once. This
 * is how you actually prove they happened one at a time, not together.
 * (Added after a real bug where bulk-move fired every request at once and
 * overloaded the database.)
 *
 * It also records which item each call was for, so a test can prove a bulk
 * action sent every selected item exactly once.
 *
 * Usage:
 *   const tracker = trackSequentialCalls(mockSelectFolder);
 *   // ...invoke the code under test...
 *   expect(tracker.maxInFlight).toBe(1);
 *   expect(tracker.callOrder).toEqual([1, 2, 3]);
 *
 *   // A function called as deleteX(id), where item 2 was already deleted:
 *   const tracker = trackSequentialCalls(mockDelete, undefined, {
 *     key: (id: number) => id,
 *     respond: (id: number) => (id === 2 ? Promise.reject(notFound) : undefined),
 *   });
 */
export function trackSequentialCalls(
  mockFn: Mock,
  result: unknown = { success: true },
  options: SequentialCallOptions = {},
): SequentialCallTracker {
  const tracker: SequentialCallTracker = { maxInFlight: 0, callOrder: [] };
  let inFlight = 0;
  const keyOf = (options.key ?? ((args: { targetId: number }) => args.targetId)) as (
    ...args: unknown[]
  ) => number | string;
  const respond = options.respond as ((...args: unknown[]) => unknown) | undefined;

  mockFn.mockImplementation(async (...args: unknown[]) => {
    inFlight += 1;
    tracker.maxInFlight = Math.max(tracker.maxInFlight, inFlight);
    tracker.callOrder.push(keyOf(...args));
    await Promise.resolve();
    try {
      return respond ? await respond(...args) : result;
    } finally {
      inFlight -= 1;
    }
  });

  return tracker;
}
