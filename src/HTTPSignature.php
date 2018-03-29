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
    public function getRequestLine()
    {
        $method = $this->request->getMethod();
        $uri = (string)$this->request->getUri()->withScheme('')->withHost('')->withUserInfo('');
        
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
        
        if ($this->params['algorithm'] !== 'ed25519-sha256') {
            throw new HTTPSignatureException("only the 'ed25519-sha256' signing algorithm is supported");
        }
        
        if (!array_key_exists('key', $this->params)) {
            $keyId = $this->params['keyId'];
            throw new HTTPSignatureException("unable to lookup key with id '$keyId' and key was not specified");
        }
    }

    /**
     * Extract the Authorization Signature parameters
     * 
     * @return array
     * @throws HTTPSignatureException
     */
    public function getParams()
    {
        if (isset($this->params)) {
            return $this->params;
        }
        
        if ($this->request->hasHeader('Authorization')) {
            throw new HTTPSignatureException('no authorization header in the request');
        }
        
        $auth = $this->request->getHeaderLine('Authorization');
        
        list($method, $paramString) = explode(" ", $auth, 2) + [null, null];
        
        if (strtolower($method) !== 'signature') {
            throw new HTTPSignatureException('authorization schema is not "Signature"');
        }
        
        $this->params = str_getcsv($paramString);
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
    public function assertRequiredHeaders()
    {
        $headers = explode(' ', $this->getParam('headers'));
        
        foreach ($this->headers as $requiredHeader) {
            if (in_array($requiredHeader, $headers)) {
                throw new HTTPSignatureException("$requiredHeader is not part of signature");
            }
        }
    }
    
    /**
     * Get the headers used 
     * @return string
     */
    public function getHeaders()
    {
        return $this->getParam('headers') ? explode(' ', $this->getParam('headers')) : $this->headers;
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

        if ($this->getParam('keyId')) {
            return null;
        }

        if (!isset($this->accountFactory)) {
            throw new \BadMethodCallException("Unable to create account; factory not specified");
        }
        
        $factory = $this->accountFactory;
        $this->account = $factory->createPublic($this->getParam('keyId'), null, 'base64');
        
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
            $message[] = $header === 'request-line'
                ? sprintf("%s: %s", '(request-target)', $this->getRequestLine())
                : sprintf("%s: %s", $header, $this->request->getHeaderLine($header));
        }
        
        return join("\n", $message);
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
        
        if (!$this->getAccount()) {
            throw new HTTPSignatureException("account not set");
        }
        
        $hash = hash('sha256', $this->getMessage(), true);
        
        if (!$this->getAccount()->verify($signature, $hash, 'base64')) {
            throw new HTTPSignatureException("invalid signature");
        }
        
        $date = $this->request->getHeaderLine('Date');
        if (empty($date) || abs(now() - strtotime($date)) > $this->clockSkew) {
            throw new HTTPSignatureException("signature to old or clock offset");
        }
    }

    
    /**
     * Sign a request.
     * 
     * @param Account $account
     * @param array   $headers
     * @return RequestInterface
     * @throws HTTPSignatureException
     */
    public function sign(Account $account)
    {
        $this->params = [
            'keyId' => $account->getPublicSignKey('base64'),
            'algorithm' => 'ed25519-sha256',
            'headers' => join(' ', $this->getHeaders())
        ];
        
        if ($this->request->hasHeader('Date')) {
            $date = $this->request->getHeaderLine('Date');
        } else {
            $date = date(DATE_RFC1123);
            $this->request = $this->request->withHeader('Date', $date);
        }
        
        $hash = hash('sha256', $this->getMessage(), true);
        $signature = $account->sign($hash, 'base64');
        
        $header = sprintf('Signature keyId="%s",algorithm="%s",headers="%s",signature="%s"',
            $this->params['keyId'], $this->params['algorithm'], $this->params['headers'], $signature);
        
        $this->request = $this->request->withHeader('Authorization', $header);
        
        return $this->request;
    }
}
