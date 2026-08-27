# 🚀 Live Server & Deployment Automation

## 1. Hosting Environment

- **Server**: Hostinger VPS (see `.env` / hosting dashboard for the IP)
- **Domain**: `pavancab.com`
- **Application Path**: `/domains/pavancab.com/public_html/app/`
- **Database Host**: `<DB_HOST — see .env>`
- **Database User**: `<DB_USER — see .env>`
- **Database Name**: `<DB_NAME — see .env>`

---

## 2. Live Synchronization via WinSCP

Live deployment from local directory `c:\Users\Admin\Desktop\Goa Taxi App\app` to the Hostinger remote directory is automated using `deploy.ps1`.

### PowerShell Execution Command
```powershell
powershell -ExecutionPolicy Bypass -File .\deploy.ps1
```

### `winscp_upload_live.txt` Configuration
```
open ftp://<SITE_USER>:<SITE_PASSWORD>@<SITE_HOST>/
option transfer binary
synchronize remote "C:\Users\Admin\Desktop\Goa Taxi App\app" /domains/pavancab.com/public_html/app
exit
```

### Important Deployment Rules
1. Never commit database passwords or Meta API tokens in public repositories.
2. Always execute `deploy.ps1` after editing any file in `app/`.
3. Verify live endpoints using HTTP status checks immediately after deployment.
