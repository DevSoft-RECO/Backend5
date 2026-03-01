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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();

            // 1. Codigo Cliente (integer) - Obligatorio (Unique)
            $table->integer('codigo_cliente')->unique()->index();

            // 2. Actualizacion (date) - Obligatorio
           $table->date('actualizacion')->nullable()->index();

            // 3. Nombre1 (string) - Puede ser Razon Social
            $table->string('nombre1', 150)->nullable();
            // 4. Nombre2 (string)
            $table->string('nombre2', 150)->nullable();
            // 5. Nombre3 (string)
            $table->string('nombre3', 150)->nullable();
            // 6. Apellido1 (string)
            $table->string('apellido1', 150)->nullable();
            // 7. Apellido2 (string)
            $table->string('apellido2', 150)->nullable();

            // 8. Celular (string)
            $table->string('celular', 50)->nullable();

            // 9. Genero (string)
            $table->string('genero', 20)->nullable();

            // 10. Tipo Cliente (string)
            $table->string('tipo_cliente', 50)->nullable();

            // 11. Fecha Nacimiento (date)
            $table->date('fecha_nacimiento')->nullable();

            // 12. Dpi (string)
            $table->string('dpi', 20)->nullable()->index();

            // 13. Depto Domicilio (string)
            $table->string('depto_domicilio', 100)->nullable();
            // 14. Muni Domicilio (string)
            $table->string('muni_domicilio', 100)->nullable();

            // 15. Edad (integer)
            $table->integer('edad')->nullable();

            // 16. Saldo_Aportaciones (decimal)
            $table->decimal('saldo_aportaciones', 18, 2)->default(0);

            // 17. Saldo_Ahorros (decimal)
            $table->decimal('saldo_ahorros', 18, 2)->default(0);

            $table->timestamps();

            // Índice compuesto para validaciones de vigencia
            $table->index(['codigo_cliente', 'actualizacion'], 'idx_cliente_actualizacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
