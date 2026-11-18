export interface ApiResponse<T> {
  success: boolean;
  data: T;
  message?: string;
  error?: {
    code: string;
    message: string;
    details?: any;
  };
  meta: {
    timestamp: string;
    version: string;
    [key: string]: any;
  };
}

export interface User {
  id: string;
  phone_number: string;
  email: string | null;
  first_name: string;
  last_name: string;
  user_type: 'customer' | 'provider' | 'admin' | 'system';
  status: 'active' | 'suspended' | 'banned' | 'pending_verification';
  roles: Role[];
  created_at: string;
}

export interface Role {
  id: string;
  name: string;
  display_name: string;
  description: string;
  is_system_role: boolean;
}

export interface ProviderProfile {
  id: string;
  user_id: string;
  business_name: string | null;
  cnic_number: string | null;
  status: 'draft' | 'pending_approval' | 'approved' | 'rejected' | 'suspended';
  onboarding_step: number;
  rating_average: number;
  total_reviews: number;
  completed_jobs_count: number;
  acceptance_rate: number;
  is_online: boolean;
  is_available: boolean;
  user?: User;
  documents?: ProviderDocument[];
  bank_accounts?: ProviderBankAccount[];
  created_at: string;
}

export interface ProviderDocument {
  id: string;
  provider_profile_id: string;
  document_type: 'cnic_front' | 'cnic_back' | 'police_clearance' | 'trade_license' | 'certification';
  document_number: string | null;
  file_path_s3: string;
  file_mime_type: string;
  verification_status: 'pending' | 'verified' | 'rejected';
  rejection_reason?: string | null;
  verified_at?: string | null;
  created_at: string;
}

export interface ProviderBankAccount {
  id: string;
  provider_profile_id: string;
  bank_name: string;
  account_title: string;
  is_verified: boolean;
  is_primary: boolean;
}

export interface ServiceCategory {
  id: string;
  parent_id: string | null;
  name: string;
  slug: string;
  description: string | null;
  icon_url: string | null;
  display_order: number;
  is_active: boolean;
  children?: ServiceCategory[];
}

export interface Service {
  id: string;
  category_id: string;
  name: string;
  slug: string;
  pricing_model: 'fixed' | 'hourly' | 'inspection_quote';
  base_price: number;
  minimum_price: number;
  maximum_price: number;
  estimated_duration_minutes: number;
  is_active: boolean;
  category?: ServiceCategory;
}

export interface Booking {
  id: string;
  booking_number: string;
  customer_profile_id: string;
  provider_profile_id: string | null;
  address_id: string;
  service_id: string;
  status: 
    | 'pending'
    | 'broadcasted'
    | 'accepted'
    | 'quotation_submitted'
    | 'awaiting_customer_confirmation'
    | 'confirmed'
    | 'provider_on_the_way'
    | 'in_progress'
    | 'completed'
    | 'cancelled'
    | 'disputed'
    | 'rescheduled'
    | 'no_show';
  payment_mode: 'online_escrow' | 'cash_on_delivery' | 'wallet';
  payment_status: 'pending' | 'escrow_locked' | 'released_to_provider' | 'refunded' | 'failed';
  scheduled_start_at: string;
  scheduled_end_at: string;
  base_price: number;
  additional_charges: number;
  discount_amount: number;
  commission_rate: number;
  platform_fee: number;
  agreed_total_price: number;
  cancellation_fee: number;
  no_show_responsible_party?: 'customer' | 'provider' | null;
  service?: Service;
  customer_profile?: { user: User };
  provider_profile?: { user: User; business_name: string | null };
  items?: BookingItem[];
  quotations?: Quotation[];
  created_at: string;
}

export interface BookingItem {
  id: string;
  booking_id: string;
  description: string;
  unit_price: number;
  quantity: number;
  total_price: number;
}

export interface Quotation {
  id: string;
  booking_id: string;
  provider_profile_id: string;
  inspection_notes: string | null;
  labor_cost: number;
  material_cost: number;
  total_quote_amount: number;
  status: 'submitted' | 'accepted' | 'rejected' | 'expired';
  items?: QuotationItem[];
}

export interface QuotationItem {
  id: string;
  quotation_id: string;
  description: string;
  item_type: 'labor' | 'material' | 'equipment';
  unit_price: number;
  quantity: number;
  total_price: number;
}

export interface LedgerAccount {
  id: string;
  account_code: string;
  account_name: string;
  account_type: 'asset' | 'liability' | 'equity' | 'revenue' | 'expense';
  currency: string;
  current_balance: number;
  description: string | null;
}

export interface LedgerTransaction {
  id: string;
  transaction_number: string;
  idempotency_key: string;
  reference_type: string;
  reference_id: string | null;
  description: string;
  status: 'draft' | 'posted' | 'reversed';
  posted_at: string;
  entries: LedgerEntry[];
}

export interface LedgerEntry {
  id: string;
  ledger_transaction_id: string;
  ledger_account_id: string;
  direction: 'debit' | 'credit';
  amount: number;
  currency: string;
  description: string | null;
  account?: LedgerAccount;
}

export interface Settlement {
  id: string;
  settlement_number: string;
  provider_profile_id: string;
  amount: number;
  currency: string;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  payout_reference?: string | null;
  processed_at?: string | null;
  provider_profile?: ProviderProfile;
  bank_account?: ProviderBankAccount;
  created_at: string;
}

export interface Dispute {
  id: string;
  booking_id: string;
  raised_by_user_id: string;
  dispute_reason: string;
  dispute_status: 'opened' | 'under_review' | 'resolved_refund' | 'resolved_payout' | 'resolved_split' | 'rejected';
  customer_claim_amount: number;
  provider_counter_claim_amount: number;
  resolved_refund_amount: number;
  resolved_payout_amount: number;
  arbitration_notes?: string | null;
  resolved_at?: string | null;
  booking?: Booking;
  raised_by?: User;
  created_at: string;
}

export interface AuditLog {
  id: string;
  user_id: string | null;
  action: string;
  entity_type: string;
  entity_id: string | null;
  old_values: any;
  new_values: any;
  ip_address: string | null;
  user_agent: string | null;
  created_at: string;
  user?: User;
}
