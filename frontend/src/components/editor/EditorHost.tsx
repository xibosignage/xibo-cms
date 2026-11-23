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

import { Loader2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import type { NavigateFunction } from 'react-router-dom';

import EditorChrome, { type HelpSlot } from '@/components/editor/EditorChrome';
import { withPublicPath } from '@/config/publicPath';
import { HelpSourceContext } from '@/context/HelpSourceContext';

interface EditorHostProps {
  id: string;
  editorBasePath: string;
  stayPathPrefix: string;
  returnPath?: string;
  onExit?: () => void;
  onEditorNavigate?: (newId: string) => void;
  onScheduleRequest?: (campaignId: number, layoutName?: string) => void;
  title: string;
  forwardQuery?: string;
  readySelector?: string;
  chrome?: 'full' | 'helpOnly';
  contentName?: string;
}

interface EditorContent {
  id: string;
  name?: string;
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
  onEditorNavigate,
  onScheduleRequest,
  title,
  forwardQuery,
  readySelector,
  chrome,
  contentName,
}: EditorHostProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const iframeRef = useRef<HTMLIFrameElement>(null);
  const pollRef = useRef<number | null>(null);
  const lastIdRef = useRef(id);
  const [loading, setLoading] = useState(true);
  const [fullscreen, setFullscreen] = useState(false);
  const [content, setContent] = useState<EditorContent>({ id, name: contentName });
  const [helpSlot, setHelpSlot] = useState<HelpSlot | null>(null);
  const showChrome = chrome === 'full' || (chrome === 'helpOnly' && helpSlot !== null);

  const helpSource = content.name
    ? t('{{editor}}: {{name}} (ID {{id}})', { editor: title, name: content.name, id: content.id })
    : t('{{editor}} (ID {{id}})', { editor: title, id: content.id });

  const exit = () => leaveEditor(navigate, returnPath, onExit);

  useEffect(() => {
    const onMessage = (event: MessageEvent) => {
      if (event.origin !== window.location.origin) {
        return;
      }

      if (event.data?.type === 'xibo:editor-exit') {
        leaveEditor(navigate, returnPath, onExit);
      }

      if (event.data?.type === 'xibo:editor-fullscreen') {
        setFullscreen(Boolean(event.data.fullscreen));
      }

      if (event.data?.type === 'xibo:editor-schedule') {
        onScheduleRequest?.(event.data.campaignId, event.data.layoutName);
      }

      if (event.data?.type === 'xibo:editor-context') {
        setContent({ id: String(event.data.id), name: event.data.name });
      }

      if (event.data?.type === 'xibo:editor-help-slot') {
        setHelpSlot({ top: Number(event.data.top), left: Number(event.data.left) });
      }
    };

    window.addEventListener('message', onMessage);
    return () => window.removeEventListener('message', onMessage);
  }, [navigate, returnPath, onExit, onScheduleRequest]);

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
      const prefix = withPublicPath(stayPathPrefix);
      if (path && !path.startsWith(prefix)) {
        exit();
        return;
      }

      if (path) {
        const newId = path.slice(prefix.length).replace(/^\//, '').split('/')[0];
        if (newId && newId !== lastIdRef.current) {
          lastIdRef.current = newId;
          onEditorNavigate?.(newId);
        }
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

      {!loading && showChrome && !fullscreen && (
        <HelpSourceContext.Provider value={helpSource}>
          <EditorChrome helpSlot={chrome === 'helpOnly' ? helpSlot : null} />
        </HelpSourceContext.Provider>
      )}

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
