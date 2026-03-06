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
        Schema::table('confirmacion_asistencia', function (Blueprint $table) {
            $table->string('usuario_registro', 100)->nullable()->after('observacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('confirmacion_asistencia', function (Blueprint $table) {
            $table->dropColumn('usuario_registro');
        });
    }
};
