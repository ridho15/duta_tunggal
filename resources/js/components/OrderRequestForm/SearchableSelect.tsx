import React, { useState, useRef, useEffect, useMemo } from 'react';
import { Search, ChevronDown, X, Check } from 'lucide-react';

export interface SelectOption {
  value: number | string;
  label: string;
  sublabel?: string;
  badge?: string;
}

interface Props {
  options: SelectOption[];
  value: number | string | null;
  placeholder?: string;
  searchPlaceholder?: string;
  hasError?: boolean;
  disabled?: boolean;
  onChange: (value: number | string | null) => void;
}

export const SearchableSelect: React.FC<Props> = ({
  options,
  value,
  placeholder = '-- Pilih --',
  searchPlaceholder = 'Cari...',
  hasError = false,
  disabled = false,
  onChange,
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [search, setSearch] = useState('');
  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  const selectedOption = useMemo(() => {
    return options.find((opt) => String(opt.value) === String(value));
  }, [options, value]);

  const filteredOptions = useMemo(() => {
    if (!search.trim()) return options.slice(0, 100);
    const query = search.toLowerCase();
    return options
      .filter((opt) => {
        const matchLabel = opt.label.toLowerCase().includes(query);
        const matchBadge = opt.badge ? opt.badge.toLowerCase().includes(query) : false;
        const matchSub = opt.sublabel ? opt.sublabel.toLowerCase().includes(query) : false;
        return matchLabel || matchBadge || matchSub;
      })
      .slice(0, 100);
  }, [options, search]);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  useEffect(() => {
    if (isOpen && inputRef.current) {
      inputRef.current.focus();
    }
  }, [isOpen]);

  return (
    <div className="relative w-full" ref={containerRef}>
      {/* Trigger Button (38px standard) */}
      <div
        onClick={() => {
          if (!disabled) {
            setIsOpen(!isOpen);
            setSearch('');
          }
        }}
        className={`w-full h-[38px] min-h-[38px] px-3 flex items-center justify-between text-sm rounded-lg border bg-white cursor-pointer select-none transition-all shadow-xs ${
          hasError
            ? 'border-rose-400 ring-1 ring-rose-400'
            : isOpen
            ? 'border-primary-600 ring-2 ring-primary-500/20'
            : 'border-gray-300 hover:border-gray-400'
        } ${disabled ? 'opacity-50 cursor-not-allowed bg-gray-100' : ''}`}
      >
        <div
          className="flex-1 truncate mr-2"
          title={selectedOption ? `${selectedOption.label} ${selectedOption.sublabel ? `(${selectedOption.sublabel})` : ''}` : undefined}
        >
          {selectedOption ? (
            <div className="flex items-center gap-1.5 truncate">
              {selectedOption.badge && (
                <span className="px-1.5 py-0.5 rounded text-[11px] font-mono font-bold bg-gray-100 border border-gray-200 text-gray-800 shrink-0">
                  {selectedOption.badge}
                </span>
              )}
              <span className="text-gray-900 font-medium truncate text-sm">
                {selectedOption.label}
              </span>
              {selectedOption.sublabel && (
                <span className="text-xs text-gray-500 truncate">
                  ({selectedOption.sublabel})
                </span>
              )}
            </div>
          ) : (
            <span className="text-gray-400 text-sm">{placeholder}</span>
          )}
        </div>

        <div className="flex items-center gap-1 shrink-0 text-gray-400">
          {selectedOption && !disabled && (
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation();
                onChange(null);
              }}
              className="p-0.5 hover:text-gray-600 rounded"
              title="Hapus Pilihan"
            >
              <X className="w-3.5 h-3.5" />
            </button>
          )}
          <ChevronDown
            className={`w-4 h-4 transition-transform duration-200 ${
              isOpen ? 'rotate-180 text-primary-600' : ''
            }`}
          />
        </div>
      </div>

      {/* Dropdown Menu (Pure Light Mode - Min Width Expanded for No Clipping) */}
      {isOpen && (
        <div className="absolute z-50 mt-1 left-0 min-w-full md:min-w-[340px] max-w-xl bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden animate-in fade-in zoom-in-95 duration-100">
          {/* Search Input */}
          <div className="p-2 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
            <Search className="w-4 h-4 text-gray-400 shrink-0 ml-1" />
            <input
              ref={inputRef}
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={searchPlaceholder}
              className="w-full bg-transparent text-sm text-gray-900 placeholder-gray-400 focus:outline-none py-1"
            />
            {search && (
              <button
                type="button"
                onClick={() => setSearch('')}
                className="text-gray-400 hover:text-gray-600 p-1"
              >
                <X className="w-3.5 h-3.5" />
              </button>
            )}
          </div>

          {/* Options List */}
          <div className="max-h-60 overflow-y-auto p-1 space-y-0.5 custom-scrollbar">
            {filteredOptions.length === 0 ? (
              <div className="py-4 text-center text-xs text-gray-400 italic">
                Tidak ada data yang sesuai.
              </div>
            ) : (
              filteredOptions.map((opt) => {
                const isSelected = String(opt.value) === String(value);
                const fullText = `${opt.label} ${opt.sublabel ? `(${opt.sublabel})` : ''}`;
                return (
                  <div
                    key={opt.value}
                    title={fullText}
                    onClick={() => {
                      onChange(opt.value);
                      setIsOpen(false);
                    }}
                    className={`px-2.5 py-2 rounded-lg text-sm flex items-center justify-between cursor-pointer transition-colors ${
                      isSelected
                        ? 'bg-primary-50 text-primary-700 font-semibold'
                        : 'hover:bg-gray-100 text-gray-800'
                    }`}
                  >
                    <div className="flex items-center gap-2 truncate pr-2">
                      {opt.badge && (
                        <span
                          className={`px-1.5 py-0.5 rounded text-[11px] font-mono font-bold shrink-0 ${
                            isSelected
                              ? 'bg-primary-100 text-primary-800 border border-primary-200'
                              : 'bg-gray-100 text-gray-700 border border-gray-200'
                          }`}
                        >
                          {opt.badge}
                        </span>
                      )}
                      <span className="truncate">{opt.label}</span>
                      {opt.sublabel && (
                        <span className="text-xs text-gray-500 truncate">
                          ({opt.sublabel})
                        </span>
                      )}
                    </div>
                    {isSelected && (
                      <Check className="w-4 h-4 text-primary-600 shrink-0" />
                    )}
                  </div>
                );
              })
            )}
          </div>
        </div>
      )}
    </div>
  );
};
