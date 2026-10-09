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

import { test } from 'vitest';

/**
 * Pin a defect that is still open, as a `test.fails` that starts failing (and so gets noticed) once
 * the defect is fixed.
 *
 * `test.fails` passes on *any* error, so a test whose setup breaks (a renamed column, a changed
 * PHP allow-list, a button that no longer renders) would stay green without checking the defect at
 * all. An `expect` in the setup doesn't help: inside `test.fails` a failed assertion is just another
 * error. So the setup also runs in its own ordinary test, which goes red when the setup breaks.
 *
 * - `setup` does the lookups, rendering and any control assertions, and returns what `bug` needs.
 * - `bug` holds exactly one assertion: the defect. One defect per call, so fixing one defect can't
 *   hide behind another that is still open.
 *
 * In the fix, replace the call with an ordinary `test` that runs both.
 */
export function knownFailure<S>(
  name: string,
  setup: () => S | Promise<S>,
  bug: (state: S) => unknown,
): void {
  test(`${name} (setup still runs)`, async () => {
    await setup();
  });
  test.fails(name, async () => {
    await bug(await setup());
  });
}
