# Deployment Guide

## Architecture

```
GitHub Actions
    │ (build images, trigger via SSM)
    ▼
EC2 t2.micro (Docker: app + nginx + supervisor)
    │
    ▼
RDS PostgreSQL db.t3.micro (private, only EC2 can reach it)
```

Two containers run on EC2 via docker compose:

- **app** — Laravel PHP-FPM (port 9000, internal only)
- **nginx** — Nginx reverse proxy (port 80/443), copies static assets from the app image at build time

---

## Phase 1: AWS Setup

### Security Groups

**`tmdb-app-sg`** (EC2)
| Type | Port | Source |
|---|---|---|
| HTTP | 80 | 0.0.0.0/0 |
| HTTPS | 443 | 0.0.0.0/0 |

> No SSH inbound rule — EC2 access is handled via SSM Session Manager instead. Opening SSH to all IPs is not safe, and maintaining a GitHub Actions IP allowlist is impractical since their IPs change constantly.

**`tmdb-db-sg`** (RDS)
| Type | Port | Source |
|---|---|---|
| PostgreSQL | 5432 | `tmdb-app-sg` |

### RDS PostgreSQL

- Engine: PostgreSQL, Template: Free tier
- Instance: `db.t3.micro`, Storage: 20GB gp2
- Public access: No
- Security group: `tmdb-db-sg`

### EC2 Instance

- AMI: Ubuntu 24.04 LTS, Instance type: `t2.micro`
- Security group: `tmdb-app-sg`
- No key pair required (using SSM for access)
- Allocate an **Elastic IP** and associate it to the instance so the IP doesn't change on restart

### ECR Repositories

Create two private repositories:

- `tmdb-app`
- `tmdb-nginx`

---

## Phase 2: IAM Setup

Two separate identities — never share credentials between CI and the EC2 instance.

### IAM User — GitHub Actions

| Policy                                |
| ------------------------------------- |
| `AmazonEC2ContainerRegistryPowerUser` |
| `AmazonSSMFullAccess`                 |

> Must be `PowerUser`, not `ReadOnly` — `ReadOnly` doesn't have permission to push images (`ecr:InitiateLayerUpload` will be denied).

Generate access keys and add them as GitHub repository secrets.

### IAM Role — EC2 Instance

Create a role with `EC2` as the trusted entity and attach:

| Policy                               |
| ------------------------------------ |
| `AmazonSSMManagedInstanceCore`       |
| `AmazonEC2ContainerRegistryReadOnly` |

Attach via **EC2 → Instance → Actions → Security → Modify IAM role**.

> EC2 uses a role (not a user) so it never needs static credentials — temporary credentials are provided automatically by AWS.

---

## Phase 3: Server Setup

Access the EC2 via **SSM Session Manager** in the AWS console (no SSH needed).

### Install dependencies

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y docker.io docker-compose-v2 supervisor awscli
sudo usermod -aG docker ubuntu
newgrp docker
```

### Fix ownership

`/opt/tmdb` is created as root by default. Change it to `ubuntu` so you can work without sudo:

```bash
sudo chown -R ubuntu:ubuntu /opt/tmdb
```

### Clone the repository

The repo is cloned on EC2 so the deploy script always has the latest `docker-compose.prod.yml` without manually copying files.

For private repos, set up a deploy key first:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/deploy_key -N ""
cat ~/.ssh/deploy_key.pub  # add this to GitHub → repo → Settings → Deploy keys
```

Then clone:

```bash
GIT_SSH_COMMAND="ssh -i ~/.ssh/deploy_key" git clone git@github.com:<user>/<repo>.git /opt/tmdb/repo
```

### Environment file

```bash
nano /opt/tmdb/.env
```

