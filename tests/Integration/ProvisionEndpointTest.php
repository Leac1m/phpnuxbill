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

    public function testPortalEndpointReturnsValidHtml() {
        $context = stream_context_create(['http' => ['ignore_errors' => true]]);
        $response = file_get_contents($this->baseUrl . '/provision.php?portal=1&router=10.66.66.2', false, $context);
        
        $this->assertStringContainsString('<meta http-equiv="refresh"', $response, 'Portal should serve a meta refresh redirect');
        $this->assertStringContainsString('nux-mac=$(mac)', $response, 'Portal should safely pass the $(mac) variable un-evaluated');
        $this->assertStringContainsString('nux-ip=$(ip)', $response, 'Portal should safely pass the $(ip) variable un-evaluated');
        $this->assertStringContainsString('nux-router=10.66.66.2', $response, 'Portal should inject the provided router IP');
    }
}
