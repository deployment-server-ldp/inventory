<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request, 'month');
        $query = ActivityLog::query()->with('user')->whereBetween('created_at', [$range->from, $range->to]);
        if ($q = $request->query('q')) {
            $query->where(fn ($w) => $w->where('description', 'like', self::like($q))->orWhere('reference', 'like', self::like($q)));
        }
        if ($u = $request->integer('user_id')) {
            $query->where('user_id', $u);
        }
        if ($m = $request->query('module')) {
            $query->where('module', $m);
        }
        if ($a = $request->query('action')) {
            $query->where('action', 'like', str_replace('*', '%', $a));
        }

        return view('admin.activity.index', [
            'logs' => $query->latest('id')->paginate($this->perPage($request))->withQueryString(),
            'range' => $range,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'modules' => ActivityLog::query()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module'),
        ]);
    }

    public function show(ActivityLog $log): View
    {
        return view('admin.activity.show', ['log' => $log->load('user')]);
    }
}
