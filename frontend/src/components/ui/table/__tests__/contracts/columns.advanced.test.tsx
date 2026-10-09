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

import type { ColumnDef } from '@tanstack/react-table';
import type { TFunction } from 'i18next';
import { describe, expect, test } from 'vitest';

import { EPOCH, SQL_DATETIME, formatDateTime, propsWith, statesOf, t, textOf } from './helpers';

import { getAuditTrailColumns } from '@/pages/Advanced/AuditTrail/AuditTrailConfig';
import { getLogsColumns } from '@/pages/Advanced/Logs/LogsConfig';
import { getSessionColumns } from '@/pages/Advanced/Sessions/SessionsConfig';

describe('Audit Trail grid', () => {
  const columns = getAuditTrailColumns(t, formatDateTime);
  const log = (fields: object) => ({ logId: 1, message: 'm', ...fields });

  // entityId 0 and NULL both mean "no entity".
  test('the Entity ID column shows "no entity" the same for 0 and null, and the id otherwise', () => {
    const states = statesOf(columns, 'entityId', {
      zero: log({ entityId: 0 }),
      none: log({ entityId: null }),
      entity: log({ entityId: 42 }),
    });

    expect(states.zero).toBe(states.none);
    expect(states.entity).not.toBe(states.none);
  });

  test('the Date column shows the date through the CMS formatter', () => {
    expect(textOf(columns, 'logDate', log({ logDate: EPOCH }))).toMatch(/^formatted:/);
  });
});

describe('Logs grid', () => {
  // getLogsColumns takes only `t`, so there is no way to hand it the CMS
  // formatter; the cast passes one anyway so the test starts passing once the getter accepts it.
  test.fails('the Date column shows the date through the CMS formatter', () => {
    const getColumns = getLogsColumns as unknown as (
      translate: TFunction,
      format: typeof formatDateTime,
    ) => ColumnDef<object>[];

    expect(
      textOf(getColumns(t, formatDateTime), 'logDate', { logId: 1, logDate: SQL_DATETIME }),
    ).toMatch(/^formatted:/);
  });
});

describe('Sessions grid', () => {
  const columns = getSessionColumns(propsWith());
  const session = (fields: object) => ({ userId: 1, userName: 'u', ...fields });

  test('the Active column shows an expired session differently from an active one', () => {
    const states = statesOf(columns, 'isExpired', {
      expired: session({ isExpired: 1 }),
      active: session({ isExpired: 0 }),
    });

    expect(states.expired).not.toBe(states.active);
  });

  // types/session.ts declares `isExpired: boolean`; the API sends 0/1 and the
  // cell compares with 1, so an expired session built to the declared type shows as active.
  test.fails(
    'the Active column shows the declared boolean `true` the same as the API value 1',
    () => {
      const states = statesOf(columns, 'isExpired', {
        api: session({ isExpired: 1 }),
        typed: session({ isExpired: true }),
      });

      expect(states.typed).toBe(states.api);
    },
  );

  test('the Last Accessed column shows the date through the CMS formatter', () => {
    expect(textOf(columns, 'lastAccessed', session({ lastAccessed: SQL_DATETIME }))).toMatch(
      /^formatted:/,
    );
  });
});
