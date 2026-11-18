<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Domains\Communication\Models\Conversation;
use App\Domains\Communication\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    /**
     * List user's active conversations
     */
    public function listConversations(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $conversations = Conversation::whereHas('participants', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->with(['participants.user', 'messages' => fn($q) => $q->latest('created_at')->limit(1)])
        ->latest('updated_at')
        ->get();

        return $this->successResponse($conversations);
    }

    /**
     * Get messages for a conversation
     */
    public function getMessages(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->id;

        $conversation = Conversation::where('id', $id)
            ->whereHas('participants', fn($q) => $q->where('user_id', $userId))
            ->firstOrFail();

        $messages = Message::where('conversation_id', $conversation->id)
            ->with(['sender', 'attachments'])
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        return $this->successResponse($messages);
    }

    /**
     * Send a new message
     */
    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $userId = $request->user()->id;

        $conversation = Conversation::where('id', $id)
            ->whereHas('participants', fn($q) => $q->where('user_id', $userId))
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
            'message_type' => 'nullable|in:text,image,location,system_event',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('VALIDATION_ERROR', 'Invalid message content.', $validator->errors(), 422);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userId,
            'message_type' => $request->input('message_type', 'text'),
            'content' => $request->input('content'),
        ]);

        // In production with Reverb: broadcast(new MessageSentEvent($message))->toOthers();

        return $this->successResponse($message->load('sender'), 'Message sent.', [], 201);
    }
}
