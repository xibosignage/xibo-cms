import type { TFunction } from 'i18next';
import { z } from 'zod';

export const getUserGroupSchema = (t: TFunction) =>
  z.object({
    group: z
      .string()
      .min(1, t('Group name is required'))
      .max(50, t('Group name must be less than 50 characters')),

    description: z.string().max(500, t('Description must be less than 500 characters')).optional(),

    libraryQuota: z
      .number({ invalid_type_error: t('Library quota must be a number') })
      .min(0, t('Library quota must be a positive number'))
      .optional(),
  });
