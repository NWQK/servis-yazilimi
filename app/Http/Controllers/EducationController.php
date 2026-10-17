<?php

namespace App\Http\Controllers;

class EducationController extends Controller
{
    public function index()
    {
        abort_unless(isBusinessOwner() || auth()->user()->type === 'super admin', 403);
        $groups = config('education.groups');
        $topics = collect(config('education.topics'))->map(function ($topic) {
            $topic['links'] = collect($topic['links'] ?? [])->filter(fn ($link) =>
                (!($link['owner_only'] ?? false) || isBusinessOwner()) &&
                (empty($link['permission']) || auth()->user()->can($link['permission']))
            )->map(fn ($link) => ['label' => $link['label'], 'url' => route($link['route'], $link['params'] ?? [])])->all();
            return $topic;
        });
        return view('education.index', compact('groups', 'topics'));
    }
}
