<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'title',
        'package_amount',
        'interval',
        'user_limit',
        'client_limit',
        'employee_limit',
        'enabled_logged_history',
    ];

    public static function intervals()
    {
        return [
            'Monthly' => __('Monthly'),
            'Quarterly' => __('Quarterly'),
            'Yearly' => __('Yearly'),
            'Unlimited' => __('Unlimited'),
        ];
    }

    public function couponCheck()
    {
        $packages = Coupon::whereRaw("find_in_set($this->id,applicable_packages)")->count();
        return $packages;
    }

}
