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

import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Navigate,
  useLoaderData,
  useLocation,
  useNavigate,
  useParams,
  useSearchParams,
} from 'react-router-dom';

import EditorHost from '@/components/editor/EditorHost';
import ScheduleEventModal from '@/components/ui/modals/ScheduleEventModal';
import { withPublicPath } from '@/config/publicPath';
import { BrandingProvider } from '@/context/BrandingContext';
import { UserProvider } from '@/context/UserContext';
import { layoutQueryKeys } from '@/pages/Design/Layouts/hooks/useLayoutData';
import { templateQueryKeys } from '@/pages/Design/Templates/hooks/useTemplatesData';
import { EventTypeId } from '@/types/event';
import type { User } from '@/types/user';

export default function LayoutEditorHost() {
  const { t } = useTranslation();
  const { id } = useParams<{ id: string }>();
  const [searchParams] = useSearchParams();
  const location = useLocation();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { user } = useLoaderData() as { user: User | null };
  const isTemplateEditor = searchParams.get('isTemplateEditor') === '1';
  const [scheduleRequest, setScheduleRequest] = useState<{
    campaignId: number;
    layoutName?: string;
  } | null>(null);

  const from = (location.state as { from?: string } | null)?.from;
  const returnPath = from ?? (isTemplateEditor ? '/design/templates' : '/design/layout');

  if (!id) {
    return <Navigate to="/design/layout" replace />;
  }

  const handleEditorNavigate = (newId: string) => {
    if (newId === id) {
      return;
    }
    const newPath = withPublicPath(`/design/layout/${newId}/editor`);
    window.history.replaceState(window.history.state, '', newPath + window.location.search);
  };

  // The legacy editor runs in an iframe with its own document, so edits made there
  // (name changes, publish scheduling, etc.) never touch the SPA's React Query cache.
  // Invalidate the list this editor returns to so it reflects those edits without
  // requiring a manual refresh.
  const handleExit = () => {
    queryClient.invalidateQueries({
      queryKey: isTemplateEditor ? templateQueryKeys.all : layoutQueryKeys.all,
    });
    navigate(returnPath);
  };

  return (
    <BrandingProvider branding={user?.branding}>
      <UserProvider initialUser={user}>
        <EditorHost
          id={id}
          editorBasePath="/layout/designer"
          stayPathPrefix="/layout/designer"
          onExit={handleExit}
          onEditorNavigate={handleEditorNavigate}
          onScheduleRequest={(campaignId, layoutName) =>
            setScheduleRequest({ campaignId, layoutName })
          }
          forwardQuery={isTemplateEditor ? 'isTemplateEditor=1' : undefined}
          title={isTemplateEditor ? t('Template Editor') : t('Layout Editor')}
          readySelector="#layout-editor"
          chrome="full"
        />
        {scheduleRequest && (
          <ScheduleEventModal
            isOpen
            onClose={() => setScheduleRequest(null)}
            mode="schedule"
            eventTypeId={EventTypeId.Layout}
            contentId={scheduleRequest.campaignId}
            contentName={scheduleRequest.layoutName}
          />
        )}
      </UserProvider>
    </BrandingProvider>
  );
}
