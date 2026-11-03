<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Lecture;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $upcomingEvents = Event::query()->whereNull('archived_at')->where('ends_at', '>=', now());

        return view('dashboard', [
            'activeNav' => 'dashboard',
            'lectureCount' => Lecture::query()->where('creator_id', $request->user()->id)->where('is_active', true)->whereNull('archived_at')->count(),
            'publicLectureCount' => Lecture::query()->where('visibility', 'public')->where('is_active', true)->whereNull('archived_at')->count(),
            'upcomingEventCount' => (clone $upcomingEvents)->count(),
            'upcomingEvents' => $upcomingEvents->with(['lecture:id,title', 'creator:id,name', 'lecturerUser:id,name'])->orderBy('starts_at')->limit(5)->get(),
        ]);
    }
}
