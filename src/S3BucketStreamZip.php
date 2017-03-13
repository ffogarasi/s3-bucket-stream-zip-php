<?php

namespace MTL\S3BucketStreamZip;

use Aws\S3\Exception\S3Exception;
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
     * @var object
     */
    private $s3Client;

    /**
     * @var object
     */
    private $params;

    /**
     * Create a new ZipStream object.
     *
     * @param array $auth - AWS key and secret
     * @throws InvalidParameterException
     */
    public function __construct($auth)
    {
        $this->validateAuth($auth);

        $this->auth = $auth;

        // S3 User in $this->auth should have permission to execute ListBucket on any buckets
        // AND GetObject on any object with which you need to interact.
        $this->s3Client = new S3Client([
            'version'     => (isset($this->auth['version'])) ? $this->auth['version'] : 'latest',
            'region'      => (isset($this->auth['refion'])) ? $this->auth['region'] : 'us-east',
            'credentials' => [
                'key'    => $this->auth['key'],
                'secret' => $this->auth['secret'],
            ],
        ]);

        // Register the stream wrapper from an S3Client object
        // This allows you to access buckets and objects stored in Amazon S3 using the s3:// protocol
        $this->s3Client->registerStreamWrapper();
    }

    public function bucket($bucket)
    {
        $this->params = new S3Params($bucket);

        return $this;
    }

    public function prefix($prefix)
    {
        $this->params->setParam('Prefix', $prefix);

        return $this;
    }

    public function addParams(array $params)
    {
        foreach($params as $key => $value) {
            $this->params->setParam($key, $value);
        }

        return $this;
    }

    /**
     * Stream a zip file to the client
     *
     * @param string $filename - Name for the file to be sent to the client
     * $filename will be what is sent in the content-disposition header
     * @param $params
     * @throws InvalidParameterException
     * @internal param array - See the documentation for the List Objects API for valid parameters.
     * Only `Bucket` is required.
     *
     * http://docs.aws.amazon.com/AmazonS3/latest/API/RESTBucketGET.html
     *
     * ['Bucket' => 'YOUR_BUCKET']
     */
    public function send($filename)
    {
        $params = $this->params->getParams();

        $this->validateParams($params);

        $zip = new ZipStream($filename);
        // The iterator fetches ALL of the objects without having to manually loop over responses.
        $files = $this->s3Client->getIterator('ListObjects', $params);

        // Add each object from the ListObjects call to the new zip file.
        foreach ($files as $file) {
            // Get the file name on S3 so we can save it to the zip file using the same name.
            $fileName = basename($file['Key']);

            if (is_file("s3://{$params['Bucket']}/{$file['Key']}")) {
                $context = stream_context_create([
                    's3' => ['seekable' => true]
                ]);
                // open seekable(!) stream
                if ($stream = fopen("s3://{$params['Bucket']}/{$file['Key']}", 'r', false, $context)) {
                    $zip->addFileFromStream($fileName, $stream);
                }
            }
        }

        // Finalize the zip file.
        $zip->finish();
    }

    private function validateAuth($auth)
    {
        // We require the AWS key to be passed in $auth.
        if (!isset($auth['key'])) {
            throw new InvalidParameterException('$auth parameter to constructor requires a `key` attribute');
        }

        // We require the AWS secret to be passed in $auth.
        if (!isset($auth['secret'])) {
            throw new InvalidParameterException('$auth parameter to constructor requires a `secret` attribute');
        }

        if (!isset($auth['region'])) {
            throw new InvalidParameterException('$auth parameter to constructor requires a `region` attribute');
        }

        if (!isset($auth['version'])) {
            throw new InvalidParameterException('$auth parameter to constructor requires a `version` attribute');
        }
    }

    private function validateParams($params)
    {
        if (!isset($params['Bucket'])) {
            throw new InvalidParameterException('$params parameter to send() requires a `Bucket` attribute (with a capital B)');
        }

        $this->doesDirectoryExist($params);
    }

    protected function doesDirectoryExist($params)
    {
        // Maybe this isn't an actual key, but a prefix.
        // Do a prefix listing of objects to determine.
        $command = $this->s3Client->getCommand('listObjects', $params);

        try {
            $result = $this->s3Client->execute($command);

            return $result['Contents'] || $result['CommonPrefixes'];
        } catch (S3Exception $e) {
            if ($e->getStatusCode() === 403) {
                return false;
            }
            throw $e;
        }
    }
}
