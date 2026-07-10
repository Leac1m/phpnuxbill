<?php
/**
 * WireGuard Helper Methods
 */
class WgHelper {
    /**
     * Calculates the next available IP address in the 10.66.66.x subnet.
     * 
     * @param string|null $lastIp The highest allocated IP address so far
     * @return string The next IP address
     * @throws Exception If the subnet is exhausted
     */
    public static function getNextIp($lastIp) {
        if (empty($lastIp)) {
            return '10.66.66.2';
        }
        
        $parts = explode('.', $lastIp);
        if (count($parts) === 4) {
            $lastOctet = (int)$parts[3];
            if ($lastOctet < 254) {
                return '10.66.66.' . ($lastOctet + 1);
            }
            throw new Exception('WireGuard subnet capacity exhausted (10.66.66.254 reached)');
        }
        
        return '10.66.66.2';
    }
}
