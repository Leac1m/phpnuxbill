FROM lscr.io/linuxserver/wireguard:latest

# Install Node.js
RUN apk add --no-cache nodejs npm

# Copy API application
COPY server.js /app/server.js
COPY package.json /app/package.json
RUN cd /app && npm install

# Copy s6-overlay service definitions
COPY root/ /

EXPOSE 8080
