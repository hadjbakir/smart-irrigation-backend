# GitHub Setup Commands

## ✅ Step 1: Backend Repository (Already Done)
Git repository initialized and files committed.

## 📝 Step 2: Create GitHub Repository

1. Go to https://github.com/new
2. Repository name: `smart-irrigation-backend`
3. Description: "Laravel backend for Smart Irrigation System with weather integration, rule engine, and automation"
4. Choose **Private** or **Public**
5. **DO NOT** check "Initialize with README" (we already have files)
6. Click **Create repository**

## 🚀 Step 3: Push Backend to GitHub

After creating the repository, run these commands:

```bash
cd C:\Users\hadjb\Documents\auto-irrigation\smart-irrigation-backend
git remote add origin https://github.com/YOUR_USERNAME/smart-irrigation-backend.git
git branch -M main
git push -u origin main
```

**Replace `YOUR_USERNAME` with your GitHub username!**

---

## 📦 Step 4: Frontend Repository (Optional)

If you want to push the frontend separately:

```bash
cd C:\Users\hadjb\Documents\auto-irrigation\smart-irrigation-frontend
git init
git add .
git commit -m "Initial commit: Smart Irrigation System Frontend"
```

Then create a GitHub repository for frontend and push:

```bash
git remote add origin https://github.com/YOUR_USERNAME/smart-irrigation-frontend.git
git branch -M main
git push -u origin main
```

---

## 🔄 Future Updates

After making changes, use these commands:

```bash
git add .
git commit -m "Description of your changes"
git push
```

---

## 📋 Quick Reference

- **View status**: `git status`
- **View commits**: `git log`
- **View remote**: `git remote -v`
- **Pull latest**: `git pull`

