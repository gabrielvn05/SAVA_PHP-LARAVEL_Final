<?php

namespace App\Http\Controllers;

use App\Enums\AppRole;
use App\Services\DashboardStatsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardStatsService $statsService) {}

    public function index(): View
    {
        $user = auth()->user();

        if ($user->rol === AppRole::Superusuario) {
            return view('dashboard.superusuario');
        }

        $stats = $this->statsService->forUser($user);

        return view('dashboard.index', compact('user', 'stats'));
    }
}
