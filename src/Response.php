<?php

namespace Jc\GraphQL;

use Jc\GraphQL\Exception\GraphQLError;
use JsonSerializable;

class Response implements JsonSerializable
{
    /**
     * GraphQL data
     *
     * @var
     */
    protected $data;

    /**
     * GraphQL errors
     *
     * @var array
     */
    protected $errors;

    /**
     * Response headers
     *
     * @var array
     */
    protected $headers;


    /**
     * GraphQL Response constructor
     *
     * @param object $body
     * @param array $headers
     */
    public function __construct(object $body, array $headers)
    {
        $this->data = $body->data ?? null;
        $this->errors = $body->errors ?? [];
        $this->headers = $headers;
    }

    /**
     * Return the data. Note that you can access the data properties via the magic methods as well.
     *
     * @return mixed
     */
    public function data()
    {
        return $this->data;
    }

    /**
     * Return the first property of the data
     *
     * @return mixed|null
     */
    public function firstData()
    {
        return is_object($this->data) && !empty($this->data) ? current($this->data) : null;
    }

    /**
     * Return the response headers of the GraphQL request
     *
     * @return array
     */
    public function headers()
    {
        return $this->headers;
    }

    /**
     * Get any GraphQL errors
     *
     * @return array
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * Get only the first GraphQL error
     *
     * @return mixed|null
     */
    public function firstError()
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Throw an exception for the first error
     *
     * @throws \Jc\Graphql\Exception\GraphQLError
     * @return void
     */
    public function throwFirstError()
    {
        if($this->hasErrors()) {
            $first = $this->firstError();
            $error = $this->getErrorAsString($first);
            throw new GraphQLError('A GraphQL Error was returned by the GraphQL Server. Error: ' . $error, 500);
        }
    }

    public function getErrorAsString($error)
    {
        $err = '';
        if(!empty($error->path)) {
            $err .= '[' . $error->path . '] ';
        }
        if(!empty($error->category)) {
            $err .= $error->category . ': ';
        }
        $err .= $error->debugMessage ?? $error->message;

        return $err;
    }

    /**
     * Check if there was any GraphQL errors
     *
     * @return bool
     */
    public function hasErrors()
    {
        return (bool) count($this->errors());
    }

    /**
     * Convert any objects to arrays recursively
     *
     * @param array|\stdClass
     * @return array
     */
    public function forceArray($data)
    {
        return json_decode(json_encode($data), true);
    }

    /**
     * Return the data as a JSON string
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode($this->data);
    }

    /**
     * JSON serializable
     *
     * @return mixed|null
     */
    public function jsonSerialize()
    {
        return $this->data;
    }

    /**
     * Getter
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->data->{$name};
    }

    /**
     * Setter
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $this->data->{$name} = $value;
    }

    /**
     * Isset
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return $this->data->{$name};
    }
    
    /**
     * Unset
     *
     * @param $name
     * @return void
     */
    public function __unset($name)
    {
        unset($this->data->{$name});
    }
}

?>