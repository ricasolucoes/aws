<?php

namespace Aws;

use \Aws\Exceptions\AwsException;
use \Aws\Sqs\SqsClient;

/**
 * AWS SQS wrapper for JBGlobal
 */
class Sqs
{
    protected $queueUrl;
    protected $region;
    protected $version;
    protected $client;
    protected $stash;
    /**
     * initialize - return authenticated sqs object
     *
     * @param string $uri      URI
     * @param string $username Username
     * @param string $password Password
     *
     * @return bool
     */
    public function __construct($queueUrl, $region = 'eu-west-1', $version = '2012-11-05')
    {
        $this->queueUrl = $queueUrl;
        $this->region = $region;
        $this->version = $version;
        $this->stash = [];
        $this->authenticate();
    }

    /**
     * authenticate - authenticate and set client object
     *
     * @return \Aws\Sqs\SqsClient
     */
    public function authenticate()
    {
        $this->client = new SqsClient(
            [
            'region' => $this->region,
            'version' => $this->version
            ]
        );
        return $this->client;
    }

    /**
     * deleteMessage - try to delete a message from the queue
     *
     * @param  array $msg Array Result from getMessage
     * @return mixed
     */
    public function deleteMessage($msg, $queueurl = false, $receiptHandle = false)
    {
        $data = [
            'QueueUrl' => $this->queueUrl,
        ];
        if ($queueurl) {
            $data['QueueUrl'] = $queueurl;
        }
        if ($receiptHandle) {
            $data['ReceiptHandle'] = $receiptHandle;
        } else {
            $data['ReceiptHandle'] = $msg['allData']['ReceiptHandle'];
        }
        // echo var_export($data, true) . "\n";
        file_put_contents("/tmp/del2.txt", "trying to delete\n", FILE_APPEND);
        $result = $this->client->deleteMessage($data);
        file_put_contents("/tmp/del.txt", "res: " . var_export($result, true) . "\n", FILE_APPEND);
    }

    /**
     * getMessage - try to pull a message from the queue
     *
     * @return mixed
     */
    public function getMessage()
    {
        $result = $this->client->receiveMessage(
            [
                'MaxNumberOfMessages' => 1,
                'MessageAttributeNames' => ['All'],
                'QueueUrl' => $this->queueUrl
            ]
        );

        if (count($result->get('Messages')) > 0) {
            $message = $result->get('Messages')[0];
            $commandData = json_decode($message['MessageAttributes']['commandData']['StringValue'], true);
            $metaData = json_decode($message['MessageAttributes']['metaData']['StringValue'], true);
            $data = [
                'commandData' => $commandData,
                'metaData' => $metaData,
                'allData' => $message
            ];
            return $data;
        } else {
            return false;
        }
    }

    /**
     * sendMessage - immediately send a message
     *
     * @param  array $params Message params
     * @return mixed
     */
    public function sendMessage($params)
    {
        try {
            $preppedMessage = $this->prepMessage($params);
            $msgFilename = $this->logMessage($preppedMessage, 'single');
            return $this->client->sendMessage($preppedMessage);
        } catch (AwsException $e) {
            `mkdir -p /tmp/sqs/failed/`;
            `mv $msgFilename /tmp/sqs/failed/`;
            error_log($e->getMessage());
            echo $e->getMessage() . "\n";
        }
    }

    /**
     * logMessage - save reconstituted details of a message for later analysis
     *
     * @param  array  $msg       Message
     * @param  string $msgSource Message source
     * @return mixed
     */
    public function logMessage($msg, $msgSource = 'unknown')
    {
        file_put_contents("/tmp/sqs-prepayload.log", var_export($msg, true) . "\n", FILE_APPEND);
        $message = $msg;

        $commandData = json_decode($message['MessageAttributes']['commandData']['StringValue'], true);
        $metaData = json_decode($message['MessageAttributes']['metaData']['StringValue'], true);
        $command = $commandData['command'];
        $via = $metaData['via'];

        $reconstituted = [];

        foreach ($commandData as $k => $v) {
            $reconstituted[$k] = $v;
        }
        foreach ($metaData as $k => $v) {
            $reconstituted[$k] = $v;
        }

        $timestamp = gmdate('Y-m-d H:i:s \G\M\T');
        $reconstituted['via'] = 'sqspoller preconstitution replay at ' . $timestamp;
        $reconstitutedString = '$sqs->sendMessage(' . "\n";
        $reconstitutedString .="\t[\n";
        foreach ($reconstituted as $k => $v) {
            $reconstitutedString .= "\t\t" . "'" . $k . "' => '" . $v . "',\n";
        }
        $reconstitutedString .="\t]\n";
        $reconstitutedString .=");\n";

        file_put_contents("/tmp/sqs-preconstituted.log", $reconstitutedString, FILE_APPEND);

        $tempDir = '/tmp/sqs/msgsAttempted/' . date('Y-m-d') . '/';
        $tempFile = date('Y-m-d H:i:s') . '-' . $msgSource . '-' . time() . '-' . rand(10000, 99999) . '.txt';
        $tempPath = $tempDir . $tempFile;
        `mkdir -p $tempDir`;
        file_put_contents($tempPath, $reconstitutedString);

        return $tempPath;
    }

