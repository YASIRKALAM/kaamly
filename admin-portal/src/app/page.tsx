import React from 'react';
import { 
  TrendingUp, 
  Users, 
  Wrench, 
  AlertTriangle, 
  DollarSign, 
  ShieldCheck, 
  CheckCircle2, 
  Clock
} from 'lucide-react';
import Link from 'next/link';

export default function DashboardPage() {
  const kpis = [
    {
      title: 'Gross Marketplace Volume',
      value: 'PKR 1,428,500',
      change: '+18.4% vs last week',
      icon: DollarSign,
      color: 'bg-emerald-500',
    },
    {
      title: 'Active In-Progress Jobs',
      value: '42',
      change: '14 technicians en-route',
      icon: Clock,
      color: 'bg-teal-500',
    },
    {
      title: 'Online Active Technicians',
      value: '128',
      change: '94% shift adherence',
      icon: Wrench,
      color: 'bg-blue-500',
    },
    {
      title: 'Pending Verifications',
      value: '7',
      change: 'Requires CNIC/License audit',
      icon: AlertTriangle,
      color: 'bg-amber-500',
    },
  ];

  const recentBookings = [
    { id: '1', number: 'BK-20261116-A82F', service: 'AC Master Chemical Wash', customer: 'Ali Raza', provider: 'Tariq Mehmood', status: 'in_progress', amount: 'PKR 2,400' },
    { id: '2', number: 'BK-20261116-B91C', service: 'Distribution Board Breaker Repair', customer: 'Hamza Khan', provider: 'Usman Ali', status: 'provider_on_the_way', amount: 'PKR 1,500' },
    { id: '3', number: 'BK-20261116-C03D', service: 'Full Apartment Deep Cleaning', customer: 'Zainab Bibi', provider: 'ProCleaners Team', status: 'confirmed', amount: 'PKR 9,500' },
    { id: '4', number: 'BK-20261116-D44E', service: 'Geyser Installation & Descaling', customer: 'Bilal Ahmed', provider: 'Pending Dispatch', status: 'broadcasted', amount: 'PKR 2,000' },
    { id: '5', number: 'BK-20261116-E55F', service: 'Inverter Diagnostic Inspection', customer: 'Sarmad Malik', provider: 'Tariq Mehmood', status: 'quotation_submitted', amount: 'PKR 4,800' },
  ];

  const statusBadges: Record<string, string> = {
    broadcasted: 'bg-purple-100 text-purple-800 border-purple-200',
    confirmed: 'bg-blue-100 text-blue-800 border-blue-200',
    provider_on_the_way: 'bg-amber-100 text-amber-800 border-amber-200',
    in_progress: 'bg-teal-100 text-teal-800 border-teal-200',
    quotation_submitted: 'bg-indigo-100 text-indigo-800 border-indigo-200',
    completed: 'bg-emerald-100 text-emerald-800 border-emerald-200',
  };

  return (
    <div className="space-y-8">
      {/* Welcome Banner */}
      <div className="flex flex-col md:flex-row md:items-center justify-between pb-6 border-b border-slate-200 gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Marketplace Operations Control</h1>
          <p className="text-sm text-slate-500 mt-1">Real-time telemetry, spatial dispatch monitor, and double-entry escrow tracking.</p>
        </div>
        <div className="flex items-center space-x-3">
          <Link
            href="/providers"
            className="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-sm font-semibold transition-colors shadow-sm"
          >
            Review Pending Technicians (7)
          </Link>
          <Link
            href="/finance"
            className="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm font-semibold transition-colors shadow-sm shadow-teal-600/20"
          >
            Ledger Audit
          </Link>
        </div>
      </div>

      {/* KPI Cards Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        {kpis.map((kpi) => {
          const Icon = kpi.icon;
          return (
            <div key={kpi.title} className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm flex items-start justify-between">
              <div>
                <p className="text-xs font-semibold text-slate-500 uppercase tracking-wider">{kpi.title}</p>
                <h3 className="text-2xl font-black text-slate-900 mt-2">{kpi.value}</h3>
                <p className="text-xs font-medium text-slate-600 mt-2 flex items-center">
                  <span className="text-emerald-600 font-semibold">{kpi.change}</span>
                </p>
              </div>
              <div className={`w-11 h-11 rounded-lg ${kpi.color} text-white flex items-center justify-center shadow-md`}>
                <Icon className="w-6 h-6" />
              </div>
            </div>
          );
        })}
      </div>

      {/* 13-State Lifecycle Pipeline Funnel */}
      <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h2 className="text-base font-bold text-slate-900">Active Booking Lifecycle Pipeline</h2>
            <p className="text-xs text-slate-500 mt-0.5">Real-time status progression across all active marketplace requests</p>
          </div>
          <Link href="/bookings" className="text-xs font-semibold text-teal-600 hover:text-teal-700">View All 13 States &rarr;</Link>
        </div>

        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 pt-2">
          {[
            { label: 'Broadcasted', count: 18, color: 'border-purple-300 bg-purple-50 text-purple-900' },
            { label: 'Accepted', count: 12, color: 'border-blue-300 bg-blue-50 text-blue-900' },
            { label: 'Confirmed (Escrow)', count: 24, color: 'border-teal-300 bg-teal-50 text-teal-900' },
            { label: 'On The Way', count: 14, color: 'border-amber-300 bg-amber-50 text-amber-900' },
            { label: 'In Progress', count: 42, color: 'border-emerald-300 bg-emerald-50 text-emerald-900' },
            { label: 'Disputed', count: 2, color: 'border-rose-300 bg-rose-50 text-rose-900' },
          ].map((stage) => (
            <div key={stage.label} className={`p-4 rounded-lg border ${stage.color} flex flex-col justify-between`}>
              <span className="text-xs font-bold uppercase tracking-wider">{stage.label}</span>
              <span className="text-2xl font-black mt-2">{stage.count}</span>
            </div>
          ))}
        </div>
      </div>

      {/* Live Booking Telemetry Table */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
          <h2 className="text-base font-bold text-slate-900">Live Booking Feed</h2>
          <span className="text-xs font-medium text-slate-500">Auto-refresh via Laravel Reverb</span>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50/75 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                <th className="py-3 px-6">Booking Number</th>
                <th className="py-3 px-6">Service</th>
                <th className="py-3 px-6">Customer</th>
                <th className="py-3 px-6">Provider</th>
                <th className="py-3 px-6">Status</th>
                <th className="py-3 px-6 text-right">Amount</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {recentBookings.map((b) => (
                <tr key={b.id} className="hover:bg-slate-50/80 transition-colors">
                  <td className="py-3.5 px-6 font-mono font-semibold text-slate-900">{b.number}</td>
                  <td className="py-3.5 px-6 font-medium text-slate-800">{b.service}</td>
                  <td className="py-3.5 px-6 text-slate-600">{b.customer}</td>
                  <td className="py-3.5 px-6 text-slate-600">{b.provider}</td>
                  <td className="py-3.5 px-6">
                    <span className={`inline-block px-2.5 py-1 rounded-full text-xs font-semibold border ${statusBadges[b.status] || 'bg-slate-100 text-slate-700'}`}>
                      {b.status.replace(/_/g, ' ')}
                    </span>
                  </td>
                  <td className="py-3.5 px-6 text-right font-semibold text-slate-900">{b.amount}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
