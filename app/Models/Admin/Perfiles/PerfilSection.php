<?php

namespace App\Models\Admin\Perfiles;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Admin\Perfiles\PerfilSection
 *
 * @property int $id
 * @property int|null $section_id
 * @property int|null $perfil_id
 * @property string $home
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|PerfilSection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PerfilSection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PerfilSection query()
 * @method static \Illuminate\Database\Eloquent\Builder|PerfilSection whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PerfilSection whereHome($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PerfilSection whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PerfilSection wherePerfilId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PerfilSection whereSectionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PerfilSection whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PerfilSection extends Model
{
    use HasFactory;
}
