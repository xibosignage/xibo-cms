import { useState, useRef, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';

interface RowActionsProps<TData> {
  row: TData;
  onEdit?: (row: TData) => void;
  onDelete?: (row: TData) => void;
}

export default function RowActions<TData>({ row, onEdit, onDelete }: RowActionsProps<TData>) {
  const [open, setOpen] = useState(false);
  const [coords, setCoords] = useState({ top: 0, left: 0 });

  const buttonRef = useRef<HTMLButtonElement>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);

  const { t } = useTranslation();

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      const target = event.target as Node;
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(target) &&
        buttonRef.current &&
        !buttonRef.current.contains(target)
      ) {
        setOpen(false);
      }
    }

    function handleScroll() {
      if (open) {
        setOpen(false);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);
    window.addEventListener('scroll', handleScroll, true);
    window.addEventListener('resize', handleScroll);

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
      window.removeEventListener('scroll', handleScroll, true);
      window.removeEventListener('resize', handleScroll);
    };
  }, [open]);

  const toggleOpen = () => {
    if (!open && buttonRef.current) {
      const rect = buttonRef.current.getBoundingClientRect();
      setCoords({
        top: rect.bottom + 4,
        left: rect.right - 144,
      });
    }
    setOpen(!open);
  };

  const handleEdit = () => {
    if (onEdit) onEdit(row);
    setOpen(false);
  };

  const handleDelete = () => {
    if (onDelete) onDelete(row);
    setOpen(false);
  };

  return (
    <div className="relative inline-block text-left">
      <button
        ref={buttonRef}
        onClick={toggleOpen}
        className="text-gray-500 hover:text-gray-700 px-4 cursor-pointer focus:outline-none"
      >
        ⋮
      </button>

      {open &&
        createPortal(
          <div
            ref={dropdownRef}
            style={{
              top: coords.top,
              left: coords.left,
            }}
            className="fixed w-36 bg-white border border-gray-200 rounded shadow-lg z-100 overflow-hidden animate-in fade-in zoom-in-95 duration-100"
          >
            {onEdit ? (
              <button
                onClick={handleEdit}
                className="flex items-center w-full text-left px-4 py-2 text-sm hover:bg-gray-100 text-gray-700"
              >
                {t('Edit')}
              </button>
            ) : null}

            {onDelete ? (
              <button
                onClick={handleDelete}
                className="flex items-center w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-gray-100"
              >
                {t('Delete')}
              </button>
            ) : null}

            {!onEdit && !onDelete && (
              <div className="px-4 py-2 text-sm text-gray-400 italic">{t('No actions')}</div>
            )}
          </div>,
          document.body,
        )}
    </div>
  );
}
