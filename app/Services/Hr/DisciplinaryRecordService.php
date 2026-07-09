<?php

namespace App\Services\Hr;

use App\Models\DisciplinaryRecord;
use App\Services\Audit\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

class DisciplinaryRecordService
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

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

        $this->activityLogger->log(
            'disciplinary.created',
            $record,
            "Enregistrement disciplinaire ({$data['type']}) créé",
            userId: $issuedBy,
        );

        return $record->fresh(['employable', 'issuedBy']);
    }
}
