'use client';

import React, { useState } from 'react';
import { 
  Scale, 
  AlertTriangle, 
  CheckCircle2, 
  XCircle, 
  FileText, 
  User, 
  ArrowRight
} from 'lucide-react';

interface MockDispute {
  id: string;
  bookingNumber: string;
  service: string;
  totalAmount: number;
  raisedBy: string;
  role: string;
  reason: string;
  claimAmount: number;
  status: 'opened' | 'under_review' | 'resolved';
  resolution?: string;
  createdAt: string;
}

const initialDisputes: MockDispute[] = [
  {
    id: 'dsp-1',
    bookingNumber: 'BK-20261115-F41A',
    service: 'AC Gas Refill (R410A)',
    totalAmount: 6500.00,
    raisedBy: 'Zahid Siddiqui',
    role: 'Customer',
    reason: 'Technician refilled gas but cooling failed after 4 hours due to undetected flare nut leak.',
    claimAmount: 6500.00,
    status: 'opened',
    createdAt: '2026-11-15 18:20',
  },
];

export default function DisputesPage() {
  const [disputes, setDisputes] = useState<MockDispute[]>(initialDisputes);
  const [selectedDispute, setSelectedDispute] = useState<MockDispute | null>(null);
  const [refundAmount, setRefundAmount] = useState('3500');
  const [payoutAmount, setPayoutAmount] = useState('3000');
  const [arbitrationNotes, setArbitrationNotes] = useState('');
  const [notice, setNotice] = useState<string | null>(null);

  const handleArbitrate = () => {
    if (!selectedDispute) return;
    const ref = parseFloat(refundAmount) || 0;
    const pay = parseFloat(payoutAmount) || 0;

    if (ref + pay > selectedDispute.totalAmount) {
      alert(`Total resolution (${ref + pay}) cannot exceed booking total (${selectedDispute.totalAmount})`);
      return;
    }

    setDisputes(prev => prev.map(d => {
      if (d.id === selectedDispute.id) {
        return {
          ...d,
          status: 'resolved',
          resolution: `Split: Customer refunded PKR ${ref} / Provider paid PKR ${pay}`,
        };
      }
      return d;
    }));

    setNotice(`Dispute arbitrated! Ledger posted: Escrow 2010 debited -> Customer Refund (PKR ${ref}) and Provider Payable (PKR ${pay}).`);
    setSelectedDispute(null);
    setTimeout(() => setNotice(null), 5000);
  };

  return (
    <div className="space-y-6">
      {/* Title */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Dispute Arbitration Console</h1>
          <p className="text-sm text-slate-500 mt-1">Impartial quality review, customer claim adjudication, and escrow payout splits.</p>
        </div>
      </div>

      {notice && (
        <div className="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm flex items-center space-x-2">
          <CheckCircle2 className="w-5 h-5 text-emerald-600" />
          <span>{notice}</span>
        </div>
      )}

      {/* Disputes Table */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50/75 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                <th className="py-3 px-6">Booking</th>
                <th className="py-3 px-6">Raised By</th>
                <th className="py-3 px-6">Dispute Grievance</th>
                <th className="py-3 px-6">Claim / Total</th>
                <th className="py-3 px-6">Status</th>
                <th className="py-3 px-6 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {disputes.map((d) => (
                <tr key={d.id} className="hover:bg-slate-50/80 transition-colors">
                  <td className="py-3.5 px-6">
                    <div className="font-mono font-bold text-slate-900">{d.bookingNumber}</div>
                    <div className="text-xs text-slate-500">{d.service}</div>
                  </td>
                  <td className="py-3.5 px-6">
                    <div className="font-semibold text-slate-800">{d.raisedBy}</div>
                    <span className="text-[10px] font-semibold uppercase tracking-wider bg-slate-100 px-2 py-0.5 rounded text-slate-600">
                      {d.role}
                    </span>
                  </td>
                  <td className="py-3.5 px-6 max-w-sm text-xs text-slate-700">
                    <p className="line-clamp-2">{d.reason}</p>
                    {d.resolution && (
                      <p className="mt-1 font-semibold text-emerald-700">{d.resolution}</p>
                    )}
                  </td>
                  <td className="py-3.5 px-6 font-mono text-xs">
                    <div className="font-bold text-rose-600">Claim: PKR {d.claimAmount.toFixed(2)}</div>
                    <div className="text-slate-500">Total: PKR {d.totalAmount.toFixed(2)}</div>
                  </td>
                  <td className="py-3.5 px-6">
                    <span className={`inline-block px-2.5 py-1 rounded-full text-xs font-semibold border ${
                      d.status === 'resolved'
                        ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                        : 'bg-rose-100 text-rose-800 border-rose-200'
                    }`}>
                      {d.status}
                    </span>
                  </td>
                  <td className="py-3.5 px-6 text-right">
                    {d.status !== 'resolved' ? (
                      <button
                        onClick={() => setSelectedDispute(d)}
                        className="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold transition-colors"
                      >
                        Arbitrate Case
                      </button>
                    ) : (
                      <span className="text-xs text-slate-400 font-medium">Closed</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Arbitration Modal */}
      {selectedDispute && (
        <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 space-y-5">
            <div className="flex items-start justify-between border-b border-slate-200 pb-3">
              <div>
                <h3 className="text-lg font-bold text-slate-900">Arbitrate Dispute: {selectedDispute.bookingNumber}</h3>
                <p className="text-xs text-slate-500">Total Booking Value: PKR {selectedDispute.totalAmount.toFixed(2)}</p>
              </div>
              <button onClick={() => setSelectedDispute(null)} className="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <div className="p-3 bg-amber-50 rounded-lg border border-amber-200 text-xs text-amber-900">
              <span className="font-bold">Customer Grievance:</span> {selectedDispute.reason}
            </div>

            {/* Split Calculation */}
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                  Customer Refund (PKR)
                </label>
                <input
                  type="number"
                  value={refundAmount}
                  onChange={(e) => setRefundAmount(e.target.value)}
                  className="w-full text-sm p-2.5 rounded-lg border border-slate-300 font-mono font-semibold"
                />
              </div>

              <div>
                <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                  Provider Payout (PKR)
                </label>
                <input
                  type="number"
                  value={payoutAmount}
                  onChange={(e) => setPayoutAmount(e.target.value)}
                  className="w-full text-sm p-2.5 rounded-lg border border-slate-300 font-mono font-semibold"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                Arbitrator Findings & Notes *
              </label>
              <textarea
                value={arbitrationNotes}
                onChange={(e) => setArbitrationNotes(e.target.value)}
                placeholder="Detail the technical investigation conclusions and customer contact logs..."
                rows={3}
                className="w-full text-sm p-2.5 rounded-lg border border-slate-300"
              />
            </div>

            <div className="flex items-center justify-end space-x-3 pt-3 border-t border-slate-200">
              <button
                onClick={() => setSelectedDispute(null)}
                className="px-4 py-2 border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-100"
              >
                Cancel
              </button>
              <button
                onClick={handleArbitrate}
                disabled={!arbitrationNotes}
                className="px-4 py-2 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white rounded-lg text-sm font-semibold transition-colors"
              >
                Execute Split & Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
