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

import { useTranslation } from 'react-i18next';
import { Navigate, useLoaderData, useLocation, useParams, useSearchParams } from 'react-router-dom';

import EditorHost from '@/components/editor/EditorHost';
import { BrandingProvider } from '@/context/BrandingContext';
import { UserProvider } from '@/context/UserContext';
import type { User } from '@/types/user';

export default function LayoutEditorHost() {
  const { t } = useTranslation();
  const { id } = useParams<{ id: string }>();
  const [searchParams] = useSearchParams();
  const location = useLocation();
  const { user } = useLoaderData() as { user: User | null };
  const isTemplateEditor = searchParams.get('isTemplateEditor') === '1';

  const from = (location.state as { from?: string } | null)?.from;
  const returnPath = from ?? (isTemplateEditor ? '/design/templates' : '/design/layout');

  if (!id) {
    return <Navigate to="/design/layout" replace />;
  }

  return (
    <BrandingProvider branding={user?.branding}>
      <UserProvider initialUser={user}>
        <EditorHost
          id={id}
          editorBasePath="/layout/designer"
          stayPathPrefix="/layout/designer"
          returnPath={returnPath}
          forwardQuery={isTemplateEditor ? 'isTemplateEditor=1' : undefined}
          title={isTemplateEditor ? t('Template Editor') : t('Layout Editor')}
          readySelector="#layout-editor"
          showChrome
        />
      </UserProvider>
    </BrandingProvider>
  );
}
