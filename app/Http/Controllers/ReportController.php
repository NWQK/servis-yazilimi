<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function service(Request $request)
    {

        if (\Auth::user()->can('manage service report')) {


            $clients = User::where('parent_id', parentId())->where('type', 'client')->get()->pluck('name', 'id');
            $clients->prepend(__('Select Client'), '');


            $services = Service::where('parent_id', '=', parentId());

            if ($request->filled('client') && $request->client != 0) {
                $services->where('client', $request->client);
            }

            if ($request->filled('status')) {
                $services->where('status', $request->status);
            }

            if ($request->filled('start_date')) {
                $services->where('service_date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $services->where('due_date', '<=', $request->end_date);
            }

            $services = $services->get();

            $status = Service::status();


            return view('report.service', compact('services', 'clients', 'status'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied!'));
        }
    }


    public function income(Request $request)
    {

        if (\Auth::user()->can('manage income report')) {

            $clients = User::where('parent_id', parentId())->where('type', 'client')->get()->pluck('name', 'id');
            $clients->prepend(__('Select Client'), '');

            $status = Invoice::statues();
            $invoices = Invoice::where('parent_id', parentId());

            if ($request->filled('client') && $request->client != 0) {
                $invoices->where('client', $request->client);
            }

            if ($request->filled('status') && $request->status != 0) {
                $invoices->where('status', $request->status);
            }
            if ($request->filled(['start_date', 'end_date'])) {
                $invoices->whereBetween('invoice_date', [$request->start_date, $request->end_date]);
            }

            $invoices = $invoices->get();
            return view('report.income', compact('invoices', 'clients', 'status'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied!'));
        }
    }

    public function expense(Request $request)
    {

        if (\Auth::user()->can('manage expense report')) {

            $expenses = Expense::where('parent_id', parentId());
            if ($request->filled(['start_date', 'end_date'])) {
                $expenses->whereBetween('date', [$request->start_date, $request->end_date]);
            }
            $expenses = $expenses->get();
            return view('report.expense', compact('expenses'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied!'));
        }
    }

    public function reportProfitLoss(Request $request)
    {
        $year = $request->get('year', now()->year);

        // ✅ Income from invoice_payments
        $incomeQuery = DB::table('invoice_payments')
            ->selectRaw('MONTH(invoice_payments.payment_date) as month, SUM(invoice_payments.amount) as income')
            ->join('invoices', 'invoices.id', '=', 'invoice_payments.invoice_id')
            ->whereYear('invoice_payments.payment_date', $year);


        // DD($incomeQuery);

        $incomeData = $incomeQuery->groupBy('month')->pluck('income', 'month');

        // ✅ Expenses
        $expenseQuery = Expense::selectRaw('MONTH(date) as month, SUM(amount) as expense')
            ->whereYear('date', $year);

            $expenseData = $expenseQuery->groupBy('month')->pluck('expense', 'month');

        // ✅ Prepare final report
        $report = [];
        for ($m = 1; $m <= 12; $m++) {
            $income = $incomeData[$m] ?? 0;
            $expense = $expenseData[$m] ?? 0;

            if ($income == 0 && $expense == 0) {
                continue;
            }

            $report[] = (object) [
                'month' => date("F Y", mktime(0, 0, 0, $m, 1)),
                'income' => $income,
                'expense' => $expense,
                'profit' => $income - $expense,
            ];
        }

        $incomeExpenseByMonth = $this->incomeByMonth($year);

        // Add profit series
        $profitByMonth = [];
        foreach ($incomeExpenseByMonth['income'] as $key => $income) {
            $profitByMonth[] = $income - $incomeExpenseByMonth['expense'][$key];
        }
        return view('report.profit_loss', [
            'year' => $year,
            'years' => range(now()->year, now()->year - 10),
            'report' => $report,
            'incomeExpenseByMonth' => array_merge($incomeExpenseByMonth, ['profit' => $profitByMonth]),
        ]);
    }

public function incomeByMonth($year = null)
{
    $year = $year ?? date('Y');
    $start = strtotime("$year-01");
    $end = strtotime("$year-12");

    $currentdate = $start;
    $payment = [];

    while ($currentdate <= $end) {
        $month = date('m', $currentdate);
        $year = date('Y', $currentdate);
        $payment['label'][] = date('M-Y', $currentdate);

        $incomeQuery = InvoicePayment::join('invoices', 'invoices.id', '=', 'invoice_payments.invoice_id')
            ->whereMonth('payment_date', $month)
            ->whereYear('payment_date', $year)
            ->where('invoice_payments.parent_id', parentId());

        $payment['income'][] = (float) number_format($incomeQuery->sum('invoice_payments.amount'), 2, '.', '');

        $expenseQuery = Expense::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->where('parent_id', parentId());

        $payment['expense'][] = (float) number_format($expenseQuery->sum('amount'), 2, '.', '');

        $currentdate = strtotime('+1 month', $currentdate);
    }

    return $payment;
}
}
