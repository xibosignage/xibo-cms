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

/**
 * JSDOM has no IntersectionObserver. Paginated dropdowns load the next page when a sentinel
 * at the bottom of their list scrolls into view. This stub records which elements each live
 * observer watches, so a test can say "everything inside this list is now visible" and reach
 * only the observers watching it: one created by another library (e.g. floating-ui) for a
 * different element is never fired by mistake.
 *
 * Unlike a real IntersectionObserver, it never reports on `observe()`: nothing is "visible"
 * until a test says so, so each scroll is explicit (a second page needs a second call).
 *
 * Call it in beforeEach. Restore with vi.unstubAllGlobals(), after cleanup() so components
 * that still observe while unmounting find the stub. (Other suites have older stubs that fire
 * the most recent observer; `Reporting/Reports/__tests__/helpers/domStubs.ts` has one called
 * `stubIntersectionObserver`, hence this name.)
 *
 *   const observers = stubWatchedIntersectionObserver();
 *   observers.showEndOf(screen.getByRole('listbox'));
 */
export const stubWatchedIntersectionObserver = () => {
  const live = new Set<IntersectionObserverStub>();

  class IntersectionObserverStub {
    readonly targets = new Set<Element>();

    private readonly callback: IntersectionObserverCallback;

    constructor(callback: IntersectionObserverCallback) {
      this.callback = callback;
      live.add(this);
    }

    observe(target: Element) {
      this.targets.add(target);
    }

    unobserve(target: Element) {
      this.targets.delete(target);
    }

    disconnect() {
      this.targets.clear();
      live.delete(this);
    }

    takeRecords() {
      return [];
    }

    report(targets: Element[], isIntersecting: boolean) {
      // The callback usually sets state (e.g. "loading more"), so it runs inside act().
      act(() => {
        this.callback(
          targets.map((target) => ({ target, isIntersecting }) as IntersectionObserverEntry),
          this as unknown as IntersectionObserver,
        );
      });
    }
  }

  vi.stubGlobal('IntersectionObserver', IntersectionObserverStub);

  /** The elements inside `container` that a live observer is watching, per observer. */
  const watchingInside = (container: Element) =>
    [...live]
      .map((observer) => ({
        observer,
        targets: [...observer.targets].filter((t) => container.contains(t)),
      }))
      .filter(({ targets }) => targets.length > 0);

  return {
    /** Report everything watched inside `container` (e.g. a listbox) as scrolled into view. */
    showEndOf: (container: Element, visible = true) => {
      for (const { observer, targets } of watchingInside(container)) {
        observer.report(targets, visible);
      }
    },
    /** How many elements inside `container` live observers are watching. */
    watchedInside: (container: Element) =>
      watchingInside(container).reduce((n, { targets }) => n + targets.length, 0),
  };
};
