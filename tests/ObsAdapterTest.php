<?php


namespace Zing\Flysystem\Obs\Tests;


use Obs\ObsClient;
use Zing\Flysystem\Obs\ObsAdapter;

class ObsAdapterTest extends TestCase
{
    public function testCreate()
    {
        $config = [
            'key' => '',
            'secret' => '',
            'bucket' => '',
            'security_token' => '',
            'endpoint' => 'x',
            'signature' => '',
            'path_style' => '',
            'region' => '',
            'ssl_verify' => '',
            'ssl.certificate_authority' => '',
            'max_retry_count' => '',
            'timeout' => '',
            'socket_timeout' => '',
            'connect_timeout' => '',
            'chunk_size' => '',
            'exception_response_mode' => '',
        ];
        $client = new ObsClient($config);
        new ObsAdapter($client, $config['endpoint'], $config['bucket']);
    }
}
