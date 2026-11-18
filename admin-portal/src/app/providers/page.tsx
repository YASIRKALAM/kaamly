'use client';

import React, { useState } from 'react';
import { 
  CheckCircle, 
  XCircle, 
  FileText, 
  ShieldCheck, 
  Search, 
  Filter, 
  Clock, 
  CheckCircle2, 
  Eye
} from 'lucide-react';

interface MockProvider {
  id: string;
  name: string;
  businessName: string;
  cnic: string;
  phone: string;
  rating: number;
  jobs: number;
  status: 'pending_approval' | 'approved' | 'draft' | 'suspended';
  documents: {
    id: string;
    type: string;
    status: 'pending' | 'verified' | 'rejected';
    url: string;
  }[];
}

const mockProviders: MockProvider[] = [
  {
    id: '1',
    name: 'Tariq Mehmood',
    businessName: 'Tariq HVAC & Inverter Solutions',
    cnic: '35202-1234567-1',
    phone: '+92 301 1234567',
    rating: 4.85,
    jobs: 52,
    status: 'approved',
    documents: [
      { id: 'd1', type: 'CNIC Front', status: 'verified', url: '#' },
      { id: 'd2', type: 'CNIC Back', status: 'verified', url: '#' },
      { id: 'd3', type: 'HVAC Trade Certificate', status: 'verified', url: '#' },
    ],
  },
  {
    id: '2',
    name: 'Muhammad Usman',
    businessName: 'Usman Express Electricians',
    cnic: '35201-9876543-3',
    phone: '+92 303 5556677',
    rating: 0,
    jobs: 0,
    status: 'pending_approval',
    documents: [
      { id: 'd4', type: 'CNIC Front', status: 'pending', url: '#' },
      { id: 'd5', type: 'CNIC Back', status: 'pending', url: '#' },
      { id: 'd6', type: 'Police Clearance Certificate', status: 'pending', url: '#' },
    ],
  },
  {
    id: '3',
    name: 'Rashid Khan',
    businessName: 'Falcon Sanitary & Plumbers',
    cnic: '35202-4433221-5',
    phone: '+92 304 9988776',
    rating: 0,
    jobs: 0,
    status: 'pending_approval',
    documents: [
      { id: 'd7', type: 'CNIC Front', status: 'pending', url: '#' },
      { id: 'd8', type: 'CNIC Back', status: 'pending', url: '#' },
    ],
  },
];

