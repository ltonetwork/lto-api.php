<?php

namespace LTO;

use LTO\Account;
use LTO\AccountFactory;
use LTO\HTTPSignatureException;
use Psr\Http\Message\RequestInterface;

/**
 * Create and verify HTTP Signatures.
 * Only support signatures using the ED25519 algorithm.
 */
class HTTPSignature
{
    /**
     * @var RequestInterface 
     */
    protected $request;

    /**
     * @var int
     */
    protected $clockSkew = 300;
    
    /**
     * @var Account
     */
    protected $account;
    
    /**
     * @var AccountFactory
     */
    protected $accountFactory;
    
    /**
     * Signature parameters
     * @var array
     */
    protected $params;
    
    /**
     * Headers to using in message
     * @var array
     */
    protected $headers;
    
    
    /**
     * Class construction
     * 
     * @param RequestInterface $request
     * @parma string[]         $headers
     */
    public function __construct(RequestInterface $request, $headers = ['date'])
    {
        $this->request = $request;
        $this->headers = $headers;
    }
    
    /**
     * Return the request
     * 
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }
    
    /**
     * Build a request line.
     * 
     * @param RequestInterface $this->request
     * @return string
     */
    public function getRequestTarget()
    {
        $method = strtolower($this->request->getMethod());
        $uri = (string)$this->request->getUri()->withScheme('')->withHost('')->withPort(null)->withUserInfo('');

        if (substr($uri, 0, 1) !== '/') {
            $uri = '/' . $uri;
        }
        
        return $method . ' ' . $uri;
    }
    
    /**
     * Set the max clock offset
     * 
     * @param int $clockSkew
     * @return $this
     */
    public function setClockSkew($clockSkew = 300)
    {
        $this->clockSkew = $clockSkew;
        
        return $this;
    }
    
    /**
     * Get the max clock offset
     * 
     * @return int
     */
    public function getClockSkew()
    {
        return $this->clockSkew;
    }
    
    
    /**
     * Assert all required parameters are available
     * 
     * @throws InvalidHeaderError
     */
    protected function assertParams()
    {
        $required = ['keyId', 'algorithm', 'signature'];
        
        foreach ($required as $param) {
            if (!array_key_exists($param, $this->params)) {
                throw new HTTPSignatureException("$param was not specified");
            }
        }
        
        if (!in_array($this->params['algorithm'], ['ed25519', 'ed25519-sha256'])) {
            throw new HTTPSignatureException("only the 'ed25519' and 'ed25519-sha256' algorithms are supported");
        }
    }

    /**
     * Extract the authorization Signature parameters
     * 
     * @return array
     * @throws HTTPSignatureException
     */
    public function getParams()
    {
        if (isset($this->params)) {
            return $this->params;
        }
        
        if (!$this->request->hasHeader('authorization')) {
            throw new HTTPSignatureException('no authorization header in the request');
        }
        
        $auth = $this->request->getHeaderLine('authorization');
        
        list($method, $paramString) = explode(" ", $auth, 2) + [null, null];
        
        if (strtolower($method) !== 'signature') {
            throw new HTTPSignatureException('authorization schema is not "Signature"');
        }
        
        if (!preg_match_all('/(\w+)\s*=\s*"([^"]++)"\s*(,|$)/', $paramString, $matches, PREG_PATTERN_ORDER)) {
            throw new HTTPSignatureException('corrupt header');
        }
        
        $this->params = array_combine($matches[1], $matches[2]);
        $this->assertParams();
        
        return $this->params;
    }
    
    /**
     * Get a parameter
     * 
     * @param string $param
     * @return string
     */
    public function getParam($param)
    {
        $params = $this->getParams();
        
        return isset($params[$param]) ? $params[$param] : null;
    }
    
    
    /**
     * Asssert that required headers are present
     * 
     * @throws HTTPSignatureException
     */
    protected function assertRequiredHeaders()
    {
        $headers = explode(' ', $this->getParam('headers'));

        if (in_array('x-date', $headers)) {
            $headers[] = 'date';
        }
        
        $missing = array_diff($this->headers, $headers);
        
        if (!empty($missing)) {
            $err = sprintf("%s %s not part of signature", join(', ', $missing), count($missing) === 1 ? 'is' : 'are');
            throw new HTTPSignatureException($err);
        }
    }
    
