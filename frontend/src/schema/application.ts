import type { TFunction } from 'i18next';
import { z } from 'zod';

export const getApplicationSchema = (t: TFunction) =>
  z.object({
    name: z
      .string()
      .min(1, t('Name is required'))
      .max(254, t('Name must be less than 254 characters')),
  });
