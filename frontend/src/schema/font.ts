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

const VALID_FONT_EXTENSIONS = ['.otf', '.ttf', '.eot', '.svg', '.woff'];

export const getFontUploadSchema = (t: TFunction) =>
  z.object({
    files: z
      .array(
        z.object({
          name: z.string(),
          size: z.number(),
        }),
      )
      .min(1, t('Please select at least one font file to upload'))
      .refine(
        (files) =>
          files.every((file) => {
            const ext = file.name.substring(file.name.lastIndexOf('.')).toLowerCase();
            return VALID_FONT_EXTENSIONS.includes(ext);
          }),
        t('Only .otf, .ttf, .eot, .svg, and .woff files are accepted'),
      ),
  });
