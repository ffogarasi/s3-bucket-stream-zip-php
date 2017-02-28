<?php
// since large buckets may take lots of time we remove any time limits
set_time_limit(0);
// set a default time zone in case it's not set
date_default_timezone_set('America/Chicago');

require sprintf('%s/../vendor/autoload.php', __DIR__);

use MTL\S3BucketStreamZip\S3BucketStreamZip;

$auth = [
    'key'     => '*****',
    'secret'  => '*****',
    'region'  => 'us-east-1',
    'version' => 'latest'
];

$params = [
    'Bucket' => 'testbucket',
    'Prefix' => 'testfolder' // supply the Prefix to get files from a specific 'folder' inside Bucket
];

$stream = new S3BucketStreamZip($auth, $params);

$stream->send('name-of-zipfile-to-send.zip');
