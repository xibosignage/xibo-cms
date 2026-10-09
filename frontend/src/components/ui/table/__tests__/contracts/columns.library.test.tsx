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

import { EPOCH, SQL_DATETIME, propsWith, statesOf, textOf } from './helpers';

import { getDatasetColumns } from '@/pages/Library/Dataset/DatasetConfig';
import { getDynamicDataColumns } from '@/pages/Library/Dataset/subPages/Data/DatasetDataConfig';
import { getMediaColumns } from '@/pages/Library/Media/MediaConfig';
import { getMenuBoardColumns } from '@/pages/Library/MenuBoard/MenuBoardConfig';
import { getPlaylistColumns } from '@/pages/Library/Playlists/PlaylistsConfig';
import type { DatasetColumn } from '@/types/datasetColumn';

describe('Media grid', () => {
  const columns = getMediaColumns(propsWith());
  const media = (fields: object) => ({ mediaId: 1, name: 'a.jpg', fileSize: 1024, ...fields });

  // released (lib/Helper/LibraryDescription.php): 1 usable, 0 queued for resize, 2 too large to use.
  test('the Released column shows a usable image differently from one waiting to be resized', () => {
    const states = statesOf(columns, 'released', {
      released: media({ released: 1 }),
      pendingResize: media({ released: 0 }),
    });

    expect(states.pendingResize).not.toBe(states.released);
  });

  // "Too large" breaks every layout using the image; "pending" clears itself.
  test.fails(
    'the Released column shows an image too large to ever use differently from one waiting to be resized',
    () => {
      const states = statesOf(columns, 'released', {
        pendingResize: media({ released: 0 }),
        tooLarge: media({ released: 2 }),
      });

      expect(states.tooLarge).not.toBe(states.pendingResize);
    },
  );

  // enableStat is 'On', 'Off' or 'Inherit'; null is saved as the default, so it means Inherit too.
  test('the Stats column shows on, off and inherit distinctly, and null the same as inherit', () => {
    const states = statesOf(columns, 'enableStat', {
      on: media({ enableStat: 'On' }),
      off: media({ enableStat: 'Off' }),
      inherit: media({ enableStat: 'Inherit' }),
      none: media({ enableStat: null }),
    });

    expect(new Set([states.on, states.off, states.inherit]).size).toBe(3);
    expect(states.none).toBe(states.inherit);
  });

  test('the Revised column shows a replaced file differently from an original', () => {
    const states = statesOf(columns, 'revised', {
      revised: media({ revised: 1 }),
      original: media({ revised: 0 }),
    });

    expect(states.revised).not.toBe(states.original);
  });

  test('the Created column shows the date through the CMS formatter', () => {
    expect(textOf(columns, 'createdDt', media({ createdDt: SQL_DATETIME }))).toMatch(/^formatted:/);
  });
});

describe('Playlists grid', () => {
  const columns = getPlaylistColumns(propsWith());
  const playlist = (fields: object) => ({ playlistId: 1, name: 'P', duration: 30, ...fields });

  // requiresDurationUpdate: 0 up to date, 1 recalculation pending, an epoch = recalculation scheduled.
  test('the Duration column shows up-to-date, pending and scheduled durations distinctly', () => {
    const states = statesOf(columns, 'duration', {
      upToDate: playlist({ requiresDurationUpdate: 0 }),
      pending: playlist({ requiresDurationUpdate: 1 }),
      scheduled: playlist({ requiresDurationUpdate: EPOCH }),
    });

    expect(new Set(Object.values(states)).size).toBe(3);
  });

  test('the Stats column shows on, off and inherit distinctly', () => {
    const states = statesOf(columns, 'enableStat', {
      on: playlist({ enableStat: 'On' }),
      off: playlist({ enableStat: 'Off' }),
      inherit: playlist({ enableStat: 'Inherit' }),
    });

    expect(new Set(Object.values(states)).size).toBe(3);
  });

  test('the Dynamic column shows a dynamic playlist differently from a static one', () => {
    const states = statesOf(columns, 'isDynamic', {
      dynamic: playlist({ isDynamic: 1 }),
      fixed: playlist({ isDynamic: 0 }),
    });

    expect(states.dynamic).not.toBe(states.fixed);
  });
});

describe('Datasets grid', () => {
  const columns = getDatasetColumns(propsWith());
  const dataset = (fields: object) => ({ dataSetId: 1, dataSet: 'D', ...fields });

  test('the Remote column shows a remote dataset differently from a local one', () => {
    const states = statesOf(columns, 'isRemote', {
      remote: dataset({ isRemote: 1 }),
      local: dataset({ isRemote: 0 }),
    });

    expect(states.remote).not.toBe(states.local);
  });

  // The API sends lastDataEdit; "dataLastModified" is only a PHP sort alias.
  test.fails('the Modified column shows something for a dataset that has been edited', () => {
    expect(textOf(columns, 'dataLastModified', dataset({ lastDataEdit: EPOCH }))).not.toBe('');
  });

  // lastSync is a unix timestamp, and 0 means the dataset has never synced.
  test.fails('the Last Sync column shows a date, not a raw unix timestamp', () => {
    expect(textOf(columns, 'lastSync', dataset({ lastSync: EPOCH }))).not.toMatch(/^\d+$/);
  });

  // types/dataset.ts declares isRemote/isRealTime as boolean; the API sends 0/1.
  test.fails(
    'the Remote column shows the declared boolean `true` the same as the API value 1',
    () => {
      const states = statesOf(columns, 'isRemote', {
        api: dataset({ isRemote: 1 }),
        typed: dataset({ isRemote: true }),
      });

      expect(states.typed).toBe(states.api);
    },
  );
});

describe('Dataset data grid', () => {
  const schema = [
    { dataSetColumnId: 7, heading: 'price', dataTypeId: 2, columnOrder: 1, showSort: 1 },
  ] as unknown as DatasetColumn[];
  const columns = getDynamicDataColumns(schema, propsWith());

  test('an empty cell looks the same whether the value is null, empty or missing, and 0 is shown', () => {
    const states = statesOf(columns, 'price', {
      nullValue: { id: 1, price: null },
      empty: { id: 2, price: '' },
      missing: { id: 3 },
      zero: { id: 4, price: 0 },
    });

    expect(states.empty).toBe(states.nullValue);
    expect(states.missing).toBe(states.nullValue);
    expect(states.zero).not.toBe(states.nullValue);
  });
});

describe('Menu Boards grid', () => {
  // The cell uses the module-level formatDateTime from utils/date, which ignores the
  // CMS date format and timezone; the getter has no way to receive the CMS formatter.
  test.fails('the Modified column shows the date through the CMS formatter', () => {
    const columns = getMenuBoardColumns(propsWith());

    expect(textOf(columns, 'modifiedDt', { menuId: 1, name: 'M', modifiedDt: EPOCH })).toMatch(
      /^formatted:/,
    );
  });
});
