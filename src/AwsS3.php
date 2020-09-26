<?php

namespace Aws;

use Aws\S3\S3Client;
use \Aws\S3\StreamWrapper;

class AwsS3
{
    private static $streamWrapper = null;
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

    public function getBucket()
    {
        return $this->bucket;
    }

    public function setBucket($bucket)
    {
        $this->bucket = $bucket;
        $this->invalidateStatic();
    }

    private function invalidateStatic()
    {
        self::$streamWrapper = null;
    }

    public function setstreamChunk($value)
    {
        $this->streamChunk = $value;
    }

    /**
     * @param $objectName
     * @param null $chunks
     */
    public function openDownloadStream($objectName, $chunks = null)
    {
        $this->downloadStreamChunks = $chunks;
        if (!$this->downloadStreamChunks) {
            $this->downloadStreamChunks = $this->countDownloadStreamChunks($objectName);
        }

        $this->downloadStream = $this->getStreamWrapper();
        $openedPath = null;

        $this->downloadStream->stream_open(sprintf("s3://%s/%s", $this->bucket, $objectName), 'r', null, $openedPath);
    }

    /**
     * @param  $objectName
     * @return int
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
     * @param  int $offSet
     * @return null
     */
    public function getDownloadStream($offSet = 0)
    {
        if (!$this->downloadStreamChunks) {
            $this->closeDownloadStream();
            return null;
        }

        if ($offSet > $this->downloadStreamChunks) {
            $offSet = $this->downloadStreamChunks;
        }

        while (!$this->downloadStream->stream_eof()) {
            $this->downloadStream->stream_seek($offSet * $this->streamChunk);
            $data = $this->downloadStream->stream_read($this->streamChunk);
            if ($offSet == $this->downloadStreamChunks) {
                $this->closeDownloadStream();
            }
            return $data;
        }
    }

    /**
     * Closes the download stream
     */
    public function closeDownloadStream()
    {
        $this->downloadStream->stream_close();
    }

    /**
     * @param $sourceFile
     * @param $destinationFile
     * @param string $acl
     */
    public function copyLocalObject($sourceFile, $destinationFile, $acl = 'bucket-owner-full-control')
    {
        $data = [
            'ACL' => $acl,
            'Bucket' => $this->bucket,
            'Key' => $destinationFile,
            'CopySource' => $this->bucket . '/' . $sourceFile
        ];

        try {
            $this->s3Client->copyObject($data);
        } catch (S3Exception $e) {
            throw new \S3Exception(sprintf("Failed to copy file '%s' to '%s' in S3.", $sourceFile, $destinationFile));
        }
    }

    /**
     * @param $sourceBucket
     * @param $sourceFile
     * @param $destinationFile
     * @param $acl
     */
    public function moveObject($sourceBucket, $sourceFile, $destinationFile, $acl = 'bucket-owner-full-control')
    {
        $this->copyObject($sourceBucket, $sourceFile, $destinationFile, $acl);
        $this->deleteObject($sourceFile);
    }


    /**
     * @param $sourceBucket
     * @param $sourceFile
     * @param $destinationFile
     * @param string $acl
     */
    public function copyObject($sourceBucket, $sourceFile, $destinationFile, $acl = 'bucket-owner-full-control')
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
     */
    public function putObject($localFilename, $s3filename, $acl = 'bucket-owner-full-control')
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
     * Saves an object to a local location
     *
     * @param $s3filename
     * @param $localFilename
     */
    public function saveObject($s3filename, $localFilename)
    {
        try {
            $this->s3Client->getObject(
                [
                'Bucket' => $this->bucket,
                'Key' => $s3filename,
                'SaveAs' => $localFilename
                ]
            );
        } catch (S3Exception $e) {
            throw new \S3Exception(sprintf("Failed to get file '%s' from S3.", $s3filename));
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
     * @param  string $folder
     * @param  string $delimiter
     * @return mixed
     */
    public function listObjects($folder = '',$delimiter = '')
    {
        try {
            return $this->s3Client->getIterator(
                'ListObjects', array(
                "Bucket" => $this->bucket,
                "Prefix" => $folder,
                "Delimiter" => $delimiter
                )
            );
        } catch (S3Exception $e) {
            throw new \S3Exception(sprintf("Failed to list objects in '%s' from S3.", $this->bucket));
        }
    }

    /**
     * Alternative name for deleteObject
     *
     * @param $s3filename
     */
    public function removeObject($s3filename)
    {
        return $this->deleteObject($s3filename);
    }

    /**
     * @param $s3filename
     */
    public function deleteObject($s3filename)
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
