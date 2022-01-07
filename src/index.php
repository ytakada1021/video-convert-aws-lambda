<?php

declare(strict_types=1);

require_once('Assert.php');
require_once('PathInfoUtil.php');

use Aws\MediaConvert\MediaConvertClient;

/**
 * @var string EXPECTED_EVENT_VERSION
 */
const EXPECTED_EVENT_VERSION = '2.2';

/**
 * @param array $event
 * @return void
 */
function index(array $event): void
{
    Assert::isNotNull($event, '$event cannot be null.');

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
    Assert::isNotEmpty($_ENV['MEDIA_CONVERT_EXECUTION_REGION'], 'Environment variable MEDIA_CONVERT_EXECUTION_REGION must be provided.');
    Assert::isNotEmpty($_ENV['MEDIA_CONVERT_ENDPOINT'], 'Environment variable MEDIA_CONVERT_EXECUTMEDIA_CONVERT_ENDPOINTION_REGION must be provided.');
    Assert::isNotEmpty($_ENV['AWS_ACCESS_KEY_ID'], 'Environment variable AWS_ACCESS_KEY_ID must be provided.');
    Assert::isNotEmpty($_ENV['AWS_SECRET_ACCESS_KEY'], 'Environment variable AWS_SECRET_ACCESS_KEY must be provided.');

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

/**
 * MediaConvertパラメータ参照先: https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-mediaconvert-2017-08-29.html#createjob
 *
 * @param MediaConvertClient $client
 * @param string $inputBucketName
 * @param string $inputObjectKey
 * @return void
 */
function makeVideoConvertRequest(MediaConvertClient $client, string $inputBucketName, string $inputObjectKey): void
{
    Assert::isNotEmpty($_ENV['MEDIA_CONVERT_EXECUTION_ROLE_ARN'], 'Environment variable MEDIA_CONVERT_EXECUTION_ROLE_ARN must be provided.');
    Assert::isNotEmpty($_ENV['INPUT_S3_BUCKET_NAME'], 'Environment variable INPUT_S3_BUCKET_NAME must be provided.');
    Assert::isNotEmpty($_ENV['OUTPUT_S3_BUCKET_NAME'], 'Environment variable OUTPUT_S3_BUCKET_NAME must be provided.');
    Assert::isTrue($inputBucketName === $_ENV['INPUT_S3_BUCKET_NAME'], '$inputBucketName and MEDIA_CONVERT_EXECUTION_REGION must be the same.');

    /** @var string $outputObjectKey */
    $outputObjectKey = PathInfoUtil::filenameOf($inputObjectKey);

    $client->createJob([
        'Role' => $_ENV['MEDIA_CONVERT_EXECUTION_ROLE_ARN'],
        'Settings' => [
            'TimecodeConfig' => [
                'Source' => 'ZEROBASED'
            ],
            'OutputGroups' => [
                [
                    'Name' => 'Apple HLS',
                    'Outputs' => [
                        [
                            'ContainerSettings' => [
                                'Container' => 'M3U8',
                                'M3u8Settings' => []
                            ],
                            'VideoDescription' => [
                                'CodecSettings' => [
                                    'Codec' => 'H_264',
                                    'H264Settings' => [
                                        'MaxBitrate' => 2000000,
                                        'RateControlMode' => 'QVBR',
                                        'SceneChangeDetect' => 'TRANSITION_DETECTION'
                                    ]
                                ]
                            ],
                            'AudioDescriptions' => [
                                [
                                    'AudioSourceName' => 'Audio Selector 1',
                                    'CodecSettings' => [
                                        'Codec' => 'AAC',
                                        'AacSettings' => [
                                            'Bitrate' => 96000,
                                            'CodingMode' => 'CODING_MODE_2_0',
                                            'SampleRate' => 48000
                                        ]
                                    ]
                                ]
                            ],
                            'OutputSettings' => [
                                'HlsSettings' => []
                            ],
                            'NameModifier' => '_0'
                        ]
                    ],
                    'OutputGroupSettings' => [
                        'Type' => 'HLS_GROUP_SETTINGS',
                        'HlsGroupSettings' => [
                            'SegmentLength' => 10,
                            'Destination' => sprintf('s3://%s/%s', $_ENV['OUTPUT_S3_BUCKET_NAME'], $outputObjectKey),
                            'MinSegmentLength' => 0
                        ]
                    ]
                ]
            ],
            'Inputs' => [
                [
                    'AudioSelectors' => [
                        'Audio Selector 1' => [
                            'DefaultSelection' => 'DEFAULT'
                        ]
                    ],
                    'VideoSelector' => [],
                    'TimecodeSource' => 'ZEROBASED',
                    'FileInput' => sprintf('s3://%s/%s', $inputBucketName, $inputObjectKey),
                ]
            ]
        ]
    ]);
}
