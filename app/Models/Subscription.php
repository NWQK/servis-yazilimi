<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $casts = ['vehicle_limit' => 'integer', 'vehicle_block_amount' => 'decimal:2'];
    protected $fillable = [
        'title',
        'package_amount',
        'interval',
        'user_limit',
        'client_limit',
        'employee_limit',
        'enabled_logged_history',
        'vehicle_limit',
        'vehicle_block_amount',
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
