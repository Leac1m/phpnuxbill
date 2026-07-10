# PHPNuxBill <-> MikroTik WireGuard Setup Guide

This guide explains how to connect a remote or local MikroTik router (e.g., a local VirtualBox VM) securely to the production PHPNuxBill instance using the integrated WireGuard Docker container.

## 1. Start the WireGuard Container

The production stack (`docker-compose.prod.yml`) now includes a `linuxserver/wireguard` service. 

When you start the stack, WireGuard will automatically initialize and generate a client configuration for `peer1`:
```bash
docker compose -f docker-compose.prod.yml up -d
```

## 2. Retrieve the Client Configuration

To get the client configuration for the MikroTik router, run the following command on the production server to read the generated file:
```bash
docker exec -it nuxbill-wireguard cat /config/peer1/peer1.conf
```

You will see output similar to this:
```ini
[Interface]
Address = 10.66.66.2
PrivateKey = <YOUR_PRIVATE_KEY>
ListenPort = 51820
DNS = 10.66.66.1

[Peer]
PublicKey = <SERVER_PUBLIC_KEY>
Endpoint = <SERVER_IP>:51820
AllowedIPs = 0.0.0.0/0
```

## 3. Provision the MikroTik Router

MikroTik RouterOS (v7+) treats WireGuard as standard network interfaces rather than using cryptokey routing directly. 

> **Important:** Do NOT try to copy and paste these commands directly into the MikroTik interactive SSH terminal if they contain long keys, as the terminal buffer will often swallow the lines.

Instead, on your local Linux machine (or the machine where you have SSH access to the MikroTik), run this block to push the script directly into the router. 

*Replace the keys and IP addresses with the exact ones from Step 2. Be extremely careful with Base64 characters (e.g., `0` vs `O`, `l` vs `1`).*

```bash
# 1. Open a normal Bash terminal on your computer
# 2. Paste this block to create the router script (update variables first!)
cat << 'EOF' > wg.rsc
/interface wireguard peers remove [find]
/interface wireguard remove [find]
/ip address remove [find address="10.66.66.2/24"]
/ip address remove [find invalid=yes]

/interface wireguard add name=wg0 private-key="<YOUR_PRIVATE_KEY>" listen-port=13231
/ip address add address=10.66.66.2/24 interface=wg0
/interface wireguard peers add interface=wg0 public-key="<SERVER_PUBLIC_KEY>" endpoint-address="<SERVER_IP>" endpoint-port=51820 allowed-address=10.66.66.0/24 persistent-keepalive=25s
/interface wireguard enable wg0
/ip service enable api
EOF

# 3. Transfer the script to the router (replace 192.168.1.50 with router's local IP)
scp wg.rsc admin@192.168.1.50:/

# 4. SSH into the router and run the import command
ssh admin@192.168.1.50 "/import file-name=wg.rsc"
```

## 4. Verify the Connection

Log into the MikroTik router and ping the WireGuard container's IP:
```routeros
/ping 10.66.66.1 count=4
```
If you get replies, the tunnel is successfully established.

## 5. Add the Router to PHPNuxBill

1. Log into your PHPNuxBill Admin Dashboard.
2. Navigate to **Network -> Routers**.
3. Click **Add Router**.
4. Use the following settings:
   - **IP Address:** `10.66.66.2` (The secure VPN IP)
   - **Username / Password:** The MikroTik API credentials (usually `admin`)
   - **API Port:** `8728`
5. Click **Save**. PHPNuxBill is now managing the router securely over the internet!
