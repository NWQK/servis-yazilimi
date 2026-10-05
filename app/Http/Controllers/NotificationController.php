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
    public function index()
    {
        if (\Auth::user()->can('manage notification')) {
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
        $Notifications = defaultTemplateList();
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
                    'module' => 'required|in:' . implode(',', array_keys(defaultTemplateList())),
                    'subject' => 'required',
                    'message' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $exist = Notification::where('parent_id', parentId())->where('module', $request->module)->first();
            if (empty($exist)) {
                $notification = new Notification();
                $notification->module = $request->module;
                $definition = defaultTemplateList()[$request->module];
                $notification->name = $definition['name'];
                $notification->short_code = json_encode($definition['short_code']);
                $notification->subject = $request->subject;
                $notification->message = $request->message;
                $notification->enabled_email = $request->boolean('enabled_email') ? 1 : 0;
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
        $definition = defaultTemplateList()[$notification->module] ?? null;
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

            $definition = defaultTemplateList()[$notification->module] ?? null;
            $useDefault = $request->boolean('use_default_template') && $definition;
            $notification->subject = $useDefault ? $definition['subject'] : $request->subject;
            $notification->message = $useDefault ? $definition['templete'] : $request->message;
            $notification->enabled_email = $request->boolean('enabled_email') ? 1 : 0;
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
