<?php

namespace App\Models\Admin\Secciones;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Admin\Secciones\Section
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string|null $to
 * @property string|null $url
 * @property string $name
 * @property string|null $icon
 * @property int|null $order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|Section newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Section newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Section query()
 * @method static \Illuminate\Database\Eloquent\Builder|Section whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Section whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Section whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Section whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Section whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Section whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Section whereTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Section whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Section whereUrl($value)
 * @mixin \Eloquent
 */
class Section extends Model
{
    use HasFactory;



    protected $fillable = [
        'parent_id',
        'route_name',
        'url',
        'name',
        'icon',
        'sort_order',
        'platform',
        'status',
    ];

    protected $casts = [
        'id'            => 'integer',
        'parent_id'     => 'integer',
        'sort_order'    => 'integer',
        'status'        => 'boolean',
    ];

    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }


    public function parent(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Section::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->whereIn('platform', [$platform, 'both']);
    }
}
