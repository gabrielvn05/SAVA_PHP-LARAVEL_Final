<?php

namespace App\Services;

use App\Models\AccountRequest;
use App\Models\AuditLog;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function log(string $action, Model $model, ?array $oldData = null): void
    {
        AuditLog::create([
            'table_name' => $model->getTable(),
            'record_id' => (string) $model->getKey(),
            'action' => $action,
            'changed_by' => Auth::id(),
            'changed_at' => now(),
            'old_data' => $oldData,
            'new_data' => $model->toArray(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
