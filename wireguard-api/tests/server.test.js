const request = require('supertest');

// Mock child_process and fs
jest.mock('child_process', () => ({
    execSync: jest.fn((cmd) => {
        if (cmd === 'wg genkey') return Buffer.from('mocked_private_key\n');
        if (cmd.includes('wg pubkey')) return Buffer.from('mocked_public_key\n');
        if (cmd.includes('wg show')) return Buffer.from('mocked_server_public_key\n');
        return Buffer.from('');
    })
}));
jest.mock('fs', () => ({
    appendFileSync: jest.fn(),
    readFileSync: jest.fn(() => 'mocked_server_public_key\n'),
    writeFileSync: jest.fn()
}));

const app = require('../server.js');

describe('WireGuard Sidecar API', () => {
    it('should return 400 if IP is missing', async () => {
        const res = await request(app)
            .post('/peers')
            .send({});
        expect(res.statusCode).toEqual(400);
        expect(res.body.error).toBe('IP required');
    });

    it('should generate keys and return 200 for valid IP', async () => {
        const res = await request(app)
            .post('/peers')
            .send({ ip: '10.66.66.5/32' });
        
        expect(res.statusCode).toEqual(200);
        expect(res.body.private_key).toBe('mocked_private_key');
        expect(res.body.public_key).toBe('mocked_public_key');
        expect(res.body.server_public_key).toBe('mocked_server_public_key');
    });
});
