import '@testing-library/jest-dom';
import { QueryClient, QueryCache, MutationCache } from '@tanstack/react-query';
import { beforeAll, vi } from 'vitest';

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
