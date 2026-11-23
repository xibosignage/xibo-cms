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
