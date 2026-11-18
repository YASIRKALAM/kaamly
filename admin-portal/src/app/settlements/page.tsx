'use client';

import React, { useState } from 'react';
import { 
  Banknote, 
  CheckCircle2, 
  Clock, 
  ArrowUpRight, 
  PlusCircle, 
  Building2, 
  CreditCard 
} from 'lucide-react';

interface MockSettlement {
  id: string;
  batchNumber: string;
  providerName: string;
  bankName: string;
  accountTitle: string;
  amount: number;
  status: 'pending' | 'processing' | 'completed';
  payoutRef?: string;
  createdAt: string;
}

const initialSettlements: MockSettlement[] = [
  {
    id: 's1',
    batchNumber: 'SETTLE-20261116-89A1',
    providerName: 'Tariq Mehmood',
    bankName: 'Habib Bank Limited (HBL)',
    accountTitle: 'Tariq Mehmood',
    amount: 14200.00,
    status: 'completed',
    payoutRef: 'IBFT-HBL-99882211',
    createdAt: '2026-11-16 09:30',
  },
  {
    id: 's2',
    batchNumber: 'SETTLE-20261116-44B2',
    providerName: 'Usman Ali',
    bankName: 'Meezan Bank Limited',
    accountTitle: 'Muhammad Usman',
    amount: 8500.00,
    status: 'pending',
    createdAt: '2026-11-16 16:45',
  },
];

export default function SettlementsPage() {
  const [settlements, setSettlements] = useState<MockSettlement[]>(initialSettlements);
  const [message, setMessage] = useState<string | null>(null);

  const handleProcess = (id: string) => {
    setSettlements(prev => prev.map(s => {
      if (s.id === id) {
        return {
          ...s,
          status: 'completed',
          payoutRef: 'IBFT-DISBURSE-' + Math.floor(100000 + Math.random() * 900000),
        };
      }
      return s;
    }));

    setMessage(`Disbursement processed! Ledger updated: Debit 2020 (Provider Payable) / Credit 1010 (Platform Bank).`);
    setTimeout(() => setMessage(null), 4000);
  };

  return (
    <div className="space-y-6">
      {/* Title */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Provider Settlements & Payouts Engine</h1>
          <p className="text-sm text-slate-500 mt-1">Batch disbursements to verified provider bank accounts via 1Link IBFT.</p>
        </div>
      </div>

      {message && (
        <div className="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm flex items-center space-x-2">
          <CheckCircle2 className="w-5 h-5 text-emerald-600" />
          <span>{message}</span>
        </div>
      )}

      {/* Summary Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="p-5 rounded-xl border border-slate-200 bg-white shadow-sm">
          <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Disbursed (This Week)</span>
          <div className="text-2xl font-black text-slate-900 mt-2">PKR 342,800.00</div>
          <span className="text-xs text-emerald-600 font-semibold mt-1 inline-block">100% IBFT delivery rate</span>
        </div>
        <div className="p-5 rounded-xl border border-slate-200 bg-white shadow-sm">
          <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pending Batch Payouts</span>
          <div className="text-2xl font-black text-amber-600 mt-2">PKR 8,500.00</div>
          <span className="text-xs text-slate-500 mt-1 inline-block">1 provider batch queued</span>
        </div>
        <div className="p-5 rounded-xl border border-slate-200 bg-white shadow-sm">
          <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Eligibility Threshold</span>
          <div className="text-2xl font-black text-slate-900 mt-2">PKR 1,000.00</div>
          <span className="text-xs text-slate-500 mt-1 inline-block">Net Wallet balance requirement</span>
        </div>
      </div>

      {/* Settlements Table */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h2 className="text-base font-bold text-slate-900">Settlement Batches</h2>
          <span className="text-xs text-slate-500">Double-entry verified</span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50/75 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                <th className="py-3 px-6">Batch ID</th>
                <th className="py-3 px-6">Provider Name</th>
                <th className="py-3 px-6">Bank & Account</th>
                <th className="py-3 px-6">Amount (PKR)</th>
                <th className="py-3 px-6">Status</th>
                <th className="py-3 px-6 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {settlements.map((s) => (
                <tr key={s.id} className="hover:bg-slate-50/80 transition-colors">
                  <td className="py-3.5 px-6 font-mono font-bold text-slate-900">{s.batchNumber}</td>
                  <td className="py-3.5 px-6 font-semibold text-slate-800">{s.providerName}</td>
                  <td className="py-3.5 px-6">
                    <div className="font-semibold text-slate-900 flex items-center">
                      <Building2 className="w-3.5 h-3.5 mr-1 text-slate-400" />
                      {s.bankName}
                    </div>
                    <div className="text-xs text-slate-500">{s.accountTitle}</div>
                  </td>
                  <td className="py-3.5 px-6 font-mono font-bold text-slate-900">
                    PKR {s.amount.toFixed(2)}
                  </td>
                  <td className="py-3.5 px-6">
                    <span className={`inline-block px-2.5 py-1 rounded-full text-xs font-semibold border ${
                      s.status === 'completed'
                        ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                        : 'bg-amber-100 text-amber-800 border-amber-200'
                    }`}>
                      {s.status}
                    </span>
                    {s.payoutRef && (
                      <div className="text-[10px] font-mono text-slate-400 mt-0.5">{s.payoutRef}</div>
                    )}
                  </td>
                  <td className="py-3.5 px-6 text-right">
                    {s.status === 'pending' ? (
                      <button
                        onClick={() => handleProcess(s.id)}
                        className="px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-semibold transition-colors shadow-sm shadow-teal-600/20"
                      >
                        Disburse IBFT
                      </button>
                    ) : (
                      <span className="text-xs text-slate-400 font-medium">Reconciled</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
