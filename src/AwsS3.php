<?php

namespace Aws;

use Aws\S3\S3Client;
use \Aws\S3\StreamWrapper;

class AwsS3
{
    private static ?StreamWrapper $streamWrapper = null;
    /**
     * @var object
     */
    protected $s3Client;
    /**
     * @var
     */
    protected $bucket;

    /**
     * 10K Chunk
     *
     * @var int
     */
    protected $streamChunk = 10240;

    /**
     * @var null
     */
    protected $downloadStreamChunks = null;

    /**
     * @var null
     */
    protected $downloadStream = null;


    public function __construct()
    {
        $this->s3Client = S3Client::factory();
    }

    private function invalidateStatic(): void
    {
        self::$streamWrapper = null;
    }

    /**
     * @param $objectName
     *
     * @return int|int
     */
    public function countDownloadStreamChunks($objectName)
    {
        $stream = $this->getStreamWrapper();
        $openedPath = null;

        $stream->stream_open(sprintf("s3://%s/%s", $this->bucket, $objectName), 'r', null, $openedPath);

        $chunkCount = 0;
        while (!$stream->stream_eof()) {
            $stream->stream_read($this->streamChunk);
            $chunkCount++;
        }

        $stream->stream_close();
        return $chunkCount;
    }

    /**
     * Gets a stream wrapper to load data in chunks
     *
     * The docs in the Aws\S3\StreamWrapper class are well worth a look.
     *
     * @return StreamWrapper
     */
    public function getStreamWrapper()
    {
        if (self::$streamWrapper === null) {
            StreamWrapper::register($this->s3Client);
            self::$streamWrapper = new StreamWrapper();
        }
        return self::$streamWrapper;
    }

    /**
     * Closes the download stream
     *
     * @return void
     */
    public function closeDownloadStream(): void
    {
        $this->downloadStream->stream_close();
    }


    /**
     * @param $sourceBucket
     * @param $sourceFile
     * @param $destinationFile
     * @param string $acl
     *
     * @return void
     */
    public function copyObject($sourceBucket, $sourceFile, $destinationFile, $acl = 'bucket-owner-full-control'): void
    {
        $data = [
            'ACL' => $acl,
            'Bucket' => $this->bucket,
            'Key' => $destinationFile,
            'CopySource' => $sourceBucket . '/' . $sourceFile
        ];

        try {
            $this->s3Client->copyObject($data);
        } catch (S3Exception $e) {
            throw new \S3Exception(sprintf("Failed to copy file '%s' to '%s' in S3.", $sourceFile, $destinationFile));
        }
    }

    /**
     * @param $localFilename
     * @param $s3filename
     * @param string $acl
     *
     * @return void
     */
    public function putObject($localFilename, $s3filename, $acl = 'bucket-owner-full-control'): void
    {
        $data = [
            'ACL' => $acl,
            'Bucket' => $this->bucket,
            'Key' => $s3filename,
            'SourceFile' => $localFilename
        ];
        try {
            $this->s3Client->putObject($data);
        } catch (S3Exception $e) {
            throw new \S3Exception(sprintf("Failed to upload file '%s' to S3.", $s3filename));
        }
    }

    /**
     * Reads an object of off the S3 Bucket
     *
     * @param  $s3filename
     * @return mixed
     */
    public function getObject($s3filename)
    {
        try {
            $result = $this->s3Client->getObject(
                [
                'Bucket' => $this->bucket,
                'Key' => $s3filename
                ]
            );
            return $result['Body'];
        } catch (S3Exception $e) {
            throw new \S3Exception(sprintf("Failed to read file '%s' from S3.", $s3filename));
        }
    }

    /**
     * @param $s3filename
     *
     * @return void
     */
    public function deleteObject($s3filename): void
    {
        try {
            $this->s3Client->deleteObject(
                [
                'Bucket' => $this->bucket,
                'Key' => $s3filename
                ]
            );
        } catch (S3Exception $e) {
            throw new \S3Exception(sprintf("Failed to delete file '%s' from S3.", $s3filename));
        }
    }
}
