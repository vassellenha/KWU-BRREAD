<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Order;
use App\Services\CashflowExporter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
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

    public function storeExpense(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(Expense::CATEGORIES)],
            'amount' => ['required', 'integer', 'min:1', 'max:9999999999'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'expense_date.before_or_equal' => 'The expense date cannot be in the future.',
        ]);

        Expense::create($validated + ['user_id' => $request->user()->id]);

        $date = CarbonImmutable::parse($validated['expense_date'])->format('d M Y');

        return back()->with('status', "Expense recorded for {$date}.");
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

        return (new CashflowExporter)->stream($ledger, $stats, $from, $to, $filename);
    }

    /**
     * @return array{0: Collection<int, array<string, mixed>>, 1: array<string, mixed>}
     */
    private function buildLedger(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $orders = Order::whereBetween('created_at', [$from, $to->endOfDay()])->get();
        $expenses = Expense::whereBetween('expense_date', [$from, $to->endOfDay()])->get();

        $totalIncome = (float) $orders->sum('total_price');
        $totalExpenses = (float) $expenses->sum('amount');

        $openingBalance = (float) Order::where('created_at', '<', $from)->sum('total_price')
            - (float) Expense::where('expense_date', '<', $from)->sum('amount');
        $closingBalance = $openingBalance + $totalIncome - $totalExpenses;

        $entries = $orders->map(fn (Order $order) => [
            'date' => $order->created_at,
            'type' => 'in',
            'description' => "Order {$order->order_number} — {$order->customer_name}",
            'category' => 'Sales',
            'amount' => (float) $order->total_price,
        ])->concat($expenses->map(fn (Expense $expense) => [
            'date' => $expense->expense_date->setTimeFrom($expense->created_at),
            'type' => 'out',
            'description' => $expense->description,
            'category' => $expense->category,
            'amount' => (float) $expense->amount,
            'expense' => $expense,
        ]))->sortByDesc('date')->values();

        $balanceAfter = $closingBalance;
        $ledger = $entries->map(function (array $entry) use (&$balanceAfter) {
            $entry['balance_after'] = $balanceAfter;
            $balanceAfter -= $entry['type'] === 'in' ? $entry['amount'] : -$entry['amount'];

            return $entry;
        });

        return [$ledger, [
            'opening_balance' => $openingBalance,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net' => $totalIncome - $totalExpenses,
            'closing_balance' => $closingBalance,
            'order_count' => $orders->count(),
            'transaction_count' => $orders->count() + $expenses->count(),
            'expenses_by_category' => $expenses
                ->groupBy('category')
                ->map(fn (Collection $group) => (float) $group->sum('amount'))
                ->sortDesc(),
        ]];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to']) : CarbonImmutable::today();
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from']) : $to->startOfMonth();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->startOfDay(), $to->startOfDay()];
    }
}
