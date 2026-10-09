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

import { getCommandColumns } from '@/pages/Displays/Commands/CommandsConfig';
import { buildCommand } from '@/pages/Displays/Commands/__tests__/fixtures/command';
import { getDisplayGroupColumns } from '@/pages/Displays/DisplayGroup/DisplayGroupConfig';
import { buildDisplayGroup } from '@/pages/Displays/DisplayGroup/__tests__/fixtures/displayGroup';
import { getDisplayProfileColumns } from '@/pages/Displays/DisplayProfile/DisplayProfileConfig';
import { buildDisplayProfile } from '@/pages/Displays/DisplayProfile/__tests__/fixtures/displayProfile';
import { getDisplayColumns } from '@/pages/Displays/Displays/DisplaysConfig';
import { buildDisplay } from '@/pages/Displays/Displays/__tests__/fixtures/display';
import { getPlayerVersionColumns } from '@/pages/Displays/PlayerVersions/PlayerVersionsConfig';
import { buildPlayerVersion } from '@/pages/Displays/PlayerVersions/__tests__/fixtures/playerVersion';
import { getSyncGroupColumns } from '@/pages/Displays/SyncGroups/SyncGroupsConfig';
import { buildSyncGroup } from '@/pages/Displays/SyncGroups/__tests__/fixtures/syncGroup';
import { knownFailure } from '@/testUtils/knownFailure';

describe('Displays grid', () => {
  const columns = getDisplayColumns(propsWith());
  const display = (fields: object) => ({ ...buildDisplay(), ...fields });

  // 1 complete, 2 incomplete (Xmds/Soap.php), 3 pending (DisplayNotifyService), null never reported.
  test('the Status column shows up to date, downloading, out of date and unknown distinctly', () => {
    const states = statesOf(columns, 'mediaInventoryStatus', {
      upToDate: display({ mediaInventoryStatus: 1 }),
      downloading: display({ mediaInventoryStatus: 2 }),
      outOfDate: display({ mediaInventoryStatus: 3 }),
      unknown: display({ mediaInventoryStatus: null }),
    });

    expect(new Set(Object.values(states)).size).toBe(4);
  });

  // Entity/Display.php: 0 not licensed, 1 licensed fully, 2 trial, 3 not applicable.
  test('the Commercial Licence column shows each licence state distinctly', () => {
    const states = statesOf(columns, 'commercialLicence', {
      none: display({ commercialLicence: 0 }),
      full: display({ commercialLicence: 1 }),
      trial: display({ commercialLicence: 2 }),
      notApplicable: display({ commercialLicence: 3 }),
    });

    expect(new Set(Object.values(states)).size).toBe(4);
  });

  // 0 failed, 1 succeeded, 2 unknown (Entity/Display.php).
  test('the Last Command column shows failed, succeeded and unknown distinctly', () => {
    const states = statesOf(columns, 'lastCommandSuccess', {
      failed: display({ lastCommandSuccess: 0 }),
      succeeded: display({ lastCommandSuccess: 1 }),
      unknown: display({ lastCommandSuccess: 2 }),
    });

    expect(new Set(Object.values(states)).size).toBe(3);
  });

  test('the Licensed column shows a licensed display differently from an unlicensed one', () => {
    const states = statesOf(columns, 'licensed', {
      licensed: display({ licensed: 1 }),
      unlicensed: display({ licensed: 0 }),
    });

    expect(states.licensed).not.toBe(states.unlicensed);
  });

  test('the Last Accessed column shows the date through the CMS formatter', () => {
    expect(textOf(columns, 'lastAccessed', display({ lastAccessed: EPOCH }))).toMatch(
      /^formatted:/,
    );
  });

  // Cancelling a transfer writes '' (Display::moveCmsCancel); never-transferred is NULL.
  knownFailure(
    'the CMS Transfer column shows a cancelled transfer the same as a display never transferred',
    () => {
      const states = statesOf(columns, 'cmsTransfer', {
        neverTransferred: display({ newCmsAddress: null }),
        cancelled: display({ newCmsAddress: '' }),
        inProgress: display({ newCmsAddress: 'https://cms.example.com' }),
      });
      // Control: the column does tell a transfer in progress from none.
      expect(states.inProgress).not.toBe(states.neverTransferred);
      return states;
    },
    (states) => expect(states.cancelled).toBe(states.neverTransferred),
  );

  // A cleared XMR channel is '' and means "not registered".
  knownFailure(
    'the XMR Registered column shows a cleared channel the same as a display never registered',
    () => {
      const states = statesOf(columns, 'xmrRegistered', {
        neverRegistered: display({ xmrChannel: null }),
        cleared: display({ xmrChannel: '' }),
        registered: display({ xmrChannel: 'abc123channel' }),
      });
      // Control: the column does tell a registered display from one never registered.
      expect(states.registered).not.toBe(states.neverRegistered);
      return states;
    },
    (states) => expect(states.cleared).toBe(states.neverRegistered),
  );

  // The edit form saves '' for a blank TeamViewer serial, and
  // `teamViewerSerial ?? webkeySerial` keeps the '' instead of falling through to Webkey.
  knownFailure(
    'the Remote column shows the Webkey serial when the TeamViewer serial is blank',
    () => textOf(columns, 'remote', display({ teamViewerSerial: '', webkeySerial: 'WK-1' })),
    (text) => expect(text).toBe('WK-1'),
  );
});

