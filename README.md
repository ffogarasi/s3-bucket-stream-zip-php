# S3BucketStreamZip

Forked from `jmathai/s3-bucket-stream-zip-php`

## Overview
This library lets you efficiently stream the contents of an S3 bucket/folder as a zip file to the client.

Uses v3 of AWS SDK to stream files directly from S3.

## Installation
Installation is done via composer by adding the a dependency on .

```
composer require michaeltlee/s3-bucket-stream-zip-php
composer install
```

## Usage
```php
// taken from examples/simple.php
// since large buckets may take lots of time we remove any time limits
set_time_limit(0);
require sprintf('%s/../vendor/autoload.php', __DIR__);

use MTL\S3BucketStreamZip\S3BucketStreamZip;
use MTL\S3BucketStreamZip\Exception\InvalidParameterException;

$auth = [
    'key'     => '*********',   // required
    'secret'  => '*********',   // required
    'region'  => 'YOUR_REGION', // required
    'version' => 'latest',      // required
];

$params = [
    'Bucket' => 'bucketname',  // required
    'Prefix' => 'subfolder/',  // optional (path to folder to stream)
];

$stream = new S3BucketStreamZip($auth, $params);

$stream->send('name-of-zipfile-to-send.zip');

```

## Authors
* Jaisen Mathai <jaisen@jmathai.com> - http://jaisenmathai.com

## Dependencies
* Paul Duncan <pabs@pablotron.org> - http://pablotron.org/
* Jonatan Männchen <jonatan@maennchen.ch> - http://commanders.ch
* Jesse G. Donat <donatj@gmail.com> - https://donatstudios.com
