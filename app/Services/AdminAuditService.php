<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AdminAuditService
{
    public function record(string $action, string $table, int|string|null $recordId, string $description): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => auth()->id(),
            'action' => $action,
            'table_name' => $table,
            'record_id' => $recordId,
            'description' => $description,
            'ip_address' => request()->route() ? request()->ip() : null,
            'created_at' => now(),
        ]);
    }
}
