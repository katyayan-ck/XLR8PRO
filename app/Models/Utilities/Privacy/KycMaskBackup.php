<?php

namespace App\Models\Utilities\Privacy;

use Illuminate\Database\Eloquent\Model;

/**
 * Original value of one history cell masked by `privacy:mask-kyc-history` (D26, DEC-095 #19), kept encrypted with the
 * app key so `--restore` can put it back. Written and read only by `KycHistoryMaskingService`.
 *
 * @property int $id
 * @property string $source_table
 * @property int $source_id
 * @property string $source_column
 * @property string $original_encrypted
 */
class KycMaskBackup extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'xlr8_privacy_kyc_mask_backup';

    protected $fillable = ['source_table', 'source_id', 'source_column', 'original_encrypted'];
}
