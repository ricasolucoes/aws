<?php

namespace Aws;

class aws
{
    public function s3(): AwsS3
    {
        return new AwsS3();
    }
}
