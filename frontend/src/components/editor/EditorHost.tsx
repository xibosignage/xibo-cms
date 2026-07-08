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

import { Loader2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import type { NavigateFunction } from 'react-router-dom';

import EditorChrome from '@/components/editor/EditorChrome';
import { withPublicPath } from '@/config/publicPath';

interface EditorHostProps {
  id: string;
  editorBasePath: string;
  stayPathPrefix: string;
  returnPath?: string;
  onExit?: () => void;
  title: string;
  forwardQuery?: string;
  readySelector?: string;
  showChrome?: boolean;
}

const READY_TIMEOUT_MS = 8000;
const READY_POLL_MS = 80;

function leaveEditor(
  navigate: NavigateFunction,
  returnPath: string | undefined,
  onExit: (() => void) | undefined,
) {
  if (onExit) {
    onExit();
    return;
  }

  if (returnPath) {
    navigate(returnPath);
  }
}

export default function EditorHost({
  id,
  editorBasePath,
  stayPathPrefix,
  returnPath,
  onExit,
  title,
  forwardQuery,
  readySelector,
  showChrome,
}: EditorHostProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const iframeRef = useRef<HTMLIFrameElement>(null);
  const pollRef = useRef<number | null>(null);
  const [loading, setLoading] = useState(true);

  const exit = () => leaveEditor(navigate, returnPath, onExit);

  useEffect(() => {
    const onMessage = (event: MessageEvent) => {
      if (event.origin !== window.location.origin) {
        return;
      }

      if (event.data?.type === 'xibo:editor-exit') {
        leaveEditor(navigate, returnPath, onExit);
      }
    };

    window.addEventListener('message', onMessage);
    return () => window.removeEventListener('message', onMessage);
  }, [navigate, returnPath, onExit]);

  useEffect(() => {
    return () => {
      if (pollRef.current !== null) {
        window.clearInterval(pollRef.current);
      }
    };
  }, []);

  const handleLoad = () => {
    const frame = iframeRef.current;
    if (!frame) {
      return;
    }

    try {
      const path = frame.contentWindow?.location.pathname ?? '';
      // The iframe loads the legacy editor under the install root (e.g. /cms/layout/designer/5),
      // so compare against the rootUri-prefixed prefix — otherwise we'd wrongly detect a
      // navigation-away and exit on alias installs.
      if (path && !path.startsWith(withPublicPath(stayPathPrefix))) {
        exit();
        return;
      }
    } catch {
      // Ignore
    }

    frame.focus();

    if (!readySelector) {
      setLoading(false);
      return;
    }

    let elapsed = 0;
    if (pollRef.current !== null) {
      window.clearInterval(pollRef.current);
    }
    pollRef.current = window.setInterval(() => {
      elapsed += READY_POLL_MS;
      let ready = elapsed >= READY_TIMEOUT_MS;
      try {
        const root = frame.contentWindow?.document.querySelector(readySelector);
        ready = ready || !root || root.childElementCount > 0;
      } catch {
        ready = true;
      }

      if (ready) {
        if (pollRef.current !== null) {
          window.clearInterval(pollRef.current);
          pollRef.current = null;
        }
        setLoading(false);
      }
    }, READY_POLL_MS);
  };

  // editorBasePath is a CMS route (e.g. /layout/designer), so prefix with the install root
  // (rootUri) — the iframe must load /cms/layout/designer/5 on alias installs, not domain root.
  const src = withPublicPath(
    `${editorBasePath}/${id}?embed=1${forwardQuery ? `&${forwardQuery}` : ''}`,
  );

  return (
    <div className="relative h-screen w-full">
      {loading && (
        <div className="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-white/80">
          <Loader2 className="h-10 w-10 animate-spin text-gray-400" />
          <span className="text-sm font-medium text-gray-500">{t('Loading editor…')}</span>
        </div>
      )}

      {!loading && showChrome && <EditorChrome />}

      <iframe
        ref={iframeRef}
        src={src}
        title={title}
        className="h-full w-full border-0"
        onLoad={handleLoad}
      />
    </div>
  );
}
