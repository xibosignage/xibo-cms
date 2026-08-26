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

import { PlayCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import PlaceholderCard from './PlaceholderCard';

// Placeholder only, per explicit scope direction — display.currentLayout is
// real data, but this card is deliberately not wired to it yet (see
// wiggly-doodling-wren.md's "Deferred to a future pass" section). Takes no
// props for now; a future pass adds `display` back once it's actually used.
export default function NowPlayingCard() {
  const { t } = useTranslation();

  return <PlaceholderCard icon={PlayCircle} title={t('Now Playing')} />;
}
