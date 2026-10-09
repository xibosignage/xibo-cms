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

import { getCampaignColumn } from '@/pages/Design/Campaigns/CampaignConfig';
import { getLayoutColumns } from '@/pages/Design/Layouts/LayoutConfig';
import { getResolutionColumns } from '@/pages/Design/Resolutions/ResolutionsConfig';
import { getTemplateColumn } from '@/pages/Design/Templates/TemplatesConfig';
import { knownFailure } from '@/testUtils/knownFailure';

describe('Layouts grid', () => {
  const columns = getLayoutColumns(propsWith());
  const layout = (fields: object) => ({ layoutId: 1, layout: 'L', ...fields });

  // lib/Helper/Status.php: 1 valid, 2 valid with player warnings, 3 not built yet, 4 invalid.
  test('the Valid? column shows a valid layout differently from an invalid one', () => {
    const states = statesOf(columns, 'valid', {
      valid: layout({ status: 1 }),
      invalid: layout({ status: 4 }),
    });

    expect(states.valid).not.toBe(states.invalid);
  });

  //"Not built yet" is the normal state for most layouts. This column is visible by default.
  knownFailure(
    'the Valid? column shows a layout that has not been built yet differently from a broken one',
    () =>
      statesOf(columns, 'valid', {
        notBuilt: layout({ status: 3 }),
        invalid: layout({ status: 4 }),
      }),
    (states) => expect(states.notBuilt).not.toBe(states.invalid),
  );

  // The legacy grid drew a warning icon for status 2.
  knownFailure(
    'the Valid? column shows a layout with player warnings differently from a fully valid one',
    () =>
      statesOf(columns, 'valid', {
        valid: layout({ status: 1 }),
        warnings: layout({ status: 2 }),
      }),
    (states) => expect(states.warnings).not.toBe(states.valid),
  );

  // enableStat NULL means "use LAYOUT_STATS_ENABLED_DEFAULT" (Entity/Layout.php), not "off".
  knownFailure(
    'the Stats? column shows "use the default" differently from "off"',
    () =>
      statesOf(columns, 'enableStat', {
        inherit: layout({ enableStat: null }),
        off: layout({ enableStat: 0 }),
      }),
    (states) => expect(states.inherit).not.toBe(states.off),
  );

  test('the Stats? column shows on differently from off', () => {
    const states = statesOf(columns, 'enableStat', {
      on: layout({ enableStat: 1 }),
      off: layout({ enableStat: 0 }),
    });

    expect(states.on).not.toBe(states.off);
  });

  test('the Status column shows a published layout differently from a draft', () => {
    const states = statesOf(columns, 'publishedStatus', {
      published: layout({ publishedStatus: 'Published', publishedDate: null }),
      draft: layout({ publishedStatus: 'Draft', publishedDate: null }),
    });

    expect(states.published).not.toBe(states.draft);
  });

  test('the Modified column shows the date through the CMS formatter', () => {
    expect(textOf(columns, 'modifiedDt', layout({ modifiedDt: SQL_DATETIME }))).toMatch(
      /^formatted:/,
    );
  });

  test('the Code column shows a dash when the layout has no code', () => {
    expect(textOf(columns, 'code', layout({ code: null }))).toBe('-');
  });
});

describe('Campaigns grid', () => {
  const columns = getCampaignColumn(propsWith({ canAccessAdCampaign: true }));
  const campaign = (fields: object) => ({ campaignId: 1, campaign: 'C', ...fields });

  test('the Type column shows an ad campaign differently from a layout list', () => {
    const states = statesOf(columns, 'type', {
      ad: campaign({ type: 'ad' }),
      list: campaign({ type: 'list' }),
    });

    expect(states.ad).not.toBe(states.list);
  });

  // CampaignFactory runs startDt through intval, so a list campaign has 0, meaning "no start date".
  test('the Start column shows a dash for a campaign with no start date, and a CMS date otherwise', () => {
    expect(textOf(columns, 'startDt', campaign({ startDt: 0 }))).toBe('-');
    expect(textOf(columns, 'startDt', campaign({ startDt: EPOCH }))).toMatch(/^formatted:/);
  });

  test('the Cycle Based Playback column shows enabled differently from disabled', () => {
    const states = statesOf(columns, 'cyclePlaybackEnabled', {
      enabled: campaign({ cyclePlaybackEnabled: 1 }),
      disabled: campaign({ cyclePlaybackEnabled: 0 }),
    });

    expect(states.enabled).not.toBe(states.disabled);
  });

  // The getter receives formatDateTime but these two cells print the server string as-is.
  knownFailure(
    'the Created column shows the date through the CMS formatter',
    () => textOf(columns, 'createdAt', campaign({ createdAt: SQL_DATETIME })),
    (text) => expect(text).toMatch(/^formatted:/),
  );

  knownFailure(
    'the Modified column shows the date through the CMS formatter',
    () => textOf(columns, 'modifiedAt', campaign({ modifiedAt: SQL_DATETIME })),
    (text) => expect(text).toMatch(/^formatted:/),
  );
});

describe('Templates grid', () => {
  const columns = getTemplateColumn(propsWith());
  const template = (fields: object) => ({ layoutId: 1, layout: 'T', ...fields });

  test('the Status column shows a published template differently from a draft', () => {
    const states = statesOf(columns, 'publishedStatus', {
      published: template({ publishedStatus: 'Published' }),
      draft: template({ publishedStatus: 'Draft' }),
    });

    expect(states.published).not.toBe(states.draft);
  });

  test('the Modified column shows the date through the CMS formatter', () => {
    expect(textOf(columns, 'modifiedDt', template({ modifiedDt: SQL_DATETIME }))).toMatch(
      /^formatted:/,
    );
  });
});

describe('Resolutions grid', () => {
  const columns = getResolutionColumns(propsWith());
  const resolution = (fields: object) => ({ resolutionId: 1, resolution: 'HD', ...fields });

  test('the Enabled column shows an enabled resolution differently from a disabled one', () => {
    const states = statesOf(columns, 'enabled', {
      enabled: resolution({ enabled: 1 }),
      disabled: resolution({ enabled: 0 }),
    });

    expect(states.enabled).not.toBe(states.disabled);
  });

  // types/resolution.ts declares `enabled: boolean`, but the API sends 0/1 and the
  // cell compares with 1. A row built to the declared type shows as disabled.
  knownFailure(
    'the Enabled column shows the declared boolean `true` the same as the API value 1',
    () =>
      statesOf(columns, 'enabled', {
        api: resolution({ enabled: 1 }),
        typed: resolution({ enabled: true }),
      }),
    (states) => expect(states.typed).toBe(states.api),
  );
});
