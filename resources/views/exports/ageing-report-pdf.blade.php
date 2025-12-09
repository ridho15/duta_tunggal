<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $reportTitle }}</title>
    <style>
        @page {
            margin: 0.5in;
            size: A4 landscape;
        }

        body {
            font-family: 'DejaVu Sans', 'Arial', sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .header {
            background: linear-gradient(135deg, #2E75B6 0%, #1E4D7B 100%);
            color: white;
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }

        .header .subtitle {
            margin: 5px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .report-info {
            display: table;
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }

        .report-info .info-row {
            display: table-row;
        }

        .report-info .info-cell {
            display: table-cell;
            padding: 8px 12px;
            border: 1px solid #ddd;
            background: #f8f9fa;
        }

        .report-info .info-label {
            font-weight: bold;
            background: #e9ecef;
            width: 150px;
        }

        .summary-section {
            margin-bottom: 25px;
        }

        .summary-section h3 {
            background: #4472C4;
            color: white;
            padding: 10px;
            margin: 0 0 15px 0;
            border-radius: 5px;
            font-size: 14px;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 9px;
        }

        .summary-table th,
        .summary-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }

        .summary-table th {
            background: #f8f9fa;
            font-weight: bold;
        }

        .summary-table .total-row {
            background: #fff3cd;
            font-weight: bold;
        }

        .data-section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }

        .data-section h3 {
            background: #28a745;
            color: white;
            padding: 10px;
            margin: 0 0 15px 0;
            border-radius: 5px;
            font-size: 14px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 8px;
        }

        .data-table th,
        .data-table td {
            border: 1px solid #ddd;
            padding: 6px;
            vertical-align: top;
        }

        .data-table th {
            background: #f8f9fa;
            font-weight: bold;
            text-align: center;
            white-space: nowrap;
        }

        .data-table td {
            word-wrap: break-word;
        }

        .data-table .no-column {
            width: 40px;
            text-align: center;
        }

        .data-table .amount-column {
            text-align: right;
            font-family: 'Courier New', monospace;
        }

        .data-table .center-column {
            text-align: center;
        }

        .data-table tbody tr:nth-child(even) {
            background: #f8f9fa;
        }

        .data-table tbody tr:hover {
            background: #e3f2fd;
        }

        .aging-current { background: #d4edda; }
        .aging-31-60 { background: #fff3cd; }
        .aging-61-90 { background: #ffeaa7; }
        .aging-overdue { background: #f8d7da; }

        .cash-flow-section {
            margin-bottom: 25px;
        }

        .cash-flow-section h3 {
            background: #dc3545;
            color: white;
            padding: 10px;
            margin: 0 0 15px 0;
            border-radius: 5px;
            font-size: 14px;
        }

        .cash-flow-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        .cash-flow-table th,
        .cash-flow-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }

        .cash-flow-table th {
            background: #f8f9fa;
            font-weight: bold;
        }

        .cash-flow-table .positive {
            color: #28a745;
            font-weight: bold;
        }

        .cash-flow-table .negative {
            color: #dc3545;
            font-weight: bold;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #dee2e6;
            text-align: center;
            font-size: 9px;
            color: #6c757d;
        }

        .page-break {
            page-break-before: always;
        }

        .no-data {
            text-align: center;
            padding: 20px;
            color: #6c757d;
            font-style: italic;
        }

        @media print {
            .data-table {
                font-size: 7px;
            }

            .data-table th,
            .data-table td {
                padding: 4px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>{{ $companyName }}</h1>
        <div class="subtitle">{{ $reportTitle }}</div>
    </div>

    <!-- Report Information -->
    <table class="report-info">
        <tr class="info-row">
            <td class="info-cell info-label">Report Date:</td>
            <td class="info-cell">{{ $asOfDate }}</td>
            <td class="info-cell info-label">Branch:</td>
            <td class="info-cell">{{ $cabangName }}</td>
        </tr>
        <tr class="info-row">
            <td class="info-cell info-label">Generated At:</td>
            <td class="info-cell">{{ $generatedAt }}</td>
            <td class="info-cell info-label">Report Type:</td>
            <td class="info-cell">{{ ucfirst($type ?? 'both') }}</td>
        </tr>
    </table>

    <!-- Summary Section -->
    <div class="summary-section">
        <h3>📊 Ageing Summary</h3>

        @if(($type === 'receivables' || $type === 'both') && count($summary['receivables']['total']) > 0)
        <h4 style="color: #2E75B6; margin: 15px 0 10px 0;">Account Receivables Summary</h4>
        <table class="summary-table">
            <thead>
                <tr>
                    <th>Ageing Bucket</th>
                    <th>Count</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Current (0-30 days)</td>
                    <td>{{ number_format($summary['receivables']['Current']['count']) }}</td>
                    <td>Rp {{ number_format($summary['receivables']['Current']['amount'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>31-60 days</td>
                    <td>{{ number_format($summary['receivables']['31–60']['count']) }}</td>
                    <td>Rp {{ number_format($summary['receivables']['31–60']['amount'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>61-90 days</td>
                    <td>{{ number_format($summary['receivables']['61–90']['count']) }}</td>
                    <td>Rp {{ number_format($summary['receivables']['61–90']['amount'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>>90 days</td>
                    <td>{{ number_format($summary['receivables']['>90']['count']) }}</td>
                    <td>Rp {{ number_format($summary['receivables']['>90']['amount'], 0, ',', '.') }}</td>
                </tr>
                <tr class="total-row">
                    <td><strong>TOTAL</strong></td>
                    <td><strong>{{ number_format($summary['receivables']['total']['count']) }}</strong></td>
                    <td><strong>Rp {{ number_format($summary['receivables']['total']['amount'], 0, ',', '.') }}</strong></td>
                </tr>
            </tbody>
        </table>
        @endif

        @if(($type === 'payables' || $type === 'both') && count($summary['payables']['total']) > 0)
        <h4 style="color: #C00000; margin: 15px 0 10px 0;">Account Payables Summary</h4>
        <table class="summary-table">
            <thead>
                <tr>
                    <th>Ageing Bucket</th>
                    <th>Count</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Current (0-30 days)</td>
                    <td>{{ number_format($summary['payables']['Current']['count']) }}</td>
                    <td>Rp {{ number_format($summary['payables']['Current']['amount'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>31-60 days</td>
                    <td>{{ number_format($summary['payables']['31–60']['count']) }}</td>
                    <td>Rp {{ number_format($summary['payables']['31–60']['amount'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>61-90 days</td>
                    <td>{{ number_format($summary['payables']['61–90']['count']) }}</td>
                    <td>Rp {{ number_format($summary['payables']['61–90']['amount'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>>90 days</td>
                    <td>{{ number_format($summary['payables']['>90']['count']) }}</td>
                    <td>Rp {{ number_format($summary['payables']['>90']['amount'], 0, ',', '.') }}</td>
                </tr>
                <tr class="total-row">
                    <td><strong>TOTAL</strong></td>
                    <td><strong>{{ number_format($summary['payables']['total']['count']) }}</strong></td>
                    <td><strong>Rp {{ number_format($summary['payables']['total']['amount'], 0, ',', '.') }}</strong></td>
                </tr>
            </tbody>
        </table>
        @endif
    </div>

    <!-- Cash Flow Projection -->
    <div class="cash-flow-section">
        <h3>💰 Cash Flow Projection</h3>
        <table class="cash-flow-table">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Expected Collections (AR)</th>
                    <th>Expected Payments (AP)</th>
                    <th>Net Cash Flow</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cashFlowProjection as $days => $projection)
                <tr>
                    <td>{{ $days }} Days</td>
                    <td>Rp {{ number_format($projection['receivables'], 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($projection['payables'], 0, ',', '.') }}</td>
                    <td class="{{ $projection['net_cash_flow'] >= 0 ? 'positive' : 'negative' }}">
                        Rp {{ number_format(abs($projection['net_cash_flow']), 0, ',', '.') }}
                        {{ $projection['net_cash_flow'] >= 0 ? '(Surplus)' : '(Deficit)' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Receivables Data -->
    @if(($type === 'receivables' || $type === 'both') && count($receivables) > 0)
    <div class="data-section">
        <h3>📈 Account Receivables Details</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="no-column">No</th>
                    <th>Customer</th>
                    <th>Contact</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Invoice</th>
                    <th>Inv Date</th>
                    <th>Due Date</th>
                    <th>Terms</th>
                    <th class="center-column">Days Out</th>
                    <th class="amount-column">Total</th>
                    <th class="amount-column">Paid</th>
                    <th class="amount-column">Remaining</th>
                    <th class="center-column">Bucket</th>
                    <th class="center-column">Status</th>
                    <th>Branch</th>
                    <th>Sales Person</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($receivables as $receivable)
                <tr class="aging-{{ strtolower(str_replace([' ', '>'], ['', 'overdue'], $receivable['aging_bucket'])) }}">
                    <td class="center-column">{{ $receivable['no'] }}</td>
                    <td>{{ $receivable['customer_name'] }}</td>
                    <td>{{ $receivable['contact_person'] }}</td>
                    <td>{{ $receivable['phone'] }}</td>
                    <td>{{ $receivable['email'] }}</td>
                    <td>{{ $receivable['invoice_number'] }}</td>
                    <td class="center-column">{{ $receivable['invoice_date'] }}</td>
                    <td class="center-column">{{ $receivable['due_date'] }}</td>
                    <td>{{ $receivable['payment_terms'] }}</td>
                    <td class="center-column">{{ $receivable['days_outstanding'] }}</td>
                    <td class="amount-column">Rp {{ number_format($receivable['total_amount'], 0, ',', '.') }}</td>
                    <td class="amount-column">Rp {{ number_format($receivable['paid_amount'], 0, ',', '.') }}</td>
                    <td class="amount-column">Rp {{ number_format($receivable['remaining_amount'], 0, ',', '.') }}</td>
                    <td class="center-column">{{ $receivable['aging_bucket'] }}</td>
                    <td class="center-column">{{ $receivable['status'] }}</td>
                    <td>{{ $receivable['branch'] }}</td>
                    <td>{{ $receivable['sales_person'] }}</td>
                    <td>{{ $receivable['notes'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @elseif($type === 'receivables')
    <div class="no-data">No receivables data found for the selected criteria.</div>
    @endif

    <!-- Payables Data -->
    @if(($type === 'payables' || $type === 'both') && count($payables) > 0)
    <div class="data-section {{ ($type === 'both' && count($receivables) > 0) ? 'page-break' : '' }}">
        <h3>📉 Account Payables Details</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="no-column">No</th>
                    <th>Supplier</th>
                    <th>Contact</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Invoice</th>
                    <th>Inv Date</th>
                    <th>Due Date</th>
                    <th>Terms</th>
                    <th class="center-column">Days Out</th>
                    <th class="amount-column">Total</th>
                    <th class="amount-column">Paid</th>
                    <th class="amount-column">Remaining</th>
                    <th class="center-column">Bucket</th>
                    <th class="center-column">Status</th>
                    <th>Purchase Type</th>
                    <th>Procurement</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payables as $payable)
                <tr class="aging-{{ strtolower(str_replace([' ', '>'], ['', 'overdue'], $payable['aging_bucket'])) }}">
                    <td class="center-column">{{ $payable['no'] }}</td>
                    <td>{{ $payable['supplier_name'] }}</td>
                    <td>{{ $payable['contact_person'] }}</td>
                    <td>{{ $payable['phone'] }}</td>
                    <td>{{ $payable['email'] }}</td>
                    <td>{{ $payable['invoice_number'] }}</td>
                    <td class="center-column">{{ $payable['invoice_date'] }}</td>
                    <td class="center-column">{{ $payable['due_date'] }}</td>
                    <td>{{ $payable['payment_terms'] }}</td>
                    <td class="center-column">{{ $payable['days_outstanding'] }}</td>
                    <td class="amount-column">Rp {{ number_format($payable['total_amount'], 0, ',', '.') }}</td>
                    <td class="amount-column">Rp {{ number_format($payable['paid_amount'], 0, ',', '.') }}</td>
                    <td class="amount-column">Rp {{ number_format($payable['remaining_amount'], 0, ',', '.') }}</td>
                    <td class="center-column">{{ $payable['aging_bucket'] }}</td>
                    <td class="center-column">{{ $payable['status'] }}</td>
                    <td>{{ $payable['purchase_type'] }}</td>
                    <td>{{ $payable['procurement_person'] }}</td>
                    <td>{{ $payable['notes'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @elseif($type === 'payables')
    <div class="no-data">No payables data found for the selected criteria.</div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>This report was generated on {{ $generatedAt }} | {{ $companyName }} - Ageing Report</p>
        <p>Confidential - For Internal Use Only</p>
    </div>
</body>
</html>