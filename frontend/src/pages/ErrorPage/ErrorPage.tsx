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

import { AlertTriangle, RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useRouteError } from 'react-router-dom';

import Button from '@/components/ui/Button';
import { publicPath } from '@/config/publicPath';
import { BrandingProvider } from '@/context/BrandingContext';

const RELOAD_KEY = 'xibo_chunk_reload';

/**
 * Detects errors caused by stale JS chunks after a new deployment.
 * These happen when the browser tries to load a code-split chunk whose
 * filename hash has changed since the page was first loaded.
 */
function isChunkLoadError(error: unknown): boolean {
  if (error instanceof Error) {
    const msg = error.message;
    return (
      msg.includes('Failed to fetch dynamically imported module') ||
      msg.includes('Importing a module script failed') ||
      msg.includes('Loading chunk') ||
      msg.includes('Loading CSS chunk')
    );
  }
  return false;
}

export default function ErrorPage() {
  const { t } = useTranslation();
  const error = useRouteError();
  const [reloading, setReloading] = useState(false);

  useEffect(() => {
    if (!isChunkLoadError(error)) return;

    // Prevent infinite reload loops: only auto-reload once per navigation.
    // The flag is cleared on successful route load (RootLayout), so a later
    // deploy in the same session will still trigger an auto-reload.
    if (sessionStorage.getItem(RELOAD_KEY)) return;

    sessionStorage.setItem(RELOAD_KEY, '1');
    setReloading(true);
    window.location.reload();
  }, [error]);

  // While auto-reloading for stale chunks, show a minimal message
  if (reloading) {
    return (
      <BrandingProvider>
        <section className="flex min-h-screen flex-col items-center justify-center gap-4 text-center">
          <p className="text-gray-500">{t('Updating application...')}</p>
        </section>
      </BrandingProvider>
    );
  }

  return (
    <BrandingProvider>
      <section className="flex min-h-screen flex-col items-center justify-center gap-3 text-center">
        <div className="inline-flex justify-center items-center size-15.5 rounded-full bg-xibo-blue-100 text-xibo-blue-800 border-7 border-xibo-blue-50">
          <AlertTriangle className="shrink-0 size-6" />
        </div>

        <div>
          <h1
            className="text-4xl font-bold text-gray-800"
            style={{ fontFamily: "'Maven Pro', sans-serif" }}
          >
            {t('Unexpected Error')}
          </h1>
          <p className="mt-3 text-gray-500">
            {t('An error occurred while loading this page. Please try again.')}
          </p>
        </div>

        <div className="flex items-center justify-center gap-3">
          <Button variant="secondary" leftIcon={RefreshCw} onClick={() => window.location.reload()}>
            {t('Reload Page')}
          </Button>
          <Button variant="primary" onClick={() => (window.location.href = publicPath || '/')}>
            {t('Go to Dashboard')}
          </Button>
        </div>
      </section>
    </BrandingProvider>
  );
}
