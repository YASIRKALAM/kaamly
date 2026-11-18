'use client';

import React from 'react';
import { useRouter } from 'next/navigation';
import { Bell, LogOut, User } from 'lucide-react';
import { api } from '@/lib/api';

export default function Header() {
  const router = useRouter();

  const handleLogout = async () => {
    try {
      await api.logout();
    } catch {
      // Continue cleanup
    }
    router.push('/login');
  };

  return (
    <header className="h-16 bg-white border-b border-slate-200 px-8 flex items-center justify-between sticky top-0 z-20">
      <div className="flex items-center space-x-3">
        <span className="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
          Backend Connected
        </span>
        <span className="text-xs text-slate-500">Laravel 13 • Reverb Active</span>
      </div>

      <div className="flex items-center space-x-4">
        {/* Notifications Icon */}
        <button 
          aria-label="Notifications"
          className="p-2 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 relative transition-colors"
        >
          <Bell className="w-5 h-5" />
          <span className="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-teal-500 ring-2 ring-white"></span>
        </button>

        <div className="h-5 w-px bg-slate-200"></div>

        {/* User Badge */}
        <div className="flex items-center space-x-3">
          <div className="w-8 h-8 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-xs">
            SA
          </div>
          <div className="text-left hidden sm:block">
            <div className="text-sm font-semibold text-slate-900 leading-tight">Super Administrator</div>
            <div className="text-xs text-slate-500">admin@kaamly.com</div>
          </div>
        </div>

        {/* Logout Button */}
        <button
          onClick={handleLogout}
          aria-label="Sign Out"
          title="Sign Out"
          className="p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
        >
          <LogOut className="w-5 h-5" />
        </button>
      </div>
    </header>
  );
}
