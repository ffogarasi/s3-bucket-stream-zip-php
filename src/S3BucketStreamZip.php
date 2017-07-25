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
     *     'region'  => 'YOUR_REGION', // optional. defaults to 'us-east-1'
     *     'version' => 'latest', // optional. defaults to 'latest'
     * ]
     */
    private $auth = [];

    /**
     * @var object
     */
    private $s3Client;
    
    private $exclude = [];

    /**
     * @var object
     */
    private $params;

    /**
     * Create a new ZipStream object.
     *
     * @param array $params - AWS key and secret
     * @throws InvalidParameterException
     */
    public function __construct($params)
    {
        $this->validateAuth($params);

        // S3 User in $this->auth should have permission to execute ListBucket on any buckets
        // AND GetObject on any object with which you need to interact.
        
        $params['version'] = (isset($params['version'])) ? $params['version'] : 'latest';
        $params['region'] = (isset($params['region'])) ? $params['region'] : 'us-east-1';

        $this->s3Client = new S3Client($params);

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
        $this->params->setParam('Prefix', rtrim($prefix, '/') . '/');

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
     * 
     * @param array $patterns regexps to exclude files/folders from output
     */
    public function exclude($exclude)
    {
        $this->exclude = $exclude;
        
        return $this;
    }

    /**
     * Stream a zip file to the client
     *
     * @param string $filename - Name for the file to be sent to the client
     * $filename will be what is sent in the content-disposition header
     * @throws InvalidParameterException
     * @internal param array - See the documentation for the List Objects API for valid parameters.
     * Only `Bucket` is required.
     *
     * http://docs.aws.amazon.com/AmazonS3/latest/API/RESTBucketGET.html
     */
    public function send($filename)
    {
        $zip = new ZipStream($filename);
        
        $params = $this->params->getParams();
        $root_folder = isset($params['Prefix']) ? $params['Prefix'] : '';

        $this->append($zip, $root_folder);

        // Finalize the zip file.
        $zip->finish();
    }
    
    private function append($zip, $root_folder)
    {
        if( $this->checkExclude($root_folder))
        {
            return;
        }

        $this->prefix($root_folder);
        $params = $this->params->getParams();
        $this->doesDirectoryExist($params);

        $results = $this->s3Client->getPaginator('ListObjects', $params);

        // Add each object from the ListObjects call to the new zip file.
        foreach ($results->search("Contents[].Key") as $file) {
            if ($file == $root_folder || $this->checkExclude($file))
            {
                continue;
            }
            // Get the file name on S3 so we can save it to the zip file using the same name.
            $fileName = $file;

            $context = stream_context_create([
                's3' => ['seekable' => true]
            ]);
            // open seekable(!) stream
            if ($stream = fopen("s3://{$params['Bucket']}/{$file}", 'r', false, $context)) {
                $zip->addFileFromStream($fileName, $stream);
            }
        }

        foreach ($results->search("CommonPrefixes[].Prefix") as $folder) {
            $this->append($zip, $folder);
        }
    }
    
    private function checkExclude($path)
    {
        foreach($this->exclude as $exclude)
        {
            if (preg_match($exclude, $path))
            {
                return true;
            }
        }
        
        return false;
    }

    private function validateAuth($auth)
    {
        // We require the AWS key to be passed in $auth.
        if (!isset($auth['credentials'])) {
            throw new InvalidParameterException('$auth parameter to constructor requires a `credentials` attribute');
        }

        // We require the AWS secret to be passed in $auth.
        if (!isset($auth['credentials']['key'])) {
            throw new InvalidParameterException('$auth parameter to constructor requires a `key` attribute');
        }

        // We require the AWS secret to be passed in $auth.
        if (!isset($auth['credentials']['secret'])) {
            throw new InvalidParameterException('$auth parameter to constructor requires a `secret` attribute');
        }
    }

    protected function doesDirectoryExist($params)
    {
        $command = $this->s3Client->getCommand('listObjects', $params);

        try {
            $result = $this->s3Client->execute($command);

            if (empty($result['Contents']) && empty($result['CommonPrefixes'])) {
                throw new InvalidParameterException('Bucket or Prefix does not exist');
            }
        } catch (S3Exception $e) {
            if ($e->getStatusCode() === 403) {
                return false;
            }
            throw $e;
        }
    }
}
