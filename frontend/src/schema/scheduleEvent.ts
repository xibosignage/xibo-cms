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

import { EventTypeId } from '@/types/event';

export const getScheduleEventSchema = (t: TFunction) =>
  z
    .object({
      eventTypeId: z.nativeEnum(EventTypeId, {
        required_error: t('Event Type is required'),
      }),

      mediaId: z.number().nullable(),
      campaignId: z.number().nullable(),
      commandId: z.number().nullable(),
      playlistId: z.number().nullable(),
      syncGroupId: z.number().nullable(),
      dataSetId: z.number().nullable(),
      dataSetParams: z.string().optional(),
      syncDisplayLayouts: z.record(z.string(), z.number().nullable()),
      actionType: z.string(),
      actionTriggerCode: z.string(),
      actionLayoutCode: z.string(),
      shareOfVoice: z.number().min(0).max(3600),

      displaySpecificGroupIds: z.array(z.number()),
      displayGroupIds: z.array(z.number()),

      dayPartId: z.string().min(1, t('Dayparting is required')),

      fromDt: z.string().optional(),
      toDt: z.string().optional(),
      useRelativeTime: z.boolean(),
      relativeHours: z.number().min(0),
      relativeMinutes: z.number().min(0),
      relativeSeconds: z.number().min(0),

      name: z.string().max(50, t('Name must be less than 50 characters')).optional(),
      layoutDuration: z.number().min(0).optional(),
      resolutionId: z.string().optional(),
      backgroundColor: z.string().optional(),
      displayOrder: z.number().min(0),
      isPriority: z.number().min(0),
      maxPlaysPerHour: z.number().min(0),
      syncTimezone: z.boolean(),

      recurrenceType: z.string(),
      recurrenceDetail: z.number().min(1),
      recurrenceRepeatsOn: z.array(z.string()),
      recurrenceMonthlyRepeatsOn: z.number(),
      recurrenceRange: z.string(),

      reminders: z.array(
        z.object({
          value: z.number(),
          type: z.number(),
          option: z.number(),
          isEmail: z.boolean(),
        }),
      ),

      isGeoAware: z.boolean(),

      criteria: z.array(
        z.object({
          type: z.string(),
          metric: z.string(),
          condition: z.string(),
          value: z.string(),
        }),
      ),
    })
    .superRefine((data, ctx) => {
      if (
        data.displaySpecificGroupIds.length === 0 &&
        data.displayGroupIds.length === 0 &&
        data.eventTypeId !== EventTypeId.Sync
      ) {
        ctx.addIssue({
          path: ['displayGroupIds'],
          code: z.ZodIssueCode.custom,
          message: t('Please select at least one Display or Display Group'),
        });
      }

      const contentTypes = [
        EventTypeId.Layout,
        EventTypeId.Overlay,
        EventTypeId.Interrupt,
        EventTypeId.Campaign,
      ];

      if (contentTypes.includes(data.eventTypeId) && !data.campaignId) {
        ctx.addIssue({
          path: ['campaignId'],
          code: z.ZodIssueCode.custom,
          message: t('Please select a Layout or Campaign'),
        });
      }

      if (data.eventTypeId === EventTypeId.Command && !data.commandId) {
        ctx.addIssue({
          path: ['commandId'],
          code: z.ZodIssueCode.custom,
          message: t('Please select a Command'),
        });
      }

      if (data.eventTypeId === EventTypeId.Media && !data.mediaId && !data.campaignId) {
        ctx.addIssue({
          path: ['mediaId'],
          code: z.ZodIssueCode.custom,
          message: t('Please select a Media item'),
        });
      }

      if (data.eventTypeId === EventTypeId.Playlist && !data.playlistId && !data.campaignId) {
        ctx.addIssue({
          path: ['playlistId'],
          code: z.ZodIssueCode.custom,
          message: t('Please select a Playlist'),
        });
      }

      if (data.eventTypeId === EventTypeId.Sync && !data.syncGroupId) {
        ctx.addIssue({
          path: ['syncGroupId'],
          code: z.ZodIssueCode.custom,
          message: t('Please select a Sync Group'),
        });
      }

      if (data.eventTypeId === EventTypeId.DataConnector && !data.dataSetId) {
        ctx.addIssue({
          path: ['dataSetId'],
          code: z.ZodIssueCode.custom,
          message: t('Please select a DataSet'),
        });
      }

      if (data.eventTypeId === EventTypeId.Action) {
        if (!data.actionType) {
          ctx.addIssue({
            path: ['actionType'],
            code: z.ZodIssueCode.custom,
            message: t('Please select an Action Type'),
          });
        }
        if (!data.actionTriggerCode) {
          ctx.addIssue({
            path: ['actionTriggerCode'],
            code: z.ZodIssueCode.custom,
            message: t('Please enter a Trigger Code'),
          });
        }
        if (data.actionType === 'navLayout' && !data.actionLayoutCode) {
          ctx.addIssue({
            path: ['actionLayoutCode'],
            code: z.ZodIssueCode.custom,
            message: t('Please select a Layout Code'),
          });
        }
        if (data.actionType === 'command' && !data.commandId) {
          ctx.addIssue({
            path: ['commandId'],
            code: z.ZodIssueCode.custom,
            message: t('Please select a Command'),
          });
        }
      }

      if (data.eventTypeId === EventTypeId.Interrupt && data.shareOfVoice <= 0) {
        ctx.addIssue({
          path: ['shareOfVoice'],
          code: z.ZodIssueCode.custom,
          message: t('Share of Voice must be between 1 and 3600'),
        });
      }

      if (data.useRelativeTime) {
        if (data.relativeHours === 0 && data.relativeMinutes === 0 && data.relativeSeconds === 0) {
          ctx.addIssue({
            path: ['relativeHours'],
            code: z.ZodIssueCode.custom,
            message: t('Please set a duration greater than 0'),
          });
        }
      } else if (data.eventTypeId !== EventTypeId.Command && data.fromDt && data.toDt) {
        if (new Date(data.toDt) <= new Date(data.fromDt)) {
          ctx.addIssue({
            path: ['toDt'],
            code: z.ZodIssueCode.custom,
            message: t('End time must be after start time'),
          });
        }
      }
    });
