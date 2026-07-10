const express = require('express');
const { execSync } = require('child_process');
const fs = require('fs');

const app = express();
app.use(express.json());

const WG_CONF = '/config/wg0.conf';

// Basic security middleware (in production, use a shared secret via ENV)
app.use((req, res, next) => {
    // For internal docker network use only, but adding simple token check is good practice
    const token = req.headers['x-api-token'];
    if (process.env.API_TOKEN && token !== process.env.API_TOKEN) {
        return res.status(401).json({ error: 'Unauthorized' });
    }
    next();
});

app.post('/peers', (req, res) => {
    const { ip } = req.body; // e.g. 10.66.66.5/32
    if (!ip) return res.status(400).json({error: 'IP required'});

    try {
        // Generate private and public key for the new router
        const privKey = execSync('wg genkey').toString().trim();
        const pubKey = execSync(`echo "${privKey}" | wg pubkey`).toString().trim();
        
        // 1. Add to the active interface instantly (no reboot required)
        execSync(`wg set wg0 peer "${pubKey}" allowed-ips ${ip}`);
        
        // 2. Append to config for persistence across reboots
        const peerConfig = `\n[Peer]\nPublicKey = ${pubKey}\nAllowedIPs = ${ip}\n`;
        fs.appendFileSync(WG_CONF, peerConfig);
        
        // 3. Get the server's public key to return to the client
        let serverPubKey = "";
        try {
            // linuxserver/wireguard writes it here
            serverPubKey = fs.readFileSync('/config/server/publickey-server', 'utf8').trim();
        } catch(e) {
            // fallback
            serverPubKey = execSync('wg show wg0 public-key').toString().trim();
        }

        res.json({
            status: 'success',
            private_key: privKey,
            public_key: pubKey,
            server_public_key: serverPubKey
        });
    } catch (e) {
        console.error(e);
        res.status(500).json({error: e.toString()});
    }
});

// Remove a peer
app.delete('/peers/:pubkey', (req, res) => {
    const pubKey = req.params.pubkey;
    try {
        // Remove from active interface
        execSync(`wg set wg0 peer "${pubKey}" remove`);
        
        // Remove from persistent config
        let config = fs.readFileSync(WG_CONF, 'utf8');
        // This is a naive regex replacement, but sufficient for standard configs
        const regex = new RegExp(`\\[Peer\\][\\s\\S]*?PublicKey\\s*=\\s*${pubKey}[\\s\\S]*?(?=\\[Peer\\]|$)`, 'g');
        config = config.replace(regex, '');
        fs.writeFileSync(WG_CONF, config.trim() + '\n');
        
        res.json({ status: 'success' });
    } catch (e) {
        console.error(e);
        res.status(500).json({error: e.toString()});
    }
});

const PORT = process.env.PORT || 8080;
if (require.main === module) {
    app.listen(PORT, '0.0.0.0', () => {
        console.log(`WireGuard Sidecar API listening on port ${PORT}`);
    });
}
module.exports = app;
