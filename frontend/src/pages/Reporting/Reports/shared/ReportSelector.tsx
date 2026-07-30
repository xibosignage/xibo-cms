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

import { ChevronDown } from 'lucide-react';
import { useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';

import Button from '@/components/ui/Button';
import { withPublicPath } from '@/config/publicPath';
import { useClickOutside } from '@/hooks/useClickOutside';
import { useAllReportsData } from '@/pages/Reporting/AllReports/hooks/useAllReportsData';

export default function ReportSelector({
  currentReportName,
  fallbackLabel,
}: {
  currentReportName: string;
  fallbackLabel?: string;
}) {
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  useClickOutside(containerRef, () => setOpen(false));

  const { data } = useAllReportsData(true);

  const category = data
    ? Object.values(data).find((reports) =>
        reports.some((report) => report.name === currentReportName),
      )
    : undefined;
  const current = category?.find((report) => report.name === currentReportName);
  const siblings =
    category?.filter((report) => report.hidden === 0 || report.name === currentReportName) ?? [];

  return (
    <div className="relative" ref={containerRef}>
      <Button variant="link" rightIcon={ChevronDown} onClick={() => setOpen((prev) => !prev)}>
        {current?.description ?? fallbackLabel ?? ''}
      </Button>

      {open && siblings.length > 0 && (
        <div className="absolute right-0 top-full mt-1 z-50 bg-white border border-gray-200 rounded-lg shadow-lg min-w-72 py-1">
          {siblings.map((report) => {
            const isActive = report.name === currentReportName;
            return (
              <button
                key={report.name}
                type="button"
                className={`w-full text-left px-4 py-2 text-sm hover:bg-gray-100 ${
                  isActive ? 'font-semibold text-gray-900' : 'text-gray-700'
                }`}
                onClick={() => {
                  if (!isActive) {
                    if (report.url) {
                      navigate(report.url);
                    } else {
                      window.location.href = withPublicPath(`report/form/${report.name}`);
                    }
                  }
                  setOpen(false);
                }}
              >
                {report.description}
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}
