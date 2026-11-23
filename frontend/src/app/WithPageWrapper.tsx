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

import { useEffect } from 'react';
import { Outlet, useMatches } from 'react-router-dom';

import PageWrapper from '@/app/PageWrapper';
import { useBranding } from '@/context/BrandingContext';

export default function WithPageWrapper() {
  const matches = useMatches();
  const { productName } = useBranding();

  const title = matches
    .slice()
    .reverse()
    .find((m) => (m.handle as { title?: string } | null)?.title)?.handle as
    | { title?: string }
    | undefined;

  useEffect(() => {
    document.title = title?.title ? `${title.title} | ${productName}` : productName;
  }, [title, productName]);

  return (
    <PageWrapper>
      <Outlet />
    </PageWrapper>
  );
}
