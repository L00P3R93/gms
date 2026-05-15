<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; margin: 40px; }
        h1 { color: #1e40af; font-size: 20px; margin-bottom: 4px; }
        h2 { color: #374151; font-size: 13px; margin: 16px 0 6px; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; }
        .label { color: #6b7280; width: 50%; }
        .header { display: flex; justify-content: space-between; margin-bottom: 24px; }
        .logo { font-size: 22px; font-weight: bold; color: #1e40af; }
        .meta { color: #6b7280; font-size: 11px; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">Kadi Kings GMS</div>
        <div class="meta">Generated: {{ now()->format('d M Y H:i') }}</div>
    </div>

    <h1>Player Payslip</h1>

    <h2>Player Information</h2>
    <table>
        <tr><td class="label">Name</td><td>{{ $account->name }}</td></tr>
        <tr><td class="label">Phone</td><td>{{ $account->phone }}</td></tr>
        <tr><td class="label">Email</td><td>{{ $account->email }}</td></tr>
        <tr><td class="label">Game Credits</td><td>{{ number_format($account->credit) }}</td></tr>
    </table>

    <h2>Financial Summary</h2>
    <table>
        <tr><td class="label">Total Deposits</td><td>KES {{ number_format($totalDeposits, 2) }}</td></tr>
        <tr><td class="label">Total Withdrawals</td><td>KES {{ number_format($totalWithdrawals, 2) }}</td></tr>
        <tr><td class="label">Total Purchases</td><td>KES {{ number_format($totalPurchases, 2) }}</td></tr>
    </table>

    <h2>Game Statistics</h2>
    <table>
        <tr><td class="label">Games Played</td><td>{{ number_format($gamesPlayed) }}</td></tr>
        <tr><td class="label">Games Won</td><td>{{ number_format($gamesWon) }}</td></tr>
        <tr><td class="label">Win Rate</td><td>{{ $winRate }}%</td></tr>
    </table>
</body>
</html>
