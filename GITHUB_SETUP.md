# GitHub Setup Instructions

## Step 1: Initialize Git Repository (Backend)

Navigate to the backend directory and run:

```bash
cd C:\Users\hadjb\Documents\auto-irrigation\smart-irrigation-backend
git init
git add .
git commit -m "Initial commit: Smart Irrigation System Backend"
```

## Step 2: Create GitHub Repository

1. Go to https://github.com/new
2. Repository name: `smart-irrigation-backend` (or your preferred name)
3. Description: "Laravel backend for Smart Irrigation System with weather integration, rule engine, and automation"
4. Choose **Private** or **Public** (your choice)
5. **DO NOT** initialize with README, .gitignore, or license (we already have these)
6. Click **Create repository**

## Step 3: Connect and Push Backend

After creating the repository, GitHub will show you commands. Use these:

```bash
cd C:\Users\hadjb\Documents\auto-irrigation\smart-irrigation-backend
git remote add origin https://github.com/YOUR_USERNAME/smart-irrigation-backend.git
git branch -M main
git push -u origin main
```

Replace `YOUR_USERNAME` with your GitHub username.

## Step 4: Initialize Git Repository (Frontend)

Navigate to the frontend directory and run:

```bash
cd C:\Users\hadjb\Documents\auto-irrigation\smart-irrigation-frontend
git init
git add .
git commit -m "Initial commit: Smart Irrigation System Frontend"
```

## Step 5: Create GitHub Repository for Frontend

1. Go to https://github.com/new
2. Repository name: `smart-irrigation-frontend` (or your preferred name)
3. Description: "Next.js frontend for Smart Irrigation System dashboard"
4. Choose **Private** or **Public**
5. **DO NOT** initialize with README, .gitignore, or license
6. Click **Create repository**

## Step 6: Connect and Push Frontend

```bash
cd C:\Users\hadjb\Documents\auto-irrigation\smart-irrigation-frontend
git remote add origin https://github.com/YOUR_USERNAME/smart-irrigation-frontend.git
git branch -M main
git push -u origin main
```

## Alternative: Single Monorepo (Both in One Repository)

If you prefer to have both backend and frontend in one repository:

```bash
cd C:\Users\hadjb\Documents\auto-irrigation
git init
git add .
git commit -m "Initial commit: Smart Irrigation System (Backend + Frontend)"
git remote add origin https://github.com/YOUR_USERNAME/smart-irrigation-system.git
git branch -M main
git push -u origin main
```

## Important Notes

1. **Environment Files**: Make sure `.env` files are in `.gitignore` (they already are)
2. **API Keys**: Never commit API keys or sensitive data
3. **Dependencies**: `vendor/` (backend) and `node_modules/` (frontend) are already ignored
4. **Database**: Consider adding a `.env.example` file with placeholder values

## Create .env.example Files

### Backend .env.example
```env
APP_NAME="Smart Irrigation System"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=irrigation_db
DB_USERNAME=root
DB_PASSWORD=

OPENWEATHERMAP_API_KEY=your_api_key_here
WEATHER_LATITUDE=36.8065
WEATHER_LONGITUDE=10.1815
```

### Frontend .env.example
```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

