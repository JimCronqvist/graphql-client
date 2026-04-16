<?php

namespace Jc\GraphQL;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Jc\GraphQL\Exception\GraphQLInvalidResponse;

class GraphQLClient
{
    /**
     * GraphQL URL endpoint
     *
     * @var string
     */
    protected $url;

    /**
     * Bearer token for authentication
     *
     * @var string
     */
    protected $authToken;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $guzzle;


    /**
     * GraphQL Client constructor
     *
     * @param string $url
     * @param string $authToken
     */
    public function __construct($url, $authToken = null)
    {
        $this->url = $url;
        $this->guzzle = new Client();
        $this->authToken = $authToken;
    }

    /**
     * Set the GraphQL endpoint to query against
     *
     * @param string $url
     * @return static
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Set the bearer authorization token to use for the GraphQL requests
     *
     * @param string $authToken
     * @return static
     */
    public function setAuthToken($authToken)
    {
        $this->authToken = $authToken;
        return $this;
    }

    /**
     * Set the instance of guzzle client to use for the underlying http request
     *
     * @param \GuzzleHttp\Client $guzzle
     * @return static
     */
    public function setGuzzle(Client $guzzle)
    {
        $this->guzzle = $guzzle;
        return $this;
    }

    /**
     * Make a GraphQL Request and return a GraphQL Response instance
     *
     * @param string $query
     * @param array $variables
     * @param array $headers
     * @throws \GuzzleHttp\Exception\RequestException
     * @throws \Jc\GraphQL\Exception\GraphQLInvalidResponse
     * @return \Jc\GraphQL\Response
     */
    public function fetch($query, array $variables = [], array $headers = [])
    {
        if($this->authToken && !array_key_exists('Authorization', $headers)) {
            $headers['Authorization'] = 'Bearer ' . $this->authToken;
        }

        try {
            $options = $this->buildRequestOptions($query, $variables, $headers);
            $response = $this->guzzle->request('POST', $this->url, $options);
        } catch (RequestException $e) {
            //$request = $e->getRequest();
            $response = $e->getResponse();
            $error = $response->getBody()->getContents();

            if($response) {
                $err = 'The GraphQL Server responded with a non-200 HTTP status code (' . $response->getStatusCode() . ' ' . $response->getReasonPhrase() . '). ';
            } else {
                $err = 'The GraphQL request failed before a response was received. ';
            }

            throw new GraphQLInvalidResponse($err . $e->getMessage(), $e->getCode(), $e);
        }

        $json = json_decode($response->getBody()->getContents());

        if(json_last_error() !== JSON_ERROR_NONE) {
            throw new GraphQLInvalidResponse('The GraphQL Server did not provide a valid JSON response. 
                Please make sure that you have provided the correct URL to a GraphQL Endpoint.');
        }

        return new Response($json, $response->getHeaders());
    }

    /**
     * @param string $query
     * @param array $variables
     * @param array $headers
     * @return array
     */
    protected function buildRequestOptions($query, array $variables, array $headers)
    {
        $options = [
            'headers' => $headers,
            'http_errors' => true,
        ];

        $fileMap = [];
        $mappedVariables = $this->mapFiles($variables, 'variables', $fileMap);
        if(empty($fileMap)) {
            $options['json'] = [
                'query' => $query,
                'variables' => empty($variables) ? new \stdClass() : $variables,
            ];
            return $options;
        }

        // When files are present - Build multipart request according to GraphQL multipart request specification
        $operations = [
            'query' => $query,
            'variables' => empty($mappedVariables) ? new \stdClass() : $mappedVariables,
        ];

        $multipart = [
            [
                'name' => 'operations',
                'contents' => json_encode($operations),
            ],
        ];

        $map = new \stdClass();
        foreach(array_values($fileMap) as $index => $file) {
            $map->{$index} = [$file['path']];
        }

        $multipart[] = [
            'name' => 'map',
            'contents' => json_encode($map),
        ];

        foreach(array_values($fileMap) as $index => $file) {
            $part = [
                'name' => (string) $index,
                'contents' => $file['contents'],
            ];

            if($file['filename'] !== null) {
                $part['filename'] = $file['filename'];
            }

            if($file['contentType'] !== null) {
                $part['headers'] = [
                    'Content-Type' => $file['contentType'],
                ];
            }

            $multipart[] = $part;
        }

        unset($options['headers']['Content-Type']);
        unset($options['headers']['content-type']);

        $options['multipart'] = $multipart;

        return $options;
    }

    /**
     * Walk variables recursively, replace uploads with null, and collect paths.
     *
     * @param mixed $value
     * @param string $path
     * @param array $fileMap
     * @return mixed
     */
    protected function mapFiles($value, $path, array &$fileMap)
    {
        if($value instanceof Upload) {
            $fileMap[] = [
                'path' => $path,
                'contents' => $value->getContents(),
                'filename' => $value->getFilename(),
                'contentType' => $value->getContentType(),
            ];
            return null;
        }

        if(is_array($value)) {
            $mapped = [];
            foreach($value as $key => $item) {
                $mapped[$key] = $this->mapFiles($item, $path . '.' . $key, $fileMap);
            }
            return $mapped;
        }

        return $value;
    }
}