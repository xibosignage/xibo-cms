import type { TFunction } from 'i18next';
import { z } from 'zod';

export const getMediaSchema = (t: TFunction) =>
  z.object({
    name: z.string().min(1, t('Name is required')),
    folderId: z.number().nullable().optional(),
    duration: z.number().optional(),
    orientation: z.enum(['portrait', 'landscape', 'square']).optional(),
    enableStat: z.enum(['On', 'Off', 'Inherit']).optional(),
    retired: z.coerce.boolean().optional(),
    updateInLayouts: z.boolean().optional(),
    tags: z.array(
      z.object({
        tag: z.string(),
        value: z.any().optional(),
      }),
    ),
  });
