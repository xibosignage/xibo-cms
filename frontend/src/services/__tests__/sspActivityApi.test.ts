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

// Boundary tests for src/services/sspActivityApi.ts (rule 1.6). Page tests mock fetchSspActivity
// and fetchSspPartners, so these are the only checks of what reaches the connector proxy.

import { vi, beforeEach, describe, test, expect } from 'vitest';

import { fetchConnectorProxy } from '@/services/connectorApi';
import { fetchSspActivity, fetchSspPartners } from '@/services/sspActivityApi';

vi.mock('@/services/connectorApi', () => ({
  fetchConnectors: vi.fn(),
  fetchConnectorProxy: vi.fn().mockResolvedValue({}),
}));

describe('sspActivityApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('activity sends the display, partner and dates to the connector', async () => {
    const signal = new AbortController().signal;

    await fetchSspActivity({
      connectorId: 7,
      displayId: 1,
      partnerId: 'partnerA',
      activityFromDt: '2027-01-01 00:00:00',
      activityToDt: '2027-01-01 23:59:00',
      signal,
    });

    expect(fetchConnectorProxy).toHaveBeenCalledWith(
      '7',
      'activity',
      {
        displayId: 1,
        partnerId: 'partnerA',
        activityFromDt: '2027-01-01 00:00:00',
        activityToDt: '2027-01-01 23:59:00',
      },
      signal,
    );
  });

  test('activity with no partner chosen sends none', async () => {
    await fetchSspActivity({
      connectorId: 7,
      displayId: 1,
      partnerId: '',
      activityFromDt: '2027-01-01 00:00:00',
      activityToDt: '2027-01-01 23:59:00',
    });

    expect(fetchConnectorProxy).toHaveBeenCalledWith(
      '7',
      'activity',
      expect.objectContaining({ partnerId: undefined }),
      undefined,
    );
  });

  test('partners are asked for with the partner-list method', async () => {
    const signal = new AbortController().signal;

    await fetchSspPartners(7, signal);

    expect(fetchConnectorProxy).toHaveBeenCalledWith(
      '7',
      'getAvailablePartnersFilter',
      undefined,
      signal,
    );
  });
});
