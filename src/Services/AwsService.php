<?php

namespace Aws\Services;

/**
 *
 */
class AwsService
{
    protected $config;

    public function __construct($config = false)
    {
        if (!$this->config = $config) {
            $this->config = \Illuminate\Support\Facades\Config::get('aws');
        }
    }
}
