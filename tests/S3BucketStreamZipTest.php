<?php

namespace MTL\S3BucketStreamZipTest;

use MTL\S3BucketStreamZip\S3BucketStreamZip;
use PHPUnit_Framework_TestCase;

class S3BucketStreamZipTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \MTL\S3BucketStreamZip\Exception\InvalidParameterException
     */
    public function testInvalidParamsToConstructorKey()
    {
        $client = new S3BucketStreamZip([], []);
    }

    /**
     * @expectedException \MTL\S3BucketStreamZip\Exception\InvalidParameterException
     */
    public function testInvalidParamsToConstructorSecret()
    {
        $client = new S3BucketStreamZip(['key' => 'foo'], []);
    }

    /**
     * @expectedException \MTL\S3BucketStreamZip\Exception\InvalidParameterException
     */
    public function testInvalidParamsToConstructorBucket()
    {
        $client = new S3BucketStreamZip(['key' => 'foo', 'secret' => 'bar'], []);
    }

    public function testValidParamsToConstructor()
    {
        $client = new S3BucketStreamZip([
            'key'     => 'foo',
            'secret'  => 'bar',
            'region'  => '',
            'version' => 'latest',
        ], ['Bucket' => 'foobar']);
    }
}
