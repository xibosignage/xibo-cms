import {
  useFloating,
  autoUpdate,
  offset,
  flip,
  shift,
  size,
  useClick,
  useDismiss,
  useInteractions,
  FloatingPortal,
} from '@floating-ui/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { DateTime } from 'luxon';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import DatePicker from './DatePicker';

import { useDateFormatter } from '@/hooks/useDateFormatter';
import type { FilterOption } from '@/types/filter';
import { DATE_KEY_REGEX, formatCmsDate, formatTime, toLocalDateKey } from '@/utils/date';

// Reconstructs the Date the user picked from a `<dateKey>` or `<dateKey>T<HH:mm>` boundary, so
// reopening the picker can be seeded with the previous selection.
function parseRangeBoundary(raw: string | undefined, timeZone?: string): Date | undefined {
  if (!raw) return undefined;
  const [dateKey, time] = raw.split('T');
  const match = dateKey?.match(DATE_KEY_REGEX);
  if (!match) return undefined;
  const [, year, month, day] = match;
  if (!time) return new Date(Number(year), Number(month) - 1, Number(day));
  const [hour, minute] = time.split(':').map(Number);
  if (timeZone) {
    return DateTime.fromObject(
      { year: Number(year), month: Number(month), day: Number(day), hour, minute },
      { zone: timeZone },
    ).toJSDate();
  }
  return new Date(Number(year), Number(month) - 1, Number(day), hour, minute);
}

function parseRangeValue(value: string, timeZone?: string): { from?: Date; to?: Date } | undefined {
  if (!value.startsWith('range:')) return undefined;
  const [fromRaw, toRaw] = value.replace('range:', '').split('|');
  const from = parseRangeBoundary(fromRaw, timeZone);
  const to = parseRangeBoundary(toRaw, timeZone);
  return from && to ? { from, to } : undefined;
}

// Mirrors the AM/PM display already used by DatePicker's own time-of-day selects.
function formatTimeLabel(time: string): string {
  const [hour = 0, minute = 0] = time.split(':').map(Number);
  const period = hour >= 12 ? 'PM' : 'AM';
  const hour12 = hour % 12 || 12;
  return `${String(hour12).padStart(2, '0')}:${String(minute).padStart(2, '0')} ${period}`;
}

type DateRangeFilterProps = {
  label: string;
  name: string;
  value: string;
  options: FilterOption[];
  onChange: (name: string, value: string | number | null) => void;
  isJalali?: boolean;
  className?: string;
  showTimePicker?: boolean;
};

