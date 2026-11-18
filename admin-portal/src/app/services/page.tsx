'use client';

import React, { useState } from 'react';
import { 
  Layers, 
  Tag, 
  Clock, 
  CheckCircle2, 
  AlertCircle, 
  PlusCircle, 
  Search 
} from 'lucide-react';

interface MockService {
  id: string;
  category: string;
  name: string;
  pricingModel: 'fixed' | 'hourly' | 'inspection_quote';
  basePrice: number;
  minPrice: number;
  maxPrice: number;
  duration: number;
  status: 'active' | 'inactive';
}

const mockServices: MockService[] = [
  {
    id: 's1',
    category: 'Air Conditioning & HVAC',
    name: 'AC Master Service & Chemical Wash',
    pricingModel: 'fixed',
    basePrice: 2500.00,
    minPrice: 2000.00,
    maxPrice: 3500.00,
    duration: 60,
    status: 'active',
  },
  {
    id: 's2',
    category: 'Air Conditioning & HVAC',
    name: 'AC Gas Refill (R410A / R32 / R22)',
    pricingModel: 'fixed',
    basePrice: 6500.00,
    minPrice: 5000.00,
    maxPrice: 9000.00,
    duration: 90,
    status: 'active',
  },
  {
    id: 's3',
    category: 'Air Conditioning & HVAC',
    name: 'Inverter AC Diagnostic & Circuit Inspection',
    pricingModel: 'inspection_quote',
    basePrice: 1000.00,
    minPrice: 1000.00,
    maxPrice: 50000.00,
    duration: 45,
    status: 'active',
  },
  {
    id: 's4',
    category: 'Electrical Services',
    name: 'Ceiling Fan / Chandelier Installation',
    pricingModel: 'fixed',
    basePrice: 800.00,
    minPrice: 600.00,
    maxPrice: 2500.00,
    duration: 45,
    status: 'active',
  },
  {
    id: 's5',
    category: 'Plumbing & Water Systems',
    name: 'Water Geyser Installation & Servicing',
    pricingModel: 'fixed',
    basePrice: 2000.00,
    minPrice: 1500.00,
    maxPrice: 4500.00,
    duration: 75,
    status: 'active',
  },
  {
    id: 's6',
    category: 'Deep Cleaning & Sanitization',
    name: 'Full Apartment Deep Cleaning (2-3 Bed)',
    pricingModel: 'fixed',
    basePrice: 9500.00,
    minPrice: 8000.00,
    maxPrice: 16000.00,
    duration: 240,
    status: 'active',
  },
];

export default function ServicesPage() {
  const [searchTerm, setSearchTerm] = useState('');

  const filtered = mockServices.filter(s => 
    s.name.toLowerCase().includes(searchTerm.toLowerCase()) || 
    s.category.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div className="space-y-6">
      {/* Title */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Service Catalog & Rate Limits</h1>
          <p className="text-sm text-slate-500 mt-1">Configure service categories, pricing models, and allowable custom price envelopes.</p>
        </div>

        <div className="relative">
          <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
          <input
            type="text"
            placeholder="Search catalog services..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="pl-9 pr-4 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
          />
        </div>
      </div>

      {/* Services Table */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50/75 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                <th className="py-3 px-6">Service Name</th>
                <th className="py-3 px-6">Category</th>
                <th className="py-3 px-6">Pricing Model</th>
                <th className="py-3 px-6">Base Price</th>
                <th className="py-3 px-6">Allowed Price Envelope</th>
                <th className="py-3 px-6 text-right">Duration</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {filtered.map((s) => (
                <tr key={s.id} className="hover:bg-slate-50/80 transition-colors">
                  <td className="py-3.5 px-6 font-semibold text-slate-900">{s.name}</td>
                  <td className="py-3.5 px-6 text-slate-600">{s.category}</td>
                  <td className="py-3.5 px-6">
                    <span className="inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase bg-slate-100 text-slate-700">
                      {s.pricingModel.replace(/_/g, ' ')}
                    </span>
                  </td>
                  <td className="py-3.5 px-6 font-mono font-bold text-slate-900">
                    PKR {s.basePrice.toFixed(2)}
                  </td>
                  <td className="py-3.5 px-6 font-mono text-xs text-slate-600">
                    PKR {s.minPrice.toFixed(2)} — PKR {s.maxPrice.toFixed(2)}
                  </td>
                  <td className="py-3.5 px-6 text-right font-medium text-slate-600">
                    {s.duration} mins
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
