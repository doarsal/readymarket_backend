<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\MicrosoftAuthService;

class SyncProductAvailabilities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:sync-availabilities
                            {--market=MX : Market code (MX, US, etc)}
                            {--batch-size=50 : Number of products to process per batch}
                            {--delay=5 : Seconds to wait between batches}
                            {--force : Force sync even if recently synced}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza los AvailabilityIds de todos los productos desde Microsoft Partner Center';

    protected $authService;
    protected $stats = [
        'total' => 0,
        'updated' => 0,
        'unavailable' => 0,
        'errors' => 0,
        'skipped' => 0
    ];

    public function __construct(MicrosoftAuthService $authService)
    {
        parent::__construct();
        $this->authService = $authService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $market = $this->option('market');
        $batchSize = (int) $this->option('batch-size');
        $delay = (int) $this->option('delay');
        $force = $this->option('force');

        $this->info("╔════════════════════════════════════════════════════════════╗");
        $this->info("║   SINCRONIZACIÓN DE AVAILABILITY IDS DE PRODUCTOS          ║");
        $this->info("╚════════════════════════════════════════════════════════════╝");
        $this->newLine();

        $this->info("Configuración:");
        $this->line("  • Market: {$market}");
        $this->line("  • Batch size: {$batchSize}");
        $this->line("  • Delay: {$delay} segundos");
        $this->line("  • Force sync: " . ($force ? 'SI' : 'NO'));
        $this->newLine();

        // Obtener token de autenticación
        $this->info('Obteniendo token de autenticación...');
        try {
            $token = $this->authService->getAccessToken();
            $this->info('✓ Token obtenido correctamente');
        } catch (\Exception $e) {
            $this->error('✗ Error al obtener token: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $this->newLine();

        // Obtener productos únicos (ProductId + SkuId)
        $query = DB::table('products')
            ->select('ProductId', 'SkuId')
            ->distinct();

        // Si no es force, solo sincronizar productos que no se han revisado o tienen errores
        if (!$force) {
            $query->where(function($q) {
                $q->whereNull('availability_checked_at')
                  ->orWhere('availability_error', '!=', '')
                  ->orWhereNotNull('availability_error');
            });
        }

        $uniqueProducts = $query->get();
        $this->stats['total'] = $uniqueProducts->count();

        if ($this->stats['total'] === 0) {
            $this->warn('No hay productos para sincronizar.');
            $this->info('Usa --force para forzar la sincronización de todos los productos.');
            return Command::SUCCESS;
        }

        $this->info("Productos a procesar: {$this->stats['total']}");
        $this->newLine();

        // Barra de progreso
        $bar = $this->output->createProgressBar($this->stats['total']);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %message%');
        $bar->setMessage('Iniciando...');
        $bar->start();

        $partnerCenterApiUrl = config('services.microsoft.partner_center_base_url');
        $processed = 0;

        foreach ($uniqueProducts as $product) {
            $processed++;
            $bar->setMessage("Procesando {$product->ProductId}:{$product->SkuId}");

            try {
                // Consultar availabilities de Microsoft
                $url = "{$partnerCenterApiUrl}/products/{$product->ProductId}/skus/{$product->SkuId}/availabilities?country={$market}";

                $response = Http::timeout(30)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json'
                    ])
                    ->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    $availabilities = $data['items'] ?? [];

                    if (!empty($availabilities)) {
                        // Preferir availabilities de segmento Commercial
                        $commercialAvailability = collect($availabilities)->first(function($item) {
                            return isset($item['segment']) && strtolower($item['segment']) === 'commercial';
                        });

                        $selectedAvailability = $commercialAvailability ?? $availabilities[0];
                        $availabilityId = $selectedAvailability['id'];
                        $segment = ucfirst(strtolower($selectedAvailability['segment'] ?? 'commercial'));

                        // Actualizar todos los productos con este ProductId + SkuId
                        DB::table('products')
                            ->where('ProductId', $product->ProductId)
                            ->where('SkuId', $product->SkuId)
                            ->update([
                                'Id' => $availabilityId,
                                'Segment' => $segment,
                                'is_available' => true,
                                'availability_checked_at' => now(),
                                'availability_error' => null
                            ]);

                        $this->stats['updated']++;
                    } else {
                        // Sin availabilities
                        DB::table('products')
                            ->where('ProductId', $product->ProductId)
                            ->where('SkuId', $product->SkuId)
                            ->update([
                                'is_available' => false,
                                'availability_checked_at' => now(),
                                'availability_error' => 'No availabilities found'
                            ]);

                        $this->stats['unavailable']++;
                    }
                } elseif ($response->status() === 404) {
                    // Producto no existe
                    DB::table('products')
                        ->where('ProductId', $product->ProductId)
                        ->where('SkuId', $product->SkuId)
                        ->update([
                            'is_available' => false,
                            'availability_checked_at' => now(),
                            'availability_error' => 'Product not found (404)'
                        ]);

                    $this->stats['errors']++;
                } else {
                    // Otro error
                    $this->stats['errors']++;
                }

            } catch (\Exception $e) {
                Log::error("Error syncing availability for {$product->ProductId}:{$product->SkuId}", [
                    'error' => $e->getMessage()
                ]);
                $this->stats['errors']++;
            }

            $bar->advance();

            // Delay entre batches
            if ($processed % $batchSize === 0 && $processed < $this->stats['total']) {
                $bar->setMessage("Pausa de {$delay} segundos...");
                sleep($delay);
            }
        }

        $bar->setMessage('Completado');
        $bar->finish();
        $this->newLine(2);

        // Mostrar estadísticas
        $this->info("╔════════════════════════════════════════════════════════════╗");
        $this->info("║                    RESULTADOS                              ║");
        $this->info("╚════════════════════════════════════════════════════════════╝");
        $this->newLine();

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Total procesados', $this->stats['total']],
                ['✓ Actualizados', "<info>{$this->stats['updated']}</info>"],
                ['⚠ No disponibles', "<comment>{$this->stats['unavailable']}</comment>"],
                ['✗ Errores', "<error>{$this->stats['errors']}</error>"],
            ]
        );

        Log::info('Product availabilities sync completed', $this->stats);

        return Command::SUCCESS;
    }
}
