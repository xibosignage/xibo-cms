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
import { defineConfig } from 'vite';
import tsconfigPaths from 'vite-tsconfig-paths';

export default defineConfig(({ mode }) => ({
  base: '/prototype/',
  plugins: [
    react({
      babel: {
        plugins: [['babel-plugin-react-compiler']],
      },
    }),
    tailwindcss(),
    tsconfigPaths(),
    visualizer({
      filename: 'stats.html',
      open: true,
      gzipSize: true,
      brotliSize: true,
    }),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: './src/setupTests.ts',
    include: ['**/*.test.{js,jsx,ts,tsx}'],
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
      // Proxy everything that isn't a Vite-internal path or an existing proxy rule.
      // @vite, @fs, @id, src/, node_modules/ are Vite's own paths and must NOT be
      // forwarded to PHP — Vite handles them before the proxy in its middleware stack.
      '^/(?!prototype|api|authorize|swagger.json|@vite|@fs|@id|src|node_modules).*': {
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
        // Static object form: only named packages go into the chunk; all other
        // deps are auto-chunked by Rollup so login doesn't inherit the main
        // app's heavy vendor modules (TanStack, Router, i18n, etc.).
        manualChunks: {
          'react-core': ['react', 'react-dom', 'scheduler'],
        },
      },
    },
  },
  define: {
    __APP_VERSION__: JSON.stringify(process.env.npm_package_version),
    __APP_MODE__: JSON.stringify(mode),
  },
}));
