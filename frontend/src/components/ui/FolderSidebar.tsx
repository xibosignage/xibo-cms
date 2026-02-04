import { FolderPlus, Home, Loader2, Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import Button from './Button';
import FolderTreeList from './FolderTreeList';
import Checkbox from './forms/Checkbox';

import { fetchFolderTree, searchFolders } from '@/services/folderApi';
import type { Folder } from '@/types/folder';

interface FolderSidebarProps {
  isOpen: boolean;
  selectedFolderId: number | null;
  onSelect: (folder: { id: number | null; text: string }) => void;
  onClose: () => void;
  className?: string;
}

type FolderTab = 'Home' | 'My Files';

export default function FolderSidebar({
  isOpen,
  selectedFolderId,
  onSelect,
  onClose,
  className = '',
}: FolderSidebarProps) {
  const { t } = useTranslation();

  const [activeTab, setActiveTab] = useState<FolderTab>('Home');
  const [searchQuery, setSearchQuery] = useState('');
  const [treeData, setTreeData] = useState<Folder[]>([]);
  const [searchResults, setSearchResults] = useState<Folder[]>([]);
  const [isLoadingTree, setIsLoadingTree] = useState(false);

  useEffect(() => {
    if (!isOpen) return;

    const controller = new AbortController();

    async function loadTree() {
      setIsLoadingTree(true);
      try {
        if (searchQuery.trim()) {
          const results = await searchFolders(searchQuery, controller.signal);
          setSearchResults(results);
        } else {
          const tree = await fetchFolderTree(controller.signal);
          setTreeData(tree);
        }
      } catch (e: unknown) {
        if (e instanceof Error && e.name !== 'CanceledError') console.error(e);
      } finally {
        setIsLoadingTree(false);
      }
    }

    loadTree();
    return () => controller.abort();
  }, [isOpen, searchQuery, activeTab]);

  const handleAction = (action: string, folder: Folder) => {
    console.log(`Action: ${action} on folder: ${folder.text} (${folder.id})`);
  };

  const handleCreateRoot = () => {
    handleAction('create', { id: 1, text: 'Root' } as Folder);
  };

  const handleAllItemsToggle = () => {
    if (selectedFolderId === null) {
      onSelect({ id: 1, text: t('Root Folder') });
    } else {
      onSelect({ id: null, text: t('All Items') });
    }
  };

  // TODO: Design
  return (
    <div
      className={`bg-white border-r border-gray-200 flex flex-col transition-all duration-300 ease-in-out overflow-hidden ${
        isOpen ? 'w-72 opacity-100' : 'w-0 opacity-0 border-none'
      } ${className}`}
    >
      <div className="flex items-center justify-between p-4 border-b border-gray-100 bg-gray-50/50 min-w-[18rem]">
        <div className="font-bold text-gray-800 flex items-center gap-2">{t('Select Folder')}</div>
        <X className="cursor-pointer" onClick={() => onClose()} />
      </div>

      <div className="flex px-4 pt-3 border-b border-gray-100 gap-4 min-w-[18rem]">
        {(['Home', 'My Files'] as FolderTab[]).map((tab) => (
          <button
            key={tab}
            onClick={() => setActiveTab(tab)}
            className={`pb-3 text-sm font-medium border-b-2 transition-colors flex items-center gap-2 ${
              activeTab === tab
                ? 'border-xibo-blue-600 text-xibo-blue-700'
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            {tab === 'Home' && <Home size={14} />}
            {t(tab)}
          </button>
        ))}
      </div>

      <div className="my-2 border-gray-50 relative">
        <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
          <Search className="w-4 h-4 text-gray-400 group-focus-within:text-xibo-blue-500 transition-colors" />
        </div>
        <input
          type="text"
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          placeholder={t('Search Folder')}
          className="py-2 pl-9 pr-4 block w-full rounded-md border-gray-200 bg-gray-50 text-sm focus:border-xibo-blue-500 focus:ring-xibo-blue-500 transition-shadow"
        />
      </div>

      <Button
        variant="tertiary"
        className="flex items-center justify-center"
        leftIcon={FolderPlus}
        onClick={handleCreateRoot}
      >
        {t('New Folder')}
      </Button>

      <Checkbox
        id="all-items-checkbox"
        label={t('All Items')}
        checked={selectedFolderId === null}
        onChange={handleAllItemsToggle}
        className="mx-4 mt-2"
      />

      <div className="flex-1 overflow-y-auto p-2 custom-scrollbar">
        {isLoadingTree ? (
          <div className="flex flex-col items-center justify-center h-40 gap-3 text-gray-400">
            <Loader2 className="w-6 h-6 animate-spin text-xibo-blue-500" />
            <span className="text-xs">{t('Loading folders...')}</span>
          </div>
        ) : (
          <FolderTreeList
            folders={searchQuery.trim() ? searchResults : treeData}
            selectedId={selectedFolderId}
            onSelect={(folder) => onSelect({ id: folder.id, text: folder.text })}
            onAction={(action, folder) => handleAction(action, folder)}
          />
        )}
      </div>
    </div>
  );
}
