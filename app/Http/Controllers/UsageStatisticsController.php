<?php

namespace App\Http\Controllers;

use App\Services\UsageStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UsageStatisticsController extends Controller
{
    public function __invoke(Request $request, UsageStatistics $statistics)
    {
        abort_unless(UsageStatistics::accessible(), 403);
        $dates = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $start = $dates['start_date'] ?? now()->startOfMonth()->toDateString();
        $end = $dates['end_date'] ?? now()->toDateString();
        if ($start > $end || CarbonImmutable::parse($start)->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['end_date' => 'Başlangıç ve bitiş tarihini en fazla bir yıllık aralık olacak şekilde seçin.']);
        }
        $report = $statistics->report((int) parentId(), $start, $end);
        return response()->view('report.usage', compact('report', 'start', 'end'))->header('Cache-Control', 'no-store, private');
    }
}
