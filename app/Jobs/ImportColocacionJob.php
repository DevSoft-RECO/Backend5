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

class ImportColocacionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $jobId;

    /**
     * Create a new job instance.
     *
     * @param string $filePath Absolute path to the file
     * @param string $jobId Unique identifier for cache tracking
     */
    public function __construct($filePath, $jobId)
    {
        $this->filePath = $filePath;
        $this->jobId = $jobId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $cacheKey = "import_colocacion_{$this->jobId}";

        try {
            if (!file_exists($this->filePath)) {
                throw new \Exception("El archivo no existe: {$this->filePath}");
            }

            Cache::put($cacheKey, [
                'status' => 'processing',
                'progress' => 0,
                'current_row' => 0,
                'processed' => 0,
                'message' => 'Iniciando lectura de archivo...'
            ], 3600);

            $file = fopen($this->filePath, 'r');
            $batchSize = 500;
            $batchData = [];
            $totalProcessed = 0;
            $currentRow = 0;

            // Count total lines
            $totalLines = 0;
            $handle = fopen($this->filePath, "r");
            while(!feof($handle)){
                $line = fgets($handle);
                if ($line !== false) $totalLines++;
            }
            fclose($handle);
            $totalLines = max($totalLines - 1, 1);

            while (($row = fgetcsv($file, 0, ";")) !== FALSE) {
                $currentRow++;

                // Skip Header (if first col is not numeric)
                if ($currentRow === 1 && !is_numeric($row[0])) {
                    continue;
                }

                $data = $this->mapRow($row);
                if ($data) {
                    $batchData[] = $data;
                }

                if (count($batchData) >= $batchSize) {
                    $this->processBatch($batchData);
                    $totalProcessed += count($batchData);
                    $batchData = [];

                    $this->updateProgress($cacheKey, $totalProcessed, "Procesando registros... ($totalProcessed insertados)", $totalLines);
                }
            }

            if (count($batchData) > 0) {
                $this->processBatch($batchData);
                $totalProcessed += count($batchData);
            }

            fclose($file);

            if (file_exists($this->filePath)) {
                @unlink($this->filePath);
            }

            Cache::put($cacheKey, [
                'status' => 'completed',
                'progress' => 100,
                'processed' => $totalProcessed,
                'message' => "Importación completada. $totalProcessed registros actualizados."
            ], 3600);

        } catch (\Throwable $e) {
            Log::error("Colocacion Import Failed: " . $e->getMessage());
            Cache::put($cacheKey, [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'message' => 'Error durante la importación.'
            ], 3600);
            throw $e;
        }
    }

    private function updateProgress($key, $processed, $message, $totalLines)
    {
        $percentage = ($totalLines > 0) ? round(($processed / $totalLines) * 100) : 0;
        Cache::put($key, [
            'status' => 'processing',
            'progress' => min($percentage, 99),
            'processed' => $processed,
            'message' => $message
        ], 3600);
    }

    private function processBatch(array $batchData)
    {
        if (empty($batchData)) return;

        $firstItem = $batchData[0];
        $columnsToUpdate = array_keys($firstItem);
        $columnsToUpdate = array_diff($columnsToUpdate, ['numerodocumento', 'created_at']);

        DB::table('datos_colocacion')->upsert(
            $batchData,
            ['numerodocumento'],
            $columnsToUpdate
        );
    }

    private function mapRow($row)
    {
        if (count($row) < 4) return null;

        return [
            'cliente'         => $this->intVal($row, 0),
            'numerodocumento' => $this->val($row, 1),
            'diasmora'        => $this->intVal($row, 2),
            'saldocapital'    => $this->decVal($row, 3),
            'created_at'      => now(),
            'updated_at'      => now(),
        ];
    }

    private function val($row, $index)
    {
        return isset($row[$index]) && trim($row[$index]) !== '' ? trim($row[$index]) : null;
    }

    private function intVal($row, $index)
    {
        $val = $this->val($row, $index);
        return $val !== null ? (int)$val : 0;
    }

    private function decVal($row, $index)
    {
        $val = $this->val($row, $index);
        return $val !== null ? (float)$val : 0.0;
    }
}
