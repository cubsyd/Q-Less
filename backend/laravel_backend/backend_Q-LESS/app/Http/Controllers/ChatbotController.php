<?php

namespace App\Http\Controllers;

use App\Services\ChatbotRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatbotController extends Controller
{
    public function __construct(private ChatbotRecommendationService $chatbotRecommendationService)
    {
    }

    public function recommend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|min:3|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'La consulta del chatbot no es valida.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        return response()->json(
            $this->chatbotRecommendationService->recommend($data['message'])
        );
    }
}
