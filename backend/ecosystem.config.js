/**
 * Bagel Boyz NJ - PM2 Ecosystem Configuration
 * Social media automation scheduling
 */

module.exports = {
  apps: [
    // ─── Social Autopilot ────────────────────────────────
    // Posts 3x daily: 7 AM (breakfast rush), 11:30 AM (lunch), 4 PM (afternoon)
    {
      name: 'bb-social-autopilot',
      script: 'src/workers/social-autopilot.js',
      cwd: __dirname,
      cron_restart: '0 7 * * *,30 11 * * *,0 16 * * *',
      autorestart: false,
      watch: false,
      max_memory_restart: '500M',
      env: {
        NODE_ENV: 'production',
        TZ: 'America/New_York'
      },
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      error_file: 'logs/social-autopilot-error.log',
      out_file: 'logs/social-autopilot-out.log',
      merge_logs: true
    },

    // ─── Social Feed Generator ───────────────────────────
    // Generates static JSON for the website social feed page
    // Runs every 2 hours to keep the social.html page updated
    {
      name: 'bb-feed-generator',
      script: 'src/workers/feed-generator.js',
      cwd: __dirname,
      cron_restart: '0 */2 * * *',
      autorestart: false,
      watch: false,
      max_memory_restart: '200M',
      env: {
        NODE_ENV: 'production',
        TZ: 'America/New_York'
      },
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      error_file: 'logs/feed-generator-error.log',
      out_file: 'logs/feed-generator-out.log',
      merge_logs: true
    },

    // ─── Kitchen Print Bridge ────────────────────────────
    // Pulls kitchen tickets from bagelboyznj.com and writes them to the
    // Star TSP143IIIW over the shop LAN (TCP 9100).
    //
    // Unlike the two workers above, this one runs ON A MACHINE AT THE SHOP,
    // not on a build box — it needs to be on the same wifi as the printer.
    // It must stay up all day, so autorestart is ON and there is no cron.
    //
    //   pm2 start ecosystem.config.js --only bb-print-bridge
    //   pm2 save && pm2 startup       # survive a reboot
    {
      name: 'bb-print-bridge',
      script: 'src/print-bridge.js',
      cwd: __dirname,
      autorestart: true,
      watch: false,
      max_memory_restart: '150M',
      restart_delay: 5000,
      env: {
        NODE_ENV: 'production',
        TZ: 'America/New_York'
      },
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      error_file: 'logs/print-bridge-error.log',
      out_file: 'logs/print-bridge-out.log',
      merge_logs: true
    }
  ]
};