```env
APP_NAME=Laravel
APP_ENV=production
APP_DEBUG=false
APP_URL=http://<your-elastic-ip>
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

DB_CONNECTION=pgsql
DB_HOST=<your-rds-endpoint>
DB_PORT=5432
DB_DATABASE=tmdb
DB_USERNAME=tmdb_user
DB_PASSWORD=<your-rds-password>

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
DB_QUEUE_CONNECTION=pgsql

ALLOW_REGISTER=false
MOVIE_DB_IMG_URL=<tmdb-image-base-url>
```

> `APP_KEY` must be generated once and never changed — rotating it invalidates all user sessions and encrypted data. Generate it locally with `php artisan key:generate --show` and paste the value here.

> `DB_QUEUE_CONNECTION` must be set explicitly — `config/queue.php` has no default for this value and the queue worker will fail silently without it.

### Compose env file

Docker compose needs `ECR_URI` to know where to pull images from. Keep it separate from the app `.env`:

```bash
nano /opt/tmdb/.env.compose
```

```env
ECR_URI=<account-id>.dkr.ecr.<region>.amazonaws.com
```

### Supervisor (queue worker)

```bash
sudo nano /etc/supervisor/conf.d/tmdb-worker.conf
```

```ini
[program:tmdb-worker]
command=docker exec tmdb-app-1 php artisan queue:work --queue=default
autostart=false
autorestart=true
stderr_logfile=/var/log/tmdb-worker.err.log
stdout_logfile=/var/log/tmdb-worker.out.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
```

> `autostart=false` — supervisor starts on boot before Docker is ready, which causes the worker to fail. The deploy script handles starting it after containers are up.

> The container name is `tmdb-app-1` because docker compose appends a replica number suffix (`-1`) by default. The `name: tmdb` at the top of `docker-compose.prod.yml` ensures this name stays consistent regardless of what folder the repo is cloned into.

### Deploy script

Create `/opt/tmdb/deploy.sh`:

```bash
#!/bin/bash
set -e

export ECR_URI="<account-id>.dkr.ecr.<region>.amazonaws.com"
REGION="<your-region>"

echo "Pulling latest code..."
cd /opt/tmdb/repo
git pull origin master

echo "Logging into ECR..."
aws ecr get-login-password --region $REGION | docker login --username AWS --password-stdin $ECR_URI

echo "Pulling latest images..."
docker compose --env-file /opt/tmdb/.env.compose -f /opt/tmdb/repo/docker-compose.prod.yml pull

echo "Restarting containers..."
docker compose --env-file /opt/tmdb/.env.compose -f /opt/tmdb/repo/docker-compose.prod.yml up -d

echo "Running migrations..."
docker compose --env-file /opt/tmdb/.env.compose -f /opt/tmdb/repo/docker-compose.prod.yml exec app php artisan migrate --force

echo "Restarting queue worker..."
sudo supervisorctl restart tmdb-worker

echo "Done!"
```

```bash
chmod +x /opt/tmdb/deploy.sh
```

---

## Phase 4: GitHub Actions Secrets

Go to **repo → Settings → Secrets and variables → Actions → Repository secrets** and add:

| Secret                  | Value                      |
| ----------------------- | -------------------------- |
| `AWS_ACCESS_KEY_ID`     | From IAM user              |
| `AWS_SECRET_ACCESS_KEY` | From IAM user              |
| `AWS_REGION`            | e.g. `ap-northeast-2`      |
| `EC2_INSTANCE_ID`       | e.g. `i-1234567890abcdef0` |

> Use **repository secrets**, not environment secrets. Environment secrets are for multi-environment setups (e.g. staging vs production with different values).

---

## How Deployments Work

On every push to `master`, GitHub Actions will:

1. Build and push the `tmdb-app` Docker image to ECR
2. Build and push the `tmdb-nginx` image to ECR (copies `public/build` static assets from the app image at build time)
3. Trigger `/opt/tmdb/deploy.sh` on EC2 via SSM — no open SSH port needed

---

## Debugging Notes

### ECR push forbidden (`ecr:InitiateLayerUpload`)

The IAM user had `AmazonEC2ContainerRegistryReadOnly` — not enough for pushing. Must be changed to `AmazonEC2ContainerRegistryPowerUser`.

