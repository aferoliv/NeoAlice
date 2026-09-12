# Docker deployment

This repository now runs as two containers:

| Service | Purpose | Persistent data |
| --- | --- | --- |
| `web` | Apache, PHP 7.4, and the application | Uploaded practice files |
| `db` | MySQL 5.7, matching the original dump's server version | Application database |

The JavaScript, Phaser simulator, CSS, fonts, and other browser dependencies are already committed to this repository. No Composer, npm, or web build command is required.

## Local setup

1. Install Docker Engine and the Docker Compose plugin.
2. Create the local environment file and replace both passwords with long, unique values:

   ```bash
   cp .env.example .env
   ```

3. Start the application:

   ```bash
   docker compose up -d --build
   ```

   If the machine provides the older standalone command instead, replace `docker compose` with `docker-compose` throughout this guide.

4. Open `http://localhost:8080/`.

The first database start imports `quimica.sql`. The web container creates the ignored `lab-config.php` from environment variables and prepares the writable `uploads/praticas/` directory. Do **not** use `install.php` with this Docker setup: it is only for a manual database installation and refuses databases that already contain tables.

The seeded account is `admin` / `123456`. Change it immediately after signing in. The project uses legacy SHA-1 password hashing, so it must not be exposed publicly without access controls and a later security modernization.

Useful local commands:

```bash
docker compose ps
docker compose logs -f web
docker compose logs -f db
docker compose down
```

To completely erase local database and upload data and start again, run the destructive command below, then run `docker compose up -d --build` again:

```bash
docker compose down -v
```

## DigitalOcean deployment

1. Create an Ubuntu Droplet, add your SSH key, and install Docker Engine plus the Compose plugin using Docker's current official instructions.
2. Configure the DigitalOcean firewall to allow only SSH (`22`) and web traffic (`80` and `443`). Do not publish MySQL port `3306`; this Compose file keeps it private to the Docker network.
3. Copy or clone this repository onto the Droplet, then create `.env` from `.env.example`.
4. Set the public hostname and URL in `.env`, including the trailing slash. Keep the web port private to the Droplet; Caddy will provide public HTTPS:

   ```dotenv
   URL_SITE=https://lab.example.com/
   DOMAIN=lab.example.com
   APP_PORT=8080
   APP_BIND_ADDRESS=127.0.0.1
   ```

5. Point the domain's DNS `A` record to the Droplet. The record must resolve before Caddy can obtain the TLS certificate.
6. Start the complete stack, including the included Caddy reverse proxy:

   ```bash
   docker compose -f compose.yaml -f compose.server.yaml up -d --build
   ```

   Caddy terminates HTTPS and forwards traffic to the `web` container. No Nginx, Apache, PHP, MySQL, or TLS configuration is needed on the Droplet host.
7. Verify it with `docker compose ps` and `docker compose logs -f web`.

For updates, make a database backup first, pull/copy the new code, then rebuild only the web service:

```bash
docker compose exec db mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" > backup.sql
docker compose up -d --build web
```

Run the backup command from the same shell that has loaded the values from `.env` (for example, `set -a; . ./.env; set +a`). Store `backup.sql` outside the Droplet as well.

## ISPConfig deployment

Use this method when ISPConfig already owns the server's web ports and certificates. Do **not** start `compose.server.yaml` or Caddy on that server: ISPConfig's Apache or nginx must remain the only service listening on ports `80` and `443`.

1. In ISPConfig, add an `A` record for `neoalice.cheqmath.com` to the server's public IPv4 address. Add an `AAAA` record only if the server is reachable over IPv6.
2. In **Sites**, add a website for `neoalice.cheqmath.com`, enable SSL and Let's Encrypt, and save it. ISPConfig will create and renew the certificate after the DNS record resolves.
3. Copy this repository to a non-web-managed server directory such as `/opt/nealice`, then create `.env` from `.env.example`. Set:

   ```dotenv
   URL_SITE=https://neoalice.cheqmath.com/
   APP_PORT=8080
   APP_BIND_ADDRESS=127.0.0.1
   ```

   Set unique values for `MYSQL_PASSWORD` and `MYSQL_ROOT_PASSWORD`. The loopback bind makes the Docker application reachable only from the server itself.
4. Check the server architecture with `uname -m`. On the usual 64-bit Armbian result (`aarch64`), include `compose.arm64.yaml`: MySQL 5.7 has no ARM64 image, while the compatible MariaDB 10.5 image does. On an `x86_64` server, omit that extra file.
5. Start only the application and database containers:

   ```bash
   # ARM64 Armbian
   docker compose -f compose.yaml -f compose.arm64.yaml up -d --build

   # x86_64 server
   # docker compose up -d --build
   ```

6. Confirm the private backend works before configuring the public proxy:

   ```bash
   curl -I http://127.0.0.1:8080/
   ```

7. In the site's **Options** tab, add *one* of the following snippets to the custom directives field for the web server that handles public HTTPS traffic. Do not edit ISPConfig-generated virtual-host files directly; ISPConfig can overwrite them.

   For **Apache Directives**:

   ```apache
   ProxyPreserveHost On
   ProxyPass / http://127.0.0.1:8080/
   ProxyPassReverse / http://127.0.0.1:8080/
   RequestHeader set X-Forwarded-Proto "https"
   RequestHeader set X-Forwarded-Port "443"
   ```

   For **nginx Directives**:

   ```nginx
   location / {
       proxy_pass http://127.0.0.1:8080;
       proxy_set_header Host $host;
       proxy_set_header X-Real-IP $remote_addr;
       proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
       proxy_set_header X-Forwarded-Proto $scheme;
   }
   ```

   If Apache is the public web server, ensure its `proxy`, `proxy_http`, and `headers` modules are enabled before saving the Apache snippet:

   ```bash
   sudo a2enmod proxy proxy_http headers
   sudo apachectl configtest
   sudo systemctl reload apache2
   ```

8. Visit `https://neoalice.cheqmath.com/`, then change the seeded `admin` password immediately.

To identify the public web server on an unfamiliar ISPConfig host, check `sudo ss -ltnp '( sport = :80 or sport = :443 )'`. Use the nginx directives when nginx owns the public ports (including nginx-in-front-of-Apache setups); otherwise use the Apache directives. ISPConfig supports both custom Apache and nginx directives for individual sites.

## Operational notes

- The MySQL import executes only when the `mysql-data` volume is new. Editing `quimica.sql` later does not modify an existing deployment.
- Keep `.env`, `lab-config.php`, database dumps, and the Docker volumes out of version control.
- The application has legacy authorization patterns and unauthenticated-looking endpoint wrappers. Keep it behind HTTPS, use strong database credentials, change the seeded application password, and restrict access while it is being evaluated.
- `URL_SITE` is used by PHP to generate browser URLs. A wrong hostname, path, or missing trailing slash will break navigation and AJAX calls.
