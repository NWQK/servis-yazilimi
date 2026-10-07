<?php
namespace App\Http\Controllers;
use App\Models\{User, Vehicle, Service, Invoice, Item, SupportTicket, AppointmentProfile};
use App\Services\{VehicleCapacity, InvoiceBilling, OwnerSetupWarnings};
class BusinessDetailController extends Controller
{
    public function show(int $id)
    {
        abort_unless(auth()->user()?->type === 'super admin',403);
        $owner=User::where('type','owner')->findOrFail($id);
        $usage=\App\Services\BusinessUsage::query()->findOrFail($owner->id);
        $capacity=app(VehicleCapacity::class)->summary($owner);
        $business=InvoiceBilling::business($owner->id);
        $profile=AppointmentProfile::where('owner_id',$owner->id)->first();
        $counts=['Müşteriler'=>User::where('parent_id',$owner->id)->where('type','client')->whereNull('client_archived_at')->count(),
            'Araçlar'=>$capacity['used'], 'Servisler'=>Service::where('parent_id',$owner->id)->count(),
            'Faturalar'=>Invoice::where('parent_id',$owner->id)->count(),'Ürünler'=>Item::where('parent_id',$owner->id)->count()];
        $tickets=SupportTicket::where('owner_id',$owner->id)->latest('last_message_at')->limit(8)->get();
        $ticketCount=SupportTicket::where('owner_id',$owner->id)->where('status','!=','closed')->count();
        $pendingAppointments=$profile ? $profile->appointments()->where('status','pending')->count() : 0;
        $warnings=app(OwnerSetupWarnings::class)->forUser($owner,false);
        return view('admin.business-detail',compact('owner','capacity','business','profile','counts','tickets','ticketCount','pendingAppointments','warnings','usage'));
    }
}
