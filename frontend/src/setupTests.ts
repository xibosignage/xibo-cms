import '@testing-library/jest-dom';
import { QueryClient, QueryCache, MutationCache } from '@tanstack/react-query';
import { beforeAll, vi } from 'vitest';

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
