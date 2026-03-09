# WAJIRA Backend API

Backend API untuk sistem manajemen terintegrasi yang mencakup:

- Master Data
- Transaction Management
- Warehouse Management
- Finance Management
- Reporting System
- User & Role Management (RBAC)

---

# Tech Stack

- PHP 8+
- Laravel Framework
- MySQL
- Laravel Sanctum / JWT (Authentication)
- Spatie Laravel Permission (RBAC)

---

# Installation Guide

## Clone Repository

```bash
git clone https://github.com/username/project-wajira-backend.git
cd project-wajira-backend
```

## Installation Script
```bash
chmod 744 install.sh
./install.sh
```

# API Docs

## postman collection and environment
https://deraly-dev-workspace.postman.co/workspace/Deraly-Dev-Workspace-Workspace~e9e32e9f-cd9e-4ac6-87e4-c2d77795e00e/collection/39336331-2189027e-e1d7-4b6e-b46a-cd2264fcf5fb?action=share&creator=39336331&active-environment=39336331-6ccd793e-2c31-4457-9637-9328c569efe7

# Developed by Deraly Digital Innovation

# After docker up

docker exec -it backend php artisan key:generate
docker exec -it backend php artisan migrate
docker exec -it backend php artisan optimize:clear