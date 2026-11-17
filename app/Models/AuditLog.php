<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Maravel\Models\ModelBase;

/**
 * Journal d'audit des actions système (kill process, restart service, etc.).
 *
 * Les entrées sont créées via AuditLog::record() — jamais modifiées ni
 * supprimées par l'application (append-only).
 */
class AuditLog extends ModelBase
{
    protected $fillable = [
        'user_id',
        'action',
        'target',
        'details',
        'status',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    /**
     * Formatage automatique des dates (ModelTrait) : ajoute created_at_fr.
     */
    protected $dateCasts = [
        'created_at' => 'd/m/Y H:i:s',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Enregistre une action dans l'audit trail.
     */
    public static function record(
        string $action,
        ?string $target = null,
        array $details = [],
        string $status = 'success',
        ?User $user = null,
    ): self {
        $request = request();

        return static::create([
            'user_id' => $user?->id ?? $request?->user()?->id,
            'action' => $action,
            'target' => $target,
            'details' => $details ?: null,
            'status' => $status,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
