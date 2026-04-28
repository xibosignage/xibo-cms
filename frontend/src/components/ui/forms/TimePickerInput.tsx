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

import {
  useFloating,
  autoUpdate,
  offset,
  flip,
  shift,
  useClick,
  useDismiss,
  useInteractions,
  FloatingPortal,
} from '@floating-ui/react';
import { Clock, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import Button from '@/components/ui/Button';

type TimeFormat = 'HH:mm:ss' | 'HH:mm';

interface TimePickerInputProps {
  label: string;
  value?: string;
  onChange: (value: string) => void;
  helpText?: string;
  error?: string;
  className?: string;
  timeFormat?: TimeFormat;
}

function pad(n: number): string {
  return String(n).padStart(2, '0');
}

function parseTime(value?: string): { hour: number; minute: number } {
  if (!value) return { hour: 0, minute: 0 };
  const [h = '0', m = '0'] = value.split(':');
  return { hour: parseInt(h, 10), minute: parseInt(m, 10) };
}

function TimePicker({
  value,
  onApply,
  onCancel,
  timeFormat = 'HH:mm:ss',
}: {
  value?: string;
  onApply: (value: string) => void;
  onCancel: () => void;
  timeFormat?: TimeFormat;
}) {
  const { t } = useTranslation();
  const { hour: initHour, minute: initMinute } = parseTime(value);
  const [hour, setHour] = useState(initHour);
  const [minute, setMinute] = useState(initMinute);

  const hourRef = useRef<HTMLDivElement>(null);
  const minuteRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const el = hourRef.current?.children[hour] as HTMLElement | undefined;
    el?.scrollIntoView({ block: 'center', behavior: 'instant' });
  }, [hour]);

  useEffect(() => {
    const el = minuteRef.current?.children[minute] as HTMLElement | undefined;
    el?.scrollIntoView({ block: 'center', behavior: 'instant' });
  }, [minute]);

  return (
    <div className="p-3 flex flex-col gap-3 w-44">
      <div className="flex items-start justify-center gap-1">
        <div className="flex flex-col items-center gap-1">
          <span className="text-xs text-gray-500 font-medium">{t('HH')}</span>
          <div
            ref={hourRef}
            className="h-40 overflow-y-auto flex flex-col [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
          >
            {Array.from({ length: 24 }, (_, i) => (
              <button
                key={i}
                type="button"
                onClick={() => setHour(i)}
                className={twMerge(
                  'w-14 py-1.5 text-sm rounded-md text-center shrink-0',
                  hour === i
                    ? 'bg-xibo-blue-500 text-white font-semibold'
                    : 'text-gray-700 hover:bg-gray-100',
                )}
              >
                {pad(i)}
              </button>
            ))}
          </div>
        </div>

        <span className="text-lg font-semibold text-gray-400 mt-7">:</span>

        <div className="flex flex-col items-center gap-1">
          <span className="text-xs text-gray-500 font-medium">{t('MM')}</span>
          <div
            ref={minuteRef}
            className="h-40 overflow-y-auto flex flex-col [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
          >
            {Array.from({ length: 60 }, (_, i) => (
              <button
                key={i}
                type="button"
                onClick={() => setMinute(i)}
                className={twMerge(
                  'w-14 py-1.5 text-sm rounded-md text-center shrink-0',
                  minute === i
                    ? 'bg-xibo-blue-500 text-white font-semibold'
                    : 'text-gray-700 hover:bg-gray-100',
                )}
              >
                {pad(i)}
              </button>
            ))}
          </div>
        </div>
      </div>

      <div className="flex gap-1 border-t border-gray-100 pt-2">
        <Button className="min-w-auto" variant="secondary" onClick={onCancel}>
          {t('Cancel')}
        </Button>
        <Button
          className="flex-1 min-w-auto"
          onClick={() =>
            onApply(
              timeFormat === 'HH:mm'
                ? `${pad(hour)}:${pad(minute)}`
                : `${pad(hour)}:${pad(minute)}:00`,
            )
          }
        >
          {t('Apply')}
        </Button>
      </div>
    </div>
  );
}

export default function TimePickerInput({
  label,
  value,
  onChange,
  helpText,
  error,
  className,
  timeFormat = 'HH:mm:ss',
}: TimePickerInputProps) {
  const { t } = useTranslation();
  const [isOpen, setIsOpen] = useState(false);

  const { refs, floatingStyles, context } = useFloating({
    open: isOpen,
    onOpenChange: setIsOpen,
    placement: 'bottom-start',
    whileElementsMounted: autoUpdate,
    middleware: [offset(4), flip(), shift()],
  });

  const click = useClick(context);
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  return (
    <div className={twMerge('flex flex-col gap-1.5 relative', className)}>
      <label className="text-sm font-semibold text-gray-500">{label}</label>

      <div
        ref={refs.setReference}
        {...getReferenceProps()}
        className="flex relative cursor-pointer h-11.25 w-full min-w-0"
      >
        <button
          type="button"
          className="flex items-center justify-center bg-white border border-gray-200 border-r-0 rounded-l-lg px-3 hover:bg-gray-200 transition-colors"
        >
          <Clock size={18} className="text-gray-500" />
        </button>

        <input
          type="text"
          readOnly
          value={value ?? ''}
          placeholder={t('Select time')}
          className={twMerge(
            'flex-1 min-w-0 py-2 px-3 bg-white border border-gray-200 text-sm cursor-pointer outline-none focus:border-xibo-blue-500',
            !value ? 'rounded-r-lg' : '',
          )}
        />

        {value && (
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              onChange('');
            }}
            className="flex items-center justify-center bg-white border border-gray-200 border-l-0 rounded-r-lg px-3 hover:bg-gray-200 transition-colors z-10 relative"
          >
            <X size={18} className="text-gray-500" />
          </button>
        )}
      </div>

      {error ? (
        <p className="text-xs text-red-600">{error}</p>
      ) : helpText ? (
        <p className="text-xs text-gray-400">{helpText}</p>
      ) : null}

      <FloatingPortal>
        {isOpen && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className="z-9999 bg-white border border-gray-200 rounded-lg shadow-xl"
          >
            <TimePicker
              value={value}
              timeFormat={timeFormat}
              onApply={(v) => {
                onChange(v);
                setIsOpen(false);
              }}
              onCancel={() => setIsOpen(false)}
            />
          </div>
        )}
      </FloatingPortal>
    </div>
  );
}
