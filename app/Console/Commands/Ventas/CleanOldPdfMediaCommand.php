<?php

namespace App\Console\Commands\Ventas;

use App\Models\GpCotizacion;
use App\Models\GpPedido;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CleanOldPdfMediaCommand extends Command
{

    protected $signature = 'media:clean-old-pdfs {--months=3} {--dry-run}';

    protected $description = 'Eliminar PDFs antiguos de pedidos y cotizaciones';


    public function handle()
    {
        $months = (int) $this->option('months');
        $dryRun = (bool) $this->option('dry-run');
        $limitDate = now()->subMonths($months);
        $this->info("Buscando PDF anteriores a {$limitDate->toDateTimeString()}...");

        $query = Media::query()
            ->where('collection_name', 'documentos_pdf')
            ->where('mime_type', 'application/pdf')
            ->whereIn('model_type', [
                GpPedido::class,
                GpCotizacion::class,
            ])->where('created_at', '<', $limitDate)
            ->orderBy('id');
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No hay PDFs antiguos para eliminar.');
            return self::SUCCESS;
        }

        $this->warn("Se encontraron {$total} PDFs antiguos para eliminar.");
        $deleted = 0;

        $query->chunk(100, function ($mediaItems) use (&$deleted, $dryRun) {
            foreach ($mediaItems as $media) {
                $this->line("[" . $media->id . "] {$media->file_name}");

                if (! $dryRun) {
                    $media->delete(); // elimina registro + archivos físicos
                    $deleted++;
                }
            }
        });
        if ($dryRun) {
            $this->comment('Dry run finalizado. No se eliminó ningún archivo.');
        } else {
            $this->info("Proceso completado. Eliminados: {$deleted}");
        }

        return self::SUCCESS;
    }
}
