# PHP ATLASSIAN INTEGRATION - JIRA and Confluence Rest Client

Aws's Jira, Confluence & Confluence Question REST API Client for PHP Users.

[![Latest Stable Version](https://poser.pugx.org/ricasolucoes/aws/v/stable)](https://packagist.org/packages/ricasolucoes/aws)
[![Latest Unstable Version](https://poser.pugx.org/ricasolucoes/aws/v/unstable)](https://packagist.org/packages/ricasolucoes/aws)
[![Build Status](https://travis-ci.org/ricasolucoes/aws.svg?branch=master)](https://travis-ci.org/ricasolucoes/aws)
[![StyleCI](https://styleci.io/repos/30015369/shield?branch=master&style=flat)](https://styleci.io/repos/30015369)
[![Scrutinizer](https://img.shields.io/scrutinizer/g/ricasolucoes/aws/master.svg?style=flat-square)](https://scrutinizer-ci.com/g/ricasolucoes/aws/)
[![Coverage Status](https://coveralls.io/repos/github/ricasolucoes/aws/badge.svg?branch=master)](https://coveralls.io/github/ricasolucoes/aws?branch=master)
[![License](https://poser.pugx.org/ricasolucoes/aws/license)](https://packagist.org/packages/ricasolucoes/aws)
[![Total Downloads](https://poser.pugx.org/ricasolucoes/aws/downloads)](https://packagist.org/packages/ricasolucoes/aws)
[![Monthly Downloads](https://poser.pugx.org/ricasolucoes/aws/d/monthly)](https://packagist.org/packages/ricasolucoes/aws)
[![Daily Downloads](https://poser.pugx.org/ricasolucoes/aws/d/daily)](https://packagist.org/packages/ricasolucoes/aws)

# Requirements


## Changelog

Refer to the [Changelog](CHANGELOG.md) for a full history of the project.


## Support

The following support channels are available at your fingertips:

- [Chat on Slack](https://bit.ly/sierratecnologia-slack)
- [Help on Email](mailto:help@sierratecnologia.com.br)
- [Follow on Twitter](https://twitter.com/sierratecnologia)


## Contributing & Protocols

Thank you for considering contributing to this project! The contribution guide can be found in [CONTRIBUTING.md](CONTRIBUTING.md).

Bug reports, feature requests, and pull requests are very welcome.

- [Versioning](CONTRIBUTING.md#versioning)
- [Pull Requests](CONTRIBUTING.md#pull-requests)
- [Coding Standards](CONTRIBUTING.md#coding-standards)
- [Feature Requests](CONTRIBUTING.md#feature-requests)
- [Git Flow](CONTRIBUTING.md#git-flow)


## Security Vulnerabilities

If you discover a security vulnerability within this project, please send an e-mail to [help@sierratecnologia.com.br](help@sierratecnologia.com.br). All security vulnerabilities will be promptly addressed.


## About SierraTecnologia

SierraTecnologia is a software solutions startup, specialized in integrated enterprise solutions for SMEs established in Rio de Janeiro, Brazil since June 2008. We believe that our drive The Value, The Reach, and The Impact is what differentiates us and unleash the endless possibilities of our philosophy through the power of software. We like to call it Innovation At The Speed Of Life. That’s how we do our share of advancing humanity.


## License

This software is released under [The MIT License (MIT)](LICENSE).

(c) 2008-2020 SierraTecnologia, This package no has rights reserved.

# PHP AWS Connection

Version: 1.0.0

## Table of Contents

- [Summary](#summary)
- [Install](#install)
  - [Compile](#Compile) 
- [Usage](#usage)
  - [Call AWS Service](#Call AWS Service)
  - _S3_
  - [Call S3](#Call S3)
  - [Set S3 Bucket](#Set S3 Bucket)
  - [S3 List Objects](#S3 List Objects)
  - [S3 Stream Objects](#S3 Stream Objects)
- [Maintainers](#maintainers)

 
## Summary
PHP Library to connect to the AWS Services

## Install
Install Composer:
```sh
$ php -r "readfile('https://getcomposer.org/installer');" | php
```

Install dependencies:
```sh
$ php composer.phar install
```

Add to PHP
```php
require 'vendor/autoload.php';
```

## AWS Credentials
The code here will not allow for the credentials to be added to the code. We'll use the environment variables, or if in AWS, use IAMS
The AWS SDK will use this by default, you dont need to configure it
###### Bash
```bash 
$ export AWS_ACCESS_KEY_ID=
$ export AWS_SECRET_ACCESS_KEY=
$ export AWS_DEFAULT_REGION=
```

###### PowerShell
```bash
PS C:\> $Env:AWS_ACCESS_KEY_ID=""
PS C:\> $Env:AWS_SECRET_ACCESS_KEY=""
PS C:\> $Env:AWS_DEFAULT_REGION=""
```

## Call AWS Service
```php
$this->aws = new aws();
```

# S3

#### Call S3
```php
$this->s3 = $this->aws->s3();
```

#### Set S3 Bucket
```php
$this->s3->setBucket('test-bucket');
```

#### S3 Commands

##### List Objects
S3 List Objects in a "folder"
```php
$list = $this->s3->listObjects('folder');
foreach ($list as $object) {
    print_r($object['Key']);
}
```

##### Get object
Get object ($s3filename) from S3 and save it locally $localFilename
```php
$this->s3->getObject($localFilename, $s3filename)
```

### S3 Streaming

##### Opening a streaming wrapper
You can 
```php
$awsS3 = $this->aws->s3();
$awsS3->setBucket('test-bucket');
$streamWrapper = $awsS3->getStreamWrapper()
```

####= Download an object via a stream
```php
$awsS3 = $this->aws->s3();
$awsS3->setBucket('test-bucket');

$objectName = 'folder/folder/file.xml';
$chunkCount =  $awsS3->countDownloadStreamChunks($objectName);

echo sprintf(
    "\nUsing a stream, there were %d chunks\n",
    $chunkCount
);

// Opens the file for Streaming
$awsS3->openDownloadStream($objectName);

//outputs the streamed data
for($i=0;$i<$chunkCount;$i++) {
    echo $awsS3->getDownloadStream($i);
}

// Closes the streamed file
$awsS3->closeDownloadStream();

```

#### Upload an object via a stream
Coming soon


## Thanks
[@halfer](https://github.com/halfer) for adding in the stream wrapper

## Maintainers
[@devtoolboxuk](https://github.com/devtoolboxuk/).
