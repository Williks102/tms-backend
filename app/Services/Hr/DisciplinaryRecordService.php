<?php

namespace App\Services\Hr;

use App\Models\DisciplinaryRecord;
use Illuminate\Database\Eloquent\Model;

class DisciplinaryRecordService
{
    public function create(Model $employable, array $data, int $issuedBy): DisciplinaryRecord
    {
        $record = DisciplinaryRecord::create([
            'employable_type' => $employable::class,
            'employable_id'   => $employable->getKey(),
            'type'            => $data['type'],
            'description'     => $data['description'],
            'issued_by'       => $issuedBy,
            'issued_at'       => now(),
        ]);

        return $record->fresh(['employable', 'issuedBy']);
    }
}
