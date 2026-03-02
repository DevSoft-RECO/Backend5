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
        Schema::create('resultados_votos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('urna_id')->constrained('urnas')->onDelete('cascade');
            $table->foreignId('candidato_id')->constrained('candidatos')->onDelete('cascade');
            $table->integer('votos')->default(0);
            $table->timestamps();

            // Un candidato solo puede tener un registro de votos por urna
            $table->unique(['urna_id', 'candidato_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resultados_votos');
    }
};
