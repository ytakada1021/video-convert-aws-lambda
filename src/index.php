<?php

declare(strict_types=1);

require_once('Assert.php');

use Aws\MediaConvert\MediaConvertClient;
use Aws\Exception\AwsException;

/**
 * @var string EXPECTED_EVENT_VERSION
 */
const EXPECTED_EVENT_VERSION = '2.2';

/**
 * AWS SDK MediaConvert: https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-mediaconvert-2017-08-29.html#createjob
 *
 * @param array $event
 * @return void
 */
function index(array $event): void
{
    Assert::isNotNull($event);

    /** @var array $records */
    $records = $event['Records'];

    /** @var MediaConvertClient $mediaConvertClient */
    $mediaConvertClient = buildMediaConvertClient();

    foreach ($records as $record) {
        if ($record['eventName'] !== 's3:ObjectCreated:*') {
            continue;
        }

        // イベントバージョンを検証し、想定されていないバージョンの場合はログに警告メッセージを出力する
        // イベントバージョン参考: https://docs.aws.amazon.com/ja_jp/AmazonS3/latest/userguide/notification-content-structure.html
        /** @var string $eventVersion */
        $eventVersion = $record['eventVersion'];
        if ($eventVersion !== EXPECTED_EVENT_VERSION) {
            printf(<<< 'EOT'
            想定S3イベントバージョン（=%1$s）以外のバージョンのイベントを処理しようとしています（バージョン%2$s）.
            バージョン%2$sへの対応を検討ください.
            EOT, EXPECTED_EVENT_VERSION, $eventVersion);
        }

        /** @var string $bucketName */
        $bucketName = $record['s3']['bucket']['name'];

        /** @var string $objectKey */
        $objectKey = $record['s3']['object']['key'];

        makeVideoConvertRequest($mediaConvertClient, $bucketName, $objectKey);
    }
}

function buildMediaConvertClient(): MediaConvertClient
{
    Assert::isNotEmpty($_ENV['MEDIA_CONVERT_EXECUTION_REGION']);
    Assert::isNotEmpty($_ENV['MEDIA_CONVERT_EXECUTION_PROFILE']);
    Assert::isNotEmpty($_ENV['MEDIA_CONVERT_ENDPOINT']);

    return new MediaConvertClient([
        'version' => '2017-08-29',
        'region' => $_ENV['MEDIA_CONVERT_EXECUTION_REGION'],
        'endpoint' => $_ENV['MEDIA_CONVERT_ENDPOINT'],
        'credentials' => [
            'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
            'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
        ]
    ]);
}

function makeVideoConvertRequest(MediaConvertClient $client, string $inputBucketName, string $inputObjectKey): void
{
    Assert::isNotEmpty($_ENV['INPUT_S3_BUCKET_NAME']);
    Assert::isNotEmpty($_ENV['MEDIA_CONVERT_EXECUTION_ROLE_ARN']);
    Assert::isNotEmpty($_ENV['MEDIA_CONVERT_JOB_TEMPLATE_NAME']);
    Assert::isNotEmpty($_ENV['OUTPUT_S3_BUCKET_NAME']);
    Assert::isTrue($inputBucketName === $_ENV['INPUT_S3_BUCKET_NAME']);

    try {
        $client->createJob([
            'Role' => $_ENV['MEDIA_CONVERT_EXECUTION_ROLE_ARN'],
            'Settings' => prepareMediaConvertSetting($inputBucketName, $inputObjectKey, $_ENV['OUTPUT_S3_BUCKET_NAME']),
            'JobTemplate' => $_ENV['MEDIA_CONVERT_JOB_TEMPLATE_NAME']
        ]);
    } catch (AwsException $e) {
        echo $e->getMessage();
        echo '\n';
    }
}

function prepareMediaConvertSetting(string $inputBucketName, string $inputObjectKey, string $outputBucketname): array
{
    /** @var string $outputObjectKey */
    $outputObjectKey = $inputObjectKey;

    return [
        'Inputs' => [
            [
                'FileInput' => sprintf('s3://%s/%s', $inputBucketName, $inputObjectKey)
            ]
        ],
        'OutputGroups' => [
            [
                'Name' => 'Apple HLS',
                'OutputGroupSettings' => [
                    'Type' => 'HLS_GROUP_SETTINGS',
                    'HlsGroupSettings' => [
                        'Destination' => sprintf('s3://%s/%s', $outputBucketname, $outputObjectKey),
                    ],
                ],
                'Outputs' => [
                    [
                        'VideoDescription' => [
                            'Width' => 640,
                            'Height' => 360,
                        ],
                    ],
                ],
            ],
        ]
    ];
}
