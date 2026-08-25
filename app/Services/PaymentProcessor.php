<?php

declare(strict_types=1);

namespace Sukli\Services;

/**
 * The one place payment-method validation, cash change, and split-payment
 * math happen — shared by every part of Sukli that takes a payment against
 * a total (POS checkout, E-Load transactions, and anywhere else that's
 * added later). Keeping this logic in one service is what prevents POS and
 * E-Load from computing change or validating a split payment two slightly
 * different ways.
 *
 * This class only validates and computes — it never touches the database.
 * Callers are responsible for persisting the result (their own sale/
 * transaction row, their own payment rows, calling UtangService when
 * `usesUtang` is true, etc.), since what gets persisted differs by caller.
 */
class PaymentProcessor
{
    /**
     * @param array<int, array{method?:string, amount?:float|string}> $paymentsRaw Raw payment rows from the client (one row per method; more than one means split payment).
     * @return array{payments: array<int, array{method:string, amount:float}>, isSplit: bool, usesUtang: bool, paymentMethodLabel: string, amountTendered: float, changeAmount: float}
     * @throws \RuntimeException On any invalid/incomplete payment input, with a message safe to show the cashier directly.
     */
    public static function process(array $paymentsRaw, float $total, int $storeId): array
    {
        if (count($paymentsRaw) === 0) {
            throw new \RuntimeException('Please select a payment method.');
        }

        $enabledMethods = PaymentMethodService::enabled($storeId);
        $isSplit = count($paymentsRaw) > 1;
        $payments = [];
        $paymentsSum = 0.0;
        $usesUtang = false;

        foreach ($paymentsRaw as $row) {
            $method = (string) ($row['method'] ?? '');
            $amount = round((float) ($row['amount'] ?? 0), 2);
            if (!isset($enabledMethods[$method])) {
                throw new \RuntimeException('One of the selected payment methods is not available.');
            }
            if ($amount <= 0) {
                throw new \RuntimeException('Payment amounts must be greater than zero.');
            }
            if ($method === 'utang') {
                $usesUtang = true;
            }
            $payments[] = ['method' => $method, 'amount' => $amount];
            $paymentsSum += $amount;
        }

        $changeAmount = 0.0;
        if ($isSplit) {
            if (abs($paymentsSum - $total) > 0.01) {
                throw new \RuntimeException('Split payment amounts must add up to the total.');
            }
        } else {
            $only = $payments[0];
            if ($only['method'] === 'cash') {
                if ($only['amount'] < $total) {
                    throw new \RuntimeException('Cash received is less than the total.');
                }
                $changeAmount = round($only['amount'] - $total, 2);
            } elseif ($paymentsSum + 0.01 < $total) {
                throw new \RuntimeException('Payment amount is less than the total.');
            }
        }

        $paymentMethodLabel = $isSplit ? 'split' : $payments[0]['method'];
        $amountTendered = $isSplit ? $paymentsSum : ($payments[0]['method'] === 'cash' ? $payments[0]['amount'] : $total);

        return [
            'payments' => $payments,
            'isSplit' => $isSplit,
            'usesUtang' => $usesUtang,
            'paymentMethodLabel' => $paymentMethodLabel,
            'amountTendered' => $amountTendered,
            'changeAmount' => $changeAmount,
        ];
    }
}
