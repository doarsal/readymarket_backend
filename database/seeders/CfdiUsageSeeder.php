<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CfdiUsage;
use App\Models\TaxRegime;
use Illuminate\Support\Facades\DB;

class CfdiUsageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Datos de los usos de CFDI según la documentación del SAT
        $cfdiUsages = [
            [
                'code' => 'G01',
                'description' => 'Adquisición de mercancías',
                'applies_to_physical' => true,
                'applies_to_moral' => true,
                'applicable_tax_regimes' => ['601', '603', '606', '612', '620', '621', '622', '623', '624', '625', '626']
            ],
            [
                'code' => 'G02',
                'description' => 'Devoluciones, descuentos o bonificaciones',
                'applies_to_physical' => true,
                'applies_to_moral' => true,
                'applicable_tax_regimes' => ['601', '603', '606', '612', '616', '620', '621', '622', '623', '624', '625', '626']
            ],
            [
                'code' => 'G03',
                'description' => 'Gastos en general',
                'applies_to_physical' => true,
                'applies_to_moral' => true,
                'applicable_tax_regimes' => ['601', '603', '606', '612', '620', '621', '622', '623', '624', '625', '626']
            ],
            [
                'code' => 'I01',
                'description' => 'Construcciones',
                'applies_to_physical' => true,
                'applies_to_moral' => true,
                'applicable_tax_regimes' => ['601', '603', '606', '612', '620', '621', '622', '623', '624', '625', '626']
            ],
            [
                'code' => 'I02',
                'description' => 'Mobiliario y equipo de oficina para inversiones',
                'applies_to_physical' => true,
                'applies_to_moral' => true,
                'applicable_tax_regimes' => ['601', '603', '606', '612', '620', '621', '622', '623', '624', '625', '626']
            ],
            [
                'code' => 'I03',
                'description' => 'Equipo de transporte',
                'applies_to_physical' => true,
                'applies_to_moral' => true,
                'applicable_tax_regimes' => ['601', '603', '606', '612', '620', '621', '622', '623', '624', '625', '626']
            ],
            [
                'code' => 'I04',
                'description' => 'Equipo de cómputo y accesorios',
                'applies_to_physical' => true,
                'applies_to_moral' => true,
                'applicable_tax_regimes' => ['601', '603', '606', '612', '620', '621', '622', '623', '624', '625', '626']
            ],
            [
                'code' => 'I05',
                'description' => 'Dados, troqueles, moldes, matrices y herramental',
                'applies_to_physical' => true,
                'applies_to_moral' => true,
                'applicable_tax_regimes' => ['601', '603', '606', '612', '620', '621', '622', '623', '624', '625', '626']
            ],
            [
                'code' => 'I06',
                'description' => 'Comunicaciones telefónicas',
                'applies_to_physical' => true,
                'applies_to_moral' => true,
                'applicable_tax_regimes' => ['601', '603', '606', '612', '620', '621', '622', '623', '624', '625', '626']
            ],
            [
                'code' => 'I07',
                'description' => 'Comunicaciones satelitales',
                'applies_to_physical' => true,
                'applies_to_moral' => true,
                'applicable_tax_regimes' => ['601', '603', '606', '612', '620', '621', '622', '623', '624', '625', '626']
            ],
            [
                'code' => 'I08',
                'description' => 'Otra maquinaria y equipo',
                'applies_to_physical' => true,
                'applies_to_moral' => true,
                'applicable_tax_regimes' => ['601', '603', '606', '612', '620', '621', '622', '623', '624', '625', '626']
            ],
            [
                'code' => 'D01',
                'description' => 'Honorarios médicos, dentales y hospitalarios',
                'applies_to_physical' => true,
                'applies_to_moral' => false,
                'applicable_tax_regimes' => ['605', '606', '608', '611', '612', '614', '607', '615', '625']
            ],
            [
                'code' => 'D02',
                'description' => 'Gastos médicos por incapacidad o discapacidad',
                'applies_to_physical' => true,
                'applies_to_moral' => false,
                'applicable_tax_regimes' => ['605', '606', '608', '611', '612', '614', '607', '615', '625']
            ],
            [
                'code' => 'D03',
                'description' => 'Gastos funerales',
                'applies_to_physical' => true,
                'applies_to_moral' => false,
                'applicable_tax_regimes' => ['605', '606', '608', '611', '612', '614', '607', '615', '625']
            ],
            [
                'code' => 'D04',
                'description' => 'Donativos',
                'applies_to_physical' => true,
                'applies_to_moral' => false,
                'applicable_tax_regimes' => ['605', '606', '608', '611', '612', '614', '607', '615', '625']
            ],
            [
                'code' => 'D05',
                'description' => 'Intereses reales pagados por créditos hipotecarios',
                'applies_to_physical' => true,
                'applies_to_moral' => false,
                'applicable_tax_regimes' => ['605', '606', '608', '611', '612', '614', '607', '615', '625']
            ],
            [
                'code' => 'D06',
                'description' => 'Aportaciones voluntarias al SAR',
                'applies_to_physical' => true,
                'applies_to_moral' => false,
                'applicable_tax_regimes' => ['605', '606', '608', '611', '612', '614', '607', '615', '625']
            ],
            [
                'code' => 'D07',
                'description' => 'Primas de seguros de gastos médicos',
                'applies_to_physical' => true,
                'applies_to_moral' => false,
                'applicable_tax_regimes' => ['605', '606', '608', '611', '612', '614', '607', '615', '625']
            ],
            [
                'code' => 'D08',
                'description' => 'Gastos de transportación escolar obligatoria',
                'applies_to_physical' => true,
                'applies_to_moral' => false,
                'applicable_tax_regimes' => ['605', '606', '608', '611', '612', '614', '607', '615', '625']
            ],
            [
                'code' => 'D09',
                'description' => 'Depósitos en cuentas para el ahorro, primas de pensiones',
                'applies_to_physical' => true,
                'applies_to_moral' => false,
                'applicable_tax_regimes' => ['605', '606', '608', '611', '612', '614', '607', '615', '625']
            ],
            [
                'code' => 'D10',
                'description' => 'Pagos por servicios educativos (colegiaturas)',
                'applies_to_physical' => true,
                'applies_to_moral' => false,
                'applicable_tax_regimes' => ['605', '606', '608', '611', '612', '614', '607', '615', '625']
            ],
            [
                'code' => 'S01',
                'description' => 'Sin efectos fiscales',
                'applies_to_physical' => true,
                'applies_to_moral' => true,
                'applicable_tax_regimes' => ['601', '603', '605', '606', '608', '610', '611', '612', '614', '616', '620', '621', '622', '623', '624', '607', '615', '625', '626']
            ],
            [
                'code' => 'CP01',
                'description' => 'Pagos',
                'applies_to_physical' => true,
                'applies_to_moral' => true,
                'applicable_tax_regimes' => ['601', '603', '605', '606', '608', '610', '611', '612', '614', '616', '620', '621', '622', '623', '624', '607', '615', '625', '626']
            ],
            [
                'code' => 'CN01',
                'description' => 'Nómina',
                'applies_to_physical' => true,
                'applies_to_moral' => false,
                'applicable_tax_regimes' => ['605']
            ],
        ];

        // Insertar datos en la tabla cfdi_usages
        foreach ($cfdiUsages as $usage) {
            $storeId = 1; // ID de la tienda principal

            CfdiUsage::create([
                'code' => $usage['code'],
                'description' => $usage['description'],
                'applies_to_physical' => $usage['applies_to_physical'],
                'applies_to_moral' => $usage['applies_to_moral'],
                'applicable_tax_regimes' => $usage['applicable_tax_regimes'],
                'active' => true,
                'store_id' => $storeId
            ]);
        }

        // Crear relaciones en la tabla pivote
        $this->createTaxRegimeCfdiUsageRelations();
    }

    /**
     * Create relationships between tax regimes and CFDI usages
     */
    private function createTaxRegimeCfdiUsageRelations()
    {
        // Obtener todos los usos CFDI
        $cfdiUsages = CfdiUsage::all();

        // Obtener todos los regímenes fiscales
        $taxRegimes = TaxRegime::all();

        // Para cada uso CFDI
        foreach ($cfdiUsages as $cfdiUsage) {
            $applicableTaxRegimes = $cfdiUsage->applicable_tax_regimes;

            // Relacionar con los regímenes fiscales aplicables
            foreach ($taxRegimes as $taxRegime) {
                $satCode = (string) $taxRegime->sat_code;

                // Si el código SAT del régimen fiscal está en la lista de aplicables
                if (in_array($satCode, $applicableTaxRegimes)) {
                    // Crear la relación en la tabla pivote
                    DB::table('tax_regime_cfdi_usage')->insert([
                        'tax_regime_id' => $taxRegime->id,
                        'cfdi_usage_id' => $cfdiUsage->id,
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
    }
}
