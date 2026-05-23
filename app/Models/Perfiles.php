<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Perfiles
 *
 * @property int $id
 * @property string|null $name
 * @property string $state
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|Perfiles newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Perfiles newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Perfiles query()
 * @method static \Illuminate\Database\Eloquent\Builder|Perfiles whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Perfiles whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Perfiles whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Perfiles whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Perfiles whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Perfiles extends Model
{
    use HasFactory;

    protected $table = 'perfiles';

    protected $fillable =  [
        'name',
        'platform',
        'state',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const PLATFORM_WEB = 'web';
    public const PLATFORM_APP = 'app';

    public const STATE_ACTIVE = 'active';
    public const STATE_INACTIVE = 'inactive';



    public static function platforms(): array
    {
        return [
            self::PLATFORM_WEB,
            self::PLATFORM_APP
        ];
    }

    public static function states(): array
    {
        return [
            self::STATE_ACTIVE,
            self::STATE_INACTIVE
        ];
    }

    public function sections()
    {
        return $this->belongsToMany(
            Section::class,
            'perfil_sections',
            'perfil_id',
            'section_id'
        )
            ->withPivot([
                'home',
                'state',
            ])->withTimestamps();
    }

    public function users(): HasMany
    {
        return $this->hasMany(GpUsuario::class, 'perfil_id', 'id');
    }

    public function usersApp(): HasMany
    {
        return $this->hasMany(GpUsuario::class, 'perfil_app_id', 'id');
    }

    public function scopePlatform(Builder $query, ?string $platform): Builder
    {
        return $query->when($platform, function ($q) use ($platform) {
            $q->where('platform', $platform);
        });
    }

    public function scopeState(Builder $query, ?string $state = null): Builder
    {
        if (!filled($state) || in_array($state, ['all', 'todos', 'null', 'undefined'], true)) {
            return $query;
        }

        return $query->where('state', $state);
    }
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('state', self::STATE_ACTIVE);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function ($q) use ($search) {
            $q->where('name', 'like', '%' . trim($search) . '%');
        });
    }

    public function isActive(): bool
    {
        return $this->state === self::STATE_ACTIVE;
    }

    public function isWeb(): bool
    {
        return $this->platform === self::PLATFORM_WEB;
    }

    public function isApp(): bool
    {
        return $this->platform === self::PLATFORM_APP;
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = trim($value);
    }

    public function setPlatformAttribute(string $value): void
    {
        $this->attributes['platform'] = strtolower(trim($value));
    }

    public function setStateAttribute(string $value): void
    {
        $this->attributes['state'] = strtolower(trim($value));
    }
}
