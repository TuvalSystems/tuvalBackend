<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
