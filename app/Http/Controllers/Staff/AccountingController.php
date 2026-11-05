<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Order;
use App\Services\CashflowExporter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingController extends Controller
{
    public function index(Request $request): View
    {
        [$from, $to] = $this->resolveRange($request);
        [$ledger, $stats] = $this->buildLedger($from, $to);

        return view('staff.accounting.index', [
            'from' => $from,
            'to' => $to,
            'ledger' => $ledger,
            'stats' => $stats,
            'categories' => Expense::CATEGORIES,
        ]);
    }

    /** @return array{0: \Illuminate\Support\Collection, 1: array} */
    private function buildLedger(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $orders = Order::whereBetween('created_at', [$from, $to->endOfDay()])->get();
        $expenses = Expense::with('user')->whereBetween('expense_date', [$from, $to])->get();

        $totalIncome = (float) $orders->sum('total_price');
        $totalExpenses = (float) $expenses->sum('amount');

        $ledger = $orders->map(fn (Order $order) => [
                'date' => $order->created_at,
                'type' => 'in',
                'description' => "Order {$order->order_number} — {$order->customer_name}",
                'category' => 'Sales',
                'amount' => (float) $order->total_price,
            ])
            ->concat($expenses->map(fn (Expense $expense) => [
                'date' => $expense->expense_date->setTimeFrom($expense->created_at),
                'type' => 'out',
                'description' => $expense->description,
                'category' => $expense->category,
                'amount' => (float) $expense->amount,
                'expense' => $expense,
            ]))
            ->sortByDesc('date')
            ->values();

        $runningBalance = $totalIncome - $totalExpenses;
        $ledger = $ledger->map(function ($row) use (&$runningBalance) {
            $row['balance_after'] = $runningBalance;
            $runningBalance -= $row['type'] === 'in' ? $row['amount'] : -$row['amount'];

            return $row;
        });

        return [$ledger, [
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net' => $totalIncome - $totalExpenses,
            'transaction_count' => $orders->count() + $expenses->count(),
        ]];
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:'.implode(',', Expense::CATEGORIES)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        Expense::create($validated + ['user_id' => $request->user()->id]);

        return back()->with('status', 'Expense recorded.');
    }

    public function destroyExpense(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return back()->with('status', 'Expense removed.');
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->resolveRange($request);
        [$ledger, $stats] = $this->buildLedger($from, $to);

        $filename = 'brread-cashflow-'.$from->format('Y-m-d').'_to_'.$to->format('Y-m-d').'.xlsx';

        return (new CashflowExporter())->stream($ledger, $stats, $from, $to, $filename);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function resolveRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to']) : CarbonImmutable::today();
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from']) : $to->startOfMonth();

        return [$from->startOfDay(), $to->startOfDay()];
    }
}
