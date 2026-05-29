<?php

namespace App\Services;

use App\Models\CartReservation;
use App\Models\Producto;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CartReservationService
{
    private const STATUS_ACTIVE = 'active';
    private const STATUS_RELEASED = 'released';
    private const STATUS_PURCHASED = 'purchased';
    private const RESERVATION_MINUTES = 5;

    public function cleanupExpiredReservations(): void
    {
        if (!$this->reservationsTableExists()) {
            return;
        }

        $expiredReservations = CartReservation::with('producto')
            ->where('status', self::STATUS_ACTIVE)
            ->get();

        foreach ($expiredReservations as $reservation) {
            if (!$reservation->expires_at || $reservation->expires_at->isFuture()) {
                continue;
            }

            DB::transaction(function () use ($reservation) {
                $lockedReservation = CartReservation::lockForUpdate()->find($reservation->id);

                if (!$lockedReservation || $lockedReservation->status !== self::STATUS_ACTIVE || !$lockedReservation->expires_at || $lockedReservation->expires_at->isFuture()) {
                    return;
                }

                $product = Producto::lockForUpdate()->find($lockedReservation->producto_id);

                if ($product) {
                    $product->increment('stock', $lockedReservation->cantidad);
                }

                $lockedReservation->update([
                    'status' => self::STATUS_RELEASED,
                ]);
            });
        }
    }

    public function getCartByUserId(int $userId): array
    {
        if (!$this->reservationsTableExists()) {
            return $this->buildCartResponse([]);
        }

        $this->cleanupExpiredReservations();

        $items = $this->activeReservationsForUser($userId)
            ->map(fn (CartReservation $reservation) => $this->formatReservation($reservation))
            ->values()
            ->all();

        return $this->buildCartResponse($items);
    }

    public function reserveProduct(int $userId, int $productId): array
    {
        if (!$this->reservationsTableExists()) {
            throw ValidationException::withMessages([
                'carrito' => 'La tabla de reservas del carrito aun no esta creada. Ejecuta la migracion pendiente.',
            ]);
        }

        $this->cleanupExpiredReservations();

        DB::transaction(function () use ($userId, $productId) {
            $existingReservation = CartReservation::lockForUpdate()
                ->where('user_id', $userId)
                ->where('producto_id', $productId)
                ->where('status', self::STATUS_ACTIVE)
                ->first();

            if ($existingReservation) {
                $product = Producto::lockForUpdate()->findOrFail($productId);

                if ($product->stock < 1) {
                    throw ValidationException::withMessages([
                        'producto_id' => 'No hay mas unidades disponibles para agregar en este momento.',
                    ]);
                }

                $product->decrement('stock', 1);

                $existingReservation->update([
                    'cantidad' => $existingReservation->cantidad + 1,
                    'expires_at' => $existingReservation->expires_at,
                ]);

                return;
            }

            $product = Producto::lockForUpdate()->findOrFail($productId);

            if ($product->stock < 1) {
                throw ValidationException::withMessages([
                    'producto_id' => 'Este producto no tiene stock disponible en este momento.',
                ]);
            }

            $product->decrement('stock', 1);

            CartReservation::create([
                'user_id' => $userId,
                'producto_id' => $productId,
                'cantidad' => 1,
                'status' => self::STATUS_ACTIVE,
                'expires_at' => $this->expirationTime(),
            ]);
        });

        return $this->getCartByUserId($userId);
    }

    public function decreaseProduct(int $userId, int $productId): array
    {
        if (!$this->reservationsTableExists()) {
            return $this->buildCartResponse([]);
        }

        $this->cleanupExpiredReservations();

        DB::transaction(function () use ($userId, $productId) {
            $reservation = CartReservation::lockForUpdate()
                ->where('user_id', $userId)
                ->where('producto_id', $productId)
                ->where('status', self::STATUS_ACTIVE)
                ->first();

            if (!$reservation) {
                return;
            }

            $product = Producto::lockForUpdate()->find($reservation->producto_id);

            if ($product) {
                $product->increment('stock', 1);
            }

            if ($reservation->cantidad > 1) {
                $reservation->update([
                    'cantidad' => $reservation->cantidad - 1,
                    'expires_at' => $reservation->expires_at,
                ]);

                return;
            }

            $reservation->update([
                'status' => self::STATUS_RELEASED,
            ]);
        });

        return $this->getCartByUserId($userId);
    }

    public function removeProduct(int $userId, int $productId): array
    {
        if (!$this->reservationsTableExists()) {
            return $this->buildCartResponse([]);
        }

        $this->cleanupExpiredReservations();

        DB::transaction(function () use ($userId, $productId) {
            $reservation = CartReservation::lockForUpdate()
                ->where('user_id', $userId)
                ->where('producto_id', $productId)
                ->where('status', self::STATUS_ACTIVE)
                ->first();

            if (!$reservation) {
                return;
            }

            $product = Producto::lockForUpdate()->find($reservation->producto_id);

            if ($product) {
                $product->increment('stock', $reservation->cantidad);
            }

            $reservation->update([
                'status' => self::STATUS_RELEASED,
            ]);
        });

        return $this->getCartByUserId($userId);
    }

    public function clearCart(int $userId): array
    {
        if (!$this->reservationsTableExists()) {
            return $this->buildCartResponse([]);
        }

        $this->cleanupExpiredReservations();

        DB::transaction(function () use ($userId) {
            $reservations = CartReservation::lockForUpdate()
                ->where('user_id', $userId)
                ->where('status', self::STATUS_ACTIVE)
                ->get();

            foreach ($reservations as $reservation) {
                $product = Producto::lockForUpdate()->find($reservation->producto_id);

                if ($product) {
                    $product->increment('stock', $reservation->cantidad);
                }

                $reservation->update([
                    'status' => self::STATUS_RELEASED,
                ]);
            }
        });

        return $this->getCartByUserId($userId);
    }

    public function checkout(int $userId): array
    {
        if (!$this->reservationsTableExists()) {
            return $this->buildCartResponse([]);
        }

        $this->cleanupExpiredReservations();

        DB::transaction(function () use ($userId) {
            CartReservation::lockForUpdate()
                ->where('user_id', $userId)
                ->where('status', self::STATUS_ACTIVE)
                ->update([
                    'status' => self::STATUS_PURCHASED,
                    'purchased_at' => now(),
                ]);
        });

        return $this->buildCartResponse([]);
    }

    private function activeReservationsForUser(int $userId): Collection
    {
        return CartReservation::with('producto')
            ->where('user_id', $userId)
            ->where('status', self::STATUS_ACTIVE)
            ->orderBy('created_at')
            ->get()
            ->filter(fn (CartReservation $reservation) => $reservation->expires_at && $reservation->expires_at->isFuture())
            ->values();
    }

    private function formatReservation(CartReservation $reservation): array
    {
        $product = $reservation->producto;
        $remainingSeconds = max(0, now()->diffInSeconds($reservation->expires_at, false));
        $remainingSeconds = (int) floor($remainingSeconds);

        return [
            'id' => (int) $product->id,
            'nombre' => (string) $product->nombre,
            'precio' => (float) $product->precio,
            'image_path' => $product->image_path,
            'cantidad' => (int) $reservation->cantidad,
            'stock_available' => (int) $product->stock,
            'expires_at' => $reservation->expires_at?->toIso8601String(),
            'remaining_seconds' => $remainingSeconds,
        ];
    }

    private function buildCartResponse(array $items): array
    {
        $count = array_sum(array_map(fn (array $item) => $item['cantidad'], $items));
        $total = array_sum(array_map(fn (array $item) => $item['precio'] * $item['cantidad'], $items));

        return [
            'status' => true,
            'items' => $items,
            'count' => $count,
            'total' => $total,
        ];
    }

    private function expirationTime(): Carbon
    {
        return now()->addMinutes(self::RESERVATION_MINUTES);
    }

    private function reservationsTableExists(): bool
    {
        return Schema::hasTable('cart_reservations');
    }
}
