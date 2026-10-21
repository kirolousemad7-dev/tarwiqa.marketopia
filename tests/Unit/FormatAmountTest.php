<?php

namespace Tests\Unit;

use Tests\TestCase;

class FormatAmountTest extends TestCase
{
    public function test_format_amount_renders_exact_currency_decimals(): void
    {
        $this->assertSame('725.00', format_amount(725));
        $this->assertSame('725.50', format_amount(725.5));
        $this->assertSame('1,250.00', format_amount(1250));
        $this->assertSame('0.00', format_amount(0));
        $this->assertSame('-15.25', format_amount(-15.25));
        $this->assertSame('2,400,000.00', format_amount(2400000));
    }
}
