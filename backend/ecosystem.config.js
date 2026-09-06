// PM2 process definition for the "mitt_mediearkiv" FastAPI backend.
//
// Runs from a deployed copy at /opt/mmc_backend, NOT from this git
// checkout - unlike the frontend (plain PHP, served directly by Apache
// from /var/www), a running Python process keeps its imported modules
// in memory once started, so if it ran straight from this repo,
// switching git branches here (e.g. to work on an unrelated feature
// branch) would silently change what code the LIVE backend serves on
// its next restart, with no relation to what's actually meant to be in
// production. Use backend/deploy_backend.sh to push changes from this
// repo to /opt/mmc_backend and restart the pm2 process - see that
// script for details.
module.exports = {
  apps: [
    {
      name: "mitt_mediearkiv",
      script: "/opt/mmc_backend/.venv/bin/python",
      args: "-m uvicorn app.server:app --host 172.19.0.1 --port 9500 --workers 1",
      cwd: "/opt/mmc_backend",
      exec_mode: "fork",
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: "1G",
      out_file: "/opt/mmc_backend/logs/fastapi-out.log",
      error_file: "/opt/mmc_backend/logs/fastapi-error.log",
      log_date_format: "YYYY-MM-DD HH:mm:ss Z",
      merge_logs: true,
      env: {
        PYTHONUNBUFFERED: "1",
        OSLOMET_ENV: "prod",
        WISHLIST_COVER_MAX_BYTES: "31457280"
      }
    }
  ]
}
