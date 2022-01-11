<?php

declare(strict_types=1);

require_once('Assert.php');
require_once('MimeTypeUtil.php');
require_once('PathInfoUtil.php');

use Aws\MediaConvert\MediaConvertClient;

const CONVERSION_TARGET_MIME_TYPES = [
    'video/mp4',
    'video/3gpp',
    'video/3gpp2',
    'video/mpeg',
    'video/quicktime',
    'video/ogg',
    'video/vnd.mpegurl',
    'video/vnd.rn-realvideo',
    'video/vnd.vivo',
    'video/webm',
    'video/x-bamba',
    'video/x-mng',
    'video/x-ms-asf',
    'video/x-ms-wm',
    'video/x-ms-wmv',
    'video/x-ms-wmx',
    'video/x-msvideo',
    'video/x-qmsys',
    'video/x-sgi-movie',
    'video/x-tango',
    'video/x-vif'
];

/**
 * @param array $event
 * @return void
 */
function index(array $event): void
{
    Assert::notNull($event, '$event cannot be null.');

    /** @var array $records */
    $records = $event['Records'];

    /** @var MediaConvertClient $mediaConvertClient */
    $mediaConvertClient = buildMediaConvertClient();

    foreach ($records as $record) {
        $message = json_decode($record['Sns']['Message'], true);

        /** @var string $bucketName */
        $bucketName = $message['Records'][0]['s3']['bucket']['name'];

        /** @var string $objectKey */
        $objectKey = $message['Records'][0]['s3']['object']['key'];

        // 変換対象のファイル形式である場合にMediaConvertへのリクエストを作成する
        if (in_array(
                MimeTypeUtil::convertExtensionToMimeType(PathInfoUtil::extensionOf($objectKey)),
                CONVERSION_TARGET_MIME_TYPES,
                true)) {
            makeVideoConvertRequest($mediaConvertClient, $bucketName, $objectKey);

            print(<<< EOT
            Successfully requested video format conversion.
            Source:
                Bucket Name: $bucketName
                Object Key: $objectKey\n
            EOT);
        }
    }
}

function buildMediaConvertClient(): MediaConvertClient
{
    Assert::notEmpty($_ENV['MEDIA_CONVERT_EXECUTION_REGION'], 'Environment variable MEDIA_CONVERT_EXECUTION_REGION must be provided.');
    Assert::notEmpty($_ENV['MEDIA_CONVERT_ENDPOINT'], 'Environment variable MEDIA_CONVERT_EXECUTMEDIA_CONVERT_ENDPOINTION_REGION must be provided.');

    return new MediaConvertClient([
        'version' => '2017-08-29',
        'region' => $_ENV['MEDIA_CONVERT_EXECUTION_REGION'],
        'endpoint' => $_ENV['MEDIA_CONVERT_ENDPOINT']
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
    Assert::notEmpty($_ENV['MEDIA_CONVERT_EXECUTION_ROLE_ARN'], 'Environment variable MEDIA_CONVERT_EXECUTION_ROLE_ARN must be provided.');
    Assert::notEmpty($_ENV['OUTPUT_S3_BUCKET_NAME'], 'Environment variable OUTPUT_S3_BUCKET_NAME must be provided.');
    Assert::isTrue($inputBucketName !== $_ENV['OUTPUT_S3_BUCKET_NAME'], '$inputBucketName and OUTPUT_S3_BUCKET_NAME must be different.');

    /** @var string $outputObjectKey */
    $outputObjectKey = PathInfoUtil::pathWithoutExtensionOf($inputObjectKey);

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
