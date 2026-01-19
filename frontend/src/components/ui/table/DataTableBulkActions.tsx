import { X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export interface DataTableBulkAction<TData> {
  label: string;
  icon?: React.ReactNode;
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

  // TODO: Pending final design
  return (
    <div className="animate-in fade-in zoom-in-95 duration-200">
      <div className="flex items-center gap-2 bg-gray-100 text-gray-900 border border-gray-100">
        <div className="flex items-center gap-2 px-2 mr-1">
          <button
            onClick={onClearSelection}
            className="text-gray-900 hover:text-white transition-colors cursor-pointer"
            title={t('Clear selection')}
          >
            <X className="w-4 h-4" />
          </button>
          <span className="font-semibold text-sm whitespace-nowrap">
            {selectedCount} {t('Selected')}
          </span>
        </div>

        <div className="flex items-center gap-1">
          {actions.map((action, idx) => (
            <button
              key={idx}
              onClick={() => action.onClick(selectedRows)}
              className={`
                flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded transition-colors bg-gray-100 hover:bg-gray-200 cursor-pointer
                ${action.variant === 'danger' ? ' text-red-600' : ' text-gray-800'}
              `}
              title={action.label}
            >
              {action.icon}
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}
