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

import type { TFunction } from 'i18next';
import { describe, it, expect } from 'vitest';

import { getDatasetColumnSchema, getDatasetRssSchema, getDatasetSchema } from '@/schema/dataset';

const t = ((s: string) => s) as unknown as TFunction;

describe('getDatasetSchema', () => {
  it('rejects an empty name', () => {
    const result = getDatasetSchema(t).safeParse({ dataSet: '' });
    expect(result.success).toBe(false);
    if (!result.success) {
      const msgs = result.error.flatten().fieldErrors.dataSet;
      expect(msgs).toBeDefined();
    }
  });

  it('rejects a remote dataset with a missing URI', () => {
    const result = getDatasetSchema(t).safeParse({
      dataSet: 'My Dataset',
      isRemote: true,
      uri: '',
    });
    expect(result.success).toBe(false);
    if (!result.success) {
      const issues = result.error.issues;
      expect(issues.some((i) => i.path.includes('uri'))).toBe(true);
    }
  });

  it('accepts a valid local dataset payload', () => {
    const result = getDatasetSchema(t).safeParse({
      dataSet: 'Sales Data',
      description: 'Monthly data',
      code: 'SALES',
      isRemote: false,
    });
    expect(result.success).toBe(true);
  });

  it('accepts a valid remote dataset payload with a URI', () => {
    const result = getDatasetSchema(t).safeParse({
      dataSet: 'Remote Data',
      isRemote: true,
      uri: 'https://example.com/api/data',
    });
    expect(result.success).toBe(true);
  });
});

describe('getDatasetColumnSchema', () => {
  it('rejects an empty heading', () => {
    const result = getDatasetColumnSchema(t).safeParse({
      heading: '',
      dataSetColumnTypeId: 1,
      dataTypeId: 1,
    });
    expect(result.success).toBe(false);
    if (!result.success) {
      const msgs = result.error.flatten().fieldErrors.heading;
      expect(msgs).toBeDefined();
    }
  });

  it('rejects a heading that contains spaces', () => {
    const result = getDatasetColumnSchema(t).safeParse({
      heading: 'my column',
      dataSetColumnTypeId: 1,
      dataTypeId: 1,
    });
    expect(result.success).toBe(false);
    if (!result.success) {
      const msgs = result.error.flatten().fieldErrors.heading;
      expect(msgs).toBeDefined();
    }
  });

  it('accepts a valid column payload', () => {
    const result = getDatasetColumnSchema(t).safeParse({
      heading: 'MyColumn',
      dataSetColumnTypeId: 1,
      dataTypeId: 1,
      showFilter: false,
      showSort: false,
      isRequired: false,
    });
    expect(result.success).toBe(true);
  });
});

describe('getDatasetRssSchema', () => {
  it('rejects an empty title', () => {
    const result = getDatasetRssSchema(t).safeParse({ title: '', author: 'Author' });
    expect(result.success).toBe(false);
    if (!result.success) {
      const msgs = result.error.flatten().fieldErrors.title;
      expect(msgs).toBeDefined();
    }
  });

  it('rejects an empty author', () => {
    const result = getDatasetRssSchema(t).safeParse({ title: 'My Feed', author: '' });
    expect(result.success).toBe(false);
    if (!result.success) {
      const msgs = result.error.flatten().fieldErrors.author;
      expect(msgs).toBeDefined();
    }
  });

  it('accepts a valid RSS payload', () => {
    const result = getDatasetRssSchema(t).safeParse({
      title: 'News Feed',
      author: 'Admin',
    });
    expect(result.success).toBe(true);
  });
});
