<?php

namespace LTO;

use PHPUnit_Framework_TestCase as TestCase;
use LTO\HTTPSignature;
use LTO\Account;
use LTO\AccountFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * @covers LTO\HTTPSignature
 */
class HTTPSignatureTest extends TestCase
{
    /**
     * Create a partially mocked HTTPSignature.
     * 
     * @param RequestInterface $request
     * @param array $methods
     * @return HTTPSignature
     */
    public function createHTTPSignature(RequestInterface $request = null, array $methods = [])
    {
        if (!isset($request)) {
            $request = $this->createMock(RequestInterface::class);
        }
        
        if (empty($methods)) {
            return new HTTPSignature($request);
        }
        
        return $this->getMockBuilder(HTTPSignature::class)
            ->enableProxyingToOriginalMethods()
            ->setConstructorArgs([$request])
            ->setMethods($methods)
            ->getMock();
    }
    
    public function testGetRequest()
    {
        $request = $this->createMock(RequestInterface::class);
        $httpSign = $this->createHTTPSignature($request);
        
        $this->assertSame($request, $httpSign->getRequest());
    }
    
    public function testGetRequestLine()
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withScheme')->with('')->willReturnSelf();
        $uri->expects($this->once())->method('withHost')->with('')->willReturnSelf();
        $uri->expects($this->once())->method('withUserInfo')->with('')->willReturnSelf();
        $uri->expects($this->once())->method('__toString')->willReturn('/foos?a=1');
        
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getMethod')->willReturn('GET');
        $request->expects($this->once())->method('getUri')->willReturn($uri);
        
        $httpSign = $this->createHTTPSignature($request);
        
        $this->assertSame('GET /foos?a=1', $httpSign->getRequestLine());
    }
    
    public function testClockSkew()
    {
        $httpSign = $this->createHTTPSignature();
        
        $this->assertEquals(300, $httpSign->getClockSkew());
        
        $ret = $httpSign->setClockSkew(1000);
        $this->assertSame($httpSign, $ret);
        
        $this->assertEquals(1000, $httpSign->getClockSkew());
    }
    
    public function testGetParams()
    {
        // TODO: needs to be base64
        $paramString = join (',', [
            'keyId="BVv1ZuE3gKFa6krwWJQwEmrLYUESuUabNCXgYTmCoBt6"',
            'algorithm="ed25519-sha256"',
            'headers="(request-target) date digest content-length"',
            'signature="2DDGtVHrX66Ae8C4shFho4AqgojCBTcE4phbCRTm3qXCKPZZ7reJBXiiwxweQAkJ3Tsz6Xd3r5qgnbA67gdL5fWE"'
        ]);
        
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->any())->method('getHeader')->with('Authorization')->willReturn();
        $request->expects($this->any())->method('getHeaderLine')->with('Authorization')
            ->willReturn("Signature $paramString");
        
        $httpSign = $this->createHTTPSignature($request);
        
        $params = $httpSign->getParams();
    }
}
