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

import { Terminal } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import PlaceholderCard from './PlaceholderCard';

// Placeholder for the mockup's "All commands" card — deliberately not wired
// to any data or action yet (a future pass would take a `display` prop to
// wire real Collect now/Wake display/Send command actions — see
// wiggly-doodling-wren.md's "highest-risk section" note on why this isn't
// wired yet: no fixed default commands exist, and sendCommand/collectNow
// need displayGroupId, not displayId). See wiggly-doodling-wren.md's scope
// note and "Deferred to a future pass" section for why.
export default function AllCommandsCard() {
  const { t } = useTranslation();

  return <PlaceholderCard icon={Terminal} title={t('All Commands')} />;
}
