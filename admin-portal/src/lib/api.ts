import { ApiResponse } from '@/types';

const API_BASE = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1';

class ApiClient {
  private token: string | null = null;

  constructor() {
    if (typeof window !== 'undefined') {
      this.token = localStorage.getItem('kaamly_admin_token');
    }
  }

  setToken(token: string | null) {
    this.token = token;
    if (typeof window !== 'undefined') {
      if (token) {
        localStorage.setItem('kaamly_admin_token', token);
      } else {
        localStorage.removeItem('kaamly_admin_token');
      }
    }
  }

  getToken(): string | null {
    if (typeof window !== 'undefined' && !this.token) {
      this.token = localStorage.getItem('kaamly_admin_token');
    }
    return this.token;
  }

  async request<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(options.headers as Record<string, string> || {}),
    };

    const token = this.getToken();
    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    const res = await fetch(`${API_BASE}${endpoint}`, {
      ...options,
      headers,
    });

    const data: ApiResponse<T> = await res.json();

    if (!res.ok || !data.success) {
      const errorMsg = data.error?.message || data.message || 'API request failed';
      throw new Error(errorMsg);
    }

    return data.data;
  }

  // Auth Endpoints
  async login(login: string, password: string) {
    const res = await this.request<{ user: any; token: string }>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ login, password }),
    });
    this.setToken(res.token);
    return res;
  }

  async me() {
    return this.request<{ user: any }>('/auth/me');
  }

  async logout() {
    try {
      await this.request('/auth/logout', { method: 'POST' });
    } finally {
      this.setToken(null);
    }
  }

  // Providers & Verification
  async listProviders(status?: string) {
    const q = status ? `?status=${status}` : '';
    return this.request<any[]>(`/admin/providers${q}`);
  }

  async reviewDocument(providerId: string, docId: string, status: 'verified' | 'rejected', rejectionReason?: string) {
    return this.request(`/admin/providers/${providerId}/documents/${docId}/review`, {
      method: 'POST',
      body: JSON.stringify({ status, rejection_reason: rejectionReason }),
    });
  }

  async approveProvider(providerId: string) {
    return this.request(`/admin/providers/${providerId}/approve`, {
      method: 'POST',
    });
  }

  // Services Catalog
  async getCategories() {
    return this.request<any[]>('/catalog/categories');
  }

  async getServices() {
    return this.request<any[]>('/catalog/services');
  }

  // Bookings Monitor
  async getBookings() {
    return this.request<any>('/admin/bookings');
  }

  async getBooking(id: string) {
    return this.request<any>(`/customer/bookings/${id}`);
  }

  // Ledger & Finance
  async getChartOfAccounts() {
    return this.request<any[]>('/admin/ledger/accounts');
  }

  async getLedgerTransactions() {
    return this.request<any>('/admin/ledger/transactions');
  }

  async reverseTransaction(transactionId: string, reason: string) {
    return this.request('/admin/ledger/reversal', {
      method: 'POST',
      body: JSON.stringify({ transaction_id: transactionId, reason }),
    });
  }

  // Settlements
  async getSettlements() {
    return this.request<any>('/admin/settlements');
  }

  async createSettlementBatch(providerProfileId: string, amount: number) {
    return this.request('/admin/settlements/batch', {
      method: 'POST',
      body: JSON.stringify({ provider_profile_id: providerProfileId, amount }),
    });
  }

  async processSettlement(settlementId: string, payoutReference?: string) {
    return this.request(`/admin/settlements/${settlementId}/process`, {
      method: 'POST',
      body: JSON.stringify({ payout_reference: payoutReference }),
    });
  }

  // Disputes
  async arbitrateDispute(disputeId: string, resolution: string, refundAmount: number, payoutAmount: number, notes: string) {
    return this.request(`/admin/disputes/${disputeId}/arbitrate`, {
      method: 'POST',
      body: JSON.stringify({
        resolution,
        refund_amount: refundAmount,
        payout_amount: payoutAmount,
        notes,
      }),
    });
  }
}

export const api = new ApiClient();