    /**
     * sendMessages - send stashed messages.  useful for combining multiple sends into one api call to sqs - sqs limits to 10 messages per batch so we intelligently break it down into batches of up to 10 msgs and ensure everything is sent
     *
     * @return mixed
     */
    public function sendMessages()
    {
        $stashedTotal = count($this->stash);

        $count = 0;
        $bulk = [];
        $total = 0;
        $msgFilenames = [];
        foreach ($this->stash as $stashed) {
            $msgFilename = $this->logMessage($stashed, 'batched');

            array_push($bulk, $stashed);
            array_push($msgFilenames, $msgFilename);
            if (($count > 8) or ($total == ($stashedTotal - 1))) {
                // we've hit our limit - send the messages and reset the count
                $entries = [];
                $bulkCount = 0;
                foreach ($bulk as $bulkMsg) {
                    $bulkMsg['Id'] = $bulkCount;
                    unset($bulkMsg['QueueUrl']);
                    array_push($entries, $bulkMsg);
                    $bulkCount++;
                }
                $send = [
                    'QueueUrl' => $this->queueUrl,
                    'Entries' => $entries
                ];

                try {
                    $result = $this->client->sendMessageBatch($send);
                } catch (AwsException $e) {
                    `mkdir -p /tmp/sqs/failed/`;

                    foreach ($msgFilenames as $msgFilename) {
                        `mv "$msgFilename" /tmp/sqs/failed/`;
                        error_log($e->getMessage());
                        echo $e->getMessage() . "\n";
                    }
                }

                $count = 0;
                $bulk = [];
                $msgFilenames = [];
            } else {
                $count++;
            }
            $total++;
        }
    }

    /**
     * sendMessagesNoBatching - send stashed messages without batching them
     *
     * @return mixed
     */
    public function sendMessagesNoBatching()
    {
        foreach ($this->stash as $stashed) {
            $msgFilename = $this->logMessage($stashed, 'unbatched');
            try {
                $result = $this->client->sendMessage($stashed);
            } catch (AwsException $e) {
                `mkdir -p /tmp/sqs/failed/`;
                `mv "$msgFilename" /tmp/sqs/failed/`;
                error_log($e->getMessage());
                echo $e->getMessage() . "\n";
            }
        }
    }

    /**
     * stashMessage - put a message to one side so it can be bulk sent later using sendMessages
     *
     * @param  array $params Message params
     * @return mixed
     */
    public function stashMessage($params)
    {
        array_push($this->stash, $this->prepMessage($params));
        return true;
    }

    /**
     * prepMessage - bundle a message up ready for sending
     *
     * @param  array $params Message params
     * @return mixed
     */
    protected function prepMessage($params)
    {
        if (!array_key_exists('key', $params) or $params['key'] == '') {
            $params['key'] = 'unknown';
        }
        $data = [
                'QueueUrl' => $this->queueUrl,
                'MessageBody' => $params['key'] . " " . $params['command'] . " '" . $params['parameter'] . "' in " . ucfirst($params['system']) . "  because " . $params['because'] . " via " . $params['via'],
                'MessageGroupId' => $params['key'],
                'MessageAttributes' => []
        ];

        $commandData = [];
        $metaData = [];
        foreach ($params as $k => $v) {
            if (preg_match("/^command/", $k)) {
                if ($k == 'commandEmailData') {
                    $tempDir = '/tmp/sqs/email/' . date('Y-m-d') . '/';
                    $tempFile = date('Y-m-d H:i:s') . '-' . rand(10000, 99999) . '.txt';
                    $tempPath = $tempDir . $tempFile;

                    $emailData = json_decode($v, true);
                    $text = $emailData['message'];
                    $emailData['message'] = '';
                    $emailData['messagePath'] = $tempPath;

                    `mkdir -p $tempDir`;
                    file_put_contents($tempPath, $text);
                    $commandData['commandEmailData'] = json_encode($emailData);
                } else {
                    $commandData[$k] = $v;
                }
            } else {
                $metaData[$k] = $v;
            }
        }
        $data['MessageAttributes']['commandData'] = [
            'DataType' => 'String.json',
            'StringValue' => json_encode($commandData)
        ];
        $data['MessageAttributes']['metaData'] = [
            'DataType' => 'String.json',
            'StringValue' => json_encode($metaData)
        ];
        return $data;
    }

    /**
     * getClient - return client object
     *
     * @return \Aws\Sqs\SqsClient
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * getRegion - return the region
     *
     * @return string $region
     */
    public function getRegion()
    {
        return $this->region;
    }
    /**
     * setRegion - set the effective region
     *
     * @param  string $region Aws region string
     * @return string $region
     */
    public function setRegion($region)
    {
        $this->region = $region;
        return $this;
    }

    /**
     * getVersion - return the version
     *
     * @return string $version
     */
    public function getVersion()
    {
        return $this->version;
    }
    /**
     * setVersion - set the effective version
     *
     * @return string $version
     */
    public function setVersion($version)
    {
        $this->version = $version;
        return $this;
    }

    /**
     * getQueueUrl - return the queueUrl
     *
     * @return string $queueUrl
     */
    public function getQueueUrl()
    {
        return $this->queueUrl;
    }
    /**
     * setQueueUrl - set the effective queueUrl
     *
     * @return string $queueUrl
     */
    public function setQueueUrl($queueUrl)
    {
        $this->queueUrl = $queueUrl;
        return $this;
    }
}
