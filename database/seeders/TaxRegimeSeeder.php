<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TaxRegime;

class TaxRegimeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $taxRegimes = [
            // tr_rel = 0 (Base) - Exactamente como en la imagen
            ['sat_code' => 1, 'name' => 'Personas físicas', 'relation' => 0, 'store_id' => 1, 'active' => true],
            ['sat_code' => 1, 'name' => 'Personas morales', 'relation' => 0, 'store_id' => 1, 'active' => true],

            // tr_rel = 1 (Personas Físicas)
            ['sat_code' => 605, 'name' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios', 'relation' => 1, 'store_id' => 1, 'active' => true],
            ['sat_code' => 606, 'name' => 'Arrendamiento', 'relation' => 1, 'store_id' => 1, 'active' => true],
            ['sat_code' => 607, 'name' => 'Régimen de Enajenación o Adquisición de Bienes', 'relation' => 1, 'store_id' => 1, 'active' => true],
            ['sat_code' => 608, 'name' => 'Demás ingresos', 'relation' => 1, 'store_id' => 1, 'active' => true],
            ['sat_code' => 610, 'name' => 'Residentes en el Extranjero sin Establecimiento Permanente...', 'relation' => 1, 'store_id' => 1, 'active' => true],
            ['sat_code' => 611, 'name' => 'Ingresos por Dividendos (socios y accionistas)', 'relation' => 1, 'store_id' => 1, 'active' => true],
            ['sat_code' => 612, 'name' => 'Personas Físicas con Actividades Empresariales y Profesio...', 'relation' => 1, 'store_id' => 1, 'active' => true],
            ['sat_code' => 614, 'name' => 'Ingresos por intereses', 'relation' => 1, 'store_id' => 1, 'active' => true],
            ['sat_code' => 615, 'name' => 'Régimen de los ingresos por obtención de premios', 'relation' => 1, 'store_id' => 1, 'active' => true],
            ['sat_code' => 616, 'name' => 'Sin obligaciones fiscales', 'relation' => 1, 'store_id' => 1, 'active' => true],
            ['sat_code' => 621, 'name' => 'Incorporación Fiscal', 'relation' => 1, 'store_id' => 1, 'active' => true],
            ['sat_code' => 625, 'name' => 'Régimen de las Actividades Empresariales con ingresos a t...', 'relation' => 1, 'store_id' => 1, 'active' => true],
            ['sat_code' => 626, 'name' => 'Régimen Simplificado de Confianza', 'relation' => 1, 'store_id' => 1, 'active' => true],

            // tr_rel = 2 (Personas Morales)
            ['sat_code' => 601, 'name' => 'General de Ley Personas Morales', 'relation' => 2, 'store_id' => 1, 'active' => true],
            ['sat_code' => 603, 'name' => 'Personas Morales con Fines no Lucrativos', 'relation' => 2, 'store_id' => 1, 'active' => true],
            ['sat_code' => 610, 'name' => 'Residentes en el Extranjero sin Establecimiento Permanente...', 'relation' => 2, 'store_id' => 1, 'active' => true],
            ['sat_code' => 620, 'name' => 'Sociedades Cooperativas de Producción que optan por dif...', 'relation' => 2, 'store_id' => 1, 'active' => true],
            ['sat_code' => 622, 'name' => 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras', 'relation' => 2, 'store_id' => 1, 'active' => true],
            ['sat_code' => 623, 'name' => 'Opcional para Grupos de Sociedades', 'relation' => 2, 'store_id' => 1, 'active' => true],
            ['sat_code' => 624, 'name' => 'Coordinados', 'relation' => 2, 'store_id' => 1, 'active' => true],
            ['sat_code' => 626, 'name' => 'Régimen Simplificado de Confianza', 'relation' => 2, 'store_id' => 1, 'active' => true],
        ];

        foreach ($taxRegimes as $regime) {
            TaxRegime::create([
                'sat_code' => $regime['sat_code'],
                'name' => $regime['name'],
                'relation' => $regime['relation'],
                'store_id' => $regime['store_id'],
                'active' => $regime['active'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✅ Seeded ' . count($taxRegimes) . ' tax regimes successfully.');
    }
}
