<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    private function definitions(): array
    {
        $definitions = defaultTemplateList();
        if (auth()->user()->type === 'super admin') {
            $definitions += \App\Services\SecurityEmail::definitions();
            $definitions[\App\Services\InvoiceCustomerEmail::MODULE] = \App\Services\InvoiceCustomerEmail::definition();
        }
        return $definitions;
    }

    public function index()
    {
        if (\Auth::user()->can('manage notification')) {
            if (auth()->user()->type === 'super admin') \App\Services\InvoiceCustomerEmail::template(auth()->user());
            if (auth()->user()->type === 'super admin') foreach (array_keys(\App\Services\SecurityEmail::definitions()) as $module) \App\Services\SecurityEmail::template(auth()->user(),$module);
            $notifications = Notification::where('parent_id', parentId())->orderBy('id', 'desc')->get();
            return view('notification.index', compact('notifications'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        abort_unless(auth()->user()->can('create notification'), 403);
        $Notifications = $this->definitions();
        $notification_option = [];
        foreach ($Notifications as $key => $value) {
            $notification_option[$key] = $value['name'];
        }
        return view('notification.create', compact('notification_option', 'Notifications'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (\Auth::user()->can('create notification')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'module' => 'required|in:' . implode(',', array_keys($this->definitions())),
                    'subject' => 'required',
                    'message' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $exist = Notification::where('parent_id', parentId())->where('module', $request->module)->first();
            if ($request->module === \App\Services\SecurityEmail::RESET && !str_contains($request->message, '{reset_link}')) return back()->with('error','Şifre sıfırlama mesajı {reset_link} bağlantısını içermelidir.');
            if (empty($exist)) {
                $notification = new Notification();
                $notification->module = $request->module;
                $definition = $this->definitions()[$request->module];
                $notification->name = $definition['name'];
                $notification->short_code = json_encode($definition['short_code']);
                $notification->subject = $request->subject;
                $notification->message = $request->message;
                $notification->enabled_email = $notification->module === \App\Services\SecurityEmail::RESET || $request->boolean('enabled_email') ? 1 : 0;
                $notification->enabled_sms = 0;
                $notification->sms_message = '';
                $notification->parent_id = parentId();
                $notification->save();

                return redirect()->route('notification.index')->with('success', __('Notification successfully created.'));
            } else {
                return redirect()->back()->with('error', __('Notification already exist'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Notification  $notification
     * @return \Illuminate\Http\Response
     */
    public function show(Notification $notification)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Notification  $notification
     * @return \Illuminate\Http\Response
     */
    public function edit(Notification $notification)
    {
        abort_unless(auth()->user()->can('edit notification') && (int) $notification->parent_id === (int) parentId(), 403);
        $definition = $this->definitions()[$notification->module] ?? null;
        $notification->short_code = json_decode($notification->short_code);


        return view('notification.edit', compact('notification', 'definition'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Notification  $notification
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Notification $notification)
    {
        if (\Auth::user()->can('edit notification')) {
            abort_unless((int) $notification->parent_id === (int) parentId(), 403);
            $validator = \Validator::make(
                $request->all(),
                [
                    'subject' => 'required',
                    'message' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $definition = $this->definitions()[$notification->module] ?? null;
            $useDefault = $request->boolean('use_default_template') && $definition;
            $notification->subject = $useDefault ? $definition['subject'] : $request->subject;
            if ($notification->module === \App\Services\SecurityEmail::RESET && !$useDefault && !str_contains($request->message, '{reset_link}')) {
                return back()->with('error','Şifre sıfırlama mesajı {reset_link} bağlantısını içermelidir.');
            }
            $notification->message = $useDefault ? $definition['templete'] : $request->message;
            $notification->enabled_email = $notification->module === \App\Services\SecurityEmail::RESET || $request->boolean('enabled_email') ? 1 : 0;
            $notification->enabled_sms = 0;
            $notification->enabled_whatsapp = 0;
            $notification->save();

            return redirect()->route('notification.index')->with('success', __('Notification successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Notification  $notification
     * @return \Illuminate\Http\Response
     */
    public function destroy(Notification $notification)
    {
        if (\Auth::user()->can('delete notification')) {
            abort_unless((int) $notification->parent_id === (int) parentId(), 403);
            $notification->delete();
            return redirect()->back()->with('success', __('Notification successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
