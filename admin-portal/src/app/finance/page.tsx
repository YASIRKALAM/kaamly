'use client';

import React, { useState } from 'react';
import { 
  BookOpenCheck, 
  RotateCcw, 
  CheckCircle2, 
  ArrowUpRight, 
  ArrowDownLeft, 
  Search, 
  Filter,
  DollarSign
} from 'lucide-react';

interface Account {
  code: string;
  name: string;
  type: 'asset' | 'liability' | 'equity' | 'revenue' | 'expense';
  balance: number;
  currency: string;
  desc: string;
}

const mockAccounts: Account[] = [
  { code: '1010', name: 'Platform Main Bank Account', type: 'asset', balance: 842500.00, currency: 'PKR', desc: 'Depository operational bank account holding platform reserves.' },
  { code: '1020', name: 'Payment Gateway Clearing', type: 'asset', balance: 125400.00, currency: 'PKR', desc: 'Captured online payments in-transit from card/wallet gateways.' },
  { code: '1030', name: 'Provider Commission Receivable', type: 'asset', balance: 48600.00, currency: 'PKR', desc: 'Uncollected commission & penalties owed by technicians from direct COD jobs.' },
  { code: '2010', name: 'Customer Escrow Holding', type: 'liability', balance: 312000.00, currency: 'PKR', desc: 'Fiduciary holding of customer advance payments pending job completion.' },
  { code: '2020', name: 'Provider Payable', type: 'liability', balance: 428000.00, currency: 'PKR', desc: 'Earned technician job earnings awaiting scheduled weekly payout batches.' },
  { code: '4010', name: 'Marketplace Commission Revenue', type: 'revenue', balance: 218500.00, currency: 'PKR', desc: 'Cumulative commission fees recognized upon confirmed job completions.' },
  { code: '4030', name: 'Cancellation & Penalty Fee Revenue', type: 'revenue', balance: 14000.00, currency: 'PKR', desc: 'Liquidated penalty damages collected from unexcused no-shows and cancellations.' },
];

const mockTransactions = [
  {
    id: 'tx-1',
    number: 'TX-20261116-8A11',
    refType: 'booking_completion',
    refId: 'BK-20261116-A82F',
    desc: 'Online job completion earnings and commission for Booking #BK-20261116-A82F',
    status: 'posted',
    postedAt: '2026-11-16 14:32',
    entries: [
      { code: '2010', name: 'Customer Escrow Holding', dir: 'debit', amount: 2400.00 },
      { code: '2020', name: 'Provider Payable', dir: 'credit', amount: 2160.00 },
      { code: '4010', name: 'Platform Commission Revenue', dir: 'credit', amount: 240.00 },
    ],
  },
  {
    id: 'tx-2',
    number: 'TX-20261116-7B99',
    refType: 'cod_commission',
    refId: 'BK-20261116-C03D',
    desc: 'COD job commission recognized for Booking #BK-20261116-C03D',
    status: 'posted',
    postedAt: '2026-11-16 12:15',
    entries: [
      { code: '1030', name: 'Provider Commission Receivable', dir: 'debit', amount: 950.00 },
      { code: '4010', name: 'Platform Commission Revenue', dir: 'credit', amount: 950.00 },
    ],
  },
  {
    id: 'tx-3',
    number: 'TX-20261116-6C44',
    refType: 'cancellation_dispatch_fee',
    refId: 'BK-20261116-B91C',
    desc: 'Customer late cancellation travel fee for Booking #BK-20261116-B91C',
    status: 'posted',
    postedAt: '2026-11-16 10:40',
    entries: [
      { code: '2010', name: 'Customer Escrow Holding', dir: 'debit', amount: 500.00 },
      { code: '2020', name: 'Provider Payable', dir: 'credit', amount: 500.00 },
    ],
  },
];

