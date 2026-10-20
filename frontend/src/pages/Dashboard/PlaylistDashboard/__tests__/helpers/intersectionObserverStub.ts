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

import { act } from '@testing-library/react';
import { vi } from 'vitest';

// JSDOM has no IntersectionObserver. SelectDropdown loads the next page when a sentinel at
// the bottom of its list scrolls into view; this stub records each observer's callback so a
// test can say "the user scrolled to the end". Restore with vi.unstubAllGlobals().
export const installIntersectionObserverStub = () => {
  const callbacks: IntersectionObserverCallback[] = [];

  vi.stubGlobal(
    'IntersectionObserver',
    class {
      constructor(callback: IntersectionObserverCallback) {
        callbacks.push(callback);
      }
      observe() {}
      unobserve() {}
      disconnect() {}
      takeRecords() {
        return [];
      }
    },
  );

  return {
    // Fires the most recent observer (the one watching the current sentinel).
    scrollToEnd: () =>
      act(() => {
        callbacks[callbacks.length - 1]?.(
          [{ isIntersecting: true } as unknown as IntersectionObserverEntry],
          {} as unknown as IntersectionObserver,
        );
      }),
    observerCount: () => callbacks.length,
  };
};
