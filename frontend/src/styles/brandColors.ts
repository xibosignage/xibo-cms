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

// CSS var references (not resolved hex) so theme.css overrides apply live.
// Safe for SVG/CSS colour props (e.g. Recharts fill/stroke); not for <canvas>.
export const BRAND_PRIMARY = 'var(--brand-primary)';
export const BRAND_ACCENT = 'var(--brand-accent)';

// Status, not brand. Defined in global.css so a theme cannot recolour them.
export const STATUS_UP = 'var(--color-status-up)';
export const STATUS_DOWN = 'var(--color-status-down)';
