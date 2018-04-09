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
    use \Jasny\TestHelper;
    
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
    
    public function testGetRequestTarget()
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())->method('withScheme')->with('')->willReturnSelf();
        $uri->expects($this->once())->method('withHost')->with('')->willReturnSelf();
        $uri->expects($this->once())->method('withPort')->with('')->willReturnSelf();
        $uri->expects($this->once())->method('withUserInfo')->with('')->willReturnSelf();
        $uri->expects($this->once())->method('__toString')->willReturn('/foos?a=1');
        
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getMethod')->willReturn('GET');
        $request->expects($this->once())->method('getUri')->willReturn($uri);
        
        $httpSign = $this->createHTTPSignature($request);
        
        $this->assertSame('get /foos?a=1', $httpSign->getRequestTarget());
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
            'signature="PIw+8VW129YY/6tRfThI3ZA0VygH4cYWxIayUZbdA3I9CKUdmqttvVZvOXN5BX2Z9jfO3f1vD1/R2jxwd3BHBw=="',
            'foo="bar"'
        ]);
        
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->any())->method('hasHeader')->with('authorization')->willReturn(true);
        $request->expects($this->any())->method('getHeaderLine')->with('authorization')
            ->willReturn("Signature $paramString");
        
        $httpSign = $this->createHTTPSignature($request);
        
        $params = $httpSign->getParams();
        
        $expected = [
            'keyId' => "BVv1ZuE3gKFa6krwWJQwEmrLYUESuUabNCXgYTmCoBt6",
            'algorithm' => "ed25519-sha256",
            'headers' => "(request-target) date digest content-length",
            'signature' => "PIw+8VW129YY/6tRfThI3ZA0VygH4cYWxIayUZbdA3I9CKUdmqttvVZvOXN5BX2Z9jfO3f1vD1/R2jxwd3BHBw==",
            'foo' => "bar"
        ];
        
        $this->assertEquals($expected, $params);
    }
    
    public function testGetParam()
    {
        $httpSign = $this->createHTTPSignature(null, ['getParams']);
        $httpSign->expects($this->atLeastOnce())->method('getParams')->willReturn([
            'keyId' => "BVv1ZuE3gKFa6krwWJQwEmrLYUESuUabNCXgYTmCoBt6",
            'algorithm' => "ed25519-sha256",
            'headers' => "(request-target) date digest content-length",
            'signature' => "PIw+8VW129YY/6tRfThI3ZA0VygH4cYWxIayUZbdA3I9CKUdmqttvVZvOXN5BX2Z9jfO3f1vD1/R2jxwd3BHBw==",
            'foo' => "bar"
        ]);
        
        $this->assertEquals("BVv1ZuE3gKFa6krwWJQwEmrLYUESuUabNCXgYTmCoBt6", $httpSign->getParam('keyId'));
        $this->assertEquals("bar", $httpSign->getParam('foo'));
    }

    public function testGetHeaders()
    {
        $request = $this->createMock(RequestInterface::class);
        
        $httpSign = new HTTPSignature($request, ["(request-target)", "date", "digest", "content-length"]);
        
        $this->assertEquals(["(request-target)", "date", "digest", "content-length"], $httpSign->getHeaders());
    }
    
    public function testGetHeadersFromParams()
    {
        $httpSign = $this->createHTTPSignature(null, ['getParam']);
        $httpSign->expects($this->atLeastOnce())->method('getParam')->with('headers')
            ->willReturn("(request-target) date digest content-length");
        
        $this->setPrivateProperty($httpSign, 'params', []);
        
        $this->assertEquals(["(request-target)", "date", "digest", "content-length"], $httpSign->getHeaders());
    }
    
    public function testGetAccount()
    {
        $httpSign = $this->createHTTPSignature(null, ['getParam']);
        $httpSign->expects($this->atLeastOnce())->method('getParam')->with('keyId')
            ->willReturn("BVv1ZuE3gKFa6krwWJQwEmrLYUESuUabNCXgYTmCoBt6");
     
        $account = $this->createMock(Account::class);
        
        $accountFactory = $this->createMock(AccountFactory::class);
        $accountFactory->expects($this->once())->method('createPublic')
            ->with("BVv1ZuE3gKFa6krwWJQwEmrLYUESuUabNCXgYTmCoBt6", null, 'base64')
            ->willReturn($account);
        
        $ret = $httpSign->useAccountFactory($accountFactory);
        $this->assertSame($httpSign, $ret);
        
        $this->assertSame($account, $httpSign->getAccount());
    }
    
    public function testGetMessage()
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->atLeast(3))->method('getHeaderLine')->willReturnMap([
            ["date", "Tue, 07 Jun 2014 20:51:35 GMT"],
            ["x-date", "Tue, 07 Jun 2014 20:50:00 GMT"],
            ["digest", "SHA-256=X48E9qOokqqrvdts8nOJRJN3OWDUoyWxBf7kbu9DBPE="],
            ["content-length", '18']
        ]);
        
        $httpSign = $this->createHTTPSignature($request, ['getHeaders', 'getRequestTarget']);
        $httpSign->expects($this->atLeastOnce())->method('getHeaders')
            ->willReturn(["(request-target)", "date", "digest", "content-length"]);
        $httpSign->expects($this->atLeastOnce())->method('getRequestTarget')->willReturn('post /foo');
        
        $msg = join("\n", [
            "(request-target): post /foo",
            "date: Tue, 07 Jun 2014 20:51:35 GMT",
            "digest: SHA-256=X48E9qOokqqrvdts8nOJRJN3OWDUoyWxBf7kbu9DBPE=",
            "content-length: 18"
        ]);
        
        $this->assertEquals($msg, $httpSign->getMessage());
    }
    
    public function testGetMessageXDate()
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->atLeast(3))->method('getHeaderLine')->willReturnMap([
            ["date", "Tue, 07 Jun 2014 20:51:35 GMT"],
            ["x-date", "Tue, 07 Jun 2014 20:50:00 GMT"],
            ["digest", "SHA-256=X48E9qOokqqrvdts8nOJRJN3OWDUoyWxBf7kbu9DBPE="],
            ["content-length", '18']
        ]);
        
        $httpSign = $this->createHTTPSignature($request, ['getHeaders', 'getRequestTarget']);
        $httpSign->expects($this->atLeastOnce())->method('getHeaders')
            ->willReturn(["(request-target)", "date", "digest", "content-length"]);
        $httpSign->expects($this->atLeastOnce())->method('getRequestTarget')->willReturn('post /foo');
        
        $msg = join("\n", [
            "(request-target): post /foo",
            "date: Tue, 07 Jun 2014 20:51:35 GMT",
            "digest: SHA-256=X48E9qOokqqrvdts8nOJRJN3OWDUoyWxBf7kbu9DBPE=",
            "content-length: 18"
        ]);
        
        $this->assertEquals($msg, $httpSign->getMessage());
    }
    
    public function testAssertSignatureAge()
    {
        $date = date(DATE_RFC1123);
        
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getHeaderLine')->with("date")
            ->willReturn($date);
        
        $httpSign = $this->createHTTPSignature($request);
        
        $this->callPrivateMethod($httpSign, 'assertSignatureAge');
    }
    
    /**
     * @expectedException LTO\HTTPSignatureException
     */
    public function testAssertSignatureAgeFail()
    {
        $date = "Tue, 07 Jun 2014 20:51:35 GMT";
        
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())->method('getHeaderLine')->with("date")
            ->willReturn($date);
        
        $httpSign = $this->createHTTPSignature($request);
        
        $this->callPrivateMethod($httpSign, 'assertSignatureAge');
    }
    
    public function algorithmProvider()
    {
        return [
            [ 'ed25519' ],
            [ 'ed25519-sha256' ]
        ];
    }
    
    /**
     * @dataProvider algorithmProvider
     * 
     * @param string $algorithm
     */
    public function testVerify($algorithm)
    {
        $msg = join("\n", [
            "(request-target): post /foo",
            "date: Tue, 07 Jun 2014 20:51:35 GMT",
            "digest: SHA-256=X48E9qOokqqrvdts8nOJRJN3OWDUoyWxBf7kbu9DBPE=",
            "content-length: 18"
        ]);
        
        $hash = "0b50f70b241111e3233c84279697f7d80efae4303b54a8959c1ac68a54fe7736";
        $signature = "PIw+8VW129YY/6tRfThI3ZA0VygH4cYWxIayUZbdA3I9CKUdmqttvVZvOXN5BX2Z9jfO3f1vD1/R2jxwd3BHBw==";
        
        $account = $this->createMock(Account::class);
        $account->expects($this->once())->method('verify')
            ->with($signature, $algorithm === 'ed25519-sha256' ? pack('H*', $hash) : $msg, 'base64')
            ->willReturn(true);
        
        $httpSign = $this->createHTTPSignature(null, ['getAccount', 'getParam', 'getMessage', 'assertSignatureAge']);
        
        $httpSign->expects($this->once())->method('getAccount')->willReturn($account);
        $httpSign->expects($this->atLeastOnce())->method('getParam')->willReturnMap([
            ["algorithm", $algorithm],
            ["signature", $signature],
            ["headers", "(request-target) date digest content-length"]
        ]);
        $httpSign->expects($this->once())->method('getMessage')->willReturn($msg);
        $httpSign->expects($this->once())->method('assertSignatureAge');
        
        $httpSign->verify();
    }
    
    /**
     * @dataProvider algorithmProvider
     * 
     * @param string $algorithm
     *
     * @expectedException LTO\HTTPSignatureException
     * @expectedExceptionMessage invalid signature
     */
    public function testVerifyFail($algorithm)
    {
        $msg = join("\n", [
            "(request-target): post /foo",
            "date: Tue, 07 Jun 2014 20:51:35 GMT",
            "digest: SHA-256=X48E9qOokqqrvdts8nOJRJN3OWDUoyWxBf7kbu9DBPE=",
            "content-length: 18"
        ]);
        $hash = "0b50f70b241111e3233c84279697f7d80efae4303b54a8959c1ac68a54fe7736";
        $signature = "PIw+8VW129YY/6tRfThI3ZA0VygH4cYWxIayUZbdA3I9CKUdmqttvVZvOXN5BX2Z9jfO3f1vD1/R2jxwd3BHBw==";
        
        $account = $this->createMock(Account::class);
        $account->expects($this->once())->method('verify')
            ->with($signature, $algorithm === 'ed25519-sha256' ? pack('H*', $hash) : $msg, 'base64')
            ->willReturn(false);
        
        $httpSign = $this->createHTTPSignature(null, ['getAccount', 'getParam', 'getMessage', 'assertSignatureAge']);
        
        $httpSign->expects($this->once())->method('getAccount')->willReturn($account);
        $httpSign->expects($this->atLeastOnce())->method('getParam')->willReturnMap([
            ["algorithm", $algorithm],
            ["signature", $signature],
            ["headers", "(request-target) date digest content-length"]
        ]);
        $httpSign->expects($this->once())->method('getMessage')->willReturn($msg);
        
        $httpSign->verify();
    }
    
    
    /**
     * @dataProvider algorithmProvider
     * 
     * @param string $algorithm
     */
    public function testSignWith($algorithm)
    {
        $msg = join("\n", [
            "(request-target): post /foo",
            "date: Tue, 07 Jun 2014 20:51:35 GMT",
            "digest: SHA-256=X48E9qOokqqrvdts8nOJRJN3OWDUoyWxBf7kbu9DBPE=",
            "content-length: 18"
        ]);
        $hash = "0b50f70b241111e3233c84279697f7d80efae4303b54a8959c1ac68a54fe7736";
        $signature = "PIw+8VW129YY/6tRfThI3ZA0VygH4cYWxIayUZbdA3I9CKUdmqttvVZvOXN5BX2Z9jfO3f1vD1/R2jxwd3BHBw==";
        
        $account = $this->createMock(Account::class);
        $account->expects($this->once())->method('getPublicSignKey')->with('base64')
            ->willReturn("2yYhlEGdosg7QZC//hibHiZ1MHk2m7jp/EbUeFdzDis=");
        $account->expects($this->once())->method('sign')
            ->with($algorithm === 'ed25519-sha256' ? pack('H*', $hash) : $msg, 'base64')
            ->willReturn($signature);
        
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->any())->method('hasHeader')->with('date')->willReturn(true);
        $request->expects($this->any())->method('getHeaderLine')->with('date')
            ->willReturn("Tue, 07 Jun 2014 20:51:35 GMT");
        $request->expects($this->once())->method('withHeader')
            ->with('authorization', 'Signature keyId="2yYhlEGdosg7QZC//hibHiZ1MHk2m7jp/EbUeFdzDis=",algorithm="ed25519-sha256",headers="(request-target) date digest content-length",signature="PIw+8VW129YY/6tRfThI3ZA0VygH4cYWxIayUZbdA3I9CKUdmqttvVZvOXN5BX2Z9jfO3f1vD1/R2jxwd3BHBw=="')
            ->willReturnSelf();
        
        $httpSign = $this->createHTTPSignature($request, ['getHeaders', 'getMessage']);
        
        $httpSign->expects($this->once())->method('getHeaders')
            ->willReturn(["(request-target)", "date", "digest", "content-length"]);
        $httpSign->expects($this->once())->method('getMessage')->willReturn($msg);
        
        $ret = $httpSign->signWith($account, $algorithm);
        $this->assertSame($request, $ret);
        
        $this->assertSame($account, $httpSign->getAccount());
        $this->assertEquals([
            'keyId' => "2yYhlEGdosg7QZC//hibHiZ1MHk2m7jp/EbUeFdzDis=",
            'algorithm' => 'ed25519-sha256',
            'headers' => "(request-target) date digest content-length"
        ], $httpSign->getParams());
    }
}
