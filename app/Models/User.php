<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Lab404\Impersonate\Models\Impersonate;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use \Laravel\Sanctum\HasApiTokens;
    protected $guard_name = 'web';
    use HasRoles;
    use Notifiable;
    use Impersonate;

    protected static function booted(): void
    {
        static::deleting(function (self $user) {
            if($user->type==='employee')throw \Illuminate\Validation\ValidationException::withMessages(['employee'=>'Personeli silmek yerine Personel ayrılış ekranından erişimini kapatın. Geçmiş kayıtlar korunur.']);
            if (\Illuminate\Support\Facades\Schema::hasTable('business_groups')
                && (DB::table('business_groups')->where('manager_user_id',$user->id)->exists()
                    || DB::table('business_branches')->where('owner_id',$user->id)->exists())) {
                throw \Illuminate\Validation\ValidationException::withMessages(['user'=>'Bu hesap şube merkezine bağlı. Önce şube bağlantıları yönetilmelidir.']);
            }
        });
        static::saving(function (self $user) {
            if ($user->type === 'branch_manager' && (!$user->exists || $user->isDirty('type'))
                && auth()->user()?->type !== 'super admin') {
                throw \Illuminate\Validation\ValidationException::withMessages(['type'=>'Şube merkez hesabını yalnızca süper yönetici oluşturabilir.']);
            }
            if ($user->exists && ($user->isDirty('type') || $user->isDirty('parent_id'))
                && \Illuminate\Support\Facades\Schema::hasTable('business_groups')
                && (DB::table('business_groups')->where('manager_user_id',$user->id)->exists()
                    || DB::table('business_branches')->where('owner_id',$user->id)->exists())) {
                throw \Illuminate\Validation\ValidationException::withMessages(['type'=>'Şubeye bağlı hesabın türü veya işletme kimliği değiştirilemez.']);
            }
            app(\App\Services\DemoLifecycle::class)->prepare($user);
            // Creation forms sometimes assign email before type; move it once type is known.
            if ($user->type === 'client' && !empty($user->attributes['email'])) {
                $user->attributes['contact_email'] = $user->attributes['email'];
                $user->attributes['email'] = null;
            }
        });
    }

    public function getEmailAttribute($value)
    {
        return ($this->attributes['type'] ?? null) === 'client'
            ? ($this->attributes['contact_email'] ?? $value) : $value;
    }

    public function setEmailAttribute($value): void
    {
        $value = is_string($value) ? trim($value) : $value;
        $value = $value === '' ? null : $value;
        if (($this->attributes['type'] ?? null) === 'client') {
            $this->attributes['contact_email'] = $value;
            $this->attributes['email'] = null;
        } else {
            $this->attributes['email'] = $value;
        }
    }


    public function sendPasswordResetNotification($token)
    {
        app(\App\Services\SecurityEmail::class)->passwordReset($this,$token);
    }

    public function sendEmailVerificationNotification()
    {
        if(!\App\Services\CommunicationOutbox::asynchronous()) { $this->notify(new \Illuminate\Auth\Notifications\VerifyEmail);return; }
        $url=\Illuminate\Support\Facades\URL::temporarySignedRoute('verification.verify',now()->addMinutes(60),['id'=>$this->getKey(),'hash'=>sha1($this->getEmailForVerification())]);
        sendEmailVerification($this->email,['name'=>$this->name,'url'=>$url,'subject'=>'E-posta adresinizi doğrulayın — sanayirandevu.com']);
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'type',
        'phone_number',
        'profile',
        'lang',
        'subscription',
        'subscription_expire_date',
        'parent_id',
        'is_active',
        'twofa_secret',
    ];


    protected $hidden = [
        'password',
        'remember_token',
        'twofa_secret',
        'twofa_recovery_codes',
    ];


    protected $casts = [
        'email_verified_at' => 'datetime',
        'twofa_recovery_codes' => 'array',
        'twofa_last_used_at' => 'integer',
        'twofa_required' => 'boolean',
    ];

    public function getTwofaSecretAttribute($value)
    {
        if ($value === null || $value === '') return null;
        return preg_match('/^[A-Z2-7]+$/D', $value) ? $value : \Illuminate\Support\Facades\Crypt::decryptString($value);
    }

    public function setTwofaSecretAttribute($value): void
    {
        $this->attributes['twofa_secret'] = empty($value) ? null : \Illuminate\Support\Facades\Crypt::encryptString($value);
    }

    public function hasSuspendedSubscription(): bool
    {
        if ($this->type === 'super admin') { return false; }
        $owner = $this->type === 'owner' ? static::find($this->id) : static::where('type','owner')->find($this->parent_id);
        return $owner && $owner->subscription_suspended_at !== null;
    }

    public function canImpersonate()
    {
        // Example: Only admins can impersonate others
        return $this->type == 'super admin';
    }

    public function totalUser()
    {
        return User::whereNotIn('type', ['employee', 'client'])->where('parent_id', $this->id)->count();
    }
    public function totalTenant()
    {
        return User::where('type', 'tenant')->where('parent_id', $this->id)->count();
    }

    public function totalContact()
    {
        return Contact::where('parent_id', '=', parentId())->count();
    }

    public function roleWiseUserCount($role)
    {
        return User::where('type', $role)->where('parent_id', parentId())->count();
    }

    public static function getDevice($user)
    {
        $mobileType = '/(?:phone|windows\s+phone|ipod|blackberry|(?:android|bb\d+|meego|silk|googlebot) .+? mobile|palm|windows\s+ce|opera mini|avantgo|mobilesafari|docomo)/i';
        $tabletType = '/(?:ipad|playbook|(?:android|bb\d+|meego|silk)(?! .+? mobile))/i';
        if (preg_match_all($mobileType, $user)) {
            return 'mobile';
        } else {
            if (preg_match_all($tabletType, $user)) {
                return 'tablet';
            } else {
                return 'desktop';
            }
        }
    }

    public function totalClient()
    {
        return User::where('type', 'client')->whereNull('client_archived_at')->where('parent_id', '=', $this->id)->count();
    }
    public function totalEmployee()
    {
        return User::where('type', 'employee')->where('parent_id', '=', $this->id)->count();
    }
    public static function genderList()
    {
        return [
            'Male' => __('Male'),
            'Female' => __('Female'),
        ];
    }

    public function clients()
    {
        return $this->hasOne('App\Models\Client', 'user_id', 'id');
    }

    public function employees()
    {
        return $this->hasOne('App\Models\Employee', 'user_id', 'id');
    }

    public function subscriptions()
    {
        return $this->hasOne('App\Models\Subscription', 'id', 'subscription');
    }

    public static $systemModules = [
        'user',
        'report',
        'vehicle',
        'service',
        'client',
        'employee',
        'tax',
        'item',
        'invoice',
        'expense',
        'quotation',
        'unit',
        'pricing transation',
        'contact',
        'note',
        'logged history',
        'settings'
    ];

    public function SubscriptionLeftDay()
    {
        $Subscription = Subscription::find($this->subscription);
        if ($Subscription->interval == 'Unlimited') {
            $return = '<span class="text-success">' . __('Unlimited Days Left') . '</span>';
        } else {
            $date1 = date_create(date('Y-m-d'));
            $date2 = date_create($this->subscription_expire_date);
            $diff = date_diff($date1, $date2);
            $days = $diff->format("%R%a");
            if ($days > 0) {
                $return = '<span class="text-success">' . $days . __(' Days Left') . '</span>';
            } else {
                $return = '<span class="text-danger">' . $days . __(' Days Left') . '</span>';
            }
        }
        return $return;
    }
}
