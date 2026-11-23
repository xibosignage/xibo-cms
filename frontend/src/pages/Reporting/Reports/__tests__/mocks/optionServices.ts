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

import { vi } from 'vitest';

import {
  CAMPAIGN_PAGES,
  DISPLAY_GROUP_PAGES,
  DISPLAY_PAGES,
  LAYOUT_PAGES,
  MEDIA_PAGES,
  USER_GROUP_PAGES,
  USER_PAGES,
} from '../fixtures/options';

import { fetchCampaigns } from '@/services/campaignApi';
import { fetchDisplayGroups } from '@/services/displayGroupApi';
import { fetchDisplays } from '@/services/displaysApi';
import { fetchLayouts } from '@/services/layoutsApi';
import { fetchMedia } from '@/services/mediaApi';
import { fetchUsers } from '@/services/userApi';
import { fetchUserGroups } from '@/services/userGroupApi';

// Each service returns the first page of its list. A test that needs a later page or a single-item
// lookup overrides that service itself, so the mock never decides from the request.
// Requires vi.mock blocks for every option service (displays, layouts, users, …).
export const setupOptionServiceMocks = () => {
  vi.mocked(fetchDisplays).mockResolvedValue(DISPLAY_PAGES[0] as never);
  vi.mocked(fetchDisplayGroups).mockResolvedValue(DISPLAY_GROUP_PAGES[0] as never);
  vi.mocked(fetchLayouts).mockResolvedValue(LAYOUT_PAGES[0] as never);
  vi.mocked(fetchMedia).mockResolvedValue(MEDIA_PAGES[0] as never);
  vi.mocked(fetchCampaigns).mockResolvedValue(CAMPAIGN_PAGES[0] as never);
  vi.mocked(fetchUsers).mockResolvedValue(USER_PAGES[0] as never);
  vi.mocked(fetchUserGroups).mockResolvedValue(USER_GROUP_PAGES[0] as never);
};