### Wrong ECR repository path

The image tag must exactly match the repository name in ECR. If the repo is `tmdb-app`, the tag must be `<account-id>.dkr.ecr.<region>.amazonaws.com/tmdb-app:latest` with no extra path segments.

### SSH timeout from GitHub Actions (`dial tcp ***:22: i/o timeout`)

Caused by having SSH restricted to a specific IP. GitHub Actions IPs change constantly so an allowlist doesn't work. Removed SSH from the security group entirely and switched to SSM `send-command` — no open ports required.

### Node.js deprecation warning in GitHub Actions

The warning comes from the action versions, not the Dockerfile. Pinning to specific versions resolves it: `actions/checkout@v4.2.2`, `aws-actions/configure-aws-credentials@v4.0.3`, `aws-actions/amazon-ecr-login@v2.0.1`.

### RDS connection falling back to local socket

Symptom: `connection to server on socket "/var/run/postgresql/.s.PGSQL.5432" failed`. Caused by `DB_HOST` being empty or missing in `.env`. Must be set to the full RDS endpoint, not `localhost` or `127.0.0.1`.

### Queue worker failing with `[tries=3] queue connection has not been configured`

Caused by a typo in the supervisor config — the `--` prefix was missing from the flags, so Laravel interpreted `tries=3` as the connection name instead of a flag. Fixed by correcting the command in `tmdb-worker.conf`.

### Queue worker failing with `queue connection has not been configured` (different cause)

`config/queue.php` has `'connection' => env('DB_QUEUE_CONNECTION')` with no default value. Without `DB_QUEUE_CONNECTION=pgsql` in `.env`, Laravel can't resolve the queue database connection.

### Supervisor starts worker before Docker is ready

`autostart=true` causes supervisor to start the worker on EC2 boot, before Docker and the containers are up. Set `autostart=false` and let `deploy.sh` handle starting the worker after containers are confirmed running.

### Container named unexpectedly (e.g. `repo-app-1` instead of `tmdb-app-1`)

Docker compose uses the folder name as the project name by default. When the repo was cloned into `/opt/tmdb/repo`, the containers became `repo-app-1`. Fixed by adding `name: tmdb` at the top of `docker-compose.prod.yml` to pin the project name regardless of folder.

### APP_KEY not set

Symptom: Laravel errors or blank page on first run. Generate the key once locally with `php artisan key:generate --show` and add it to `/opt/tmdb/.env`. Never regenerate it — doing so invalidates all active sessions.

### Seeder asks for confirmation in production (`APPLICATION IN PRODUCTION. Command cancelled.`)

Laravel prompts for confirmation when running seeders in production. Bypass it with `--force`:

```bash
docker exec tmdb-app-1 php artisan db:seed --class=TitleSeeder --force
```

### CountsSeeder memory exhausted

The original seeder used `with('titles')` which loaded every title record for every keyword/language/company into PHP memory. Replaced with raw SQL `UPDATE ... SET count = (SELECT COUNT(*) ...)` queries — runs entirely on the database with no PHP memory overhead.

### SSM Session Manager stopped working after EC2 restart (prompted for username/password)

The SSM agent was not running. Ubuntu 24.04 on EC2 does not always persist snap services across reboots. Fix:

```bash
sudo snap install amazon-ssm-agent --classic
sudo systemctl enable snap.amazon-ssm-agent.amazon-ssm-agent
sudo systemctl start snap.amazon-ssm-agent.amazon-ssm-agent
```

The `enable` command ensures it starts automatically on every future boot. Once running, SSM Session Manager in the AWS console and `ssm send-command` in GitHub Actions will work again.

### Browser shows old/cached page after EC2 restart

Not an actual outage — the browser cached the previous response. `curl http://<elastic-ip>` bypasses the cache and confirms whether the app is actually up. Once curl returns HTML, the browser will work again after its cache TTL expires or on a hard refresh.