describe('Display Groups grid', () => {
  const columns = getDisplayGroupColumns(propsWith());
  const group = (fields: object) => ({ ...buildDisplayGroup(), ...fields });

  test('the Dynamic column shows a dynamic group differently from a static one', () => {
    const states = statesOf(columns, 'isDynamic', {
      dynamic: group({ isDynamic: 1 }),
      fixed: group({ isDynamic: 0 }),
    });

    expect(states.dynamic).not.toBe(states.fixed);
  });

  test('the Created column shows the date through the CMS formatter', () => {
    expect(textOf(columns, 'createdDt', group({ createdDt: SQL_DATETIME }))).toMatch(/^formatted:/);
  });
});

describe('Display Profiles grid', () => {
  const columns = getDisplayProfileColumns(propsWith());
  const profile = (fields: object) => ({ ...buildDisplayProfile(), ...fields });

  test('the Default column shows the default profile differently from the others', () => {
    const states = statesOf(columns, 'isDefault', {
      isDefault: profile({ isDefault: 1 }),
      other: profile({ isDefault: 0 }),
    });

    expect(states.isDefault).not.toBe(states.other);
  });
});

describe('Sync Groups grid', () => {
  test('the Created column shows the date through the CMS formatter', () => {
    const columns = getSyncGroupColumns(propsWith());

    expect(textOf(columns, 'createdDt', { ...buildSyncGroup(), createdDt: SQL_DATETIME })).toMatch(
      /^formatted:/,
    );
  });
});

describe('Player Versions grid', () => {
  // The getter has no CMS formatter, so the server's string is printed as-is.
  const columns = getPlayerVersionColumns(propsWith());
  const row = { ...buildPlayerVersion(), createdAt: SQL_DATETIME, modifiedAt: SQL_DATETIME };

  knownFailure(
    'the Created column shows the date through the CMS formatter',
    () => textOf(columns, 'createdAt', row),
    (text) => expect(text).toMatch(/^formatted:/),
  );

  knownFailure(
    'the Modified column shows the date through the CMS formatter',
    () => textOf(columns, 'modifiedAt', row),
    (text) => expect(text).toMatch(/^formatted:/),
  );
});

describe('Commands grid', () => {
  const columns = getCommandColumns(propsWith());
  const command = (fields: object) => ({ ...buildCommand(), ...fields });

  // availableOn NULL means every player type (Controller/Command.php).
  test('the Available On column shows "All" for a command with no player-type restriction', () => {
    expect(textOf(columns, 'availableOn', command({ availableOn: null }))).toBe('All');
    expect(textOf(columns, 'availableOn', command({ availableOn: 'android' }))).toBe('android');
  });
});
