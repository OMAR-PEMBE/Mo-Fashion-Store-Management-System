<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $service)
    {
        return response()->view('home', $service->overview($request->user()))->header('Cache-Control', 'private, no-store');
    }
}
