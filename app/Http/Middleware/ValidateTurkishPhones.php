<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class ValidateTurkishPhones
{
    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->method(),['POST','PUT','PATCH'])) {
            $rules = [];
            if ($request->exists('phone_number')) $rules['phone_number'] = ['nullable','string','regex:/\A5[0-9]{9}\z/'];
            if ($request->exists('company_phone')) $rules['company_phone'] = ['nullable','string','regex:/\A[2-5][0-9]{9}\z/'];
            if ($rules) $request->validate($rules, ['phone_number.regex'=>'Cep telefonunu başında 0 olmadan, 5 ile başlayan 10 hane olarak yazın.', 'company_phone.regex'=>'İşletme telefonunu başında 0 olmadan 10 hane olarak yazın.']);
        }
        return $next($request);
    }
}
