<?php
/**
 * Cliente S3 mínimo (AWS, Cloudflare R2, Backblaze B2, MinIO)
 * Usa Signature Version 4 via cURL — sem Composer.
 */
class BackupS3Client
{
    private string $accessKey;
    private string $secretKey;
    private string $region;
    private string $bucket;
    private string $endpoint;
    private bool $pathStyle;

    public function __construct(
        string $accessKey,
        string $secretKey,
        string $bucket,
        string $region = 'us-east-1',
        string $endpoint = '',
        bool $pathStyle = false
    ) {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->bucket = $bucket;
        $this->region = $region !== '' ? $region : 'us-east-1';
        $this->endpoint = rtrim($endpoint, '/');
        $this->pathStyle = $pathStyle || $endpoint !== '';
    }

    /**
     * @throws RuntimeException
     */
    public function putObject(string $key, string $filePath): void
    {
        if (!is_file($filePath)) {
            throw new RuntimeException('Arquivo local não encontrado para upload S3.');
        }

        $body = file_get_contents($filePath);
        if ($body === false) {
            throw new RuntimeException('Falha ao ler arquivo para upload S3.');
        }

        $key = ltrim(str_replace('\\', '/', $key), '/');
        $contentHash = hash('sha256', $body);
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');

        [$host, $url] = $this->buildUrl($key);
        $canonicalUri = $this->pathStyle
            ? '/' . $this->encodePath($this->bucket . '/' . $key)
            : '/' . $this->encodePath($key);

        $headers = [
            'host' => $host,
            'x-amz-content-sha256' => $contentHash,
            'x-amz-date' => $amzDate,
            'content-type' => 'application/octet-stream',
            'content-length' => (string) strlen($body),
        ];

        // AWS SigV4 exige os cabeçalhos canônicos em ordem alfabética por nome;
        // sem isso a assinatura calculada não bate com a do servidor (SignatureDoesNotMatch).
        ksort($headers);

        $signedHeaders = implode(';', array_keys($headers));
        $canonicalHeaders = '';
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= $name . ':' . trim($value) . "\n";
        }

        $canonicalRequest = implode("\n", [
            'PUT',
            $canonicalUri,
            '',
            $canonicalHeaders,
            $signedHeaders,
            $contentHash,
        ]);

        $credentialScope = $dateStamp . '/' . $this->region . '/s3/aws4_request';
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = $this->signingKey($dateStamp);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorization = 'AWS4-HMAC-SHA256 Credential=' . $this->accessKey . '/' . $credentialScope
            . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

        $curlHeaders = [
            'Host: ' . $host,
            'Content-Type: application/octet-stream',
            'Content-Length: ' . strlen($body),
            'x-amz-content-sha256: ' . $contentHash,
            'x-amz-date: ' . $amzDate,
            'Authorization: ' . $authorization,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('cURL S3: ' . $curlError);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("S3 HTTP $httpCode: " . trim((string) $response));
        }
    }

    private function buildUrl(string $key): array
    {
        if ($this->pathStyle) {
            $host = parse_url($this->endpoint, PHP_URL_HOST) ?: $this->endpoint;
            $scheme = parse_url($this->endpoint, PHP_URL_SCHEME) ?: 'https';
            $port = parse_url($this->endpoint, PHP_URL_PORT);
            $base = $scheme . '://' . $host . ($port ? ':' . $port : '');
            $url = $base . '/' . rawurlencode($this->bucket) . '/' . $this->encodePathSegments($key);
            return [$host, $url];
        }

        $host = $this->bucket . '.s3.' . $this->region . '.amazonaws.com';
        $url = 'https://' . $host . '/' . $this->encodePathSegments($key);
        return [$host, $url];
    }

    private function encodePath(string $path): string
    {
        $parts = explode('/', $path);
        return implode('/', array_map('rawurlencode', $parts));
    }

    private function encodePathSegments(string $key): string
    {
        return $this->encodePath($key);
    }

    private function signingKey(string $dateStamp): string
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }
}
