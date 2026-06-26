<?php

namespace App\Http\Controllers;

use App\Services\RouletteRewardService;
use Illuminate\Http\Request;

class RouletteController extends Controller
{
    public function __construct(private RouletteRewardService $rouletteRewardService)
    {
    }

    public function active(Request $request, int $userId)
    {
        return response()->json([
            'status' => true,
            'reward' => $this->rouletteRewardService->format(
                $this->rouletteRewardService->activeForUser($userId)
            ),
        ]);
    }

    public function spin(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $reward = $this->rouletteRewardService->spin((int) $data['user_id']);

        return response()->json([
            'status' => true,
            'message' => 'Premio generado correctamente.',
            'reward' => $this->rouletteRewardService->format($reward),
        ], 201);
    }
}