export default function FinancePage() {
  const [activeTab, setActiveTab] = useState<'accounts' | 'transactions'>('accounts');
  const [selectedTx, setSelectedTx] = useState<any | null>(null);
  const [reversalReason, setReversalReason] = useState('');
  const [notification, setNotification] = useState<string | null>(null);

  const handleReverse = () => {
    if (!reversalReason) return;
    setNotification(`Transaction ${selectedTx.number} successfully reversed with linking reference!`);
    setSelectedTx(null);
    setReversalReason('');
    setTimeout(() => setNotification(null), 4000);
  };

  return (
    <div className="space-y-8">
      {/* Title & Navigation */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-200">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Double-Entry Financial Ledger</h1>
          <p className="text-sm text-slate-500 mt-1">Immutable journal transactions, escrow reconciliation, and strict debit-credit balancing.</p>
        </div>

        <div className="flex items-center space-x-2 bg-slate-200/80 p-1 rounded-lg text-xs font-semibold">
          <button
            onClick={() => setActiveTab('accounts')}
            className={`px-3 py-1.5 rounded-md transition-colors ${activeTab === 'accounts' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'}`}
          >
            Chart of Accounts
          </button>
          <button
            onClick={() => setActiveTab('transactions')}
            className={`px-3 py-1.5 rounded-md transition-colors ${activeTab === 'transactions' ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'}`}
          >
            Posted Transactions Journal
          </button>
        </div>
      </div>

      {notification && (
        <div className="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm flex items-center space-x-2">
          <CheckCircle2 className="w-5 h-5 text-emerald-600" />
          <span>{notification}</span>
        </div>
      )}

      {/* Accounting Principles Banner */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="p-4 rounded-xl border border-teal-200 bg-teal-50/50">
          <div className="text-xs font-bold uppercase tracking-wider text-teal-800">Fiduciary Escrow Holding (2010)</div>
          <div className="text-xl font-black text-slate-900 mt-1">PKR 312,000.00</div>
          <p className="text-xs text-slate-600 mt-1">100% backed by gateway in-transit funds. Released upon job completion.</p>
        </div>
        <div className="p-4 rounded-xl border border-indigo-200 bg-indigo-50/50">
          <div className="text-xs font-bold uppercase tracking-wider text-indigo-800">COD Commission Receivable (1030)</div>
          <div className="text-xl font-black text-slate-900 mt-1">PKR 48,600.00</div>
          <p className="text-xs text-slate-600 mt-1">Platform commission owed by technicians from cash collections.</p>
        </div>
        <div className="p-4 rounded-xl border border-emerald-200 bg-emerald-50/50">
          <div className="text-xs font-bold uppercase tracking-wider text-emerald-800">Net Provider Wallet Formula</div>
          <div className="text-sm font-bold text-slate-800 mt-1">Payables (2020) - Receivables (1030)</div>
          <p className="text-xs text-slate-600 mt-1">Separately tracked in ledger, netted only for withdrawal eligibility.</p>
        </div>
      </div>

      {activeTab === 'accounts' ? (
        /* Chart of Accounts Table */
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
            <h2 className="text-base font-bold text-slate-900">General Ledger Accounts</h2>
            <span className="text-xs text-slate-500 font-mono">Precision: NUMERIC(14, 2)</span>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-sm">
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50/75 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                  <th className="py-3 px-6">Account Code</th>
                  <th className="py-3 px-6">Account Name</th>
                  <th className="py-3 px-6">Type</th>
                  <th className="py-3 px-6">Description</th>
                  <th className="py-3 px-6 text-right">Computed Balance</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {mockAccounts.map((acc) => (
                  <tr key={acc.code} className="hover:bg-slate-50/80 transition-colors">
                    <td className="py-3.5 px-6 font-mono font-bold text-teal-700">{acc.code}</td>
                    <td className="py-3.5 px-6 font-semibold text-slate-900">{acc.name}</td>
                    <td className="py-3.5 px-6">
                      <span className="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider bg-slate-100 text-slate-700">
                        {acc.type}
                      </span>
                    </td>
                    <td className="py-3.5 px-6 text-xs text-slate-500 max-w-xs">{acc.desc}</td>
                    <td className="py-3.5 px-6 text-right font-mono font-bold text-slate-900">
                      PKR {acc.balance.toLocaleString('en-US', { minimumFractionDigits: 2 })}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      ) : (
        /* Posted Transactions Journal */
        <div className="space-y-4">
          {mockTransactions.map((tx) => (
            <div key={tx.id} className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-100 gap-2">
                <div>
                  <div className="flex items-center space-x-3">
                    <span className="font-mono font-bold text-slate-900 text-base">{tx.number}</span>
                    <span className="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200 uppercase">
                      {tx.status}
                    </span>
                    <span className="text-xs text-slate-500">Ref: {tx.refType} ({tx.refId})</span>
                  </div>
                  <p className="text-xs text-slate-600 mt-1">{tx.desc}</p>
                </div>

                <div className="flex items-center space-x-3">
                  <span className="text-xs text-slate-400">{tx.postedAt}</span>
                  <button
                    onClick={() => setSelectedTx(tx)}
                    className="inline-flex items-center px-3 py-1.5 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-semibold transition-colors"
                  >
                    <RotateCcw className="w-3.5 h-3.5 mr-1" />
                    Reverse
                  </button>
                </div>
              </div>

              {/* Debit / Credit Lines */}
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs">
                  <thead>
                    <tr className="text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-100">
                      <th className="py-2">Account</th>
                      <th className="py-2">Direction</th>
                      <th className="py-2 text-right">Debit (PKR)</th>
                      <th className="py-2 text-right">Credit (PKR)</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {tx.entries.map((entry, idx) => (
                      <tr key={idx}>
                        <td className="py-2 font-mono">
                          <span className="font-bold text-slate-800">{entry.code}</span> - {entry.name}
                        </td>
                        <td className="py-2">
                          <span className={`inline-block px-2 py-0.5 rounded font-bold uppercase text-[10px] ${
                            entry.dir === 'debit' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'
                          }`}>
                            {entry.dir}
                          </span>
                        </td>
                        <td className="py-2 text-right font-mono font-semibold text-slate-800">
                          {entry.dir === 'debit' ? entry.amount.toFixed(2) : '-'}
                        </td>
                        <td className="py-2 text-right font-mono font-semibold text-slate-800">
                          {entry.dir === 'credit' ? entry.amount.toFixed(2) : '-'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Reversal Confirmation Modal */}
      {selectedTx && (
        <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 space-y-4">
            <h3 className="text-lg font-bold text-slate-900">Post Transaction Reversal</h3>
            <p className="text-xs text-slate-600">
              This will create an atomic double-entry reversal transaction for <span className="font-mono font-bold text-slate-800">{selectedTx.number}</span> by inverting all debits and credits. Posted entries remain immutable.
            </p>

            <div>
              <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                Reversal Justification Reason *
              </label>
              <textarea
                value={reversalReason}
                onChange={(e) => setReversalReason(e.target.value)}
                placeholder="e.g. Accounting correction authorized by finance manager due to duplicate webhook"
                rows={3}
                className="w-full text-sm p-3 rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-teal-500"
              />
            </div>

            <div className="flex items-center justify-end space-x-3 pt-3 border-t border-slate-200">
              <button
                onClick={() => setSelectedTx(null)}
                className="px-4 py-2 border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-100"
              >
                Cancel
              </button>
              <button
                onClick={handleReverse}
                disabled={!reversalReason}
                className="px-4 py-2 bg-rose-600 hover:bg-rose-700 disabled:opacity-50 text-white rounded-lg text-sm font-semibold transition-colors"
              >
                Confirm Reversal Posting
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
