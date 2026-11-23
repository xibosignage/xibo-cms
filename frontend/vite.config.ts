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
  // Internal asset base (never user-facing): where built JS/CSS physically live (web/app)
  // and the dev-server mount point. Production asset URLs are made install-root-aware at
  // runtime via experimental.renderBuiltUrl below (see window.__XIBO_ASSET_BASE__).
  base: '/app/',
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
      // Do not auto-open: it spawns a browser (powershell.exe under WSL) during the build,
      // which crashes headless/Docker/CI builds. Open frontend/stats.html manually instead.
      open: false,
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
    testTimeout: 15_000,
    hookTimeout: 15_000,
    // No retries anywhere, so a flaky test fails in CI instead of passing on its second try
    retry: 0,
    // Test tags. A tag missing from this list fails the run (strictTags), so a typo can't silently
    // drop a test. Names are typed in src/types/vitest-tags.d.ts. Run with --tags-filter=<expr>.
    tags: [
      {
        name: 'smoke',
        description: "A page's critical path: it renders with data and its main action works",
      },
      {
        name: 'quarantine',
        description:
          'Known flaky; npm run test:blocking leaves it out. Issue link and owner in a comment above',
      },
    ],
    // Pre-bundle heavy deps (every date-fns locale) to cut per-file import time.
    deps: {
      optimizer: {
        client: {
          enabled: true,
          include: [
            'react-day-picker',
            'react-day-picker/locale',
            'react-day-picker/persian',
            'date-fns',
            'date-fns/locale',
          ],
        },
      },
    },
    alias: {
      'react-i18next': path.resolve(__dirname, '__mocks__/react-i18next.tsx'),
    },
  },
  server: {
    port: 5173,
    // ViteManifest (PHP) hardcodes http://localhost:5173 for dev asset/HMR URLs, so the
    // dev server MUST bind 5173. Fail loudly if it's taken rather than silently drifting to
    // 5174 — otherwise the PHP-served shell loads scripts from a port with nothing on it.
    strictPort: true,
    // The PHP app (localhost) serves the HTML shell while Vite serves assets. Emit dev asset
    // URLs (incl. CSS-referenced fonts like @fontsource) with the full Vite origin so they
    // resolve against :5173, not the document origin (:80) — otherwise they 404 in dev.
    origin: 'http://localhost:5173',
    // Open the PHP app (which renders the SPA shell + injects <meta public-path>), NOT the
    // raw Vite server at /app/. Browsing PHP gives clean URLs (basename '/') matching prod;
    // browsing the Vite server directly would prefix every route with the '/app/' asset base.
    // Vite resolves this absolute URL as-is (new URL(open, devServerUrl)).
    open: 'http://localhost/',
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
      // Proxy everything that isn't a Vite-internal path or an existing proxy rule.
      // @vite, @fs, @id, src/, node_modules/ are Vite's own paths and must NOT be
      // forwarded to PHP — Vite handles them before the proxy in its middleware stack.
      '^/(?!app|api|authorize|swagger.json|@vite|@fs|@id|src|node_modules).*': {
        target: 'http://localhost',
        changeOrigin: true,
        secure: false,
      },
    },
  },
  build: {
    manifest: true,
    minify: process.env.NODE_ENV !== 'debug',
    outDir: path.resolve(__dirname, 'dist'),
    emptyOutDir: true,
    chunkSizeWarningLimit: 1000,
    sourcemap: process.env.NODE_ENV === 'debug',
    rollupOptions: {
      input: {
        main: path.resolve(__dirname, 'index.html'),
        'login-main': path.resolve(__dirname, 'login.html'),
      },
      output: {
        // Shared React runtime — loaded by both main app and login SPA.
        // Only core React packages go into this chunk; all other deps are
        // auto-chunked by Rollup so the login SPA doesn't inherit the main
        // app's heavy vendor modules (TanStack, i18n, etc.).
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
  experimental: {
    // Make built asset URLs install-root-aware at runtime. The `base` above bakes an
    // absolute prefix into chunk imports and CSS asset refs, which breaks sub-folder/alias
    // installs (e.g. /cms/). Instead resolve them against window.__XIBO_ASSET_BASE__, which
    // the PHP shell sets to rootUri + 'app/'. Build-only — the dev server is unaffected.
    renderBuiltUrl(filename: string, { hostType }: { hostType: 'js' | 'css' | 'html' }) {
      if (hostType === 'js') {
        // JS-hosted refs: chunk-to-chunk imports, dynamic imports, asset URLs from JS.
        return { runtime: `window.__XIBO_ASSET_BASE__ + ${JSON.stringify(filename)}` };
      }
      // CSS/HTML can't use a runtime expression; `relative` resolves against the file's own
      // URL (hashed assets sit next to their CSS in assets/), correct on any sub-path.
      return { relative: true };
    },
  },
}));
