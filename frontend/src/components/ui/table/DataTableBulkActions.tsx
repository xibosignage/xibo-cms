import type { LucideIcon } from 'lucide-react';
import { X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { twMerge } from 'tailwind-merge';

export interface DataTableBulkAction<TData> {
  label: string;
  icon?: LucideIcon;
  onClick: (selectedRows: TData[]) => void;
  variant?: 'default' | 'danger';
}

interface DataTableBulkActionsProps<TData> {
  selectedCount: number;
  actions: DataTableBulkAction<TData>[];
  onClearSelection: () => void;
  selectedRows: TData[];
}

export function DataTableBulkActions<TData>({
  selectedCount,
  actions,
  onClearSelection,
  selectedRows,
}: DataTableBulkActionsProps<TData>) {
  const { t } = useTranslation();

  if (selectedCount === 0) {
    return null;
  }

  return (
    <div className="flex p-1 bg-slate-50 rounded-full gap-3 items-center">
      <div className="flex items-center gap-2 mr-1">
        <button
          onClick={onClearSelection}
          className="cursor-pointer rounded-full px-2 py-1 flex gap-2 items-center text-gray-600 hover:bg-gray-200 focus:bg-gray-300"
          title={t('Clear selection')}
        >
          <X className="size-5.25" />
          <span className="text-sm leading-normal whitespace-nowrap">
            {selectedCount} {t('Selected')}
          </span>
        </button>
      </div>

      {actions.map(
        (action, idx) =>
          action.onClick && (
            <button
              key={idx}
              onClick={() => action.onClick(selectedRows)}
              className={twMerge(
                'cursor-pointer flex justify-center size-6 items-center text-sm font-medium rounded-lg border border-transparent focus:outline-hidden disabled:opacity-50 disabled:pointer-events-none',
                action.variant === 'danger'
                  ? 'text-red-600 hover:bg-red-50 focus:bg-red-100'
                  : 'text-gray-600 hover:bg-gray-200 focus:bg-gray-300',
              )}
              title={action.label}
            >
              {action.icon && <action.icon className="w-4 h-4"></action.icon>}
            </button>
          ),
      )}
    </div>
  );
}
