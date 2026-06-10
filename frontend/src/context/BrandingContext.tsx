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

const THEME_LINK_ID = 'xibo-theme-css';
const FAVICON_LINK_ID = 'xibo-favicon';

// Fallback used only while the /user/me API call is in-flight.
// Once the response arrives, BrandingProvider replaces these with server values.
const defaults: BrandingConfig = {
  productName: 'Xibo Digital Signage',
  appName: 'Xibo',
  logoUrl: '/brand/logo.svg',
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
    document.head.appendChild(link);
    return () => {
      document.getElementById(THEME_LINK_ID)?.remove();
    };
  }, [value.cssUrl]);

  useEffect(() => {
    let link = document.getElementById(FAVICON_LINK_ID) as HTMLLinkElement | null;
    if (!link) {
      link = document.createElement('link');
      link.rel = 'icon';
      link.id = FAVICON_LINK_ID;
      document.head.appendChild(link);
    }
    link.href = value.faviconUrl;
  }, [value.faviconUrl]);

  return <BrandingContext.Provider value={value}>{children}</BrandingContext.Provider>;
}

export function useBranding(): BrandingConfig {
  return useContext(BrandingContext);
}
