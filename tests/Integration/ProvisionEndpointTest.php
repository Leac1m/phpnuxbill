<?php
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Provisioning Endpoint.
 * Requires a running test environment.
 */
class ProvisionEndpointTest extends TestCase {
    
    private $baseUrl = 'http://localhost'; // Adjust for CI/Test environment

    public function testRejectsEmptyToken() {
        $context = stream_context_create(['http' => ['ignore_errors' => true]]);
        $response = file_get_contents($this->baseUrl . '/provision.php', false, $context);
        
        $this->assertStringContainsString('Invalid token', $response, 'Endpoint should reject requests without a token');
    }

    public function testRejectsInvalidToken() {
        $context = stream_context_create(['http' => ['ignore_errors' => true]]);
        $response = file_get_contents($this->baseUrl . '/provision.php?token=invalid123', false, $context);
        
        $this->assertStringContainsString('Token invalid or expired', $response, 'Endpoint should reject fake tokens');
    }
}
