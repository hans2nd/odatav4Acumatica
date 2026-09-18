<?php

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\AiMessage;

class ConversationContextService
{
    public function resolve(AiConversation $conversation): array
    {
        return (array) (($conversation->metadata ?? [])['analytical_context'] ?? []);
    }

    public function merge(AiConversation $conversation, array $incoming, bool $reset = false): array
    {
        $current = $reset ? [] : $this->resolve($conversation);
        $context = array_replace_recursive($current, array_filter($incoming, fn ($value) => $value !== null && $value !== []));
        $metadata = $conversation->metadata ?? [];
        $metadata['analytical_context'] = $context;
        $conversation->metadata = $metadata;
        $conversation->save();
        return $context;
    }

    public function compact(AiConversation $conversation, int $recentLimit = 6): array
    {
        $messages = AiMessage::where('conversation_id', $conversation->id)->latest()->take($recentLimit)->get()->reverse()
            ->map(fn (AiMessage $message) => ['role' => $message->role, 'content' => mb_substr($message->content, 0, 500)])->values()->all();
        $metadata = $conversation->metadata ?? [];
        return ['summary' => $metadata['conversation_summary'] ?? '', 'context' => $this->resolve($conversation), 'recent_messages' => $messages];
    }

    public function updateSummary(AiConversation $conversation): void
    {
        $context = $this->resolve($conversation);
        $bits = [];
        foreach (['domain', 'metric', 'dimensions', 'detail_dimensions', 'date_context', 'comparison', 'filters', 'current_drilldown', 'previous_insight'] as $key) {
            if (array_key_exists($key, $context)) $bits[] = $key . ': ' . json_encode($context[$key], JSON_UNESCAPED_UNICODE);
        }
        $metadata = $conversation->metadata ?? [];
        $metadata['conversation_summary'] = mb_substr(implode('; ', $bits), 0, 1500);
        $conversation->metadata = $metadata;
        $conversation->save();
    }
}
