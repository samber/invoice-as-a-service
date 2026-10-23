<?php

namespace Tests\Unit;

use App\Helpers\Delivery;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    public function testBodyFalseAndZeroWinOverEnvDefaults(): void
    {
        config(['delivery.ftp' => ['host' => 'ftp.instance.test', 'passive' => 'true', 'ssl' => 'true', 'port' => '2121']]);

        $ftp = Delivery::withDefaults(['ftp' => ['passive' => false, 'port' => 0]])['ftp'];

        $this->assertFalse($ftp['passive']);
        $this->assertSame(0, $ftp['port']);
        $this->assertTrue($ftp['ssl']);
        $this->assertSame('ftp.instance.test', $ftp['host']);
    }

    public function testEnvScalarsAreCastToTheTypesTheValidatorExpects(): void
    {
        config(['delivery.ftp' => ['host' => 'h', 'port' => '2121', 'ssl' => 'false', 'passive' => '1']]);

        $ftp = Delivery::withDefaults([])['ftp'];

        $this->assertSame(2121, $ftp['port']);
        $this->assertFalse($ftp['ssl']);
        $this->assertTrue($ftp['passive']);
    }

    public function testEnvHeadersAreDecodedAndBodyHeadersReplaceThemWhole(): void
    {
        config(['delivery.webhook' => ['url' => 'https://hook.example.com', 'headers' => '{"x-a":"1","x-b":"2"}']]);

        $this->assertSame(['x-a' => '1', 'x-b' => '2'], Delivery::withDefaults([])['webhook']['headers']);
        $this->assertSame(['x-c' => '3'], Delivery::withDefaults(['webhook' => ['headers' => ['x-c' => '3']]])['webhook']['headers']);
    }

    public function testUnrelatedBodyKeysAreLeftUntouched(): void
    {
        config(['delivery.email.to' => 'ops@instance.test']);

        $data = Delivery::withDefaults(['id' => 'INV-1', 'items' => [['title' => 'x']]]);

        $this->assertSame('INV-1', $data['id']);
        $this->assertSame([['title' => 'x']], $data['items']);
    }

    public function testChannelsInBodyIgnoresNullAndEmptyValues(): void
    {
        $this->assertSame([], Delivery::channelsInBody(['s3' => ['presigned_url' => null], 'ftp' => [], 'email' => null]));
        $this->assertSame(['ftp', 'email'], Delivery::channelsInBody(['ftp' => ['host' => 'h'], 'email' => ['to' => 'a@b.test'], 'id' => '1']));
    }
}
