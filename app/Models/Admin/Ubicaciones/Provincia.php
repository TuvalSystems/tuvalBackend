<?php

namespace App\Models\Admin\Ubicaciones;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provincia extends Model
{
    use HasFactory;

    protected $table = 'provincias';
    protected $fillable = [
        'region_id',
        'codigo_inec',
        'nombre',
    ];
    protected $casts = [
        'created_at'            => 'datetime:Y-m-d H:i:s',
        'updated_at'            => 'datetime:Y-m-d H:i:s',
    ];


    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function cantones(): HasMany
    {
        return $this->hasMany(Canton::class);
    }
}
