<?php

namespace App\Services;

use App\Models\Checkout;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class LegacyAuditClient
{
    public function __construct(private readonly IaeCentralTokenClient $tokenClient)
    {
    }

    public function submitOrderCreated(Checkout $checkout, Payment $payment, string $invoiceNumber): ?string
    {
        if (! filter_var(config('services.legacy_audit.enabled'), FILTER_VALIDATE_BOOL)) {
            return null;
        }

        $endpoint = (string) config('services.legacy_audit.endpoint', '');

        if ($endpoint === '') {
            throw new ConnectionException('Legacy SOAP Audit endpoint is not configured');
        }

        $response = Http::timeout((int) config('services.legacy_audit.timeout', 5))
            ->withToken($this->tokenClient->bearerToken())
            ->withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => (string) config('services.legacy_audit.soap_action', 'SubmitOrderAudit'),
            ])
            ->withBody($this->buildEnvelope($checkout, $payment, $invoiceNumber), 'text/xml')
            ->post($endpoint);

        if (! $response->successful()) {
            throw new ConnectionException("Legacy SOAP Audit returned HTTP {$response->status()}");
        }

        $receiptNumber = $this->extractReceiptNumber($response->body());

        if ($receiptNumber === null) {
            throw new ConnectionException('Legacy SOAP Audit response did not include ReceiptNumber');
        }

        return $receiptNumber;
    }

    private function buildEnvelope(Checkout $checkout, Payment $payment, string $invoiceNumber): string
    {
        $logContent = json_encode([
            'transaction_type' => 'ORDER_CREATED',
            'service_name' => config('services.iae.service_name', 'Checkout-Order-Service'),
            'invoice_number' => $invoiceNumber,
            'checkout_id' => $checkout->id,
            'payment_id' => $payment->id,
            'user_id' => $checkout->user_id,
            'total_amount' => (float) $checkout->total_amount,
            'payment_status' => $payment->status,
            'occurred_at' => now()->toISOString(),
            'items' => $checkout->items
                ->map(fn ($item): array => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => (float) $item->price,
                    'subtotal' => (float) $item->subtotal,
                ])
                ->values()
                ->all(),
        ], JSON_THROW_ON_ERROR);

        return sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>'.
            '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:iae="http://iae.central/audit">'.
            '<soap:Body>'.
            '<iae:AuditRequest>'.
            '<iae:TeamID>%s</iae:TeamID>'.
            '<iae:ActivityName>%s</iae:ActivityName>'.
            '<iae:LogContent><![CDATA[%s]]></iae:LogContent>'.
            '</iae:AuditRequest>'.
            '</soap:Body>'.
            '</soap:Envelope>',
            $this->escape(config('services.iae.team_id', 'TEAM-01')),
            $this->escape(config('services.legacy_audit.activity_name', 'CheckoutOrderCreated')),
            $this->cdata($logContent),
        );
    }

    private function extractReceiptNumber(string $xml): ?string
    {
        preg_match('/<([A-Za-z0-9_]+:)?ReceiptNumber[^>]*>(.*?)<\/([A-Za-z0-9_]+:)?ReceiptNumber>/s', $xml, $matches);

        return isset($matches[2]) ? trim($matches[2]) : null;
    }

    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function cdata(string $value): string
    {
        return str_replace(']]>', ']]]]><![CDATA[>', $value);
    }
}
