<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_and_remove_favorite_product(): void
    {
        $user = User::factory()->create([
            'telefono' => '3001234567',
            'rol' => 'usuario',
        ]);

        $product = Producto::create([
            'nombre' => 'Cartuchera',
            'descripcion' => 'Cartuchera escolar',
            'precio' => 9000,
            'stock' => 8,
            'categoria' => 'Herramientas escolares',
        ]);

        $this->postJson('/api/favoritos/toggle', [
            'user_id' => $user->id,
            'producto_id' => $product->id,
        ])->assertCreated()
            ->assertJsonPath('is_favorite', true);

        $this->assertDatabaseHas('favorite_products', [
            'user_id' => $user->id,
            'producto_id' => $product->id,
        ]);

        $this->getJson("/api/favoritos/{$user->id}")
            ->assertOk()
            ->assertJsonPath('product_ids.0', $product->id)
            ->assertJsonPath('products.0.id', $product->id);

        $this->postJson('/api/favoritos/toggle', [
            'user_id' => $user->id,
            'producto_id' => $product->id,
        ])->assertOk()
            ->assertJsonPath('is_favorite', false);

        $this->assertDatabaseMissing('favorite_products', [
            'user_id' => $user->id,
            'producto_id' => $product->id,
        ]);
    }
}
