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

import path from 'node:path';

import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { visualizer } from 'rollup-plugin-visualizer';
import { defineConfig, type Plugin } from 'vite';

// Vite plugin: redirect react-dom imports that originate from @dnd-kit/* to a
// shim that provides the unstable_batchedUpdates no-op removed in React 19.
function dndKitReact19Compat(): Plugin {
  return {
    name: 'dnd-kit-react19-compat',
    resolveId(source, importer) {
      if (source === 'react-dom' && importer?.includes('@dnd-kit')) {
        return path.resolve(__dirname, 'src/shims/react-dom-dnd-compat.ts');
      }
      return null;
    },
  };
}

export default defineConfig(({ mode }) => ({
  base: '/prototype/',
  plugins: [
    dndKitReact19Compat(),
    react({
      babel: {
        plugins: [['babel-plugin-react-compiler']],
      },
    }),
    tailwindcss(),
    visualizer({
      filename: 'stats.html',
      open: true,
      gzipSize: true,
      brotliSize: true,
    }),
  ],
  resolve: {
    tsconfigPaths: true,
    alias: {
      '@': path.resolve(__dirname, 'src'),
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: './src/setupTests.ts',
    include: ['**/*.test.{js,jsx,ts,tsx}'],
    // Render-heavy tests pass in ~1-2s alone but get CPU-starved under the full
    // parallel suite on a single CI runner and can exceed the 5s default. Raise the
    // global budget so the whole class of JSDOM-contention timeouts stops, instead
    // of hand-bumping individual tests. 15s matches the value already used ad-hoc on
    // the Displays/DisplayProfile filter tests.
    testTimeout: 15_000,
    hookTimeout: 15_000,
    // In CI only, retry a test once: a contention straggler passes on the less-loaded
    // retry, but a genuinely broken test fails both attempts. Stays 0 locally so
    // developers see real failures immediately. (process.env is used elsewhere in
    // this file for NODE_ENV; GitHub Actions sets CI=true automatically.)
    retry: process.env.CI ? 1 : 0,
    alias: {
      'react-i18next': path.resolve(__dirname, '__mocks__/react-i18next.tsx'),
    },
  },
  server: {
    port: 5173,
    open: '/prototype/',
    cors: {
      origin: true,
      methods: ['GET', 'OPTIONS'],
      allowedHeaders: ['Content-Type', 'X-Requested-With', 'Accept', 'Origin', 'X-PREVIEW-JWT'],
    },
    proxy: {
      '/json': {
        target: 'http://localhost',
        changeOrigin: true,
        secure: false,
      },
      '/api': {
        target: 'http://localhost',
        changeOrigin: true,
        secure: false,
      },
      '/authorize': {
        target: 'http://localhost',
        changeOrigin: true,
        secure: false,
      },
      '^/(?!prototype|api|authorize|swagger.json).*': {
        target: 'http://localhost',
        changeOrigin: true,
        secure: false,
      },
    },
  },
  build: {
    minify: process.env.NODE_ENV !== 'debug',
    outDir: path.resolve(__dirname, 'dist'),
    emptyOutDir: true,
    chunkSizeWarningLimit: 1000,
    sourcemap: process.env.NODE_ENV === 'debug',
    rollupOptions: {
      output: {
        manualChunks: (id: string) => {
          if (
            /\/node_modules\/(react|react-dom|react-router|react-router-dom|scheduler)\//.test(id)
          ) {
            return 'react-core';
          }
          return undefined;
        },
      },
    },
  },
  define: {
    __APP_VERSION__: JSON.stringify(process.env.npm_package_version),
    __APP_MODE__: JSON.stringify(mode),
  },
}));
