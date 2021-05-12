<?php /** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace LTO;

use LTO\Transaction\SetScript;

/**
 * LTO public node.
 */
class PublicNode
{
    /** @var string */
    protected $url;

    /** @var string|null */
    protected $apiKey;

    /**
     * Constructor.
     */
    public function __construct(string $url, ?string $apiKey = null)
    {
        if (!function_exists('curl_init')) {
            throw new \Exception("Curl extension not available"); // @codeCoverageIgnore
        }

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
        $info = $this->curlExec([
            CURLOPT_URL => rtrim($this->url, '/') . '/utils/script/compile',
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => $this->headers([
                'Content-Type: text/plain',
                'Accept: application/json',
            ]),
            CURLOPT_POSTFIELDS => $script,
        ]);

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
     * @return mixed
     * @throws BadResponseException
     */
    public function get(string $path)
    {
        return $this->curlExec([
            CURLOPT_URL => rtrim($this->url, '/') . $path,
            CURLOPT_HTTPHEADER => $this->headers([
                'Accept: application/json',
            ])
        ]);
    }

    /**
     * Send a HTTP POST request to the node.
     *
     * @param string $path
     * @param mixed  $data  Will be serialized to JSON
     * @return mixed
     * @throws BadResponseException
     */
    public function post(string $path, $data)
    {
        return $this->curlExec([
            CURLOPT_URL => rtrim($this->url, '/') . $path,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => $this->headers([
                'Content-Type: application/json',
                'Accept: application/json',
            ]),
            CURLOPT_POSTFIELDS => json_encode($data),
        ]);
    }

    /**
     * Send a HTTP DELETE request to the node.
     *
     * @param string $path
     * @return mixed
     * @throws BadResponseException
     */
    public function delete(string $path)
    {
        return $this->curlExec([
            CURLOPT_URL => rtrim($this->url, '/') . $path,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => $this->headers([
                'Accept: application/json',
            ]),
        ]);
    }

    /**
     * Add / modify the headers.
     *
     * @param array $headers
     * @return array
     */
    protected function headers(array $headers):array
    {
        if ($this->apiKey !== null) {
            $headers[] = 'X-Api-Key: ' . $this->apiKey;
        }

        return $headers;
    }

    /**
     * Execute curl request.
     * @codeCoverageIgnore
     *
     * @param array<int,mixed> $opts  Options for curl_setopt
     * @return mixed
     * @throws BadResponseException
     */
    protected function curlExec(array $opts)
    {
        $curl = curl_init();

        curl_setopt_array($curl, $opts);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl);

        if ($response === false) {
            throw new BadResponseException("Failed to send HTTP request to node");
        }

        [$headers, $body] = explode("\r\n\r\n", $response, 2);

        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($status !== 200) {
            throw new BadResponseException("Node responded with {$status}: $body");
        }

        if (preg_match('~content-type:\s*application/json~i', $headers)) {
            $data = json_decode($body, true);
            if (json_last_error() > 0) {
                throw new BadResponseException("Invalid JSON response: " . json_last_error_msg());
            }
        } elseif (in_array('Accept: application/json', $opts[CURLOPT_HTTPHEADER], true)) {
            throw new BadResponseException("Node did not responded with JSON.");
        } else {
            $data = $body;
        }


        return $data;
    }
}
