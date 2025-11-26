<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Log;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\HttpClient\HttpClient;

class ScrapMonexCommand extends Command
{
    protected $signature   = 'scrap:monex';
    protected $description = 'Scrapping Monex MX dollar value';

    public function handle(): float
    {
        try {
            $usdValues = Collection::make();

            $tempDir = storage_path('temp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            putenv("TMPDIR={$tempDir}");
            putenv("TEMP={$tempDir}");
            putenv("TMP={$tempDir}");

            $browser = new HttpBrowser(HttpClient::create());
            $crawler = $browser->request('GET', 'https://www.monex.com.mx/portal/inicio');

            $usdParagraph = $crawler->filter('.ticker.flex_track p strong')
                ->filterXPath('//strong[text()="USD"]')
                ->ancestors()
                ->filter('p')
                ->first();
            $usdParagraph->filter('span')->each(function($node) use ($usdValues) {
                $value = floatval($node->text());
                if ($value > 0) {
                    $usdValues->push($value);
                }
            });

            return $usdValues->isEmpty() ? 0 : $usdValues->max();
        } catch (\Throwable $e) {
            Log::error('Error scraping Monex MX dollar value: ' . $e->getMessage());

            return 0;
        }
    }
}
