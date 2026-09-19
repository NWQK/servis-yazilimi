<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Notification;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller
{
    public function index()
    {
        if (\Auth::user()->can('manage employee')) {
            $employees = User::where('parent_id', '=', parentId())->where('type', 'employee')->orderBy('id', 'desc')->get();
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        return view('employee.index', compact('employees'));
    }

    public function create()
    {
        $gender = User::genderList();
        return view('employee.create', compact('gender'));
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('create employee')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required',
                    'email' => 'required|email|unique:users',
                    'password' => 'required|min:6',
                    'phone_number' => 'required',
                    'gender' => 'required',
                    'age' => 'required',
                    'joining_date' => 'required',
                    'address' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $pricing_feature_settings = getSettingsValByIdName(1, 'pricing_feature');
            if ($pricing_feature_settings == 'on') {
                $ids = parentId();
                $authUser = User::find($ids);
                $totalEmployee = $authUser->totalEmployee();
                $subscription = Subscription::find($authUser->subscription);
                if ($totalEmployee >= $subscription->employee_limit && $subscription->employee_limit != 0) {
                    return redirect()->back()->with('error', __('Your employee limit is over, please upgrade your subscription.'));
                }
            }
            $userRole = Role::where('name', 'employee')->where('parent_id', parentId())->first();

            $user = new User();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone_number = $request->phone_number;
            $user->password = \Hash::make($request->password);
            $user->type = $userRole->name;
            $user->profile = 'avatar.png';
            $user->lang = 'english';
            $user->parent_id = parentId();
            $user->email_verified_at = now();
            $user->save();
            $user->assignRole($userRole);
            if (!empty($user)) {
                $employee = new Employee();
                $employee->employee_id = $this->employeeNumber();
                $employee->user_id = $user->id;
                $employee->gender = $request->gender;
                $employee->age = $request->age;
                $employee->joining_date = $request->joining_date;
                $employee->address = $request->address;
                $employee->reference = $request->reference;
                $employee->notes = $request->notes;
                $employee->parent_id = parentId();

                if (!empty($request->document)) {
                    $documentFilenameWithExt = $request->file('document')->getClientOriginalName();
                    $documentFilename = pathinfo($documentFilenameWithExt, PATHINFO_FILENAME);
                    $documentExtension = $request->file('document')->getClientOriginalExtension();
                    $documentFileName = $documentFilename . '_' . time() . '.' . $documentExtension;

                    $dir = storage_path('upload/document');
                    $image_path = $dir . $documentFilenameWithExt;

                    if (!file_exists($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    $request->file('document')->storeAs('upload/document/', $documentFileName);
                    $employee->document = $documentFileName;
                }
                $employee->save();
            }

            $setting = settings();
            triggerN8n('create_employee', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone_number,
                'password' => $request->password,
                'role' => $userRole->name,
                'gender' => $request->gender,
                'age' => $request->age,
                'joining_date' => $request->joining_date,
                'address' => $request->address,
                'reference' => $request->reference,
                'notes' => $request->notes,
                'company_name' => $setting['company_name'],
                'company_email' => $setting['company_email'],
                'company_phone' => $setting['company_phone'],
            ]);

            $module = 'employee_create';
            $notification = Notification::where('parent_id', parentId())->where('module', $module)->first();
            $errorMessage = '';

            if (!empty($notification)) {
                $notificationResponse = MessageReplace($notification, $user->id);

                $data['subject'] = $notificationResponse['subject'];
                $data['message'] = $notificationResponse['message'];
                $data['module'] = $module;
                $data['logo'] = $setting['company_logo'];
                $to = $request->email;
                if ($notification->enabled_email == 1) {
                    $response = commonEmailSend($to, $data);
                    if ($response['status'] == 'error') {
                        $errorMessage = $response['message'];
                    }
                }
                if ($notification->enabled_sms == 1) {
                    $twilio_sid = getSettingsValByName('twilio_sid');
                    if (!empty($twilio_sid)) {
                        send_twilio_msg($user->phone_number, $notificationResponse['sms_message']);
                    }
                }
                if ($notification->enabled_whatsapp == 1 && !empty($user->phone_number)) {
                    sendWhatshappSms([
                        'to' => $user->phone_number,
                        'body' => $notificationResponse['sms_message']
                    ]);
                }
            }
            return redirect()->route('employee.index')->with('success', __('Employee successfully created.') . '' . $errorMessage);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function show($id)
    {
        $id = decrypt($id);
        $employee = Employee::where('user_id', $id)->where('parent_id', parentId())->first();
        $user = User::find($id);
        $employee = $user->employees;
        $services = \App\Models\Service::where('assign', $id)->where('parent_id', parentId())->orderBy('service_date', 'desc')->get();
        return view('employee.show', compact('employee', 'user','services'));
    }


    public function edit($id)
    {
        $user = User::find($id);
        $employee = $user->employees;
        $gender = User::genderList();
        return view('employee.edit', compact('employee', 'user', 'gender'));
    }


    public function update(Request $request, $id)
    {
        if (\Auth::user()->can('edit employee')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required',
                    'email' => 'required|email|unique:users,email,' . $id,
                    'phone_number' => 'required',
                    'gender' => 'required',
                    'age' => 'required',
                    'joining_date' => 'required',
                    'address' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $user = User::find($id);
            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone_number = $request->phone_number;
            $user->save();

            if (!empty($user)) {
                $employee = new Employee();
                $employee->gender = $request->gender;
                $employee->age = $request->age;
                $employee->joining_date = $request->joining_date;
                $employee->address = $request->address;
                $employee->reference = $request->reference;
                $employee->notes = $request->notes;
                $employee->parent_id = parentId();

                if (!empty($request->document)) {
                    $documentFilenameWithExt = $request->file('document')->getClientOriginalName();
                    $documentFilename = pathinfo($documentFilenameWithExt, PATHINFO_FILENAME);
                    $documentExtension = $request->file('document')->getClientOriginalExtension();
                    $documentFileName = $documentFilename . '_' . time() . '.' . $documentExtension;

                    $dir = storage_path('upload/document');
                    $image_path = $dir . $documentFilenameWithExt;

                    if (!file_exists($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    $request->file('document')->storeAs('upload/document/', $documentFileName);
                    $employee->document = $documentFileName;
                }
                $employee->save();
            }
            return redirect()->route('employee.index')->with('success', __('Employee successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function destroy($id)
    {
        if (\Auth::user()->can('delete employee')) {
            $user = User::find($id);
            $user->delete();
            Employee::where('user_id', $id)->delete();

            return redirect()->route('employee.index')->with('success', __('Employee successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function employeeNumber()
    {
        $latest = Employee::where('parent_id', parentId())->latest()->first();
        if (!$latest) {
            return 1;
        }
        return $latest->employee_id + 1;
    }
}
