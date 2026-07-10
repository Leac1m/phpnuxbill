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

    public function testProvisioningPayloadIncludesWalledGarden() {
        // Insert a valid token to bypass validation and get the .rsc payload
        require_once dirname(dirname(__DIR__)) . '/config.php';
        $db = new \PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $token = bin2hex(random_bytes(16));
        $db->exec("CREATE TABLE IF NOT EXISTS `tbl_provisioning_tokens` (
            `id` int NOT NULL AUTO_INCREMENT,
            `token` varchar(64) NOT NULL,
            `expires_at` datetime NOT NULL,
            `assigned_ip` varchar(32) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $db->exec("INSERT INTO tbl_provisioning_tokens (token, expires_at, assigned_ip) VALUES ('$token', DATE_ADD(NOW(), INTERVAL 1 HOUR), '10.66.66.99')");
        
        $context = stream_context_create(['http' => ['ignore_errors' => true]]);
        $response = file_get_contents($this->baseUrl . '/provision.php?token=' . $token, false, $context);
        
        $this->assertStringContainsString('*.paystack.com', $response, 'RSC payload should include hooked plugin domains');
        $this->assertStringContainsString('*.paystack.co', $response, 'RSC payload should include hooked plugin domains');
        $this->assertStringContainsString('dst-address="', $response, 'RSC payload should include the server IP walled garden');
    }
}
