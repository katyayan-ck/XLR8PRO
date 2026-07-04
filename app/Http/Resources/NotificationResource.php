<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="NotificationResource",
 *     type="object",
 *     title="Notification Resource",
 *     @OA\Property(property="id", type="integer", description="Notification ID"),
 *     @OA\Property(property="type", type="string", description="Notification type"),
 *     @OA\Property(property="title", type="string", description="Title"),
 *     @OA\Property(property="description", type="string", description="Description"),
 *     @OA\Property(property="priority", type="string", description="Priority level"),
 *     @OA\Property(property="category", type="string", description="Category"),
 *     @OA\Property(property="is_read", type="boolean", description="Read status"),
 *     @OA\Property(property="read_at", type="string", format="date-time", description="Read timestamp (ISO 8601)"),
 *     @OA\Property(property="sent_at", type="string", format="date-time", description="Sent timestamp (ISO 8601)"),
 *     @OA\Property(property="is_sent_via_fcm", type="boolean", description="Sent via FCM"),
 *     @OA\Property(property="reference_type", type="string", description="Reference type"),
 *     @OA\Property(property="reference_id", type="integer", description="Reference ID"),
 *     @OA\Property(property="deep_link", type="string", nullable=true, description="Deep link"),
 *     @OA\Property(
 *         property="sender",
 *         type="object",
 *         @OA\Property(property="id", type="integer", nullable=true, description="Sender ID"),
 *         @OA\Property(property="name", type="string", nullable=true, description="Sender name")
 *     ),
 *     @OA\Property(property="payload", type="object", additionalProperties=true, description="Payload data"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Created timestamp (ISO 8601)")
 * )
 */
class NotificationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'category' => $this->category,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'is_sent_via_fcm' => $this->is_sent_via_fcm,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'deep_link' => $this->getDeepLink(),
            'sender' => [
                'id' => $this->sender?->id,
                'name' => $this->sender?->name,
            ],
            'payload' => $this->payload,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
