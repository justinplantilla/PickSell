<?php

namespace App\Http\Controllers;

use App\Services\Admin\AdminDashboardService;

/** Admin operational command center (dashboard.view; each section also checks its own permission). */
class AdminDashboardController extends Controller
{
    public function index(AdminDashboardService $dashboard)
    {
        return view('admin.dashboard', $dashboard->build(auth()->user()));
    }
}
