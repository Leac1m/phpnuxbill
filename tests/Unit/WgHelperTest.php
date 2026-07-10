<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../system/autoload/WgHelper.php';

class WgHelperTest extends TestCase {
    public function testGetNextIpEmpty() {
        $this->assertEquals('10.66.66.2', WgHelper::getNextIp(null));
        $this->assertEquals('10.66.66.2', WgHelper::getNextIp(''));
    }

    public function testGetNextIpIncrement() {
        $this->assertEquals('10.66.66.3', WgHelper::getNextIp('10.66.66.2'));
        $this->assertEquals('10.66.66.10', WgHelper::getNextIp('10.66.66.9'));
        $this->assertEquals('10.66.66.254', WgHelper::getNextIp('10.66.66.253'));
    }

    public function testGetNextIpExhausted() {
        $this->expectException(Exception::class);
        WgHelper::getNextIp('10.66.66.254');
    }
}
