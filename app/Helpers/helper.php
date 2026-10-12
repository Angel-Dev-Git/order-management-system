<?php
use App\Models\AuditLog;
use Illuminate\Http\Request;

if (! function_exists('auditLog')) {
    function auditLog(int $userId, string $action, $modelType, $modelId, $oldValues = null, $newValues = null)       
    {
        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_values' => $oldValues ?? null,
            'new_values' => $newValues ?? null,
        ]);
    }   
}

if(!function_exists('ensureAdmin')){
    function ensureAdmin(Request $request): void
    {
        $user = $request->user();

        abort_unless($user, 401, 'Unauthenticated.');

        abort_unless(
            strtolower($user->role?->name ?? '') === 'admin',
            403,
            'Only administrators can access this resource.'
        );
    }
}
