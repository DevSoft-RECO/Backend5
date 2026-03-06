<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('confirmacion_asistencia', function (Blueprint $table) {
            $table->id();
            $table->integer('codigo_cliente')->index();
            $table->string('dpi', 20);
            $table->string('nombre_completo', 255);
            $table->string('ubicacion', 255);
            $table->integer('edad')->nullable();
            $table->string('genero', 20)->nullable();
            $table->dateTime('fecha_asistencia')->index();
            $table->enum('tipo_asistencia', ['sistema', 'manual'])->index();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('confirmacion_asistencia');
    }
};
