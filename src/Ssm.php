<?php

namespace Aws;

use \Aws\Exceptions\AwsException;
use \Aws\Ssm\SsmClient;

/**
 * AWS SSM wrapper for JBGlobal
 */
class Ssm
{
    protected $region;
    protected $version;
    protected $client;
    protected $stash;
    protected $maxConcurrency = "100";

    /**
     * initialize - return authenticated ssm object
     *
     * @param string $region  Region
     * @param string $version Version
     *
     * @return bool
     */
    public function __construct($region = 'eu-west-1', $version = 'latest')
    {
        $this->region = $region;
        $this->version = $version;
        $this->stash = [];
        $this->authenticate();
    }

    /**
     * authenticate - authenticate and set client object
     *
     * @return \Aws\Ssm\SsmClient
     */
    public function authenticate()
    {
        $this->client = new SsmClient(
            [
                'region' => $this->region,
                'version' => $this->version
            ]
        );
        return $this->client;
    }

    // scive should each be an array or null
    /**
     * sendCommand - execute an ssm document against an inclusive set of tags based on the SCIVE, returning a command id
     *
     * @param string $documentName Document name
     * @param array  $service      Service
     * @param array  $country      Country
     * @param array  $interface    Interface
     * @param array  $variant      Variant
     * @param array  $environment  Environment
     * @param bool   $runShell     If set to true we run a shell command instead of a document
     *
     * @return string Command ID
     */
    public function sendCommand($documentName, $service = null, $country = null, $interface = null, $variant = null, $environment = null, $parameters = null, $runShell = false)
    {
        if (!$service) {
            return false;
        }

        if ($runShell) {
            // we assume some shell syntax was passed instead of a document name
            $parameters = ['commands' => [$documentName], 'executionTimeout' => ['300']];

            // the actual document name wants to be the one for running a shell script
            $documentName = 'AWS-RunShellScript';
        }

        $targets = [
            [
                'Key' => 'tag:service',
                'Values' => $service
            ]
        ];

        if ($country) {
            array_push(
                $targets,
                [
                    'Key' => 'tag:country',
                    'Values' => $country
                ]
            );
        }
        if ($interface) {
            array_push(
                $targets,
                [
                    'Key' => 'tag:interface',
                    'Values' => $interface
                ]
            );
        }
        if ($variant) {
            array_push(
                $targets,
                [
                    'Key' => 'tag:variant',
                    'Values' => $variant
                ]
            );
        }
        if ($environment) {
            array_push(
                $targets,
                [
                    'Key' => 'tag:environment',
                    'Values' => $environment
                ]
            );
        }

        $sendThis =
            [
                'Comment' => '',
                'DocumentName' => $documentName,
                'MaxConcurrency' => $this->maxConcurrency,
                'Targets' => $targets,
                'TimeoutSeconds' => 30
            ];

        if ($parameters) {
            $sendThis['Parameters'] = $parameters;
        }

        $result = $this->client->sendCommand($sendThis);
        $resultArray = $result->toArray();
        $commandId = $resultArray['Command']['CommandId'];
        return $commandId;
    }

    public function isCommandFinished($commandId)
    {
        $result = $this->client->listCommandInvocations(['CommandId' => $commandId]);
        $resultArray = $result->toArray();

        $pendingCount = 0;
        if (sizeof($resultArray['CommandInvocations']) < 1) {
            return false; // no invocations yet - we may be too hasty and they haven't been created yet
        }

        foreach ($resultArray['CommandInvocations'] as $invocation) {
            if (in_array($invocation['Status'], ['Pending', 'InProgress'])) {
                $pendingCount++;
            }
        }
        if ($pendingCount > 0) {
            return false;
        } else {
            return true;
        }
    }

    // # returns an output array
    public function getCommandOutput($commandId)
    {
        $result = $this->client->listCommandInvocations(['CommandId' => $commandId]);
        $resultArray = $result->toArray();

        $output = [];
        foreach ($resultArray['CommandInvocations'] as $invocation) {
            array_push($output, ['InstanceId' => $invocation['InstanceId'], 'InstanceName' => $invocation['InstanceName'], 'Status' => $invocation['Status']]);
        }
        return $output;
    }


    public function getCommandShellOutput($commandId, $instanceId)
    {
        $result = $this->client->getCommandInvocation(
            [
            'CommandId' => $commandId,
            'InstanceId' => $instanceId
            ]
        );

        return ['stdout' => $result['StandardOutputContent'], 'stderr' => $result['StandardErrorContent']];
    }

    // we can write this quite simply by internally calling getCommandOutput
    public function getCommandOutputSuccessPercentage($commandId)
    {
        $result = $this->getCommandOutput($commandId);

        $invocations = 0;
        $successes = 0;

        foreach ($result as $invocation) {
            $invocations++;
            if ($invocation['Status'] == 'Success') {
                $successes++;
            }
        }

        $percent = $successes / $invocations;
        $percentFriendly = number_format($percent * 100, 0);

        return ['invocations' => $invocations, 'successes' => $successes, 'percentage' => $percentFriendly];
    }
}
