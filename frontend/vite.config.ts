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
    testTimeout: 15_000,
    hookTimeout: 15_000,
    retry: process.env.CI ? 1 : 0,
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
