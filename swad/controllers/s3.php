<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\S3\Exception\S3Exception;

class S3Uploader
{
    private $s3;

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
            'http' => [
                'timeout' => 300,
                'connect_timeout' => 30
            ]
        ]);
    }

    public function uploadFile($tmp_path, $destination)
    {
        try {
            // Проверяем существование файла
            if (!file_exists($tmp_path)) {
                throw new Exception("Temporary file not found: $tmp_path");
            }

            // Проверяем доступность файла для чтения
            if (!is_readable($tmp_path)) {
                throw new Exception("Temporary file is not readable: $tmp_path");
            }

            $result = $this->s3->putObject([
                'Bucket' => AWS_S3_BUCKET_USERCONTENT,
                'Key'    => $destination,
                'Body'   => fopen($tmp_path, 'rb'),
                'ACL'    => 'public-read',
            ]);

            return $result->get('ObjectURL');
        } catch (S3Exception $e) {
            error_log("S3 Upload Error: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("File Upload Error: " . $e->getMessage());
            return false;
        }
    }

    public function deleteFile($url_or_key)
    {
        try {
            $key = $url_or_key;

            // Если передан полный URL, извлекаем ключ
            if (strpos($url_or_key, 'http') === 0) {
                $parsed = parse_url($url_or_key);
                $key = ltrim($parsed['path'] ?? '', '/');

                // Убираем имя бакета из пути если есть
                if (strpos($key, AWS_S3_BUCKET_USERCONTENT . '/') === 0) {
                    $key = substr($key, strlen(AWS_S3_BUCKET_USERCONTENT) + 1);
                }
            }

            error_log("Deleting S3 file with key: " . $key);

            $result = $this->s3->deleteObject([
                'Bucket' => AWS_S3_BUCKET_USERCONTENT,
                'Key'    => $key,
            ]);

            error_log("Delete successful for key: " . $key);
            return true;
        } catch (S3Exception $e) {
            error_log("S3 Delete Error: " . $e->getMessage());
            // Не прерываем выполнение если удаление старого файла не удалось
            return false;
        } catch (Exception $e) {
            error_log("Delete Error: " . $e->getMessage());
            return false;
        }
    }

    // Метод для проверки существования файла
    public function fileExists($key)
    {
        try {
            $this->s3->headObject([
                'Bucket' => AWS_S3_BUCKET_USERCONTENT,
                'Key'    => $key,
            ]);
            return true;
        } catch (S3Exception $e) {
            return false;
        }
    }
}
