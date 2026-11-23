<?php
/*
 * Xibo Digital Signage
 * Copyright (C) 2006-2026 Xibo Signage Ltd
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * This program is also available under a commercial licence from
 * Xibo Signage Ltd. See https://xibosignage.com/terms
 */

namespace Xibo\Factory;

use Xibo\Entity\AuditLog;
use Xibo\Support\Sanitizer\SanitizerInterface;

/**
 * Extends the audit log listing and export (AuditLogFactory::setExtension). CE has none.
 */
interface AuditLogExtensionInterface
{
    /**
     * The extra filter parameters the audit log grid accepts and passes on to filter().
     * @return string[]
     */
    public function getFilterNames(): array;

    /**
     * Extra conditions for the audit log query, from the filter: SQL starting with " AND " over the `auditlog` table,
     * and its named parameters.
     * @param SanitizerInterface $filter
     * @return array{0: string, 1: array}
     */
    public function filter(SanitizerInterface $filter): array;

    /**
     * Adds to the listed entries (as unmatched properties, and userName).
     * @param AuditLog[] $entries
     */
    public function decorate(array $entries): void;

    /**
     * Extra columns for the CSV export, after the core ones.
     * @return string[]
     */
    public function getExportHeaders(): array;

    /**
     * The extra export columns for one (decorated) entry, in getExportHeaders() order.
     * @param AuditLog $entry
     * @return array
     */
    public function getExportValues(AuditLog $entry): array;

    /**
     * The export's User column for one (decorated) entry whose userName the extension set, made safe to open in a
     * spreadsheet; null to export userName as it is.
     * @param AuditLog $entry
     * @return string|null
     */
    public function getExportUserName(AuditLog $entry): ?string;

    /**
     * One whole CSV export row (core and extension cells), made safe to open in a spreadsheet.
     * @param array $cells
     * @return array
     */
    public function getExportSafeRow(array $cells): array;

    /**
     * Attribution for rows that aren't audit entries but carry a session history (debug log, session history), as
     * decorate() attributes audit entries: the fields to set on each (userName included), keyed as the rows were.
     * Rows left out are left as they are.
     * @param array<int|string, array{sessionHistoryId: int|null, userId: int|null, userName: string|null}> $rows
     * @return array<int|string, array<string, mixed>>
     */
    public function getAttributions(array $rows): array;
}
