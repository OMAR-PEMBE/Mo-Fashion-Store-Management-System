<?php

namespace App\Http\Controllers;

use App\Models\Sale;

/**
 * The receipt a customer opens from WhatsApp. The route only answers correctly signed,
 * unexpired links, so sale numbers cannot be guessed to read other people's receipts.
 */
class ReceiptLinkController extends Controller
{
    // A plain id, loaded only after the signature check, so a tampered link cannot tell real sales from missing ones.
    public function __invoke(int $sale)
    {
        $sale = Sale::with(['customer', 'salesperson', 'items.variant.product', 'items.variant.size', 'items.variant.colour'])->findOrFail($sale);

        return response()->view('receipts.public', compact('sale'))
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
