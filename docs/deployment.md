# Deployment Guide

## Architecture

```
GitHub Actions
    │ (build image, SSM deploy)
    ▼
EC2 t2.micro (Docker: app + nginx + supervisor)
    │
    ▼
RDS PostgreSQL db.t3.micro (private, only EC2 can reach it)
```

Two containers run on EC2 via docker-compose:
- **app** — Laravel PHP-FPM (port 9000)
- **nginx** — Nginx reverse proxy (port 80/443), copies static assets from the app image at build time

---

## Phase 1: AWS Setup

### Security Groups

**`tmdb-app-sg`** (EC2)
| Type | Port | Source |
|---|---|---|
| HTTP | 80 | 0.0.0.0/0 |
| HTTPS | 443 | 0.0.0.0/0 |

> No SSH inbound rule — EC2 access is handled via SSM Session Manager instead.

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
- No key pair required (using SSM)
- Allocate an **Elastic IP** and associate it to the instance

### ECR Repositories

Create two private repositories:
- `tmdb-app`
- `tmdb-nginx`

---

## Phase 2: IAM Setup

Two separate identities — never share credentials between CI and the EC2 instance.

### IAM User — GitHub Actions

| Policy |
|---|
| `AmazonEC2ContainerRegistryPowerUser` |
| `AmazonSSMFullAccess` |

Generate access keys and add them as GitHub repository secrets.

### IAM Role — EC2 Instance

Create a role with `EC2` as the trusted entity and attach:

| Policy |
|---|
| `AmazonSSMManagedInstanceCore` |
| `AmazonEC2ContainerRegistryReadOnly` |

Attach the role via **EC2 → Instance → Actions → Security → Modify IAM role**.

> EC2 uses a role (not a user) so it never needs static credentials — it gets temporary credentials automatically.

---

## Phase 3: Server Setup

SSH in once to bootstrap (or use SSM Session Manager from the AWS console):

```bash
# Install dependencies
sudo apt update && sudo apt upgrade -y
sudo apt install -y docker.io docker-compose-v2 supervisor awscli
sudo usermod -aG docker ubuntu
newgrp docker
```

### Environment file

```bash
sudo mkdir -p /opt/tmdb
sudo nano /opt/tmdb/.env
```

```env
APP_NAME=Laravel
APP_ENV=production
APP_DEBUG=false
APP_URL=http://<your-ec2-ip>

DB_CONNECTION=pgsql
DB_HOST=<your-rds-endpoint>
DB_PORT=5432
DB_DATABASE=tmdb
DB_USERNAME=tmdb_user
DB_PASSWORD=<your-rds-password>

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

ALLOW_REGISTER=false
MOVIE_DB_IMG_URL=<tmdb-image-base-url>
```

### Supervisor (queue worker)

```bash
sudo nano /etc/supervisor/conf.d/tmdb-worker.conf
```

```ini
[program:tmdb-worker]
command=docker exec tmdb-app php artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
stderr_logfile=/var/log/tmdb-worker.err.log
stdout_logfile=/var/log/tmdb-worker.out.log
```

### Deploy script

Create `/opt/tmdb/deploy.sh`:

```bash
#!/bin/bash
set -e

export ECR_URI="<account-id>.dkr.ecr.<region>.amazonaws.com"
REGION="<your-region>"

aws ecr get-login-password --region $REGION | docker login --username AWS --password-stdin $ECR_URI

docker compose -f /opt/tmdb/docker-compose.prod.yml pull
docker compose -f /opt/tmdb/docker-compose.prod.yml up -d
docker compose -f /opt/tmdb/docker-compose.prod.yml exec app php artisan migrate --force

sudo supervisorctl restart tmdb-worker
```

```bash
sudo chmod +x /opt/tmdb/deploy.sh
```

Also copy `docker-compose.prod.yml` from the repo to `/opt/tmdb/`.

---

## Phase 4: GitHub Actions Secrets

Go to **repo → Settings → Secrets and variables → Actions** and add:

| Secret | Value |
|---|---|
| `AWS_ACCESS_KEY_ID` | From IAM user |
| `AWS_SECRET_ACCESS_KEY` | From IAM user |
| `AWS_REGION` | e.g. `ap-northeast-2` |
| `EC2_INSTANCE_ID` | e.g. `i-1234567890abcdef0` |

> Use **repository secrets** (not environment secrets) — environment secrets are for multi-environment setups like staging/production.

---

## How Deployments Work

On every push to `master`, GitHub Actions will:
1. Build and push the `tmdb-app` Docker image to ECR
2. Build and push the `tmdb-nginx` image to ECR (copies static assets from the app image)
3. Trigger `/opt/tmdb/deploy.sh` on EC2 via SSM — no open SSH port needed

---

## Debugging Notes

### ECR push forbidden (`ecr:InitiateLayerUpload`)
The IAM user had `AmazonEC2ContainerRegistryReadOnly` — not enough for pushing. Change to `AmazonEC2ContainerRegistryPowerUser`.

### Wrong ECR repository path
The ECR URI must exactly match the repository name created in the console. If your repo is `tmdb-app`, the image tag must be `<account-id>.dkr.ecr.<region>.amazonaws.com/tmdb-app:latest` — no extra path segments.

### SSH timeout from GitHub Actions (`dial tcp ***:22: i/o timeout`)
GitHub Actions IPs change constantly — maintaining an SSH allowlist is impractical. Solution: remove SSH from the security group entirely and use SSM `send-command` instead. EC2 access for manual tasks can be done via **SSM Session Manager** in the AWS console.

### Node.js deprecation warning in GitHub Actions
The warning comes from the action versions, not the Dockerfile. Pin to specific versions that use Node.js 22: `actions/checkout@v4.2.2`, `aws-actions/configure-aws-credentials@v4.0.3`, `aws-actions/amazon-ecr-login@v2.0.1`.
