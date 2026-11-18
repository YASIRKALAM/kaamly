'use client';

import React, { useState } from 'react';
import { 
  CalendarClock, 
  MapPin, 
  Filter, 
  Search, 
  Eye, 
  AlertTriangle,
  CheckCircle2,
  XCircle,
  Clock
} from 'lucide-react';

interface MockBooking {
  id: string;
  bookingNumber: string;
  service: string;
  customerName: string;
  customerPhone: string;
  address: string;
  providerName: string | null;
  status: string;
  scheduledTime: string;
  agreedPrice: number;
  paymentMode: string;
}

const mockBookings: MockBooking[] = [
  {
    id: 'b1',
    bookingNumber: 'BK-20261116-A82F',
    service: 'AC Master Chemical Wash',
    customerName: 'Ali Raza',
    customerPhone: '+92 302 1234567',
    address: 'House 45, Block B-3, Gulberg III, Lahore',
    providerName: 'Tariq Mehmood',
    status: 'in_progress',
    scheduledTime: '2026-11-16 14:00 - 15:30',
    agreedPrice: 2400.00,
    paymentMode: 'online_escrow',
  },
  {
    id: 'b2',
    bookingNumber: 'BK-20261116-B91C',
    service: 'Distribution Board Breaker Repair',
    customerName: 'Hamza Khan',
    customerPhone: '+92 300 8877665',
    address: 'Commercial Area, Phase 5, DHA, Lahore',
    providerName: 'Usman Ali',
    status: 'provider_on_the_way',
    scheduledTime: '2026-11-16 15:00 - 16:00',
    agreedPrice: 1500.00,
    paymentMode: 'cash_on_delivery',
  },
  {
    id: 'b3',
    bookingNumber: 'BK-20261116-C03D',
    service: 'Full Apartment Deep Cleaning',
    customerName: 'Zainab Bibi',
    customerPhone: '+92 321 4455667',
    address: 'Apartment 4B, Askari 10, Lahore',
    providerName: 'ProCleaners Team',
    status: 'confirmed',
    scheduledTime: '2026-11-16 16:30 - 20:30',
    agreedPrice: 9500.00,
    paymentMode: 'online_escrow',
  },
  {
    id: 'b4',
    bookingNumber: 'BK-20261116-D44E',
    service: 'Geyser Installation & Descaling',
    customerName: 'Bilal Ahmed',
    customerPhone: '+92 333 1122334',
    address: 'Street 9, Model Town, Lahore',
    providerName: null,
    status: 'broadcasted',
    scheduledTime: '2026-11-16 17:00 - 18:15',
    agreedPrice: 2000.00,
    paymentMode: 'cash_on_delivery',
  },
  {
    id: 'b5',
    bookingNumber: 'BK-20261116-E55F',
    service: 'Inverter Diagnostic Inspection',
    customerName: 'Sarmad Malik',
    customerPhone: '+92 305 7766554',
    address: 'Sector F, Bahria Town, Lahore',
    providerName: 'Tariq Mehmood',
    status: 'quotation_submitted',
    scheduledTime: '2026-11-16 11:00 - 11:45',
    agreedPrice: 4800.00,
    paymentMode: 'online_escrow',
  },
];

export default function BookingsPage() {
  const [statusFilter, setStatusFilter] = useState('all');
  const [selectedBooking, setSelectedBooking] = useState<MockBooking | null>(null);

  const filtered = mockBookings.filter(b => statusFilter === 'all' || b.status === statusFilter);

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Live Dispatch & Booking Monitor</h1>
          <p className="text-sm text-slate-500 mt-1">Real-time status tracking across the complete 13-state marketplace lifecycle.</p>
        </div>

        {/* Filter select */}
        <div className="flex items-center space-x-3">
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="text-xs font-semibold bg-white border border-slate-300 rounded-lg px-3 py-2 text-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
          >
            <option value="all">All States (13 States)</option>
            <option value="broadcasted">Broadcasted</option>
            <option value="confirmed">Confirmed</option>
            <option value="provider_on_the_way">Provider En Route</option>
            <option value="in_progress">In Progress</option>
            <option value="quotation_submitted">Quotation Submitted</option>
            <option value="completed">Completed</option>
            <option value="disputed">Disputed</option>
          </select>
        </div>
      </div>

      {/* Bookings Table */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50/75 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                <th className="py-3 px-6">Booking Number</th>
                <th className="py-3 px-6">Service</th>
                <th className="py-3 px-6">Customer & Location</th>
                <th className="py-3 px-6">Assigned Technician</th>
                <th className="py-3 px-6">Schedule Window</th>
                <th className="py-3 px-6">Status</th>
                <th className="py-3 px-6 text-right">Agreed Price</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {filtered.map((b) => (
                <tr key={b.id} className="hover:bg-slate-50/80 transition-colors">
                  <td className="py-3.5 px-6 font-mono font-bold text-slate-900">{b.bookingNumber}</td>
                  <td className="py-3.5 px-6 font-medium text-slate-800">{b.service}</td>
                  <td className="py-3.5 px-6">
                    <div className="font-semibold text-slate-900">{b.customerName}</div>
                    <div className="text-xs text-slate-500 flex items-center mt-0.5">
                      <MapPin className="w-3 h-3 mr-1 text-slate-400" />
                      {b.address}
                    </div>
                  </td>
                  <td className="py-3.5 px-6">
                    {b.providerName ? (
                      <span className="font-semibold text-slate-900">{b.providerName}</span>
                    ) : (
                      <span className="text-xs font-semibold text-purple-700 bg-purple-100 px-2 py-0.5 rounded-full">
                        Broadcasting to Nearby
                      </span>
                    )}
                  </td>
                  <td className="py-3.5 px-6 text-xs text-slate-600">{b.scheduledTime}</td>
                  <td className="py-3.5 px-6">
                    <span className="inline-block px-2.5 py-1 rounded-full text-xs font-semibold border bg-slate-100 text-slate-800 uppercase tracking-wider">
                      {b.status.replace(/_/g, ' ')}
                    </span>
                  </td>
                  <td className="py-3.5 px-6 text-right font-mono font-bold text-slate-900">
                    PKR {b.agreedPrice.toFixed(2)}
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
