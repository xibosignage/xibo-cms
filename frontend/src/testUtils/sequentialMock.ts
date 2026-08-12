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

import type { Mock } from 'vitest';

export interface SequentialCallTracker {
  /** The highest number of calls that were in flight at the same instant. */
  maxInFlight: number;
  /** The order `targetId` values arrived in, as each call started. */
  callOrder: number[];
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
 * Usage:
 *   const tracker = trackSequentialCalls(mockSelectFolder);
 *   // ...invoke the code under test...
 *   expect(tracker.maxInFlight).toBe(1);
 *   expect(tracker.callOrder).toEqual([1, 2, 3]);
 */
export function trackSequentialCalls(
  mockFn: Mock,
  result: unknown = { success: true },
): SequentialCallTracker {
  const tracker: SequentialCallTracker = { maxInFlight: 0, callOrder: [] };
  let inFlight = 0;

  mockFn.mockImplementation(async (args: { targetId: number }) => {
    inFlight += 1;
    tracker.maxInFlight = Math.max(tracker.maxInFlight, inFlight);
    tracker.callOrder.push(args.targetId);
    await Promise.resolve();
    inFlight -= 1;
    return result;
  });

  return tracker;
}
