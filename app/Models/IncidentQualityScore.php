<?php
// ══════════════════════════════════════════════════════════════════════════
// app/Models/IncidentQualityScore.php
// ══════════════════════════════════════════════════════════════════════════
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentQualityScore extends Model
{
    protected $fillable = [
        'entity_type', 'entity_id', 'month',
        'incidents_count', 'critical_count', 'high_count',
        'total_financial_impact', 'quality_score', 'avg_resolution_hours',
    ];

    protected $casts = [
        'month'                  => 'date',
        'quality_score'          => 'decimal:2',
        'total_financial_impact' => 'decimal:2',
        'avg_resolution_hours'   => 'decimal:2',
    ];
}
