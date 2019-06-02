<?php

namespace Jc\GraphQL;

use Jc\GraphQL\Exception\GraphQLInvalidResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

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
            $response = $this->guzzle->request('POST', $this->url, [
                'json' => [
                    'query' => $query,
                    'variables' => empty($variables) ? new \stdClass() : $variables
                ],
                'headers' => $headers,
                'http_errors' => true,
            ]);
        } catch (RequestException $e) {
            //$request = $e->getRequest();
            $response = $e->getResponse();
            $err = 'The GraphQL Server responded with a non-200 HTTP status code ('.$response->getStatusCode().' '.$response->getReasonPhrase().'). ';
            throw new GraphQLInvalidResponse($err . $e->getMessage(), $e->getCode(), $e);
        }

        $json = json_decode($response->getBody()->getContents());

        if(json_last_error() !== JSON_ERROR_NONE) {
            throw new GraphQLInvalidResponse('The GraphQL Server did not provide a valid JSON response. 
                Please make sure that you have provided the correct URL to a GraphQL Endpoint.');
        }

        return new Response($json, $response->getHeaders());
    }
}

?>