<?php

namespace App\Services;

use App\Models\RouletteReward;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RouletteRewardService
{
    public function activeForUser(int $userId): ?RouletteReward
    {
        if (!$this->tableExists()) {
            return null;
        }

        return RouletteReward::where('user_id', $userId)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    public function spin(int $userId): RouletteReward
    {
        if (!$this->tableExists()) {
            throw ValidationException::withMessages([
                'ruleta' => 'La tabla de premios aun no esta creada. Ejecuta las migraciones pendientes.',
            ]);
        }

        $activeReward = $this->activeForUser($userId);

        if ($activeReward) {
            throw ValidationException::withMessages([
            'ruleta' => 'Ya usaste la ruleta recientemente. Espera 10 minutos para volver a girar.',
            ]);
        }

        $prize = $this->choosePrize();

        return RouletteReward::create([
            'user_id' => $userId,
            'prize_type' => $prize['type'],
            'label' => $prize['label'],
            'discount_percent' => $prize['discount_percent'] ?? null,
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    public function applyToLine(float $unitPrice, int $quantity, ?RouletteReward $reward): array
    {
        $quantity = max(1, $quantity);
        $originalSubtotal = round($unitPrice * $quantity, 2);

        if (!$reward || $reward->prize_type === 'no_prize') {
            return [
                'unit_price' => $unitPrice,
                'subtotal' => $originalSubtotal,
                'discount_amount' => 0,
                'discount_label' => null,
                'discount_percent' => null,
                'original_unit_price' => $unitPrice,
            ];
        }

        if ($reward->prize_type === 'two_for_one') {
            $payableQuantity = (int) ceil($quantity / 2);
            $subtotal = round($payableQuantity * $unitPrice, 2);

            return [
                'unit_price' => round($subtotal / $quantity, 2),
                'subtotal' => $subtotal,
                'discount_amount' => round($originalSubtotal - $subtotal, 2),
                'discount_label' => $reward->label,
                'discount_percent' => null,
                'original_unit_price' => $unitPrice,
            ];
        }

        $discountPercent = max(0, min(90, (int) $reward->discount_percent));
        $discountedUnitPrice = round($unitPrice * (100 - $discountPercent) / 100, 2);
        $subtotal = round($discountedUnitPrice * $quantity, 2);

        return [
            'unit_price' => $discountedUnitPrice,
            'subtotal' => $subtotal,
            'discount_amount' => round($originalSubtotal - $subtotal, 2),
            'discount_label' => $reward->label,
            'discount_percent' => $discountPercent,
            'original_unit_price' => $unitPrice,
        ];
    }

    public function consumeForUser(int $userId): void
    {
        $reward = $this->activeForUser($userId);

        if ($reward) {
            $reward->update(['used_at' => now()]);
        }
    }

    public function format(?RouletteReward $reward): ?array
    {
        if (!$reward) {
            return null;
        }

        return [
            'id' => $reward->id,
            'prize_type' => $reward->prize_type,
            'label' => $reward->label,
            'discount_percent' => $reward->discount_percent,
            'expires_at' => $reward->expires_at?->toIso8601String(),
        ];
    }

    private function choosePrize(): array
    {
        $prizes = [
            ['type' => 'percent_discount', 'label' => '10% de descuento', 'discount_percent' => 10, 'weight' => 35],
            ['type' => 'percent_discount', 'label' => '15% de descuento', 'discount_percent' => 15, 'weight' => 20],
            ['type' => 'percent_discount', 'label' => '20% de descuento', 'discount_percent' => 20, 'weight' => 12],
            ['type' => 'two_for_one', 'label' => '2x1 en productos del carrito', 'weight' => 18],
            ['type' => 'no_prize', 'label' => 'Intentalo la proxima vez', 'weight' => 15],
        ];

        $ticket = random_int(1, array_sum(array_column($prizes, 'weight')));
        $current = 0;

        foreach ($prizes as $prize) {
            $current += $prize['weight'];

            if ($ticket <= $current) {
                return $prize;
            }
        }

        return $prizes[0];
    }

    private function tableExists(): bool
    {
        return Schema::hasTable('roulette_rewards');
    }
}
