<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class N8n extends Model
{
    use HasFactory;

        public static function method()
    {
        return [
            'GET' => __('GET'),
            'POST' => __('POST'),
            'PATCH' => __('PATCH'),
            'PUT' => __('PUT'),
            'HEAD' => __('HEAD')
        ];
    }

    public static function module()
    {
        return [
            'create_user' => __('Create User'),
            'create_employee' => __('Create Employee'),
            'create_client' => __('Create Client'),
            'new_vehicle' => __('New Vehicle'),
            'new_service' => __('New Service'),
            'assign_service' => __('Assign Service'),
            'create_invoice' => __('Create Invoice'),
            'new_payment' => __('New Payment'),
        ];
    }
}
