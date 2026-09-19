<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{

    public function index()
    {
        if (\Auth::user()->can('manage expense')) {
            $expenses = Expense::where('parent_id', '=', parentId())->orderBy('id', 'desc')->get();
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        return view('expense.index', compact('expenses'));
    }


    public function create()
    {
        return view('expense.create');
    }


    public function store(Request $request)
    {
        if (\Auth::user()->can('create expense')) {
            $validator = \Validator::make(
                $request->all(), [
                    'title' => 'required',
                    'date' => 'required',
                    'amount' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $expense = new Expense();
            $expense->title = $request->title;
            $expense->date = $request->date;
            $expense->amount = $request->amount;
            $expense->notes = $request->notes;
            $expense->parent_id = parentId();

            if (!empty($request->receipt)) {

                $expenseFilenameWithExt = $request->file('receipt')->getClientOriginalName();
                $expenseFilename = pathinfo($expenseFilenameWithExt, PATHINFO_FILENAME);
                $expenseExtension = $request->file('receipt')->getClientOriginalExtension();
                $expenseFileName = $expenseFilename . '_' . time() . '.' . $expenseExtension;

                $dir = storage_path('upload/expense');
                $image_path = $dir . $expenseFilenameWithExt;

                if (!file_exists($dir)) {
                    mkdir($dir, 0777, true);
                }
                $request->file('receipt')->storeAs('upload/expense/', $expenseFileName);
                $expense->receipt = $expenseFileName;
            }
            $expense->save();

            return redirect()->route('expense.index')->with('success', __('Expense successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function show(Expense $expense)
    {
        //
    }


    public function edit(Expense $expense)
    {

        return view('expense.edit', compact('expense'));
    }


    public function update(Request $request, Expense $expense)
    {
        if (\Auth::user()->can('edit expense')) {
            $validator = \Validator::make(
                $request->all(), [
                    'title' => 'required',
                    'date' => 'required',
                    'amount' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $expense->title = $request->title;
            $expense->date = $request->date;
            $expense->amount = $request->amount;
            $expense->notes = $request->notes;
            if (!empty($request->receipt)) {
                $expenseFilenameWithExt = $request->file('receipt')->getClientOriginalName();
                $expenseFilename = pathinfo($expenseFilenameWithExt, PATHINFO_FILENAME);
                $expenseExtension = $request->file('receipt')->getClientOriginalExtension();
                $expenseFileName = $expenseFilename . '_' . time() . '.' . $expenseExtension;
                $dir = storage_path('upload/expense');
                $image_path = $dir . $expenseFilenameWithExt;
                if (!file_exists($dir)) {
                    mkdir($dir, 0777, true);
                }
                $request->file('receipt')->storeAs('upload/expense/', $expenseFileName);
                $expense->receipt = $expenseFileName;
            }
            $expense->save();

            return redirect()->route('expense.index')->with('success', __('Expense successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function destroy(Expense $expense)
    {
        if (\Auth::user()->can('delete expense')) {
            $expense->delete();
            return redirect()->route('expense.index')->with('success', __('Expense successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
