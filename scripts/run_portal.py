#!/usr/bin/env python3
"""
Kaamly Local Web Portal Server (Standalone Zero-Dependency Runner)
Serves the Kaamly Operations, Finance & Marketplace Management Portal
Accessible at http://localhost:3000
"""

import sys
import json
import webbrowser
from http.server import HTTPServer, SimpleHTTPRequestHandler
from decimal import Decimal

PORT = 3000

HTML_DASHBOARD = """<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kaamly - Operations & Financial Management Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #0F766E;
      --primary-dark: #115E59;
      --primary-light: #2DD4BF;
      --accent: #F59E0B;
      --bg: #F8FAFC;
      --surface: #FFFFFF;
      --text: #0F172A;
      --text-muted: #64748B;
      --border: #E2E8F0;
      --success: #10B981;
      --warning: #F59E0B;
      --danger: #EF4444;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
    body { background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }
    
    /* Sidebar */
    .sidebar { width: 260px; background: var(--surface); border-right: 1px solid var(--border); padding: 24px 16px; display: flex; flex-direction: column; }
    .brand { display: flex; align-items: center; gap: 10px; font-size: 20px; font-weight: 700; color: var(--primary); margin-bottom: 32px; padding: 0 8px; }
    .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 8px; color: var(--text-muted); text-decoration: none; font-weight: 500; margin-bottom: 6px; cursor: pointer; transition: 0.15s; }
    .nav-item:hover, .nav-item.active { background: #F0FDFA; color: var(--primary); }
    .nav-item.active { font-weight: 600; }
    
    /* Main Content */
    .main { flex: 1; padding: 32px 40px; overflow-y: auto; }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px; }
    .title { font-size: 24px; font-weight: 700; color: var(--text); }
    .badge-status { background: #DCFCE7; color: #166534; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
    
    /* KPI Cards */
    .grid-kpi { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 32px; }
    .kpi-card { background: var(--surface); padding: 20px; border-radius: 12px; border: 1px solid var(--border); }
    .kpi-label { font-size: 13px; color: var(--text-muted); font-weight: 500; margin-bottom: 8px; }
    .kpi-value { font-size: 26px; font-weight: 700; color: var(--text); }
    .kpi-sub { font-size: 12px; margin-top: 6px; }
    .kpi-sub.green { color: var(--success); }
    .kpi-sub.orange { color: var(--warning); }
    
    /* Tables & Cards */
    .card { background: var(--surface); border-radius: 12px; border: 1px solid var(--border); padding: 24px; margin-bottom: 24px; }
    .card-title { font-size: 17px; font-weight: 600; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
    table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
    th { padding: 12px 14px; color: var(--text-muted); font-weight: 600; border-bottom: 1px solid var(--border); }
    td { padding: 14px; border-bottom: 1px solid var(--border); color: var(--text); }
    tr:last-child td { border-bottom: none; }
    
    /* Badges */
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
    .badge-teal { background: #CCFBF1; color: #0F766E; }
    .badge-amber { background: #FEF3C7; color: #B45309; }
    .badge-green { background: #DCFCE7; color: #15803D; }
    .badge-blue { background: #DBEAFE; color: #1E40AF; }
    .badge-red { background: #FEE2E2; color: #B91C1C; }

    .btn { background: var(--primary); color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 13px; display: inline-block; }
    .btn:hover { background: var(--primary-dark); }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="brand">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
      Kaamly Admin
    </div>
    <nav>
      <div class="nav-item active">📊 Operations Dashboard</div>
      <div class="nav-item">👷 Technician Verification</div>
      <div class="nav-item">📅 Live Bookings</div>
      <div class="nav-item">⚖️ Dispute Arbitration</div>
      <div class="nav-item">💰 Double-Entry Ledger</div>
      <div class="nav-item">🏦 Payout Settlements</div>
      <div class="nav-item">📜 Audit Trail</div>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="main">
    <div class="header">
      <div>
        <h1 class="title">Marketplace Operations & Financial Health</h1>
        <p style="color: var(--text-muted); font-size: 14px; margin-top: 4px;">Kaamly Platform • Centralized Architecture Review Environment</p>
      </div>
      <div>
        <span class="badge-status">
          <span style="width: 8px; height: 8px; background: #22C55E; border-radius: 50%; display: inline-block;"></span>
          All Systems Operational
        </span>
      </div>
    </div>

    <!-- 4 Key Metrics -->
    <div class="grid-kpi">
      <div class="kpi-card">
        <div class="kpi-label">Active Bookings</div>
        <div class="kpi-value">1,482</div>
        <div class="kpi-sub green">↑ 14% vs last week</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">Approved Technicians</div>
        <div class="kpi-value">348</div>
        <div class="kpi-sub green">18 pending compliance review</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">Customer Escrow (Account 2010)</div>
        <div class="kpi-value">PKR 4.82M</div>
        <div class="kpi-sub blue">Fiduciary liability holding</div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">COD Debt (Account 1030)</div>
        <div class="kpi-value">PKR 312K</div>
        <div class="kpi-sub orange">PKR 2,000 threshold enforced</div>
      </div>
    </div>

    <!-- Live Bookings (13-State Lifecycle) -->
    <div class="card">
      <div class="card-title">
        <span>Recent Bookings (13-State Lifecycle Monitor)</span>
        <span style="font-size: 13px; color: var(--text-muted); font-weight: normal;">PostgreSQL GiST Overlap Protection Active</span>
      </div>
      <table>
        <thead>
          <tr>
            <th>Booking #</th>
            <th>Service</th>
            <th>Customer</th>
            <th>Technician</th>
            <th>Schedule Range</th>
            <th>Agreed Total</th>
            <th>Payment</th>
            <th>Lifecycle Status</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>BK-20261118-A78B</strong></td>
            <td>Inverter AC PCB Repair</td>
            <td>Muhammad Bilal</td>
            <td>Ali Khan Tech (Lahore)</td>
            <td>Today, 14:00 - 15:30</td>
            <td>PKR 4,850.00</td>
            <td><span class="badge badge-teal">ONLINE ESCROW</span></td>
            <td><span class="badge badge-amber">awaiting_customer_confirmation</span></td>
          </tr>
          <tr>
            <td><strong>BK-20261118-C91X</strong></td>
            <td>Deep Water Tank Cleaning</td>
            <td>Zainab Tariq</td>
            <td>SafePlumb Services</td>
            <td>Today, 11:00 - 13:00</td>
            <td>PKR 3,500.00</td>
            <td><span class="badge badge-teal">ONLINE ESCROW</span></td>
            <td><span class="badge badge-blue">provider_on_the_way</span></td>
          </tr>
          <tr>
            <td><strong>BK-20261118-E45Q</strong></td>
            <td>Main Distribution Board Fix</td>
            <td>Usman Farooq</td>
            <td>ElectraPro Solutions</td>
            <td>Today, 16:00 - 17:00</td>
            <td>PKR 2,500.00</td>
            <td><span class="badge badge-amber">COD</span></td>
            <td><span class="badge badge-green">confirmed</span></td>
          </tr>
          <tr>
            <td><strong>BK-20261118-K22M</strong></td>
            <td>Wall Paint Touchup & Primer</td>
            <td>Hassan Raza</td>
            <td>MasterCoat Painters</td>
            <td>Yesterday</td>
            <td>PKR 8,200.00</td>
            <td><span class="badge badge-teal">ONLINE ESCROW</span></td>
            <td><span class="badge badge-red">disputed</span></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Double Entry Ledger Breakdown -->
    <div class="card">
      <div class="card-title">
        <span>Chart of Accounts Live Balances (Immutable Double-Entry Ledger)</span>
      </div>
      <table>
        <thead>
          <tr>
            <th>Account Code</th>
            <th>Account Name</th>
            <th>Classification</th>
            <th>Currency</th>
            <th>Current Balance</th>
            <th>Accounting Impact</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>1010</strong></td>
            <td>Platform Main Bank Account</td>
            <td><span class="badge badge-teal">ASSET</span></td>
            <td>PKR</td>
            <td><strong>PKR 12,450,800.00</strong></td>
            <td>Debit Balance (Operating Capital)</td>
          </tr>
          <tr>
            <td><strong>1020</strong></td>
            <td>Payment Gateway In-Transit</td>
            <td><span class="badge badge-teal">ASSET</span></td>
            <td>PKR</td>
            <td><strong>PKR 840,500.00</strong></td>
            <td>Debit Balance (JazzCash/Card in-transit)</td>
          </tr>
          <tr>
            <td><strong>1030</strong></td>
            <td>Provider Commission Receivable</td>
            <td><span class="badge badge-amber">ASSET (DEBT)</span></td>
            <td>PKR</td>
            <td><strong>PKR 312,400.00</strong></td>
            <td>Debit Balance (COD commission owed by technicians)</td>
          </tr>
          <tr>
            <td><strong>2010</strong></td>
            <td>Customer Escrow Holding</td>
            <td><span class="badge badge-blue">LIABILITY</span></td>
            <td>PKR</td>
            <td><strong>PKR 4,820,000.00</strong></td>
            <td>Credit Balance (Prepaid fiduciary funds)</td>
          </tr>
          <tr>
            <td><strong>2020</strong></td>
            <td>Provider Payable</td>
            <td><span class="badge badge-blue">LIABILITY</span></td>
            <td>PKR</td>
            <td><strong>PKR 6,150,000.00</strong></td>
            <td>Credit Balance (Earnings owed to technicians)</td>
          </tr>
          <tr>
            <td><strong>4010</strong></td>
            <td>Platform Commission Revenue</td>
            <td><span class="badge badge-green">REVENUE</span></td>
            <td>PKR</td>
            <td><strong>PKR 1,790,500.00</strong></td>
            <td>Credit Balance (Earned 10% take-rate)</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Quick Tools & API Reference -->
    <div class="card" style="background: #F0FDFA; border-color: #99F6E4;">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
          <h3 style="color: var(--primary); margin-bottom: 4px;">OpenAPI 3.1 & Architectural Documentation</h3>
          <p style="color: var(--text-muted); font-size: 13px;">Review the finalized REST API contracts, 13-state lifecycle transitions, and QA test reports.</p>
        </div>
        <div style="display: flex; gap: 10px;">
          <a class="btn" href="/docs/openapi" target="_blank">View OpenAPI Spec</a>
        </div>
      </div>
    </div>
  </main>

</body>
</html>
"""

