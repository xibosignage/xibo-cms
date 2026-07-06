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

// Root URI injected by PHP via <meta name="public-path"> (login-spa.twig / app-spa.twig).
// e.g. '/' for a root install, '/cms/' for a subfolder install.
export const publicPath: string =
  document.querySelector<HTMLMetaElement>('meta[name="public-path"]')?.content ?? '/';

/**
 * The React Router basename.
 *
 * When PHP serves the shell (login-spa.twig / app-spa.twig) — in BOTH dev and prod — it
 * injects <meta name="public-path"> with the install root ('/' or '/cms/') and the app
 * owns clean URLs under it. Only when the Vite dev server serves the raw index.html
 * directly (no meta injected) do routes live under the Vite base (import.meta.env.BASE_URL,
 * e.g. '/prototype/'). So we key off the meta's presence, not import.meta.env.DEV — a dev
 * build reached through PHP still needs the clean-URL basename. The trailing slash is
 * stripped because React Router expects a basename without one.
 */
export function getRouterBasename(): string {
  const meta = document.querySelector<HTMLMetaElement>('meta[name="public-path"]')?.content;
  const raw = meta ?? import.meta.env.BASE_URL;
  return raw.replace(/\/$/, '') || '/';
}
