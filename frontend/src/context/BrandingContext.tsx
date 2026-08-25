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

import { createContext, useContext, useEffect, type ReactNode } from 'react';

import type { BrandingConfig } from '@/types/user';
import { resolveSidebarContrastMode } from '@/utils/contrastColor';

const THEME_LINK_ID = 'xibo-theme-css';
const SIDEBAR_CONTRAST_ATTR = 'data-sidebar-contrast';

// Fallback used only while the /user/me API call is in-flight.
// Once the response arrives, BrandingProvider replaces these with server values.
// Note: the browser tab favicon is NOT managed here — it's served statically as
// /brand/favicon.ico from the Twig shell, already branded per install (default vs WL).
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
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = value.cssUrl;
    link.id = THEME_LINK_ID;

    // Re-check contrast once theme.css applies, and once up-front for installs
    // with no override.
    const applyContrast = () => {
      document.documentElement.setAttribute(SIDEBAR_CONTRAST_ATTR, resolveSidebarContrastMode());
    };
    link.addEventListener('load', applyContrast);
    link.addEventListener('error', applyContrast);
    document.head.appendChild(link);
    applyContrast();

    // CSS var changes fire no DOM event, so also watch for live edits (e.g.
    // devtools style.setProperty) via <html>'s inline style attribute.
    const observer = new MutationObserver(applyContrast);
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
