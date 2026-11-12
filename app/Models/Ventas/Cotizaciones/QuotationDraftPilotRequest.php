<?php

namespace App\Models\Ventas\Cotizaciones;

use Illuminate\Database\Eloquent\Model;

class QuotationDraftPilotRequest extends Model
{
    protected $table = 'quotation_draft_pilot_requests';

    protected $fillable = ['user_id', 'request_id', 'payload_hash', 'response'];

    protected $casts = ['response' => 'array'];
}
