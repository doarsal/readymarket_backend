<?php

namespace Database\Factories;

use App\Models\AmexNewClientForm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AmexNewClientFormFactory extends Factory
{
    protected $model = AmexNewClientForm::class;

    public function definition(): array
    {
        return [
            'contacto_nombre'          => $this->faker->word(),
            'contacto_apellidos'       => $this->faker->word(),
            'contacto_telefono'        => $this->faker->word(),
            'contacto_email'           => $this->faker->unique()->safeEmail(),
            'empresa_nombre'           => $this->faker->word(),
            'empresa_rfc'              => $this->faker->word(),
            'empresa_ciudad'           => $this->faker->word(),
            'empresa_estado'           => $this->faker->word(),
            'empresa_codigo_postal'    => $this->faker->postcode(),
            'empresa_ingresos_anuales' => $this->faker->word(),
            'empresa_info_adicional'   => $this->faker->word(),
            'fecha_envio'              => Carbon::now(),
            'status_envio'             => $this->faker->boolean(),
            'created_at'               => Carbon::now(),
            'updated_at'               => Carbon::now(),
        ];
    }
}
