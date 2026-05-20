import { ChevronDown, ChevronRight } from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

import DatePicker from './DatePicker';

import { useClickOutside } from '@/hooks/useClickOutside';
import { useKeydown } from '@/hooks/useKeydown';
import type { FilterOption } from '@/types/filter';

type DateRangeFilterProps = {
  label: string;
  name: string;
  value: string;
  options: FilterOption[];
  onChange: (name: string, value: string | number | null) => void;
  isJalali?: boolean;
  className?: string;
};

export default function DateRangeFilter({
  label,
  name,
  value,
  options,
  onChange,
  isJalali = false,
  className,
}: DateRangeFilterProps) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const [openDatePicker, setOpenDatePicker] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  const handleClose = () => {
    setOpen(false);
    setOpenDatePicker(false);
  };

  useKeydown('Escape', () => setOpen(false), !!open);
  useClickOutside(ref, handleClose);

  const selectedOption = options.find((o) => String(o.value ?? '') === value);

  const getDisplayLabel = () => {
    if (typeof value === 'string' && value.startsWith('range:')) {
      const [from, to] = value.replace('range:', '').split('|');
      if (from && to) {
        return `${new Date(from).toLocaleDateString()} - ${new Date(to).toLocaleDateString()}`;
      }
      return t('Custom Range');
    }
    return selectedOption ? t(selectedOption.label) : options[0] ? t(options[0].label) : '';
  };

  return (
    <div
      className={twMerge(
        'flex flex-col gap-1 text-gray-500 w-full md:w-auto md:flex-1 min-w-0 relative',
        className,
      )}
      ref={ref}
    >
      <label className="text-sm font-semibold text-gray-500 leading-5">{t(label)}</label>
      <button
        type="button"
        onClick={() => setOpen((p) => !p)}
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
      <div
        className={twMerge(
          'absolute z-50 mt-1 min-w-full transition-all duration-200 ease-linear flex bg-xibo-white overflow-hidden rounded-lg border border-gray-200 shadow-lg',
          open
            ? 'max-h-175 opacity-100 top-17 right-0'
            : 'max-h-0 opacity-0 top-15 pointer-events-none',
        )}
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
            'transition-all duration-1000 ease-out overflow-hidden',
            openDatePicker
              ? 'opacity-100 max-w-95 max-h-125'
              : 'opacity-0 max-w-0 max-h-0 pointer-events-none',
          )}
        >
          <div className="pb-4 box-border">
            <DatePicker
              mode="range"
              disableFutureDates
              isJalali={isJalali}
              onCancel={() => setOpenDatePicker(false)}
              onApply={(v) => {
                if (v.type === 'range') {
                  onChange(name, `range:${v.from.toISOString()}|${v.to.toISOString()}`);
                }
                handleClose();
              }}
            />
          </div>
        </div>
      </div>
    </div>
  );
}
