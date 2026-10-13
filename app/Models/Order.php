<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];

    public const FREE_SHIPPING_SUBTOTAL = 500;

    public const SHIPPING_FEE = 30;

    /**
     * Quote merchandise, shipping, and the amount a customer must pay.
     *
     * @return array{subtotal: string, shipping: string, total: string, total_cents: int}
     */
    public static function totalsFromSubtotal(float|int|string $subtotal): array
    {
        $subtotalCents = self::toCents($subtotal);
        $shippingCents = $subtotalCents >= self::FREE_SHIPPING_SUBTOTAL * 100
            ? 0
            : self::SHIPPING_FEE * 100;

        return [
            'subtotal' => self::fromCents($subtotalCents),
            'shipping' => self::fromCents($shippingCents),
            'total' => self::fromCents($subtotalCents + $shippingCents),
            'total_cents' => $subtotalCents + $shippingCents,
        ];
    }

    public static function toCents(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    protected $fillable = [
        'user_id', 'order_number', 'subtotal', 'shipping', 'total', 'status',
        'shipping_name', 'shipping_phone', 'shipping_address', 'shipping_city',
        'stripe_payment_intent_id',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->order_number ??= 'ORD-'.strtoupper(uniqid());
        });
    }
}
