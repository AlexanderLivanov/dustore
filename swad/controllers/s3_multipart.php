<?php
// swad/controllers/s3_multipart.php
// Multipart-загрузка в S3 с presigned-ссылками на части.
// Браузер PUT'ит части напрямую в S3 по этим ссылкам, S3 склеивает их
// в ОДИН объект. Сервер не хранит байты — только раздаёт presigned URL
// и финализирует. Совместимо с S3-endpoint reg.ru (path-style).

require_once __DIR__ . '/../../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class S3Multipart
{
    private S3Client $s3;
    private string $bucket;

    // Размер части. S3 требует минимум 5 МБ на часть (кроме последней).
    public const PART_SIZE = 10 * 1024 * 1024; // 10 МБ

    public function __construct()
    {
        $this->s3 = new S3Client([
            'version' => 'latest',
            'region'  => AWS_S3_REGION,
            'credentials' => [
                'key'    => AWS_S3_KEY,
                'secret' => AWS_S3_SECRET,
            ],
            'endpoint' => AWS_S3_ENDPOINT,
            'use_path_style_endpoint' => true,
        ]);
        $this->bucket = AWS_S3_BUCKET_USERCONTENT;
    }

    /** Старт multipart-загрузки. Возвращает uploadId. */
    public function initiate(string $key, string $contentType = 'application/zip'): string
    {
        $res = $this->s3->createMultipartUpload([
            'Bucket'      => $this->bucket,
            'Key'         => $key,
            'ACL'         => 'public-read',
            'ContentType' => $contentType,
        ]);
        return (string)$res['UploadId'];
    }

    /** Presigned URL на загрузку одной части (PartNumber от 1). */
    public function presignPart(string $key, string $uploadId, int $partNumber, string $expires = '+30 minutes'): string
    {
        $cmd = $this->s3->getCommand('UploadPart', [
            'Bucket'     => $this->bucket,
            'Key'        => $key,
            'UploadId'   => $uploadId,
            'PartNumber' => $partNumber,
        ]);
        return (string)$this->s3->createPresignedRequest($cmd, $expires)->getUri();
    }

    /**
     * Финализация. $parts = [['PartNumber'=>1,'ETag'=>'"..."'], ...]
     * Возвращает публичный URL готового объекта.
     */
    public function complete(string $key, string $uploadId, array $parts): string
    {
        // S3 требует части по возрастанию PartNumber.
        usort($parts, fn($a, $b) => $a['PartNumber'] <=> $b['PartNumber']);

        $res = $this->s3->completeMultipartUpload([
            'Bucket'          => $this->bucket,
            'Key'             => $key,
            'UploadId'        => $uploadId,
            'MultipartUpload' => ['Parts' => $parts],
        ]);

        if (!empty($res['Location'])) {
            return (string)$res['Location'];
        }
        // Фолбэк на path-style URL.
        return rtrim(AWS_S3_ENDPOINT, '/') . '/' . $this->bucket . '/' . $key;
    }

    /** Отмена — освобождает уже загруженные части, чтобы не копить мусор. */
    public function abort(string $key, string $uploadId): bool
    {
        try {
            $this->s3->abortMultipartUpload([
                'Bucket'   => $this->bucket,
                'Key'      => $key,
                'UploadId' => $uploadId,
            ]);
            return true;
        } catch (S3Exception $e) {
            error_log('S3 abort error: ' . $e->getMessage());
            return false;
        }
    }
}