<?php

declare(strict_types=1);

namespace LTO;

/**
 * LTO public node.
 */
class PublicNode
{
    /** @var string */
    protected $url;

    /**
     * Constructor.
     */
    public function __construct(string $url)
    {
        $this->url = $url;
    }

    /**
     * Get the node URL.
     */
    public function getUrl(): string
    {
        return $this->url;
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
        $tx = $this->sendGetRequest('/transactions/info/' . $id);

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
        $txs = $this->sendGetRequest('/transactions/unconfirmed');

        foreach ($txs as $tx) {
            $transactions[] = Transaction::fromData($tx);
        }

        return $transactions;
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

        $tx = $this->sendPostRequest('/transactions/broadcast', $transaction);

        return Transaction::fromData($tx);
    }


    /**
     * Send a HTTP request to the node.
     *
     * @param string $path
     * @return mixed
     * @throws BadResponseException
     */
    protected function sendGetRequest(string $path)
    {
        return $this->curlExec(
            rtrim($this->url, '/') . $path,
            [
                CURLOPT_HEADER => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json'
                ]
            ]
        );
    }

    /**
     * Send a HTTP request to the node.
     *
     * @param string $path
     * @param mixed  $data  Will be serialized to JSON
     * @return mixed
     * @throws BadResponseException
     */
    protected function sendPostRequest(string $path, $data)
    {
        return $this->curlExec(
            rtrim($this->url, '/') . $path,
            [
                CURLOPT_HEADER => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode($data),
            ]
        );
    }

    /**
     * Execute curl request.
     * @codeCoverageIgnore
     *
     * @param string           $url
     * @param array<int,mixed> $opts  Options for curl_setopt
     * @return mixed
     * @throws BadResponseException
     */
    public function curlExec(string $url, array $opts)
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, $opts);

        $response = curl_exec($curl);

        if ($response === false) {
            throw new BadResponseException("Failed to send HTTP request to node");
        }

        [$headers, $body] = explode("\r\n\r\n", $response, 2);

        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($status !== 200) {
            throw new BadResponseException("Node responded with {$status}: $body");
        }

        if (!preg_match('~content-type:\s*application/json~i', $headers)) {
            throw new BadResponseException("Node did not responded with JSON.");
        }

        $data = json_decode($body, true);
        if (json_last_error() > 0) {
            throw new BadResponseException("Invalid JSON response: " . json_last_error_msg());
        }

        return $data;
    }
}
