<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="AlertResource",
 *     type="object",
 *     title="Alert Resource",
 *     @OA\Property(property="id", type="integer", description="Alert ID"),
 *     @OA\Property(property="severity", type="string", description="Severity level"),
 *     @OA\Property(property="title", type="string", description="Title"),
 *     @OA\Property(property="description", type="string", description="Description"),
 *     @OA\Property(property="is_read", type="boolean", description="Read status"),
 *     @OA\Property(property="read_at", type="string", format="date-time", description="Read timestamp (ISO 8601)"),
 *     @OA\Property(property="reference_type", type="string", description="Reference type"),
 *     @OA\Property(property="reference_id", type="integer", description="Reference ID"),
 *     @OA\Property(property="deep_link", type="string", nullable=true, description="Deep link"),
 *     @OA\Property(
 *         property="sender",
 *         type="object",
 *         @OA\Property(property="id", type="integer", nullable=true, description="Sender ID"),
 *         @OA\Property(property="name", type="string", nullable=true, description="Sender name")
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Created timestamp (ISO 8601)")
 * )
 */
class AlertResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'severity' => $this->severity,
            'title' => $this->title,
            'description' => $this->description,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at?->toIso8601String(),
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'deep_link' => $this->getDeepLink(),
            'sender' => [
                'id' => $this->sender?->id,
                'name' => $this->sender?->name,
            ],
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
