'use client';

import React from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { 
  LayoutDashboard, 
  UserCheck, 
  Layers, 
  CalendarClock, 
  BookOpenCheck, 
  Banknote, 
  Scale, 
  ShieldAlert,
  Settings
} from 'lucide-react';

const navigation = [
  { name: 'Dashboard', href: '/', icon: LayoutDashboard },
  { name: 'Provider Verification', href: '/providers', icon: UserCheck },
  { name: 'Service Catalog', href: '/services', icon: Layers },
  { name: 'Live Bookings', href: '/bookings', icon: CalendarClock },
  { name: 'Financial Ledger', href: '/finance', icon: BookOpenCheck },
  { name: 'Payout Settlements', href: '/settlements', icon: Banknote },
  { name: 'Dispute Arbitration', href: '/disputes', icon: Scale },
  { name: 'Regulatory Audit', href: '/audit', icon: ShieldAlert },
];

export default function Sidebar() {
  const pathname = usePathname();

  return (
    <aside className="w-64 bg-slate-900 border-r border-slate-800 flex flex-col flex-shrink-0 h-screen sticky top-0">
      {/* Brand Logo Header */}
      <div className="h-16 flex items-center px-6 border-b border-slate-800 bg-slate-950">
        <div className="flex items-center space-x-3">
          <div className="w-9 h-9 rounded-lg bg-teal-500 flex items-center justify-center font-black text-slate-950 text-xl shadow-lg shadow-teal-500/20">
            K
          </div>
          <div>
            <span className="text-lg font-bold tracking-tight text-white">Kaamly</span>
            <span className="block text-[10px] uppercase font-semibold text-teal-400 tracking-wider">Admin Portal</span>
          </div>
        </div>
      </div>

      {/* Navigation Items */}
      <div className="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
        {navigation.map((item) => {
          const isActive = pathname === item.href || (item.href !== '/' && pathname.startsWith(item.href));
          const Icon = item.icon;

          return (
            <Link
              key={item.name}
              href={item.href}
              className={`flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-all ${
                isActive
                  ? 'bg-teal-600 text-white shadow-md shadow-teal-600/30'
                  : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
              }`}
            >
              <Icon className={`w-5 h-5 mr-3 flex-shrink-0 ${isActive ? 'text-white' : 'text-slate-400'}`} />
              <span>{item.name}</span>
            </Link>
          );
        })}
      </div>

      {/* System Status Footer */}
      <div className="p-4 border-t border-slate-800 bg-slate-950/60">
        <div className="flex items-center space-x-2 text-xs text-slate-400">
          <div className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
          <span>API v1 • PostgreSQL PostGIS</span>
        </div>
      </div>
    </aside>
  );
}
