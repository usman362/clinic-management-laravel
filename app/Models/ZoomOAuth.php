<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZoomOAuth extends Model
{
    use HasFactory;

    protected $table = 'zoom_o_auth_credentials';

    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
    ];

    /**
     * NOTE: access_token and refresh_token are encrypted at rest.
     * Existing rows must be re-encrypted by re-OAuthing the integration.
     * Old plaintext tokens will fail to decrypt.
     */
    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
    ];
}
