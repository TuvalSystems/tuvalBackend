<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGpReglaAprobacionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gp_regla_aprobacions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('regla_negocio_id')->nullable()->unsigned()->index();
            $t->unsignedBigInteger('user_autorizador_id')->nullable()->unsigned()->index();
            $t->decimal('descuento_min', 8, 2)->default(0)->nullable();
            $t->decimal('descuento_max', 8, 2)->default(0)->nullable();
            $t->enum('tipo_vendedor', ['v_externo', 'v_interno', 'mostrador', 'all'])->default('v_externo');
            $t->enum('zona', ['costa', 'sierra', 'all'])->default('costa');
            $t->foreign('user_autorizador_id')->references('id')->on('gp_usuarios');
            $t->foreign('regla_negocio_id')->references('id')->on('gp_regla_negocios');
            $t->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gp_regla_aprobacions');
    }
}
