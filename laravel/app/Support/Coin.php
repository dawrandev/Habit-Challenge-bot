<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Tanga tashlash — garov bo'yicha ovozlar ikkiga bo'linganda hal qiladi.
 *
 * Alohida klass: testda natijani qotirib qo'yish mumkin bo'lsin.
 */
class Coin
{
    /**
     * @template T
     *
     * @param  array{0: T, 1: T}  $sides
     * @return T
     */
    public function flip(array $sides): mixed
    {
        return $sides[random_int(0, 1)];
    }
}
