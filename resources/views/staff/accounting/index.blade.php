@extends('layouts.staff')

@section('title', 'Accounting — Cashflow')

@section('content')

    @php
        $rupiah = fn (float $amount): string => ($amount < 0 ? '-' : '').'Rp '.number_format(abs($amount), 0, ',', '.');
        $fieldClasses = 'rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-primary';
        $isRestoredForm = old('_form') === 'expense';
    @endphp

    <div class="flex items-center justify-between mb-space-lg flex-wrap gap-space-sm">
        <div>
            <h1 class="font-headline-md text-headline-md text-on-surface">Cashflow &amp; Financial Report</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1">
                Income from customer orders, expenses logged by staff, and the running balance between them.
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
            <label for="from" class="block font-label-md text-label-md text-on-surface-variant mb-1">From</label>
            <input id="from" type="date" name="from" value="{{ $from->toDateString() }}" class="{{ $fieldClasses }}">
        </div>
        <div>
            <label for="to" class="block font-label-md text-label-md text-on-surface-variant mb-1">To</label>
            <input id="to" type="date" name="to" value="{{ $to->toDateString() }}" class="{{ $fieldClasses }}">
        </div>
        <button type="submit" class="press-scale px-space-md py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg hover:opacity-95 transition-opacity duration-200">
            Apply
        </button>
        <a href="{{ route('staff.accounting.index') }}" class="press-scale inline-flex items-center gap-1 px-space-md py-space-xs rounded-full bg-surface-container text-on-surface-variant font-label-md text-label-md hover:text-on-surface transition-colors duration-200">
            <span class="material-symbols-outlined text-[16px]">calendar_month</span>
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
            <p class="font-headline-sm text-headline-sm text-on-surface tabular-nums">{{ $rupiah($stats['total_income']) }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-error">trending_down</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Expenses</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface tabular-nums">{{ $rupiah($stats['total_expenses']) }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border {{ $stats['net'] >= 0 ? 'border-outline-variant/30' : 'border-error/40' }} p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] {{ $stats['net'] >= 0 ? 'text-on-surface-variant' : 'text-error' }}">account_balance_wallet</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">{{ $stats['net'] >= 0 ? 'Net Profit' : 'Net Loss' }}</span>
            </div>
            <p class="font-headline-sm text-headline-sm tabular-nums {{ $stats['net'] >= 0 ? 'text-on-surface' : 'text-error' }}">{{ $rupiah($stats['net']) }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">receipt_long</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Transactions</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface tabular-nums">{{ $stats['transaction_count'] }}</p>
        </div>
    </div>

    {{-- FINANCIAL REPORT --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-space-md mb-space-lg">
        <section class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <h2 class="font-title-md text-title-md text-on-surface mb-space-sm">Financial Summary</h2>
            <dl class="font-body-md text-body-md">
                <div class="flex justify-between gap-space-md py-1.5">
                    <dt class="text-on-surface-variant">Opening balance ({{ $from->format('d M') }})</dt>
                    <dd class="text-on-surface tabular-nums">{{ $rupiah($stats['opening_balance']) }}</dd>
                </div>
                <div class="flex justify-between gap-space-md py-1.5">
                    <dt class="text-on-surface-variant">Income from {{ $stats['order_count'] }} {{ Str::plural('order', $stats['order_count']) }}</dt>
                    <dd class="text-tertiary tabular-nums">+{{ $rupiah($stats['total_income']) }}</dd>
                </div>
                <div class="flex justify-between gap-space-md py-1.5">
                    <dt class="text-on-surface-variant">Expenses</dt>
                    <dd class="text-error tabular-nums">-{{ $rupiah($stats['total_expenses']) }}</dd>
                </div>
                <div class="flex justify-between gap-space-md pt-space-xs mt-1.5 border-t border-outline-variant/30">
                    <dt class="font-label-lg text-label-lg text-on-surface">Closing balance ({{ $to->format('d M') }})</dt>
                    <dd class="font-label-lg text-label-lg tabular-nums {{ $stats['closing_balance'] >= 0 ? 'text-on-surface' : 'text-error' }}">{{ $rupiah($stats['closing_balance']) }}</dd>
                </div>
            </dl>
        </section>

        <section class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <h2 class="font-title-md text-title-md text-on-surface mb-space-sm">Expenses by Category</h2>
            @if ($stats['expenses_by_category']->isEmpty())
                <p class="font-body-md text-body-md text-on-surface-variant">No expenses in this period.</p>
            @else
                <dl class="font-body-md text-body-md">
                    @foreach ($stats['expenses_by_category'] as $category => $amount)
                        <div class="flex justify-between gap-space-md py-1.5">
                            <dt class="text-on-surface-variant">{{ $category }}</dt>
                            <dd class="text-on-surface tabular-nums">
                                {{ $rupiah($amount) }}
                                <span class="inline-block w-11 text-right text-on-surface-variant">{{ round($amount / $stats['total_expenses'] * 100) }}%</span>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>
    </div>

    {{-- CASHFLOW LEDGER --}}
    <div x-data="{ showAddForm: @js($isRestoredForm) }" class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-hidden">
        <div class="flex items-center justify-between gap-space-sm p-space-md border-b border-outline-variant/30">
            <h2 class="font-title-md text-title-md text-on-surface">Cashflow Ledger</h2>
            <button type="button" @click="showAddForm = !showAddForm" :aria-expanded="showAddForm" class="press-scale shrink-0 inline-flex items-center gap-1.5 px-space-md py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg hover:opacity-95 transition-opacity duration-200">
                <span class="material-symbols-outlined text-[18px]" x-text="showAddForm ? 'close' : 'add'">add</span>
                <span x-text="showAddForm ? 'Cancel' : 'Add Expense'">Add Expense</span>
            </button>
        </div>

        <div x-show="showAddForm" x-cloak class="p-space-md border-b border-outline-variant/30 bg-surface-container/30">
            <form method="POST" action="{{ route('staff.accounting.expenses.store') }}" class="flex flex-wrap items-end gap-space-sm">
                @csrf
                <input type="hidden" name="_form" value="expense">
                <div>
                    <label for="description" class="block font-label-md text-label-md text-on-surface-variant mb-1">Description</label>
                    <input id="description" type="text" name="description" maxlength="255" value="{{ $isRestoredForm ? old('description') : '' }}" required placeholder="e.g. Weekly flour restock" class="{{ $fieldClasses }}">
                </div>
                <div>
                    <label for="category" class="block font-label-md text-label-md text-on-surface-variant mb-1">Category</label>
                    <select id="category" name="category" required class="{{ $fieldClasses }}">
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected($isRestoredForm && old('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="amount" class="block font-label-md text-label-md text-on-surface-variant mb-1">Amount (Rp)</label>
                    <input id="amount" type="text" inputmode="numeric" data-filter="integer" name="amount" maxlength="10" value="{{ $isRestoredForm ? old('amount') : '' }}" required placeholder="850000" class="w-36 tabular-nums {{ $fieldClasses }}">
                </div>
                <div>
                    <label for="expense_date" class="block font-label-md text-label-md text-on-surface-variant mb-1">Date</label>
                    <input id="expense_date" type="date" name="expense_date" max="{{ now()->toDateString() }}" required value="{{ $isRestoredForm ? old('expense_date') : now()->toDateString() }}" class="{{ $fieldClasses }}">
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
                            <td class="px-space-md py-space-sm font-body-sm text-body-sm text-on-surface-variant whitespace-nowrap tabular-nums">{{ $entry['date']->format('d M Y') }}</td>
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
                            <td class="px-space-md py-space-sm font-body-md text-body-md text-right whitespace-nowrap tabular-nums {{ $entry['type'] === 'in' ? 'text-tertiary' : 'text-error' }}">
                                {{ $entry['type'] === 'in' ? '+' : '-' }}{{ $rupiah($entry['amount']) }}
                            </td>
                            <td class="px-space-md py-space-sm font-body-md text-body-md text-on-surface-variant text-right whitespace-nowrap tabular-nums">{{ $rupiah($entry['balance_after']) }}</td>
                            <td class="px-space-md py-space-sm text-right">
                                @if (isset($entry['expense']))
                                    <form method="POST" action="{{ route('staff.accounting.expenses.destroy', $entry['expense']) }}" onsubmit="return confirm('Remove this expense entry?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" aria-label="Remove {{ $entry['description'] }}" class="press-scale inline-flex items-center px-space-sm py-1 rounded bg-surface-container text-on-surface-variant hover:bg-error-container/30 hover:text-error transition-colors duration-200">
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
