<?php
/**
 * Created by PhpStorm.
 * User: river
 * Date: 2016/4/21
 * Time: 15:51
 */

namespace PCPayClient\test\Client;

use Mockery;
use PCPayClient\Client\PPApiClient;
use PCPayClient\Storage\ITokenStorage;
use PCPayClient\Utils\CurlTool;
use PCPayClient\Utils\PPDIC;
use PCPayClient\ValueObject\Request\PaymentPostReqVO;
use PHPUnit_Framework_TestCase;


class PPApiClientTest extends PHPUnit_Framework_TestCase
{
    var $token;
    var $tokenStr;

    protected function setUp()
    {
        $this->token = "xl_LIAOPBf5C_566CmQbPPtDnbzfMBC8kUSopx3c";
        $this->tokenStr = '{"token":"' . $this->token . '","expired_in":28800,"expired_timestamp":' . (time() + (8 * 60 * 60)) . '}';

        parent::setUp(); // TODO: Change the autogenerated stub
    }


    public function testGetTokenObj()
    {

        $mockStorage = Mockery::mock(ITokenStorage::class);
        $mockStorage->shouldReceive('getTokenStr')->andReturn($this->tokenStr)
                    ->shouldReceive('saveTokenStr')->andReturn(true);

        $srv = new PPApiClient("", "", $mockStorage);
        $this->assertEquals($this->token, $srv->getTokenObj()->getToken());                       //token must be equal
        $this->assertTrue($srv->getTokenObj()->willExpiredIn(8 * 60 * 60 + 10));            //will expired in 8 hours 10 minutes
        $this->assertFalse($srv->getTokenObj()->willExpiredIn(7 * 60 * 60));                //will not expired in 7 hours


        //=====  token not exists in storage, get from server   =====
        $mockStorage = Mockery::mock(ITokenStorage::class);
        $mockStorage->shouldReceive('getTokenStr')->andReturn("")
            ->shouldReceive('saveTokenStr')->andReturn(true);

        $mockCurl = Mockery::mock(CurlTool::class);
        $mockCurl->shouldReceive("postToken")->andReturn($this->tokenStr);
        PPDIC::set($mockCurl, CurlTool::class);

        $srv = new PPApiClient("app02", "secret", $mockStorage);
        $this->assertEquals($this->token, $srv->getTokenObj()->getToken());                       //token must be equal
        $this->assertTrue($srv->getTokenObj()->willExpiredIn(8 * 60 * 60 + 10));            //will expired in 8 hours 10 minutes
        $this->assertFalse($srv->getTokenObj()->willExpiredIn(7 * 60 * 60));                //will not expired in 7 hours

    }

    /**
     * @expectedException \PCPayClient\Exceptions\ApiAuthException
     */
    public function testGetTokenObjThrowException()
    {
        $mockStorage = Mockery::mock(ITokenStorage::class);
        $mockStorage->shouldReceive('getTokenStr')->andReturn("I'm FakeToken")
            ->shouldReceive('saveTokenStr')->andReturn(true);

        $mockCurl = Mockery::mock(CurlTool::class);
        $mockCurl->shouldReceive("postToken")->andReturn('{"error_type":"auth_error","code":0,"message":"App ID is not exist"}');
        PPDIC::set($mockCurl, CurlTool::class);

        $srv = new PPApiClient("app021", "secret", $mockStorage);
        $srv->getTokenObj();
    }


    /**
     * @expectedException \PCPayClient\Exceptions\ApiInvalidRequestException
     */
    public function testWrongToken()
    {
        $mockStorage = Mockery::mock(ITokenStorage::class);
        $mockStorage->shouldReceive('getTokenStr')->andReturn("")
            ->shouldReceive('saveTokenStr')->andReturn(true);

        $mockCurl = Mockery::mock(CurlTool::class);
        $mockCurl->shouldReceive("postToken")->andReturn('{"error_type":"invalid_request","code":0,"message":"App ID is not exist"}');
        PPDIC::set($mockCurl, CurlTool::class);

        $vo = new PaymentPostReqVO();
        $srv = new PPApiClient("", "", $mockStorage);
        $srv->postPayment($vo);
    }

    public function testPostPayment()
    {
        $orderId = time();
        $paymentUrl = "https:\/\/partner.pchomepay.com.tw\/ppwf\/?_pwfkey_=pPVGQiu0oUL-muVy1Sz5ShPM5KqmRGio,Avjq9wV-yxyY1cJAPqbgcceew__";
        $paymentUrl_1 = 'https://partner.pchomepay.com.tw/ppwf/?_pwfkey_=pPVGQiu0oUL-muVy1Sz5ShPM5KqmRGio,Avjq9wV-yxyY1cJAPqbgcceew__';


        $mockStorage = Mockery::mock(ITokenStorage::class);
        $mockStorage->shouldReceive('getTokenStr')->andReturn($this->tokenStr)
            ->shouldReceive('saveTokenStr')->andReturn(true);

        $mockCurl = Mockery::mock(CurlTool::class);
        $mockCurl->shouldReceive("postAPI")->andReturn('{"order_id":' . $orderId . ',"payment_url":"' . $paymentUrl . '"}');
        PPDIC::set($mockCurl, CurlTool::class);

        $vo = new PaymentPostReqVO();
        $srv = new PPApiClient("", "", $mockStorage);
        $result = $srv->postPayment($vo);

        $this->assertEquals($result->order_id, $orderId);
        $this->assertEquals($result->payment_url, $paymentUrl_1);

    }

    /**
     * @expectedException \PCPayClient\Exceptions\ApiInvalidRequestException
     */
    public function testGetPayment(){
        $mockStorage = Mockery::mock(ITokenStorage::class);
        $mockStorage->shouldReceive('getTokenStr')->andReturn($this->tokenStr)
            ->shouldReceive('saveTokenStr')->andReturn(true);

        $orderId = time();

        //set curl return error message
        //check params right
        $mockCurl = Mockery::mock(CurlTool::class);
        $mockCurl->shouldReceive("getAPI")
            ->with($this->token, '/payment\/'.$orderId.'$/', [])
            ->andReturn('{"error_type":"invalid_request_error","code":0,"message":"order not exists"}');
        PPDIC::set($mockCurl, CurlTool::class);

        $srv = new PPApiClient("", "", $mockStorage);
        $result = $srv->getPayment($orderId);


    }


    /**
     * @expectedException \PCPayClient\Exceptions\ApiServerError
     */
    public function testCurlReturnNonJsonStr(){
        $mockStorage = Mockery::mock(ITokenStorage::class);
        $mockStorage->shouldReceive('getTokenStr')->andReturn($this->tokenStr)
            ->shouldReceive('saveTokenStr')->andReturn(true);

        $orderId = time();

        //set curl return error message
        //check params right
        $mockCurl = Mockery::mock(CurlTool::class);
        $mockCurl->shouldReceive("getAPI")
            ->with($this->token, '/payment\/'.$orderId.'$/', [])
            ->andReturn("I'm not a json string");
        PPDIC::set($mockCurl, CurlTool::class);

        $srv = new PPApiClient("", "", $mockStorage);
        $result = $srv->getPayment($orderId);
    }

    protected function tearDown()
    {
        Mockery::close();
        parent::tearDown(); // TODO: Change the autogenerated stub
    }
}