export default function ProvidersPage() {
  const [filter, setFilter] = useState<'all' | 'pending_approval' | 'approved'>('all');
  const [selectedProvider, setSelectedProvider] = useState<MockProvider | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);

  const filtered = mockProviders.filter(p => filter === 'all' || p.status === filter);

  const handleApprove = (providerId: string) => {
    setActionSuccess(`Provider #${providerId} has been successfully verified and activated!`);
    setSelectedProvider(null);
    setTimeout(() => setActionSuccess(null), 4000);
  };

  return (
    <div className="space-y-6">
      {/* Title & Stats */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Provider Verification & Compliance Hub</h1>
          <p className="text-sm text-slate-500 mt-1">Review regulatory compliance, CNIC records, and trade credentials before approving technicians.</p>
        </div>

        {/* Tab Filters */}
        <div className="flex items-center space-x-2 bg-slate-200/80 p-1 rounded-lg text-xs font-semibold">
          <button
            onClick={() => setFilter('all')}
            className={`px-3 py-1.5 rounded-md transition-colors ${filter === 'all' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'}`}
          >
            All Providers ({mockProviders.length})
          </button>
          <button
            onClick={() => setFilter('pending_approval')}
            className={`px-3 py-1.5 rounded-md transition-colors ${filter === 'pending_approval' ? 'bg-amber-500 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'}`}
          >
            Pending Review (2)
          </button>
          <button
            onClick={() => setFilter('approved')}
            className={`px-3 py-1.5 rounded-md transition-colors ${filter === 'approved' ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'}`}
          >
            Approved (1)
          </button>
        </div>
      </div>

      {actionSuccess && (
        <div className="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm flex items-center space-x-2">
          <CheckCircle2 className="w-5 h-5 text-emerald-600" />
          <span>{actionSuccess}</span>
        </div>
      )}

      {/* Providers Table */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50/75 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                <th className="py-3 px-6">Provider / Business</th>
                <th className="py-3 px-6">CNIC Number</th>
                <th className="py-3 px-6">Contact Phone</th>
                <th className="py-3 px-6">Rating / Jobs</th>
                <th className="py-3 px-6">Status</th>
                <th className="py-3 px-6 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {filtered.map((provider) => (
                <tr key={provider.id} className="hover:bg-slate-50/80 transition-colors">
                  <td className="py-3.5 px-6">
                    <div className="font-semibold text-slate-900">{provider.name}</div>
                    <div className="text-xs text-slate-500">{provider.businessName}</div>
                  </td>
                  <td className="py-3.5 px-6 font-mono text-slate-700">{provider.cnic}</td>
                  <td className="py-3.5 px-6 text-slate-600">{provider.phone}</td>
                  <td className="py-3.5 px-6">
                    <div className="font-semibold text-slate-800">{provider.rating > 0 ? `★ ${provider.rating}` : 'Unrated'}</div>
                    <div className="text-xs text-slate-500">{provider.jobs} completed jobs</div>
                  </td>
                  <td className="py-3.5 px-6">
                    <span className={`inline-block px-2.5 py-1 rounded-full text-xs font-semibold border ${
                      provider.status === 'approved' 
                        ? 'bg-emerald-100 text-emerald-800 border-emerald-200' 
                        : 'bg-amber-100 text-amber-800 border-amber-200'
                    }`}>
                      {provider.status.replace(/_/g, ' ')}
                    </span>
                  </td>
                  <td className="py-3.5 px-6 text-right">
                    <button
                      onClick={() => setSelectedProvider(provider)}
                      className="inline-flex items-center px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold transition-colors"
                    >
                      <Eye className="w-3.5 h-3.5 mr-1.5" />
                      Review Docs
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Verification Drawer / Modal */}
      {selectedProvider && (
        <div className="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-2xl w-full p-6 space-y-6">
            <div className="flex items-start justify-between border-b border-slate-200 pb-4">
              <div>
                <h3 className="text-lg font-bold text-slate-900">{selectedProvider.name}</h3>
                <p className="text-xs text-slate-500">{selectedProvider.businessName} • CNIC: {selectedProvider.cnic}</p>
              </div>
              <button 
                onClick={() => setSelectedProvider(null)}
                className="text-slate-400 hover:text-slate-600 p-1"
              >
                ✕
              </button>
            </div>

            {/* Document Gallery */}
            <div className="space-y-4">
              <h4 className="text-xs font-bold uppercase tracking-wider text-slate-500">Submitted Compliance Documents</h4>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {selectedProvider.documents.map((doc) => (
                  <div key={doc.id} className="p-4 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-between">
                    <div className="flex items-center space-x-3">
                      <FileText className="w-8 h-8 text-teal-600" />
                      <div>
                        <div className="text-sm font-semibold text-slate-900">{doc.type}</div>
                        <span className={`inline-block text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full ${
                          doc.status === 'verified' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'
                        }`}>
                          {doc.status}
                        </span>
                      </div>
                    </div>
                    <button className="text-xs font-semibold text-teal-600 hover:text-teal-700 underline">
                      Inspect (S3)
                    </button>
                  </div>
                ))}
              </div>
            </div>

            {/* Action Buttons */}
            <div className="flex items-center justify-end space-x-3 pt-4 border-t border-slate-200">
              <button
                onClick={() => setSelectedProvider(null)}
                className="px-4 py-2 border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-100"
              >
                Cancel
              </button>
              <button
                onClick={() => handleApprove(selectedProvider.id)}
                className="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm font-semibold transition-colors shadow-sm shadow-teal-600/20"
              >
                Approve & Activate Provider
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
