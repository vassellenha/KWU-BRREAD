@extends('layouts.staff')

@section('title', 'Accounting — Cashflow')

@section('content')

    <div class="flex items-center justify-between mb-space-lg flex-wrap gap-space-sm">
        <div>
            <h1 class="font-headline-md text-headline-md text-on-surface">Cashflow &amp; Financial Report</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1">
                Income from customer orders, expenses logged manually, and the running balance between them.
            </p>
        </div>
        <a
            href="{{ route('staff.accounting.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
            class="press-scale inline-flex items-center gap-1.5 px-space-md py-space-xs rounded-full bg-surface-container-lowest border border-outline-variant/40 font-label-lg text-label-lg text-on-surface-variant hover:text-on-surface transition-colors duration-200"
        >
            <span class="material-symbols-outlined text-[18px]">download</span>
            Export to Excel
        </a>
    </div>

    {{-- DATE RANGE FILTER --}}
    <form method="GET" action="{{ route('staff.accounting.index') }}" class="flex flex-wrap items-end gap-space-sm mb-space-lg">
        <div>
            <label class="block font-label-md text-label-md text-on-surface-variant mb-1">From</label>
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <div>
            <label class="block font-label-md text-label-md text-on-surface-variant mb-1">To</label>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
        </div>
        <button type="submit" class="press-scale px-space-md py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg hover:opacity-95 transition-opacity duration-200">
            Apply
        </button>
        <a href="{{ route('staff.accounting.index') }}" class="press-scale inline-flex items-center gap-1 px-space-md py-space-xs rounded-full bg-surface-container text-on-surface-variant font-label-md text-label-md hover:text-on-surface transition-colors duration-200">
            <span class="material-symbols-outlined text-[16px]">close</span>
            This month
        </a>
    </form>

    {{-- STAT SUMMARY --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-space-md mb-space-lg">
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-tertiary">trending_up</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Income</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface" x-data x-init="window.__brreadFx?.animateCounter($el, {{ $stats['total_income'] }}, { formatter: (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID') })">Rp 0</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-error">trending_down</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Expenses</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface" x-data x-init="window.__brreadFx?.animateCounter($el, {{ $stats['total_expenses'] }}, { formatter: (n) => 'Rp ' + Math.round(n).toLocaleString('id-ID') })">Rp 0</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border {{ $stats['net'] >= 0 ? 'border-outline-variant/30' : 'border-error/40' }} p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] {{ $stats['net'] >= 0 ? 'text-on-surface-variant' : 'text-error' }}">account_balance_wallet</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Net Profit</span>
            </div>
            <p class="font-headline-sm text-headline-sm {{ $stats['net'] >= 0 ? 'text-on-surface' : 'text-error' }}" x-data x-init="window.__brreadFx?.animateCounter($el, {{ $stats['net'] }}, { formatter: (n) => (n < 0 ? '-Rp ' : 'Rp ') + Math.round(Math.abs(n)).toLocaleString('id-ID') })">Rp 0</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">receipt_long</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Transactions</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface" x-data x-init="window.__brreadFx?.animateCounter($el, {{ $stats['transaction_count'] }}, { formatter: (n) => Math.round(n) })">0</p>
        </div>
    </div>

    {{-- CASHFLOW LEDGER --}}
    <div x-data="{ showAddForm: false }" class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-hidden">
        <div class="flex items-center justify-between p-space-md border-b border-outline-variant/30">
            <span class="font-title-md text-title-md text-on-surface">Cashflow Ledger</span>
            <button type="button" @click="showAddForm = !showAddForm" class="press-scale inline-flex items-center gap-1.5 px-space-md py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg hover:opacity-95 transition-opacity duration-200">
                <span class="material-symbols-outlined text-[18px]" x-text="showAddForm ? 'close' : 'add'"></span>
                <span x-text="showAddForm ? 'Cancel' : 'Add Expense'"></span>
            </button>
        </div>

        <div
            x-show="showAddForm" x-cloak
            x-transition:enter="transition-[opacity,transform] duration-200 ease-[var(--ease-out)]"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="p-space-md border-b border-outline-variant/30 bg-surface-container/30"
        >
            <form method="POST" action="{{ route('staff.accounting.expenses.store') }}" class="flex flex-wrap items-end gap-space-sm">
                @csrf
                <div>
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-1">Description</label>
                    <input type="text" name="description" required placeholder="e.g. Weekly flour restock" class="rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-1">Category</label>
                    <select name="category" required class="rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        @foreach ($categories as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-1">Amount (Rp)</label>
                    <input type="number" name="amount" min="1" step="1" required placeholder="850000" class="w-32 rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-1">Date</label>
                    <input type="date" name="expense_date" required value="{{ now()->toDateString() }}" class="rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <button type="submit" class="press-scale px-space-md py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg hover:opacity-95 transition-opacity duration-200">
                    Save
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left">
                <thead>
                    <tr class="border-b border-outline-variant/30 bg-surface-container/50">
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Date</th>
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Type</th>
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Description</th>
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Category</th>
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide text-right">Amount</th>
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide text-right">Balance</th>
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ledger as $entry)
                        <tr class="border-b border-outline-variant/20 last:border-0">
                            <td class="px-space-md py-space-sm font-body-sm text-body-sm text-on-surface-variant whitespace-nowrap">{{ $entry['date']->format('d M Y') }}</td>
                            <td class="px-space-md py-space-sm">
                                @if ($entry['type'] === 'in')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-tertiary-fixed text-on-tertiary-fixed-variant font-label-md text-label-md">
                                        <span class="material-symbols-outlined text-[14px]">arrow_downward</span>
                                        In
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-error-container text-on-error-container font-label-md text-label-md">
                                        <span class="material-symbols-outlined text-[14px]">arrow_upward</span>
                                        Out
                                    </span>
                                @endif
                            </td>
                            <td class="px-space-md py-space-sm font-label-lg text-label-lg text-on-surface">{{ $entry['description'] }}</td>
                            <td class="px-space-md py-space-sm font-body-sm text-body-sm text-on-surface-variant">{{ $entry['category'] }}</td>
                            <td class="px-space-md py-space-sm font-body-md text-body-md text-right {{ $entry['type'] === 'in' ? 'text-tertiary' : 'text-error' }}">
                                {{ $entry['type'] === 'in' ? '+' : '-' }}Rp {{ number_format($entry['amount'], 0, ',', '.') }}
                            </td>
                            <td class="px-space-md py-space-sm font-body-md text-body-md text-on-surface-variant text-right">Rp {{ number_format($entry['balance_after'], 0, ',', '.') }}</td>
                            <td class="px-space-md py-space-sm text-right">
                                @if ($entry['type'] === 'out' && isset($entry['expense']))
                                    <form method="POST" action="{{ route('staff.accounting.expenses.destroy', $entry['expense']) }}" onsubmit="return confirm('Remove this expense entry?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="press-scale inline-flex items-center gap-1 px-space-sm py-1 rounded-DEFAULT bg-surface-container text-on-surface-variant font-label-md text-label-md hover:bg-error-container/30 hover:text-error transition-colors duration-200">
                                            <span class="material-symbols-outlined text-[16px]">delete</span>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-space-md py-space-xl text-center">
                                <div class="flex flex-col items-center gap-space-xs">
                                    <span class="material-symbols-outlined text-[40px] text-on-surface-variant/40">receipt_long</span>
                                    <p class="font-title-md text-title-md text-on-surface-variant">No transactions in this period</p>
                                    <p class="font-body-sm text-body-sm text-on-surface-variant/70 max-w-xs">Orders placed by customers and expenses you log will show up here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
