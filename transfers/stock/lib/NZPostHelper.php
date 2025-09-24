<?php
declare(strict_types=1);

/**
 * NZ Post eShip integration (OAuth2 + REST)
 * - Expects client_id/secret per outlet
 * - Returns order + parcel list (with tracking); eShip agent prints label
 */
final class NZPostHelper
{
    private static function getToken(array $tokens): string
    {
        $url  = 'https://oauth.nzpost.co.nz/as/token.oauth2';
        $post = http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $tokens['nzpost_client_id']     ?? '',
            'client_secret' => $tokens['nzpost_client_secret'] ?? '',
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        $j = json_decode($raw ?: '{}', true);
        $token = $j['access_token'] ?? '';
        if ($token === '') throw new \RuntimeException('NZ Post token failed');
        return $token;
    }

    public static function createShipment(array $tokens, array $plan): array
    {
        $token = self::getToken($tokens);
        $url   = 'https://api.nzpost.co.nz/shipments';
        $hdrs  = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];

        $body = [
            'sender_reference' => $tokens['nzpost_sender_ref'] ?? null,
            'parcels'          => self::mapParcels($plan),
            'options'          => $plan['options'] ?? [],
        ];

        $resp = self::postJson($url, $hdrs, $body);
        return [
            'order_id'     => $resp['shipment_id'] ?? null,
            'order_number' => $resp['reference']   ?? null,
            'parcels'      => array_map(static function($p){
                return [
                    'tracking'  => $p['tracking_number'] ?? null,
                    'label_url' => $p['label_url'] ?? null,
                    'weight_g'  => $p['weight_g'] ?? null,
                    'items'     => $p['items'] ?? []
                ];
            }, $resp['parcels'] ?? [])
        ];
    }

    private static function mapParcels(array $plan): array
    {
        $out = [];
        foreach (($plan['parcels'] ?? []) as $p) {
            $out[] = [
                'dimensions' => [
                    'length_mm' => $p['dims'][0] ?? null,
                    'width_mm'  => $p['dims'][1] ?? null,
                    'height_mm' => $p['dims'][2] ?? null,
                ],
                'weight_g'  => $p['weight_g'] ?? null,
                'items'     => $p['items'] ?? [],
            ];
        }
        return $out;
    }

    private static function postJson(string $url, array $headers, array $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT        => 30,
        ]);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException("NZ Post API error ($code): " . ($raw ?: $err));
        }
        $j = json_decode($raw ?: '[]', true);
        return is_array($j) ? $j : [];
    }
}
