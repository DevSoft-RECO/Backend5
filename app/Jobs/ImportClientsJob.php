<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ImportClientsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $dates;
    protected $jobId;

    /**
     * Create a new job instance.
     *
     * @param string $filePath Absolute path to the file
     * @param array $dates ['desde' => 'YYYYMMDD', 'hasta' => 'YYYYMMDD', 'full' => bool]
     * @param string $jobId Unique identifier for cache tracking
     */
    public function __construct($filePath, $dates, $jobId)
    {
        $this->filePath = $filePath;
        $this->dates = $dates;
        $this->jobId = $jobId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Debug Log Start
        file_put_contents(storage_path('logs/job_debug.log'), date('Y-m-d H:i:s') . " INFO: Job Started. ID: {$this->jobId}, Path: {$this->filePath}\n", FILE_APPEND);

        $cacheKey = "import_job_{$this->jobId}";

        try {
            if (!file_exists($this->filePath)) {
                throw new \Exception("El archivo no existe: {$this->filePath}");
            }

            Cache::put($cacheKey, [
                'status' => 'processing',
                'progress' => 0,
                'current_row' => 0,
                'processed' => 0,
                'skipped' => 0,
                'message' => 'Iniciando lectura de archivo...'
            ], 3600); // 1 hora TTL

            $file = fopen($this->filePath, 'r');
            $batchSize = 250;
            $batchData = [];
            $totalProcessed = 0;
            $totalSkipped = 0;
            $currentRow = 0;

            // Count total lines for percentage
            $totalLines = 0;
            $handle = fopen($this->filePath, "r");
            while(!feof($handle)){
                $line = fgets($handle);
                if ($line !== false) $totalLines++;
            }
            fclose($handle);
            $totalLines = max($totalLines - 1, 1); // Subtract header, ensure non-zero

            $desde = $this->dates['desde'] ?? null;
            $hasta = $this->dates['hasta'] ?? ($desde ?: null);
            $full = $this->dates['full'] ?? false;

            while (($row = fgetcsv($file, 0, ";")) !== FALSE) {
                $currentRow++;

                // Skip Header
                if (!is_numeric($row[0])) {
                    continue;
                }

                // Filtering Logic
                if (!$full) {
                    $fechaRow = isset($row[1]) ? trim($row[1]) : null;
                    if (!$fechaRow || $fechaRow < $desde || $fechaRow > $hasta) {
                        $totalSkipped++;

                        if ($currentRow % 1000 == 0) {
                            $this->updateProgress($cacheKey, $totalProcessed, $totalSkipped, "Filtrando registros... (Fila $currentRow)", $totalLines);
                        }
                        continue;
                    }
                }

                // Mapping Data (Extracted from Command logic)
                $data = $this->mapRow($row);
                $batchData[] = $data;

                if (count($batchData) >= $batchSize) {
                    $this->processBatch($batchData);
                    $totalProcessed += count($batchData);
                    $batchData = [];

                    $this->updateProgress($cacheKey, $totalProcessed, $totalSkipped, "Procesando registros... ($totalProcessed insertados)", $totalLines);
                }
            }

            // Insert remaining
            if (count($batchData) > 0) {
                $this->processBatch($batchData);
                $totalProcessed += count($batchData);
            }

            fclose($file);

            // Cleanup: Delete file after successful processing
            if (file_exists($this->filePath)) {
                @unlink($this->filePath);
            }

            // Final Success State
            Cache::put($cacheKey, [
                'status' => 'completed',
                'progress' => 100,
                'processed' => $totalProcessed,
                'skipped' => $totalSkipped,
                'message' => "Importación completada. $totalProcessed registros actualizados."
            ], 3600);

        } catch (\Throwable $e) {
            $msg = "Import Job Failed: " . $e->getMessage() . "\nStack: " . $e->getTraceAsString();
            Log::error($msg);

            // Fallback logging
            file_put_contents(storage_path('logs/job_debug.log'), date('Y-m-d H:i:s') . " ERROR: $msg\n", FILE_APPEND);

            Cache::put($cacheKey, [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'message' => 'Error durante la importación.'
            ], 3600);

            throw $e;
        }
    }

    private function updateProgress($key, $processed, $skipped, $message, $totalLines)
    {
        $totalProcessed = $processed + $skipped;
        $percentage = ($totalLines > 0) ? round(($totalProcessed / $totalLines) * 100) : 0;

        Cache::put($key, [
            'status' => 'processing',
            'progress' => $percentage,
            'processed' => $processed,
            'skipped' => $skipped,
            'message' => $message
        ], 3600);
    }

    private function processBatch(array $batchData)
    {
        if (empty($batchData)) return;

        $firstItem = $batchData[0];
        $columnsToUpdate = array_keys($firstItem);
        $columnsToUpdate = array_diff($columnsToUpdate, ['codigo_cliente', 'created_at']);

        DB::table('clientes')->upsert(
            $batchData,
            ['codigo_cliente'],
            $columnsToUpdate
        );
    }

    // --- Helpers from Command (Refactored) ---
    private function mapRow($row)
    {
        return [
            'codigo_cliente'      => $this->val($row, 0),
            'actualizacion'       => $this->dateVal($row, 1),
            'nombre1'             => $this->val($row, 2),
            'nombre2'             => $this->val($row, 3),
            'nombre3'             => $this->val($row, 4),
            'apellido1'           => $this->val($row, 5),
            'apellido2'           => $this->val($row, 6),
            'celular'             => $this->val($row, 7),
            'genero'              => $this->val($row, 8),
            'tipo_cliente'        => $this->val($row, 9),
            'fecha_nacimiento'    => $this->dateVal($row, 10),
            'dpi'                 => $this->val($row, 11),
            'depto_domicilio'     => $this->val($row, 12),
            'muni_domicilio'      => $this->val($row, 13),
            'edad'                => $this->intVal($row, 14),
            'saldo_aportaciones'  => $this->decVal($row, 15),
            'saldo_ahorros'       => $this->decVal($row, 16),
            'created_at'          => now(),
            'updated_at'          => now(),
        ];
    }

    private function val($row, $index)
    {
        return isset($row[$index]) && trim($row[$index]) !== '' ? trim($row[$index]) : null;
    }

    private function intVal($row, $index)
    {
        $val = $this->val($row, $index);
        return $val !== null ? (int)$val : null;
    }

    private function decVal($row, $index)
    {
        $val = $this->val($row, $index);
        return $val !== null ? (float)$val : null;
    }

    private function dateVal($row, $index)
    {
        $val = $this->val($row, $index);
        if (!$val) return null;
        if (strlen($val) === 8 && is_numeric($val)) {
            $year = substr($val, 0, 4);
            $month = substr($val, 4, 2);
            $day = substr($val, 6, 2);
            return "$year-$month-$day";
        }
        return null;
    }
}
