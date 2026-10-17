<?php

namespace App\Http\Controllers;

use App\Models\Tax;
use Illuminate\Http\Request;

class TaxController extends Controller
{

    public function index()
    {
        if (\Auth::user()->can('manage tax')) {
            $taxs = Tax::where('parent_id', '=', parentId())->orderBy('id', 'desc')->get();
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        return view('tax.index', compact('taxs'));
    }


    public function create()
    {
        abort_unless(auth()->user()->can('create tax'),403);
        return view('tax.create');
    }


    public function store(Request $request)
    {
        if (\Auth::user()->can('create tax')) {
            $validator = \Validator::make(
                $request->all(), [
                    'title' => 'required',
                    'rate' => 'required|numeric|between:0,100',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }
            $tax = new Tax();
            $tax->title = $request->title;
            $tax->rate = $request->rate;
            $tax->parent_id = parentId();
            $tax->save();
            return redirect()->route('tax.index')->with('success', __('Tax successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function show(Tax $tax)
    {
        //
    }


    public function edit(Tax $tax)
    {
        abort_unless(auth()->user()->can('edit tax'),403);
        return view('tax.edit',compact('tax'));
    }


    public function update(Request $request, Tax $tax)
    {
        $tax->refresh();
        if (\Auth::user()->can('edit tax')) {
            $validator = \Validator::make(
                $request->all(), [
                    'title' => 'required',
                    'rate' => 'required|numeric|between:0,100',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            if ((float)$tax->rate !== (float)$request->rate && \App\Services\TaxReferences::invoiced($tax)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['rate'=>'Bu vergi faturada kullanılmış. Geçmiş tutarları korumak için yeni oranla ayrı bir vergi oluşturun.']);
            }
            $tax->title = $request->title;
            $tax->rate = $request->rate;
            $tax->save();
            return redirect()->route('tax.index')->with('success', __('Tax successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function destroy(Tax $tax)
    {
        if (\Auth::user()->can('delete tax')) {
            if (\App\Services\TaxReferences::used($tax)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['tax'=>'Bu vergi kayıtlı ürün, hizmet veya faturada kullanılıyor. İlgili kayıtları korumak için silinemez.']);
            }
            $tax->delete();
            return redirect()->route('tax.index')->with('success', __('Tax successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
