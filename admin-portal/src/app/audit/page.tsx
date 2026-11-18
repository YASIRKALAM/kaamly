'use client';

import React from 'react';
import { ShieldCheck, Lock, Clock, Search } from 'lucide-react';

interface MockAuditLog {
  id: string;
  action: string;
  entityType: string;
  entityId: string;
  actor: string;
  ipAddress: string;
  timestamp: string;
  summary: string;
}

const mockLogs: MockAuditLog[] = [
  {
    id: 'a1',
    action: 'PROVIDER_APPROVED',
    entityType: 'provider_profiles',
    entityId: 'e290f-9988',
    actor: 'Super Admin (admin@kaamly.com)',
    ipAddress: '127.0.0.1',
    timestamp: '2026-11-16 16:50:12',
    summary: 'Approved technician Tariq Mehmood after verifying CNIC and HVAC trade certificate.',
  },
  {
    id: 'a2',
    action: 'LEDGER_POSTED',
    entityType: 'ledger_transactions',
    entityId: 'TX-20261116-8A11',
    actor: 'System Automation',
    ipAddress: '10.0.1.4',
    timestamp: '2026-11-16 14:32:05',
    summary: 'Captured PKR 240.00 commission and released PKR 2,160.00 provider payable for Booking #BK-20261116-A82F.',
  },
  {
    id: 'a3',
    action: 'DISPUTE_ARBITRATED',
    entityType: 'disputes',
    entityId: 'dsp-1',
    actor: 'Farhan Finance (finance@kaamly.com)',
    ipAddress: '127.0.0.1',
    timestamp: '2026-11-16 11:20:44',
    summary: 'Arbitrated dispute with 50/50 split: PKR 3,500 customer refund / PKR 3,000 technician payout.',
  },
  {
    id: 'a4',
    action: 'CUSTOM_PRICE_APPROVED',
    entityType: 'provider_service_prices',
    entityId: 'psp-44',
    actor: 'Super Admin (admin@kaamly.com)',
    ipAddress: '127.0.0.1',
    timestamp: '2026-11-16 09:15:30',
    summary: 'Approved custom AC chemical wash rate PKR 2,400 (within market bounds 2000-3500).',
  },
];

export default function AuditPage() {
  return (
    <div className="space-y-6">
      {/* Title */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Regulatory Compliance & Audit Trail</h1>
          <p className="text-sm text-slate-500 mt-1">Immutable, append-only log protected by PostgreSQL trigger <code className="font-mono text-teal-700">trg_protect_audit_logs</code>.</p>
        </div>

        <div className="flex items-center space-x-2 bg-emerald-50 border border-emerald-200 text-emerald-800 px-3 py-1.5 rounded-lg text-xs font-semibold">
          <Lock className="w-3.5 h-3.5 mr-1" />
          <span>Strict Immutability Enforced</span>
        </div>
      </div>

      {/* Logs Table */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50/75 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                <th className="py-3 px-6">Timestamp</th>
                <th className="py-3 px-6">Action / Event</th>
                <th className="py-3 px-6">Entity Target</th>
                <th className="py-3 px-6">Initiating Actor</th>
                <th className="py-3 px-6">IP Address</th>
                <th className="py-3 px-6">Summary Notes</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {mockLogs.map((log) => (
                <tr key={log.id} className="hover:bg-slate-50/80 transition-colors">
                  <td className="py-3.5 px-6 font-mono text-xs text-slate-500">{log.timestamp}</td>
                  <td className="py-3.5 px-6">
                    <span className="inline-block px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-slate-100 text-slate-800">
                      {log.action}
                    </span>
                  </td>
                  <td className="py-3.5 px-6 font-mono text-xs text-teal-700">
                    {log.entityType} ({log.entityId})
                  </td>
                  <td className="py-3.5 px-6 text-slate-800 font-semibold">{log.actor}</td>
                  <td className="py-3.5 px-6 font-mono text-xs text-slate-500">{log.ipAddress}</td>
                  <td className="py-3.5 px-6 text-xs text-slate-600 max-w-sm">{log.summary}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