export default function DateRangeFilter({
  label,
  name,
  value,
  options,
  onChange,
  isJalali = false,
  className,
  showTimePicker,
}: DateRangeFilterProps) {
  const { t } = useTranslation();
  const { dateFormat, timeZone } = useDateFormatter();
  const [open, setOpen] = useState(false);
  const [openDatePicker, setOpenDatePicker] = useState(false);

  const handleClose = () => {
    setOpen(false);
    setOpenDatePicker(false);
  };

  const { refs, floatingStyles, context } = useFloating({
    open,
    onOpenChange: (v) => {
      if (!v) handleClose();
      else setOpen(true);
    },
    placement: 'bottom-start',
    whileElementsMounted: autoUpdate,
    middleware: [
      offset(4),
      flip(),
      shift(),
      size({
        apply({ rects, elements }) {
          Object.assign(elements.floating.style, {
            minWidth: `${rects.reference.width}px`,
          });
        },
        padding: 8,
      }),
    ],
  });

  const click = useClick(context);
  const dismiss = useDismiss(context);
  const { getReferenceProps, getFloatingProps } = useInteractions([click, dismiss]);

  const selectedOption = options.find((o) => String(o.value ?? '') === value);

  const formatRangeBoundary = (dateKey: string) =>
    formatCmsDate(dateKey, { format: dateFormat, timeZone: 'UTC' });

  const getDisplayLabel = () => {
    if (typeof value === 'string' && value.startsWith('range:')) {
      const [fromRaw, toRaw] = value.replace('range:', '').split('|');
      const [from, fromTimeKey] = fromRaw?.split('T') ?? [];
      const [to, toTimeKey] = toRaw?.split('T') ?? [];
      if (from && to) {
        const fromLabel = fromTimeKey
          ? `${formatRangeBoundary(from)} ${formatTimeLabel(fromTimeKey)}`
          : formatRangeBoundary(from);
        const toLabel = toTimeKey
          ? `${formatRangeBoundary(to)} ${formatTimeLabel(toTimeKey)}`
          : formatRangeBoundary(to);
        return `${fromLabel} - ${toLabel}`;
      }
      return t('Custom Range');
    }
    return selectedOption ? t(selectedOption.label) : options[0] ? t(options[0].label) : '';
  };

  return (
    <div
      className={twMerge(
        'flex flex-col gap-1 text-gray-500 w-full md:w-auto md:flex-1 min-w-0',
        className,
      )}
    >
      <label className="text-sm font-semibold text-gray-500 leading-5">{label}</label>
      <button
        ref={refs.setReference}
        {...getReferenceProps()}
        type="button"
        className="w-full h-11.25 flex items-center justify-between bg-white rounded-lg border border-gray-200 pl-4 text-left"
      >
        <span className="text-sm">{getDisplayLabel()}</span>
        <div
          className={twMerge(
            'px-4 transition-all duration-200 ease-in',
            open ? 'rotate-180' : 'rotate-0',
          )}
        >
          <ChevronDown size={14} />
        </div>
      </button>
      <FloatingPortal>
        {open && (
          <div
            ref={refs.setFloating}
            style={floatingStyles}
            {...getFloatingProps()}
            className="z-9999 flex bg-white overflow-hidden rounded-lg border border-gray-200 shadow-lg"
          >
            <ul className={twMerge(openDatePicker ? 'w-50 border-r border-gray-200' : 'w-full')}>
              {options.map((option) => (
                <li key={String(option.value ?? '')}>
                  <button
                    type="button"
                    onClick={() => {
                      onChange(name, option.value);
                      handleClose();
                    }}
                    className={twMerge(
                      'w-full text-left cursor-pointer px-4 py-2 hover:bg-gray-100',
                      String(option.value ?? '') === value && 'bg-gray-50 font-medium',
                    )}
                  >
                    {t(option.label)}
                  </button>
                </li>
              ))}
              <div className="flex border-b border-gray-200" />
              <li>
                <button
                  type="button"
                  onClick={() => setOpenDatePicker((p) => !p)}
                  className="w-full text-left cursor-pointer px-4 py-2 hover:bg-gray-100 flex justify-between items-center"
                >
                  {t('Custom Range')} <ChevronRight size={14} className="text-gray-500" />
                </button>
              </li>
            </ul>
            <div
              className={twMerge(
                'grid transition-[grid-template-rows,opacity,max-width] duration-1000 ease-out',
                openDatePicker
                  ? 'opacity-100 max-w-95 grid-rows-[1fr]'
                  : 'opacity-0 max-w-0 grid-rows-[0fr] pointer-events-none',
              )}
            >
              <div className="overflow-hidden min-h-0">
                <div className="pb-4 box-border">
                  <DatePicker
                    mode="range"
                    disableFutureDates
                    isJalali={isJalali}
                    showTimePicker={showTimePicker}
                    value={parseRangeValue(value, timeZone)}
                    onCancel={() => setOpenDatePicker(false)}
                    onApply={(v) => {
                      if (v.type === 'range') {
                        const fromKey = toLocalDateKey(
                          v.from,
                          showTimePicker ? timeZone : undefined,
                        );
                        const toKey = toLocalDateKey(v.to, showTimePicker ? timeZone : undefined);
                        const rangeValue = showTimePicker
                          ? `range:${fromKey}T${formatTime(v.from, timeZone)}` +
                            `|${toKey}T${formatTime(v.to, timeZone)}`
                          : `range:${fromKey}|${toKey}`;
                        onChange(name, rangeValue);
                      }
                      handleClose();
                    }}
                  />
                </div>
              </div>
            </div>
          </div>
        )}
      </FloatingPortal>
    </div>
  );
}
