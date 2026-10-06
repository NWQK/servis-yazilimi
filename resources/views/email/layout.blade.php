<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $subject ?? 'SanayiRandevu' }}</title></head>
<body style="margin:0;background:#f4f4f5;color:#18181b;font-family:Arial,Helvetica,sans-serif">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border:1px solid #e4e4e7">
<tr><td style="padding:28px;background:#111;color:#fff;border-bottom:5px solid #ed001b;font-size:25px;font-weight:bold">@if(!empty($content['brand_logo']))<img src="{{ $content['brand_logo'] }}" alt="sanayirandevu.com" width="346" height="48" style="display:block;width:100%;max-width:346px;height:auto;border:0">@else sanayi<span style="color:#ff334b">randevu</span><span style="font-size:14px">.com</span>@endif</td></tr>
<tr><td style="padding:28px;font-size:16px;line-height:1.7;word-break:break-word">
<h1 style="font-size:21px;line-height:1.4;margin:0 0 24px">{{ $subject ?? 'Bilgilendirme' }}</h1>
@yield('message')
</td></tr>
<tr><td style="padding:20px 28px;background:#fafafa;border-top:1px solid #e4e4e7;font-size:12px;color:#71717a">{{ $company_name ?? 'SanayiRandevu' }}<br>Bu e-posta işlem bilgilendirmesi amacıyla gönderilmiştir.</td></tr>
</table></td></tr></table></body></html>
