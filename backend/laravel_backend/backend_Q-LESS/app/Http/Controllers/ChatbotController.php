<?php

namespace App\Http\Controllers;

use App\Models\ChatbotConversation;
use App\Models\User;
use App\Services\ChatbotRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
    public function __construct(private ChatbotRecommendationService $chatbotRecommendationService)
    {
    }

    public function recommend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|min:3|max:1000',
            'user_id' => 'required|integer|exists:users,id',
            'conversation_id' => 'nullable|integer|exists:chatbot_conversations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'La consulta del chatbot no es valida.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $conversation = $this->findOrCreateConversation(
            (int) $data['user_id'],
            $data['conversation_id'] ?? null,
            $data['message']
        );

        $recommendation = $this->chatbotRecommendationService->recommend($data['message']);
        $recommendation['conversation_id'] = $conversation->id;

        DB::transaction(function () use ($conversation, $data, $recommendation) {
            $conversation->messages()->create([
                'role' => 'user',
                'text' => $data['message'],
            ]);

            $conversation->messages()->create([
                'role' => 'bot',
                'reply' => $recommendation,
            ]);

            $conversation->touch();
        });

        return response()->json($recommendation);
    }

    public function conversations(int $userId)
    {
        User::findOrFail($userId);

        $conversations = ChatbotConversation::query()
            ->where('user_id', $userId)
            ->with('messages')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (ChatbotConversation $conversation) => $this->conversationSummary($conversation));

        return response()->json([
            'status' => true,
            'conversations' => $conversations,
        ]);
    }

    public function showConversation(int $userId, int $conversationId)
    {
        User::findOrFail($userId);

        $conversation = ChatbotConversation::query()
            ->where('user_id', $userId)
            ->with('messages')
            ->findOrFail($conversationId);

        return response()->json([
            'status' => true,
            'conversation' => $this->conversationPayload($conversation),
        ]);
    }

    public function destroyConversation(int $userId, int $conversationId)
    {
        User::findOrFail($userId);

        $conversation = ChatbotConversation::query()
            ->where('user_id', $userId)
            ->findOrFail($conversationId);

        $conversation->delete();

        return response()->json([
            'status' => true,
            'message' => 'Conversacion eliminada correctamente.',
        ]);
    }

    private function findOrCreateConversation(int $userId, ?int $conversationId, string $message): ChatbotConversation
    {
        if ($conversationId) {
            return ChatbotConversation::query()
                ->where('user_id', $userId)
                ->findOrFail($conversationId);
        }

        return ChatbotConversation::create([
            'user_id' => $userId,
            'title' => Str::limit($message, 70, '...'),
        ]);
    }

    private function conversationSummary(ChatbotConversation $conversation): array
    {
        $lastMessage = $conversation->messages->last();

        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'updatedAt' => optional($conversation->updated_at)->toISOString(),
            'preview' => $lastMessage?->text
                ?? ($lastMessage?->reply['summary'] ?? 'Conversacion con la IA'),
        ];
    }

    private function conversationPayload(ChatbotConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'updatedAt' => optional($conversation->updated_at)->toISOString(),
            'messages' => $conversation->messages
                ->map(fn ($message) => [
                    'role' => $message->role,
                    'text' => $message->text,
                    'reply' => $message->reply,
                ])
                ->values(),
        ];
    }
}