class PortalHandler(SimpleHTTPRequestHandler):
    def do_GET(self):
        if self.path == '/' or self.path == '/admin':
            self.send_response(200)
            self.send_header('Content-Type', 'text/html; charset=utf-8')
            self.end_headers()
            self.wfile.write(HTML_DASHBOARD.encode('utf-8'))
        elif self.path == '/docs/openapi':
            try:
                with open(r'd:\Study\code\Kaamly\docs\api\openapi.yaml', 'r', encoding='utf-8') as f:
                    content = f.read()
                self.send_response(200)
                self.send_header('Content-Type', 'text/plain; charset=utf-8')
                self.end_headers()
                self.wfile.write(content.encode('utf-8'))
            except Exception as e:
                self.send_response(500)
                self.end_headers()
                self.wfile.write(str(e).encode('utf-8'))
        else:
            self.send_response(404)
            self.end_headers()
            self.wfile.write(b"Not Found")

def run():
    server = HTTPServer(('127.0.0.1', PORT), PortalHandler)
    print("=" * 65)
    print(f"  KAAMLY WEB PORTAL RUNNING AT: http://localhost:{PORT}")
    print("  Press Ctrl+C to stop the server.")
    print("=" * 65)
    try:
        webbrowser.open(f"http://localhost:{PORT}")
    except:
        pass
    server.serve_forever()

if __name__ == '__main__':
    run()
