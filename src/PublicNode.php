<?php

declare(strict_types=1);

namespace LTO;

use JsonException;
use LTO\Transaction\SetScript;

/**
 * LTO public node.
 */
class PublicNode
{
    protected string $url;
    protected ?string $apiKey;

    /**
     * Constructor.
     */
    public function __construct(string $url, ?string $apiKey = null)
    {
        $this->url = $url;
        $this->apiKey = $apiKey;
    }

    /**
     * Get the node URL.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Get the node URL.
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * Fetch a transaction by id.
     *
     * @param string $id
     * @return Transaction
     * @throws BadResponseException
     */
    public function getTransaction(string $id): Transaction
    {
        $tx = $this->get('/transactions/info/' . $id);

        return Transaction::fromData($tx);
    }

    /**
     * Fetch all unconfirmed transactions.
     *
     * @return Transaction[]
     * @throws BadResponseException
     */
    public function getUnconfirmed(): array
    {
        $transactions = [];
        $txs = $this->get('/transactions/unconfirmed');

        foreach ($txs as $tx) {
            $transactions[] = Transaction::fromData($tx);
        }

        return $transactions;
    }

    /**
     * Compile a script for a smart account.
     *
     * @param string $script
     * @return SetScript
     */
    public function compile(string $script): SetScript
    {
        $info = $this->post(
            '/utils/script/compile',
            $script,
            ['Content-Type' => 'text/plain'],
        );

        return new SetScript($info['script']);
    }

    /**
     * Broadcast a transaction to the network via the public node.
     *
     * @throws BadResponseException
     */
    public function broadcast(Transaction $transaction): Transaction
    {
        if (!$transaction->isSigned()) {
            throw new \BadMethodCallException("Transaction is not signed");
        }

        $tx = $this->post('/transactions/broadcast', $transaction);

        return Transaction::fromData($tx);
    }


    /**
     * Send a HTTP GET request to the node.
     *
     * @param string $path
     * @param array  $headers
     * @return mixed
     * @throws BadResponseException
     */
    public function get(string $path, array $headers = [])
    {
        return $this->request('GET', $path, $headers);
    }

    /**
     * Send a HTTP POST request to the node.
     *
     * @param string $path
     * @param mixed  $data       Will be serialized to JSON
     * @param array  $headers
     * @return mixed
     * @throws BadResponseException
     */
    public function post(string $path, $data, array $headers = [])
    {
        $headers += ['Content-Type' => 'application/json'];
        $content = $headers['Content-Type'] === 'application/json' ? json_encode($data) : $data;

        return $this->request(
            'POST',
            $path,
            $headers,
            $content
        );
    }

    /**
     * Send a HTTP DELETE request to the node.
     *
     * @param string $path
     * @param array  $headers
     * @return mixed
     * @throws BadResponseException
     */
    public function delete(string $path, array $headers = [])
    {
        return $this->request('DELETE', $path, $headers);
    }


    /**
     * Turn an associative array of headers into a list.
     *
     * @param array $headers
     * @return array
     */
    protected function headerList(array $headers): array
    {
        $list = [];

        foreach ($headers as $name => $value) {
            if ($value === null) continue;
            $list[] = "$name: $value";
        }

        return $list;
    }

    /**
     * Check if the response headers contain `Content-Type: application/json`.
     *
     * @param array $responseHeaders
     * @return bool
     */
    protected function isJsonResponse(array $responseHeaders)
    {
        foreach ($responseHeaders as $header) {
            if (preg_match('~content-type:\s*application/json~i', $header)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Use `file_get_contents` to do an HTTP request.
     * @codeCoverageIgnore
     *
     * @param string $url
     * @param array $options
     * @return array
     */
    protected function doHttpRequest(string $url, array $options)
    {
        $options['ignore_errors'] = true;

        $body = @file_get_contents($url, false, stream_context_create(['http' => $options]));

        if ($body === false) {
            $err = error_get_last();
            throw new BadResponseException($err['message']);
        }

        /**
         * @var array $http_response_header
         * @see https://www.php.net/manual/en/reserved.variables.httpresponseheader.php
         */

        return ['header' => $http_response_header ?? [], 'content' => $body];
    }

    /**
     * Do an http request.
     * In case of a JSON response, decode the response body.
     *
     * @param string $method
     * @param string $path
     * @param array $headers
     * @param string|null $content
     * @return mixed
     * @throw BadResponseException
     */
    protected function request(string $method, string $path, array $headers, ?string $content = null)
    {
        $headers += ['Accept' => 'application/json'];

        if (isset($this->apiKey)) {
            $headers += ['X-Api-Key' => $this->apiKey];
        }

        $url = rtrim($this->url, '/') . ($path[0] === '/' ? '' : '/') . $path;

        $options = [
            'method' => $method,
            'header' => join("\r\n", $this->headerList($headers)),
            'content' => $content,
        ];

        ['header' => $responseHeaders, 'content' => $body] = $this->doHttpRequest($url, $options);

        $statusLine = $responseHeaders[0];
        $status = preg_match('{HTTP/\S*\s(\d{3})}', $statusLine, $match) ? (int)$match[1] : 999;

        if ($status >= 300) {
            throw new BadResponseException("$method $url responded with $statusLine", new NodeError($body));
        }

        if ($this->isJsonResponse($responseHeaders)) {
            try {
                return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new BadResponseException("Invalid JSON response for $method $url", $exception);
            }
        }

        if (isset($headers['Accept']) && $headers['Accept'] === 'application/json') {
            throw new BadResponseException("$method $url did not responded with JSON.");
        }

        return $body;
    }
}
