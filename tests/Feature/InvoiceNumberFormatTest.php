<?php

namespace Tests\Feature;

use Tests\TestCase;

class InvoiceNumberFormatTest extends TestCase
{
    private function payload(array $override = []): array
    {
        return array_replace_recursive([
            'id' => 'INV-1',
            'currency' => 'EUR',
            'lang' => 'en',
            'date' => 1700000000,
            'due_date' => 1702592000,
            'items' => [
                ['title' => 'Consulting', 'price' => 1234.5, 'quantity' => 2, 'tax' => 20],
            ],
            'customer' => ['summary' => 'Customer Inc', 'address_line_1' => '1 Customer street'],
            'company' => ['summary' => 'Company Inc', 'address_line_1' => '1 Company street'],
        ], $override);
    }

    public function testValidExampleIsAccepted(): void
    {
        foreach (['1,234.56', '1.234,56', '1 234,56', '1234.56'] as $example) {
            $this->postJson('/api/invoice/generate', $this->payload(['number_format' => $example]))
                ->assertStatus(201);
        }
    }

    public function testInvalidExampleIsRejected(): void
    {
        foreach (['1.234.56', '1,234', 'EU', '12,34.56'] as $example) {
            $this->postJson('/api/invoice/generate', $this->payload(['number_format' => $example]))
                ->assertStatus(422)
                ->assertJsonStructure(['number_format']);
        }
    }

    public function testNonStringIsRejected(): void
    {
        $this->postJson('/api/invoice/generate', $this->payload(['number_format' => 1234.56]))
            ->assertStatus(422)
            ->assertJsonStructure(['number_format']);
    }

    public function testNullFallsBackToDefault(): void
    {
        $this->postJson('/api/invoice/generate', $this->payload(['number_format' => null]))
            ->assertStatus(201);
    }

    public function testInvalidEnvValueDoesNotBreakRequests(): void
    {
        config(['invoice.number_format' => 'nonsense']);

        $this->postJson('/api/invoice/generate', $this->payload())->assertStatus(201);
    }
}
