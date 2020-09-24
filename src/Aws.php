<?php

namespace Aws;

class aws
{
    public function s3()
    {
        return new AwsS3();
    }
}
