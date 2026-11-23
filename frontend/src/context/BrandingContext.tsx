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

import { createContext, useContext, useEffect, type ReactNode } from 'react';

import type { BrandingConfig } from '@/types/user';
import { resolveSidebarBg, resolveSidebarContrastMode } from '@/utils/contrastColor';

const THEME_LINK_ID = 'xibo-theme-css';
const SIDEBAR_CONTRAST_ATTR = 'data-sidebar-contrast';
const SIDEBAR_BG_PROPERTY = '--sidebar-bg';

// Fallback used only while the /user/me API call is in-flight.
// Once the response arrives, BrandingProvider replaces these with server values.
// Note: the browser tab favicon is NOT managed here — the Twig shell links it,
// already branded per install (default vs WL).
// faviconUrl below is only used as the collapsed-sidebar icon mark (SidebarHeader.tsx).
const defaults: BrandingConfig = {
  productName: 'Xibo Digital Signage',
  appName: 'Xibo',
  logoUrl: '/brand/logo.svg',
  logoDarkUrl: '/brand/logo-dark.svg',
  faviconUrl: '/brand/logo-icon.svg',
  cssUrl: '/brand/theme.css',
  supportUrl: 'https://xibosignage.com',
};

const BrandingContext = createContext<BrandingConfig>(defaults);

interface Props {
  branding?: BrandingConfig | null;
  children: ReactNode;
}

export function BrandingProvider({ branding, children }: Props) {
  const value = branding ?? defaults;

  useEffect(() => {
    document.getElementById(THEME_LINK_ID)?.remove();

    // Value-guarded: writing --sidebar-bg mutates the [style] attribute the observer below watches.
    const applyTheme = () => {
      const root = document.documentElement;
      const brandPrimary = getComputedStyle(root).getPropertyValue('--brand-primary').trim();
      const sidebarBg = resolveSidebarBg(brandPrimary);
      if (sidebarBg && root.style.getPropertyValue(SIDEBAR_BG_PROPERTY) !== sidebarBg) {
        root.style.setProperty(SIDEBAR_BG_PROPERTY, sidebarBg);
      }
      root.setAttribute(SIDEBAR_CONTRAST_ATTR, resolveSidebarContrastMode());
    };
    // A brand without a stylesheet sets its colours in the page instead.
    if (value.cssUrl) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = value.cssUrl;
      link.id = THEME_LINK_ID;
      link.addEventListener('load', applyTheme);
      link.addEventListener('error', applyTheme);
      document.head.appendChild(link);
    }
    applyTheme();

    // CSS var changes fire no DOM event, so also watch for live edits (e.g.
    // devtools style.setProperty) via <html>'s inline style attribute.
    const observer = new MutationObserver(applyTheme);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['style'] });

    return () => {
      observer.disconnect();
      document.getElementById(THEME_LINK_ID)?.remove();
    };
  }, [value.cssUrl]);

  return <BrandingContext.Provider value={value}>{children}</BrandingContext.Provider>;
}

export function useBranding(): BrandingConfig {
  return useContext(BrandingContext);
}