    /**
     * Get the headers used 
     * @return string
     */
    public function getHeaders()
    {
        return isset($this->params) ? explode(' ', $this->getParam('headers')) : $this->headers;
    }
    

    /**
     * Use a specific Account factory.
     * 
     * @param AccountFactory $accountFactory
     * @return $this
     */
    public function useAccountFactory(AccountFactory $accountFactory)
    {
        $this->accountFactory = $accountFactory;
        
        return $this;
    }
    
    /**
     * Get the account.
     * Create from keyId param if needed.
     * 
     * @return Account
     */
    public function getAccount()
    {
        if (isset($this->account)) {
            return $this->account;
        }

        $publickey = $this->getParam('keyId');
        
        if (!isset($publickey)) {
            throw new \BadMethodCallException("Unable to get account; keyId unknown (not verified?)");
        }

        if (!isset($this->accountFactory)) {
            throw new \BadMethodCallException("Unable to create account; factory not specified");
        }
        
        $factory = $this->accountFactory;
        $this->account = $factory->createPublic($publickey, null, 'base64');
        
        return $this->account;
    }
    
    /**
     * Get message that should be signed.
     * 
     * @return string
     */
    public function getMessage()
    {
        $message = [];
        
        foreach ($this->getHeaders() as $header) {
            $message[] = $header === '(request-target)'
                ? sprintf("%s: %s", '(request-target)', $this->getRequestTarget())
                : sprintf("%s: %s", $header, $this->request->getHeaderLine($header));
        }
        
        return join("\n", $message);
    }

    /**
     * Asset that the signature is not to old
     * 
     * @throws HTTPSignatureException
     */
    protected function assertSignatureAge()
    {
        $date = $this->request->getHeaderLine('date');
        
        if (empty($date) || abs(time() - strtotime($date)) > $this->clockSkew) {
            throw new HTTPSignatureException("signature to old or clock offset");
        }
    }
    
    /**
     * Verify the signature
     * 
     * @throws HTTPSignatureException
     */
    public function verify()
    {
        $this->assertRequiredHeaders();
        
        $signature = $this->getParam('signature');
        $account = $this->getAccount();
        
        if (!isset($account)) {
            throw new HTTPSignatureException("account not set");
        }
        
        $message = $this->getParam('algorithm') === 'ed25519-sha256'
            ? hash('sha256', $this->getMessage(), true)
            : $this->getMessage();
        
        if (!$account->verify($signature, $message, 'base64')) {
            throw new HTTPSignatureException("invalid signature");
        }
        
        $this->assertSignatureAge();
    }

    
    /**
     * Sign a request.
     * 
     * @param Account $account
     * @param string  $algorithm
     * @return RequestInterface
     * @throws HTTPSignatureException
     */
    public function signWith(Account $account, $algorithm = 'ed25519-sha256')
    {
        $this->params = [
            'keyId' => $account->getPublicSignKey('base64'),
            'algorithm' => 'ed25519-sha256',
            'headers' => join(' ', $this->getHeaders())
        ];

        if (!$this->request->hasHeader('date')) {
            $date = date(DATE_RFC1123);
            $this->request = $this->request->withHeader('date', $date);
        }

        $message = $algorithm == 'ed25519-sha256' ? hash('sha256', $this->getMessage(), true) : $this->getMessage();
        $signature = $account->sign($message, 'base64');
        
        $header = sprintf('Signature keyId="%s",algorithm="%s",headers="%s",signature="%s"',
            $this->params['keyId'], $this->params['algorithm'], $this->params['headers'], $signature);
        
        $this->account = $account;
        $this->request = $this->request->withHeader('authorization', $header);
        
        return $this->request;
    }
}
