# GraphQL Client
A simple PHP library to interact with GraphQL APIs.

## Installation

Run the following command to install the package using composer:

`composer require cronqvist/graphql-client`

## Usage
Create an instance of `Jc\GraphQL\GraphQLClient`:

```
$client = new GraphQLClient($url);
$client->setAuthToken('xxx');

// Define the query or queries
$query = <<<'QUERY'
    query GetUser($id: ID!) {
      user (id: $id) {
        id,
        name,
        email
      }
    }
QUERY;

// Fetch the result of the query or queries from the GraphQL endpoint
$response = $client->fetch($query, ['id' => 1]);

// Get all the data
$response->data();

// If you only send one query, you can get access to it directly:
$user = $response->firstData();

// You can also access the data directly (user) via the getter
$user = $response->user;


// Fetch can be used with three arguments where variables and headers are optional
$response = $client->fetch($query, $variables, $headers);
```

Error handling:

```
// Easiest is to use the 'throwFirstError' method to throw an exception if any GraphQL error was returned:
$response->throwFirstError();

// or...

// Check if any errors exist
if($response->hasErrors()) {
    
    // Dump all errors
    var_dump($response->errors());
    
    // Or dump only the first error
    var_dump($response->firstError();
}
```