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

import '@testing-library/jest-dom';
import { QueryClient, QueryCache, MutationCache } from '@tanstack/react-query';
import { configure } from '@testing-library/react';
import { beforeAll, vi } from 'vitest';

// ---------------------
// Raise findBy*/waitFor timeout from 1s so slow CI runners don't cause flaky failures.
configure({ asyncUtilTimeout: 3000 });

// ---------------------
// Fast getComputedStyle for the visibility checks.
// Every *ByRole query, toBeVisible and user-event's pointer-events check call
// getComputedStyle on each element and its ancestors, and jsdom's version costs ~3ms a call.
// It was about 75% of all test time. Tests load no app CSS, so these four properties can only
// come from inline styles, the hidden attribute and the browser defaults: answer them directly.
// A tag's default display (block, table-row, ...) is read once from jsdom and cached, because
// accessible names put a space between children that aren't inline ("Delete item", not
// "Deleteitem"). Every other property (colours, padding, custom properties) still comes from jsdom.
// Limit: if a test ever loads real CSS, its rules are ignored for these four properties.
// Set VITEST_REAL_STYLES=1 to switch this off. Checked by testUtils/__tests__/fastComputedStyle.
if (process.env.VITEST_REAL_STYLES !== '1') {
  const realGetComputedStyle = window.getComputedStyle.bind(window);
  const HIDDEN_BY_DEFAULT = new Set([
    'HEAD',
    'SCRIPT',
    'STYLE',
    'TEMPLATE',
    'NOSCRIPT',
    'TITLE',
    'META',
    'LINK',
    'BASE',
    'DATALIST',
  ]);
  const FAST = new Set(['display', 'visibility', 'pointer-events', 'opacity']);
  const INHERITED = new Set(['visibility', 'pointer-events']);
  // CSS initial values. display's is 'inline'; a tag's own default comes from defaultDisplay.
  const INITIAL: Record<string, string> = {
    display: 'inline',
    visibility: 'visible',
    'pointer-events': 'auto',
    opacity: '1',
  };

  // Default display per tag, read from jsdom's own stylesheet and cached for the test file
  // (setup files re-run for each test file). The probe is a bare element (no attributes, so no
  // attribute selectors match) in a separate, empty document that is never attached anywhere:
  // no MutationObserver in the test's document fires, and no custom element constructor runs.
  const tagDisplay = new Map<string, string>();
  let probeDocument: Document | undefined;
  const defaultDisplay = (el: Element): string => {
    const key = `${el.namespaceURI}|${el.localName}`;
    let display = tagDisplay.get(key);
    if (display === undefined) {
      probeDocument ??= document.implementation.createHTMLDocument('');
      const probe = probeDocument.createElementNS(el.namespaceURI, el.localName);
      display = realGetComputedStyle(probe).display || 'inline';
      tagDisplay.set(key, display);
    }
    return display;
  };

  const fastValue = (el: Element, prop: string): string => {
    let inline = (el as HTMLElement).style?.getPropertyValue(prop) ?? '';
    // CSS-wide keywords: 'unset' is 'inherit' for inherited properties and 'initial' otherwise.
    // 'revert' is left as it is, because jsdom also returns it unresolved.
    if (inline === 'unset') inline = INHERITED.has(prop) ? 'inherit' : 'initial';
    if (inline === 'initial') return INITIAL[prop] ?? '';
    if (inline && inline !== 'inherit') return inline;
    if (prop === 'display' && !inline) {
      if (el.hasAttribute('hidden') || HIDDEN_BY_DEFAULT.has(el.tagName)) return 'none';
      // The cached tag default comes from a bare element, so attribute-dependent rules go here.
      if (el.tagName === 'DIALOG') return el.hasAttribute('open') ? 'block' : 'none';
      if (el instanceof HTMLInputElement && el.type === 'hidden') return 'none';
      return defaultDisplay(el);
    }
    if ((INHERITED.has(prop) || inline === 'inherit') && el.parentElement) {
      return fastValue(el.parentElement, prop);
    }
    return INITIAL[prop] ?? '';
  };

  const kebab = (key: string) => key.replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`);

  window.getComputedStyle = ((el: Element, pseudo?: string | null) => {
    if (pseudo || !(el instanceof Element)) return realGetComputedStyle(el, pseudo);
    let real: CSSStyleDeclaration | undefined;
    const full = () => (real ??= realGetComputedStyle(el));
    // Only reads are fast. `in` checks go to jsdom; Object.keys and spread see an empty object.
    return new Proxy({} as CSSStyleDeclaration, {
      has(_target, key) {
        return key in full();
      },
      get(_target, key) {
        if (key === 'getPropertyValue') {
          return (p: string) => (FAST.has(p) ? fastValue(el, p) : full().getPropertyValue(p));
        }
        if (typeof key === 'string' && FAST.has(kebab(key))) return fastValue(el, kebab(key));
        const value: unknown = Reflect.get(full(), key);
        return typeof value === 'function' ? value.bind(full()) : value;
      },
    });
  }) as typeof window.getComputedStyle;
}

// ---------------------
// Some shared UI components (e.g. FilterInputs) import `t` directly from
// 'i18next' rather than via useTranslation. Without this mock the function
// returns undefined in tests (i18next is not initialised).
// The mock is also chainable (use/init) and exposes the runtime language methods so
// that importing src/lib/i18n.ts (now reachable via Settings.tsx) doesn't blow up.
vi.mock('i18next', () => {
  const t = (key: string) => key;
  const i18n = {
    t,
    language: 'en',
    isInitialized: true,
    use: () => i18n,
    init: () => Promise.resolve(t),
    hasResourceBundle: () => true,
    addResourceBundle: () => i18n,
    changeLanguage: () => Promise.resolve(t),
  };
  return { default: i18n, t };
});

interface SystemError extends Error {
  code?: string;
}

// -------------------
// deterministic test
// ensure that any fetch in tests just returns an empty object and
// never hits the network.
global.fetch = vi.fn().mockResolvedValue({
  ok: true,
  json: async () => ({}),
  text: async () => '{}',
});

// -------------------
// Prevent unhandled rejections from React Query / undici
process.on('unhandledRejection', (reason: SystemError) => {
  if (reason?.code === 'UND_ERR_INVALID_ARG' || /invalid onError method/i.test(reason?.message)) {
    return;
  }
  throw reason;
});

// ---------------------
// override console.error so that only meaningful errors are logged
beforeAll(() => {
  const originalError = console.error;
  console.error = (...args) => {
    if (/Failed to fetch/i.test(args[0])) return;
    originalError(...args);
  };
});

// ---------------------
// Export a shared QueryClient for tests
export const testQueryClient = new QueryClient({
  queryCache: new QueryCache({
    onError: (error) => {
      // global query error logic
      console.log(error);
    },
  }),
  // Add this for mutations
  mutationCache: new MutationCache({
    onError: (error) => {
      // global mutation error logic
      console.log(error);
    },
  }),
  defaultOptions: {
    queries: {
      retry: false,
      refetchOnWindowFocus: false,
    },
  },
});

global.ResizeObserver = class ResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
};
