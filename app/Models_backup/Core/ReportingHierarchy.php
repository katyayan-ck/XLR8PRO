<?php

namespace App\Models\Core;

use App\Models\BaseModel;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use Kalnoy\Nestedset\NodeTrait; // Install baum/baum for NestedSet

class ReportingHierarchy extends BaseModel
{
    use CrudTrait;
    use HasFactory;
    use NodeTrait;

    protected $fillable = [
        'user_id',
        'supervisor_id',
        'topic',
        'combo_json',
        'is_active'
    ];

    protected $casts = [
        'combo_json' => 'array',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    // Get subtree user IDs for topic/combo
    public function getSubtreeUserIds(string $topic, array $combo): array
    {
        return $this->descendantsAndSelf()
            ->where('topic', $topic)
            ->whereJsonContains('combo_json', $combo)
            ->pluck('user_id')
            ->unique()
            ->toArray();
    }
}
