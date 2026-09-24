<div style="display: grid; gap: 1.5rem;">
    <livewire:customer-referral-summary :customerId="$customerId" lazy />
    <livewire:customer-referrals-table :customerId="$customerId" lazy />
    <livewire:customer-referral-bonuses-table :customerId="$customerId" lazy />
    <livewire:customer-referral-withdrawals-table :customerId="$customerId" lazy />
</div>
