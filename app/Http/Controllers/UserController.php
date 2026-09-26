<?php

namespace App\Http\Controllers;

use App\Models\LoggedHistory;
use App\Models\Notification;
use App\Models\PackageTransaction;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{

    public function index()
    {
        if (\Auth::user()->can('manage user')) {
            if (\Auth::user()->type == 'super admin') {
                $users = User::where('parent_id', parentId())->where('type', 'owner')->orderBy('id', 'desc')->get();
                return view('user.index', compact('users'));
            } else {
                $users = User::where('parent_id', '=', parentId())->whereNotIn('type', ['client', 'employee'])->get();
                return view('user.index', compact('users'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function create()
    {
        $userRoles = Role::where('parent_id', parentId())->whereNotIn('name', ['client', 'employee'])->get()->pluck('name', 'id');
        return view('user.create', compact('userRoles'));
    }


    public function store(Request $request)
    {
        if (\Auth::user()->can('create user')) {
            if (\Auth::user()->type == 'super admin') {
                $validator = \Validator::make(
                    $request->all(),
                    [
                        'name' => 'required',
                        'email' => 'required|email|unique:users',
                        'password' => 'required|min:6',
                    ]
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();

                    return redirect()->back()->with('error', $messages->first());
                }

                $user = new User();
                $user->name = $request->name;
                $user->email = $request->email;
                $user->password = \Hash::make($request->password);
                $user->phone_number = $request->phone_number;
                $user->type = 'owner';
                $user->profile = 'avatar.png';
                $user->lang = 'tr';
                $user->subscription = 1;
                $user->parent_id = parentId();
                $user->email_verified_at = now();
                $user->save();
                $userRole = Role::findByName('owner');
                $user->assignRole($userRole);

                defaultClientCreate($user->id);
                defaultEmployeeCreate($user->id);

                defultTemplate($user->id);

                if (!empty($request->profile)) {
                    $tenantFilenameWithExt = $request->file('profile')->getClientOriginalName();
                    $tenantFilename = pathinfo($tenantFilenameWithExt, PATHINFO_FILENAME);
                    $tenantExtension = $request->file('profile')->getClientOriginalExtension();
                    $tenantFileName = $tenantFilename . '_' . time() . '.' . $tenantExtension;
                    $dir = storage_path('upload/profile');
                    if (!file_exists($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    $request->file('profile')->storeAs('upload/profile/', $tenantFileName);
                    $user->profile = $tenantFileName;
                    $user->save();
                }


                $module = 'owner_create';
                $setting = settings();
                $errorMessage = '';
                if (!empty($user)) {
                    $data['subject'] = 'New User Created';
                    $data['module'] = $module;
                    $data['password'] = $request->password;
                    $data['name'] = $request->name;
                    $data['email'] = $request->email;
                    $data['url'] = env('APP_URL');
                    $data['logo'] = $setting['company_logo'];
                    $to = $user->email;
                    $response = commonEmailSend($to, $data);
                    if ($response['status'] == 'error') {
                        $errorMessage = $response['message'];
                    }
                }

                return redirect()->route('users.index')->with('success', __('User successfully created.') . $errorMessage);
            } else {

                $validator = \Validator::make(
                    $request->all(),
                    [
                        'name' => 'required',
                        'email' => 'required|email|unique:users',
                        'password' => 'required|min:6',
                        'role' => 'required',
                    ]
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();

                    return redirect()->back()->with('error', $messages->first());
                }

                $pricing_feature_settings = getSettingsValByIdName(1, 'pricing_feature');
                if ($pricing_feature_settings == 'on') {
                    $ids = parentId();
                    $authUser = \App\Models\User::find($ids);
                    $totalUser = $authUser->totalUser();
                    $subscription = Subscription::find($authUser->subscription);
                    if ($totalUser >= $subscription->user_limit && $subscription->user_limit != 0) {
                        return redirect()->back()->with('error', __('Your user limit is over, please upgrade your subscription.'));
                    }
                }
                $userRole = Role::findById($request->role);
                $user = new User();
                $user->name = $request->name;
                $user->email = $request->email;
                $user->phone_number = $request->phone_number;
                $user->password = \Hash::make($request->password);
                $user->type = $userRole->name;
                $user->email_verified_at = now();
                $user->profile = 'avatar.png';
                $user->lang = 'tr';
                $user->parent_id = parentId();
                $user->save();
                $user->assignRole($userRole);

                if (!empty($request->profile)) {
                    $tenantFilenameWithExt = $request->file('profile')->getClientOriginalName();
                    $tenantFilename = pathinfo($tenantFilenameWithExt, PATHINFO_FILENAME);
                    $tenantExtension = $request->file('profile')->getClientOriginalExtension();
                    $tenantFileName = $tenantFilename . '_' . time() . '.' . $tenantExtension;
                    $dir = storage_path('upload/profile');
                    if (!file_exists($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    $request->file('profile')->storeAs('upload/profile/', $tenantFileName);
                    $user->profile = $tenantFileName;
                    $user->save();
                }
                $setting = settings();

                triggerN8n('create_user', [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone_number,
                    'password' => $request->password,
                    'role' => $userRole->name,
                    'company_name' => $setting['company_name'],
                    'company_email' => $setting['company_email'],
                    'company_phone' => $setting['company_phone'],
                ]);
                $module = 'user_create';
                $notification = Notification::where('parent_id', parentId())->where('module', $module)->first();
                $notification->password = $request->password;
                $errorMessage = '';
                if (!empty($notification)) {
                    $notification_responce = MessageReplace($notification, $user->id);
                    $data['subject'] = $notification_responce['subject'];
                    $data['message'] = $notification_responce['message'];
                    $data['module'] = $module;
                    $data['password'] = $request->password;
                    $data['logo'] = $setting['company_logo'];
                    $to = $user->email;

                    if ($notification->enabled_email == 1) {
                        $response = commonEmailSend($to, $data);
                        if ($response['status'] == 'error') {
                            $errorMessage = $response['message'];
                        }
                    }
                    if ($notification->enabled_sms == 1) {

                    }

                }

                return redirect()->route('users.index')->with('success', __('User successfully created.') . '</br>' . $errorMessage);
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function show(User $user)
    {
        if (!\Auth::user()->can('show user')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        } else {
            $settings = settings();
            $transactions = PackageTransaction::where('user_id', $user->id)->orderBy('created_at', 'DESC')->get();
            $subscriptions = Subscription::get();
            return view('user.show', compact('user', 'transactions', 'settings', 'subscriptions'));
        }
    }


    public function edit($id)
    {
        $user = User::findOrFail($id);
        $userRoles = Role::where('parent_id', '=', parentId())->whereNotIn('name', ['client', 'employee'])->get()->pluck('name', 'id');
        $assignedRoleId = $user->roles->first()?->id;
        return view('user.edit', compact('user', 'userRoles', 'assignedRoleId'));
    }


    public function update(Request $request, $id)
    {
        if (\Auth::user()->can('edit user')) {
            if (\Auth::user()->type == 'super admin') {
                $user = User::findOrFail($id);

                $validator = \Validator::make(
                    $request->all(),
                    [
                        'name' => 'required',
                        'email' => 'required|email|unique:users,email,' . $id,
                    ]
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();

                    return redirect()->back()->with('error', $messages->first());
                }

                $userData = $request->all();
                $user->fill($userData)->save();

                if ($request->profile != '') {
                    $tenantFilenameWithExt = $request->file('profile')->getClientOriginalName();
                    $tenantFilename = pathinfo($tenantFilenameWithExt, PATHINFO_FILENAME);
                    $tenantExtension = $request->file('profile')->getClientOriginalExtension();
                    $tenantFileName = $tenantFilename . '_' . time() . '.' . $tenantExtension;
                    $dir = storage_path('upload/profile');
                    if (!file_exists($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    $request->file('profile')->storeAs('upload/profile/', $tenantFileName);
                    $user->profile = $tenantFileName;
                    $user->save();
                }

                return redirect()->route('users.index')->with('success', __('User successfully updated.'));
            } else {


                $validator = \Validator::make(
                    $request->all(),
                    [
                        'name' => 'required',
                        'email' => 'required|email|unique:users,email,' . $id,
                        'role' => 'required',
                    ]
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();

                    return redirect()->back()->with('error', $messages->first());
                }

                $userRole = Role::findById($request->role);
                $user = User::findOrFail($id);
                $user->name = $request->name;
                $user->email = $request->email;
                $user->phone_number = $request->phone_number;
                $user->type = $userRole->name;
                $user->save();

                if ($request->profile != '') {
                    $tenantFilenameWithExt = $request->file('profile')->getClientOriginalName();
                    $tenantFilename = pathinfo($tenantFilenameWithExt, PATHINFO_FILENAME);
                    $tenantExtension = $request->file('profile')->getClientOriginalExtension();
                    $tenantFileName = $tenantFilename . '_' . time() . '.' . $tenantExtension;
                    $dir = storage_path('upload/profile');
                    if (!file_exists($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    $request->file('profile')->storeAs('upload/profile/', $tenantFileName);
                    $user->profile = $tenantFileName;
                    $user->save();
                }

                $user->roles()->sync($userRole);
                return redirect()->route('users.index')->with('success', __('User successfully updated.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function destroy($id)
    {

        if (\Auth::user()->can('delete user')) {
            $user = User::find($id);
            $user->delete();

            return redirect()->route('users.index')->with('success', __('User successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function loggedHistory()
    {
        $ids = parentId();
        $authUser = \App\Models\User::find($ids);
        $subscription = \App\Models\Subscription::find($authUser->subscription);

        if (\Auth::user()->can('manage logged history') && $subscription->enabled_logged_history == 1) {
            $histories = LoggedHistory::where('parent_id', parentId())->get();
            return view('logged_history.index', compact('histories'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function loggedHistoryShow($id)
    {
        if (\Auth::user()->can('manage logged history')) {
            $histories = LoggedHistory::find($id);
            return view('logged_history.show', compact('histories'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function loggedHistoryDestroy($id)
    {
        if (\Auth::user()->can('delete logged history')) {
            $histories = LoggedHistory::find($id);
            $histories->delete();
            return redirect()->back()->with('success', __('Logged history succefully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }
}
