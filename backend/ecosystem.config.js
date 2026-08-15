module.exports = {
  apps: [
    {
      name: "mitt_mediearkiv",
      script: "/home/ubuntu/github_mmc/backend/.venv/bin/python",
      args: "-m uvicorn app.server:app --host 172.19.0.1 --port 9500 --workers 1",
      cwd: "/home/ubuntu/github_mmc/backend",
      exec_mode: "fork",
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: "1G",
      out_file: "/home/ubuntu/github_mmc/backend/logs/fastapi-out.log",
      error_file: "/home/ubuntu/github_mmc/backend/logs/fastapi-error.log",
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
