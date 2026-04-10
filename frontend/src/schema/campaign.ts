import type { TFunction } from 'i18next';
import { z } from 'zod';

export const getCampaignSchema = (t: TFunction) =>
  z
    .object({
      name: z
        .string()
        .min(1, t('Name is required'))
        .max(100, t('Name must be less than 100 characters')),

      folderId: z.number().nullable(),

      tags: z
        .array(
          z.object({
            tag: z.string(),
            value: z.string().optional(),
          }),
        )
        .optional(),

      type: z.enum(['list', 'ad']),

      cyclePlaybackEnabled: z.boolean(),

      playCount: z.union([z.number(), z.literal('')]).optional(),

      listPlayOrder: z.enum(['round', 'block']).optional(),

      targetType: z.enum(['plays', 'budget', 'impressions']).optional(),

      target: z.union([z.number(), z.literal('')]).optional(),
    })
    .superRefine((data, ctx) => {
      if (data.type === 'ad') {
        if (data.target === '' || data.target == null) {
          ctx.addIssue({
            path: ['target'],
            code: z.ZodIssueCode.custom,
            message: t('Target is required for Ad Campaigns'),
          });
        } else if (Number(data.target) <= 0) {
          ctx.addIssue({
            path: ['target'],
            code: z.ZodIssueCode.custom,
            message: t('Target must be greater than 0'),
          });
        }
      } else if (data.cyclePlaybackEnabled) {
        if (!data.playCount || data.playCount === null) {
          ctx.addIssue({
            path: ['playCount'],
            code: z.ZodIssueCode.custom,
            message: t('Play count is required when cycle playback is enabled'),
          });
        } else if (Number(data.playCount) <= 0) {
          ctx.addIssue({
            path: ['playCount'],
            code: z.ZodIssueCode.custom,
            message: t('Play count must be greater than 0'),
          });
        }
      } else {
        if (!data.listPlayOrder) {
          ctx.addIssue({
            path: ['listPlayOrder'],
            code: z.ZodIssueCode.custom,
            message: t('List play order is required'),
          });
        }
      }
    });
