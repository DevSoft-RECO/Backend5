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
        Schema::create('datos_colocacion', function (Blueprint $table) {
            $table->id();
            $table->integer('cliente')->index();
            $table->string('numerodocumento', 50)->unique();
            $table->integer('diasmora')->default(0);
            $table->decimal('saldocapital', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datos_colocacion');
    }
};
