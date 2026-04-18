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
     * Multipart mode for file uploads
     *
     * @var string
     */
    protected $multipartMode = 'operations';

    /**
     * Whether to keep null values for file placeholders in the mapped variables or not. This can be useful when the
     * server expects the variable to be present even if it is null.
     *
     * @var bool
     */
    protected $useNullValuesForFilePlaceholder = true;

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
     * Set the mode for multipart file uploads.
     *
     * Modes: 'operations' | 'split' | 'query'
     *
     * 'operations': multipart request according to GraphQL multipart request specification with operations field
     * containing query and variables, and file placeholders in the variables referencing the variable path in the
     * filename of the file part
     *
     * 'split': multipart request with query and variables as separate multipart fields, and file placeholders in the
     * variables referencing the variable path in the filename of the file part
     *
     * 'query': multipart request with variables nested in the query field, and file placeholders in the variables
     * referencing the variable path in the filename of the file part
     *
     * Recommendation: The 'operations' mode is the recommended mode and should be used when the server supports the
     * GraphQL multipart request specification. The 'split' and 'query' modes are legacy modes that can be used for
     * compatibility with servers that do not support the specification, but they are not recommended for new
     * implementations.
     *
     * @param string $mode 'operations' | 'split' | 'query'
     */
    public function useMultipartMode($mode = 'operations')
    {
        $this->multipartMode = $mode;
        return $this;
    }

    public function useNullValuesForFilePlaceholder($keepNullValues = true)
    {
        $this->useNullValuesForFilePlaceholder = $keepNullValues;
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

        if($this->multipartMode === 'operations') {
            // When files are present - Build multipart request according to GraphQL multipart request specification
            $multipart = [
                [
                    'name' => 'operations',
                    'contents' => json_encode([
                        'query' => $query,
                        'variables' => empty($mappedVariables) ? new \stdClass() : $mappedVariables,
                    ]),
                ],
            ];
        } else if($this->multipartMode === 'split') {
            // In legacy mode 'split', we add query and variables as separate multipart fields instead of using the operations field
            $multipart = [
                [
                    'name' => 'query',
                    'contents' => $query,
                ],
                [
                    'name' => 'variables',
                    'contents' => json_encode(empty($mappedVariables) ? new \stdClass() : $mappedVariables),
                ],
            ];
        } else if($this->multipartMode === 'query') {
            // In legacy mode 'query', we nest variables in the query field instead of using the operations field
            $multipart = [
                [
                    'name' => 'query',
                    'contents' => json_encode([
                        'query' => $query,
                        'variables' => empty($mappedVariables) ? new \stdClass() : $mappedVariables,
                    ]),
                ],
            ];
        }

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
            return $this->useNullValuesForFilePlaceholder ? null : $value;
        }

        if(is_array($value)) {
            $mapped = [];
            foreach($value as $key => $item) {
                $mapped[$key] = $this->mapFiles($item, $path . '.' . $key, $fileMap);
                if($mapped[$key] instanceof Upload) {
                    unset($mapped[$key]);
                }
            }
            return $mapped;
        }

        return $value;
    }
}