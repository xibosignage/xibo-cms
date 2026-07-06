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

declare global {
  interface Window {
    // Base where built assets + public files (locales) are served, injected by the PHP shell.
    __XIBO_ASSET_BASE__?: string;
  }
}

// Root URI injected by PHP via <meta name="public-path"> (login-spa.twig / app-spa.twig).
// e.g. '/' for a root install, '/cms/' for a subfolder install.
export const publicPath: string =
  document.querySelector<HTMLMetaElement>('meta[name="public-path"]')?.content ?? '/';

// Base under which built assets AND public files (locale JSON, etc.) are served. This is the
// install root plus the asset folder ('/app/' or '/cms/app/'), NOT the router basename (which
// is just the install root). The PHP shell injects it as window.__XIBO_ASSET_BASE__ so it is
// install-root-aware; the fallback covers the raw Vite dev server (no shell → no global), where
// origin + the Vite base is correct.
export const assetBase: string =
  window.__XIBO_ASSET_BASE__ ?? window.location.origin + import.meta.env.BASE_URL;

/**
 * Joins a path onto the asset base, tolerating slashes on either side.
 * e.g. withAssetBase('locale/langs/en.json') -> '/cms/app/locale/langs/en.json'.
 */
export function withAssetBase(path: string): string {
  return assetBase.replace(/\/$/, '') + '/' + path.replace(/^\//, '');
}

/**
 * Joins a path onto the install root (publicPath), tolerating slashes on either side.
 * Use for anything served by the CMS itself — the JSON API, legacy PHP pages, export
 * downloads, auth redirects — so it resolves on sub-folder/alias installs.
 * e.g. withPublicPath('json/user/me') -> '/cms/json/user/me' (or '/json/user/me' at root).
 */
export function withPublicPath(path: string): string {
  return publicPath.replace(/\/$/, '') + '/' + path.replace(/^\//, '');
}

/**
 * The React Router basename.
 *
 * When PHP serves the shell (login-spa.twig / app-spa.twig) — in BOTH dev and prod — it
 * injects <meta name="public-path"> with the install root ('/' or '/cms/') and the app
 * owns clean URLs under it. Only when the Vite dev server serves the raw index.html
 * directly (no meta injected) do routes live under the Vite base (import.meta.env.BASE_URL,
 * e.g. '/app/'). So we key off the meta's presence, not import.meta.env.DEV — a dev
 * build reached through PHP still needs the clean-URL basename. The trailing slash is
 * stripped because React Router expects a basename without one.
 */
export function getRouterBasename(): string {
  const meta = document.querySelector<HTMLMetaElement>('meta[name="public-path"]')?.content;
  const raw = meta ?? import.meta.env.BASE_URL;
  return raw.replace(/\/$/, '') || '/';
}
