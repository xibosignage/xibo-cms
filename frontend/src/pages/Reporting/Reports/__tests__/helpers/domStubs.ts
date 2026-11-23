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

import { vi } from 'vitest';

// jsdom lacks IntersectionObserver; the stub fires only when a test triggers it.
export const intersectionCallbacks: IntersectionObserverCallback[] = [];

export const stubIntersectionObserver = () => {
  intersectionCallbacks.length = 0;
  vi.stubGlobal(
    'IntersectionObserver',
    class {
      private readonly callback: IntersectionObserverCallback;
      constructor(callback: IntersectionObserverCallback) {
        this.callback = callback;
        intersectionCallbacks.push(callback);
      }
      observe() {}
      unobserve() {}
      disconnect() {
        const i = intersectionCallbacks.indexOf(this.callback);
        if (i !== -1) intersectionCallbacks.splice(i, 1);
      }
      takeRecords() {
        return [];
      }
    },
  );
};

export const triggerIntersection = () => {
  for (const callback of intersectionCallbacks) {
    callback([{ isIntersecting: true } as IntersectionObserverEntry], {} as IntersectionObserver);
  }
};
