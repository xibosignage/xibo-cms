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

import { describe, expect, test } from 'vitest';

import { statesOf, t } from './helpers';

import { getUnusedColumns } from '@/pages/Dashboard/MediaDashboard/MediaDashboardConfig';
import { getDisplayColumns } from '@/pages/Dashboard/StatusDashboard/StatusDashboardConfig';

describe('Status Dashboard - Display Activity grid', () => {
  const columns = getDisplayColumns(t);
  const display = (fields: object) => ({ displayId: 1, display: 'Lobby', ...fields });

  // The API sends 0/1, not booleans. The tick and cross icons carry no text
  // (xibosignage/xibo#4041); comparing markup works either way.
  test('the Logged In column shows a logged-in display differently from a logged-out one', () => {
    const states = statesOf(columns, 'loggedIn', {
      on: display({ loggedIn: 1 }),
      off: display({ loggedIn: 0 }),
    });

    expect(states.on).not.toBe(states.off);
  });

  test('the Logged In column shows two logged-in displays the same', () => {
    const states = statesOf(columns, 'loggedIn', {
      lobby: display({ displayId: 1, display: 'Lobby', loggedIn: 1 }),
      reception: display({ displayId: 2, display: 'Reception', loggedIn: 1 }),
    });

    expect(states.lobby).toBe(states.reception);
  });

  test('the Authorised column shows an authorised display differently from an unauthorised one', () => {
    const states = statesOf(columns, 'licensed', {
      authorised: display({ licensed: 1 }),
      unauthorised: display({ licensed: 0 }),
    });

    expect(states.authorised).not.toBe(states.unauthorised);
  });

  test('the Authorised column shows two authorised displays the same', () => {
    const states = statesOf(columns, 'licensed', {
      lobby: display({ displayId: 1, display: 'Lobby', licensed: 1 }),
      reception: display({ displayId: 2, display: 'Reception', licensed: 1 }),
    });

    expect(states.lobby).toBe(states.reception);
  });
});

describe('Media Manager Dashboard - Unused Media grid', () => {
  const columns = getUnusedColumns(t);
  const media = (fields: object) => ({ mediaId: 1, name: 'a.png', ...fields });

  // Types are the lower-case strings /library sends.
  test('the Type column shows different media types differently', () => {
    const states = statesOf(columns, 'mediaType', {
      image: media({ mediaType: 'image' }),
      video: media({ mediaType: 'video' }),
    });

    expect(states.image).not.toBe(states.video);
  });

  test('the Type column shows two items of the same type the same', () => {
    const states = statesOf(columns, 'mediaType', {
      logo: media({ mediaId: 1, name: 'logo.png', mediaType: 'image' }),
      banner: media({ mediaId: 2, name: 'banner.png', mediaType: 'image' }),
    });

    expect(states.logo).toBe(states.banner);
  });
});
