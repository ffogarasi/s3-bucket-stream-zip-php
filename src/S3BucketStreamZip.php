<?php

namespace MTL\S3BucketStreamZip;

use Aws\S3\S3Client;
use MTL\S3BucketStreamZip\Exception\InvalidParameterException;
use ZipStream\ZipStream;

class S3BucketStreamZip
{
    /**
     * @var array
     *
     * [
     *     'key'     => 'YOUR_KEY',
     *     'secret'  => 'YOUR_SECRET',
     *     'region'  => 'YOUR_REGION',
     *     'version' => 'latest',
     * ]
     */
    private $auth = [];

    /**
     * @var array
     *
     * See the documentation for the List Objects API for valid parameters.
     * Only `Bucket` is required.
     *
     * http://docs.aws.amazon.com/AmazonS3/latest/API/RESTBucketGET.html
     *
     * ['Bucket' => 'YOUR_BUCKET']
     */
    private $params = [];

    /**
     * @var object
     */
    private $s3Client;

    /**
     * Create a new ZipStream object.
     *
     * @param array $auth - AWS key and secret
     * @param array $params - AWS List Object parameters
     * $params MUST contain a 'Bucket'
     * @throws InvalidParameterException
     */
    public function __construct($auth, $params)
    {
        // We require the AWS key to be passed in $auth.
        if (!isset($auth['key'])) {
            throw new InvalidParameterException('$auth parameter to constructor requires a `key` attribute');
        }

        // We require the AWS secret to be passed in $auth.
        if (!isset($auth['secret'])) {
            throw new InvalidParameterException('$auth parameter to constructor requires a `secret` attribute');
        }

        // We require the AWS S3 bucket to be passed in $params.
        if (!isset($params['Bucket'])) {
            throw new InvalidParameterException('$params parameter to constructor requires a `Bucket` attribute (with a capital B)');
        }

        $this->auth = $auth;
        $this->params = $params;

        // S3 User in $this->auth should have permission to execute ListBucket on any buckets
        // AND GetObject on any object with which you need to interact.
        $this->s3Client = new S3Client($this->auth);

        // Register the stream wrapper from an S3Client object
        // This allows you to access buckets and objects stored in Amazon S3 using the s3:// protocol
        $this->s3Client->registerStreamWrapper();
    }

    /**
     * Stream a zip file to the client
     *
     * @param string $filename - Name for the file to be sent to the client
     * $filename will be what is sent in the content-disposition header
     */
    public function send($filename)
    {
        $zip = new ZipStream($filename);

        // The iterator fetches ALL of the objects without having to manually loop over responses.
        $files = $this->s3Client->getIterator('ListObjects', $this->params);

        // Add each object from the ListObjects call to the new zip file.
        foreach ($files as $file) {
            // Get the file name on S3 so we can save it to the zip file using the same name.
            $fileName = basename($file['Key']);

            // Open a stream in read-only mode
            if ($stream = fopen("s3://{$this->params['Bucket']}/{$file['Key']}", 'r')) {
                $zip->addFileFromStream($fileName, $stream);
                fclose($stream);
            }
        }

        // Finalize the zip file.
        $zip->finish();
    }
}
