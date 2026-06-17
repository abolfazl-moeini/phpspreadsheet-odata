<?php

declare(strict_types=1);

namespace WPDev\PhpSpreadsheetOData\OData;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

final class ODataError
{
    /**
     * @param array<string, string> $extraHeaders
     */
    public static function json(int $status, string $code, string $message, array $extraHeaders = []): ResponseInterface
    {
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'OData-Version' => '4.0',
        ], $extraHeaders);

        $body = json_encode([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);

        if ($body === false) {
            $body = '{"error":{"code":"500","message":"Internal Server Error"}}';
            $status = 500;
        }

        return new Response($status, $headers, $body);
    }

    /**
     * @param array<string, string> $extraHeaders
     */
    public static function withBody(int $status, string $contentType, string $body, array $extraHeaders = []): ResponseInterface
    {
        $headers = array_merge([
            'Content-Type' => $contentType,
            'OData-Version' => '4.0',
        ], $extraHeaders);

        return new Response($status, $headers, $body);
    }
}