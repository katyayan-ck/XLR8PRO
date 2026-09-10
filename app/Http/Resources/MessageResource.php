<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="MessageResource",
 *     type="object",
 *     title="Message Resource",
 *     @OA\Property(property="id", type="integer", description="Message ID"),
 *     @OA\Property(
 *         property="sender",
 *         type="object",
 *         @OA\Property(property="id", type="integer", description="Sender ID"),
 *         @OA\Property(property="name", type="string", description="Sender name"),
 *         @OA\Property(property="avatar", type="string", nullable=true, description="Sender avatar URL")
 *     ),
 *     @OA\Property(
 *         property="receiver",
 *         type="object",
 *         @OA\Property(property="id", type="integer", description="Receiver ID"),
 *         @OA\Property(property="name", type="string", description="Receiver name")
 *     ),
 *     @OA\Property(property="message_text", type="string", description="Message text"),
 *     @OA\Property(property="message_type", type="string", description="Message type"),
 *     @OA\Property(property="is_read", type="boolean", description="Read status"),
 *     @OA\Property(property="read_at", type="string", format="date-time", description="Read timestamp (ISO 8601)"),
 *     @OA\Property(property="attachments", type="array", @OA\Items(type="string"), description="Attachments"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Created timestamp (ISO 8601)"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Updated timestamp (ISO 8601)")
 * )
 */
class MessageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'avatar' => $this->sender->avatar_url ?? null,
            ],
            'receiver' => [
                'id' => $this->receiver->id,
                'name' => $this->receiver->name,
            ],
            'message_text' => $this->message_text,
            'message_type' => $this->message_type,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at?->toIso8601String(),
            'attachments' => $this->attachments,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
