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
import { z } from 'zod';

import type { DatasetColumn } from '@/types/datasetColumn';

export const getDatasetSchema = (t: TFunction) =>
  z
    .object({
      dataSet: z
        .string()
        .trim()
        .min(1, t('Name is required'))
        .max(250, t('Name must be at most 250 characters')),
      description: z
        .string()
        .max(250, t('Description must be at most 250 characters'))
        .nullable()
        .optional(),
      code: z.string().max(50, t('Code must be at most 50 characters')).optional().nullable(),
      folderId: z.number().nullable().optional(),

      isRemote: z.boolean().optional(),
      isRealTime: z.boolean().optional(),

      dataConnectorSource: z.string().optional().nullable(),
      method: z.string().optional(),
      uri: z.string().optional().nullable(),
      postData: z.string().optional().nullable(),
      authentication: z.string().optional(),
      username: z.string().optional().nullable(),
      password: z.string().optional().nullable(),
      customHeaders: z.string().optional().nullable(),
      userAgent: z.string().optional().nullable(),
      refreshRate: z.number().optional(),
      clearRate: z.number().optional(),
      truncateOnEmpty: z.union([z.boolean(), z.number()]).optional(),
      runsAfter: z.number().optional(),
      dataRoot: z.string().optional().nullable(),
      summarize: z.string().optional(),
      summarizeField: z.string().optional().nullable(),
      sourceId: z.union([z.string(), z.number()]).optional(),
      ignoreFirstRow: z.boolean().optional(),
      rowLimit: z.number().optional(),
      limitPolicy: z.string().optional(),
      csvSeparator: z.string().optional().nullable(),
    })
    .superRefine((data, ctx) => {
      if (data.isRemote) {
        if (!data.uri || data.uri.trim() === '') {
          ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: t('URI is required for remote datasets.'),
            path: ['uri'],
          });
        }

        const requiresAuth = ['basic', 'digest', 'ntlm'].includes(data.authentication || '');
        if (requiresAuth && (!data.username || data.username.trim() === '')) {
          ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: t('Username is required for this authentication type.'),
            path: ['username'],
          });
        }
      }
    });

export const getDatasetColumnSchema = (t: TFunction) =>
  z.object({
    heading: z
      .string()
      .trim()
      .min(1, t('Heading is required'))
      .max(250, t('Heading must be at most 250 characters'))
      .refine((val) => !/\s/.test(val), {
        message: t('You cannot use a column name with spaces.'),
      }),
    dataSetColumnTypeId: z.number(),
    dataTypeId: z.number(),
    listContent: z.string().optional().nullable(),
    remoteField: z.string().optional().nullable(),
    columnOrder: z.number().optional(),
    tooltip: z.string().optional().nullable(),
    formula: z.string().optional().nullable(),
    showFilter: z.boolean().optional(),
    dateFormat: z.string().optional().nullable(),
    showSort: z.boolean().optional(),
    isRequired: z.boolean().optional(),
  });

export const getDatasetRssSchema = (t: TFunction) =>
  z.object({
    title: z
      .string()
      .trim()
      .min(1, t('Please enter title'))
      .max(250, t('Title must be at most 250 characters')),
    author: z
      .string()
      .trim()
      .min(1, t('Please enter author name'))
      .max(250, t('Author must be at most 250 characters')),

    titleColumnId: z.coerce.number().optional().nullable(),
    summaryColumnId: z.coerce.number().optional().nullable(),
    contentColumnId: z.coerce.number().optional().nullable(),
    publishedDateColumnId: z.coerce.number().optional().nullable(),

    regeneratePsk: z.boolean().optional(),

    sort: z.string().optional(),
    useOrderingClause: z.boolean().optional(),
    orderClause: z.array(z.string()).optional(),
    orderClauseDirection: z.array(z.string()).optional(),

    filter: z.string().optional(),
    useFilteringClause: z.boolean().optional(),
    filterClause: z.array(z.string()).optional(),
    filterClauseOperator: z.array(z.string()).optional(),
    filterClauseCriteria: z.array(z.string()).optional(),
    filterClauseValue: z.array(z.string()).optional(),
  });

export type DatasetRssFormValues = z.infer<ReturnType<typeof getDatasetRssSchema>>;

export const getDatasetDataSchema = (t: TFunction, columns: DatasetColumn[]) => {
  const shape: Record<string, z.ZodTypeAny> = {};

  columns.forEach((col) => {
    if (col.dataSetColumnTypeId === 1) {
      const fieldName = `dataSetColumnId_${col.dataSetColumnId}`;
      let fieldSchema: z.ZodTypeAny;

      switch (col.dataTypeId) {
        case 2: // Number
          fieldSchema = z.number({ invalid_type_error: t('Must be a number') });
          break;
        case 3: // Date
          fieldSchema = z.string().or(z.date());
          break;
        case 5: // Media ID (Number)
          fieldSchema = z.number();
          break;
        default: // String or HTML (1, 6)
          fieldSchema = z.string().trim();
      }

      if (col.isRequired) {
        if (fieldSchema instanceof z.ZodString) {
          fieldSchema = fieldSchema.min(1, t('{{heading}} is required', { heading: col.heading }));
        } else if (fieldSchema instanceof z.ZodNumber) {
          fieldSchema = fieldSchema.min(0, t('{{heading}} is required', { heading: col.heading }));
        }
      } else {
        fieldSchema = fieldSchema.optional().nullable();
      }

      shape[fieldName] = fieldSchema;
    }
  });

  return z.object(shape);
};
